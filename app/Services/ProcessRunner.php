<?php

namespace App\Services;

use App\Models\Deployment;
use App\Models\DeploymentLog;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

class ProcessRunner
{
    public function run(
        array $command,
        string $cwd,
        Deployment $deployment,
        string $step,
        bool $sensitive = false,
        array $env = []
    ): string {
        $displayCommand = $sensitive
            ? '[redacted command]'
            : implode(' ', array_map(fn ($v) => escapeshellarg((string) $v), $command));

        $startTime = microtime(true);

        // Create log entry with running status
        $log = $deployment->logs()->create([
            'level' => 'info',
            'step' => $step,
            'message' => '$ ' . $displayCommand,
            'command' => $sensitive ? null : $displayCommand,
            'working_directory' => $cwd,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $process = new Process($command, $cwd, $env ?: null, null, config('hosting.command_timeout', 300));

            $stdoutBuffer = '';
            $stderrBuffer = '';

            $process->run(function (string $type, string $buffer) use (
                &$stdoutBuffer,
                &$stderrBuffer,
                $deployment,
                $step,
                $log
            ) {
                if ($type === Process::OUT) {
                    $stdoutBuffer .= $buffer;
                    $this->logOutput($deployment, $step, 'info', rtrim($buffer));
                } else {
                    $stderrBuffer .= $buffer;
                    $this->logOutput($deployment, $step, 'error', rtrim($buffer));
                }
            });

            $duration = (int) ((microtime(true) - $startTime) * 1000);

            // Update log with success status
            $log->update([
                'status' => 'success',
                'exit_code' => $process->getExitCode(),
                'stdout' => $stdoutBuffer,
                'stderr' => $stderrBuffer,
                'duration_ms' => $duration,
                'finished_at' => now(),
            ]);

            if (! $process->isSuccessful()) {
                $this->handleProcessFailure($process, $deployment, $step, $displayCommand, $cwd, $duration, $stdoutBuffer, $stderrBuffer);
            }

            return trim($stdoutBuffer);

        } catch (Throwable $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            $log->update([
                'status' => 'failed',
                'exit_code' => $e instanceof ProcessFailedException ? $e->getProcess()->getExitCode() : 1,
                'stdout' => $stdoutBuffer ?? '',
                'stderr' => $stderrBuffer ?? $e->getMessage(),
                'duration_ms' => $duration,
                'finished_at' => now(),
            ]);

            $this->handleProcessFailure(
                $e instanceof ProcessFailedException ? $e->getProcess() : null,
                $deployment,
                $step,
                $displayCommand,
                $cwd,
                $duration,
                $stdoutBuffer ?? '',
                $stderrBuffer ?? $e->getMessage()
            );
        }
    }

    public function capture(
        array $command,
        string $cwd,
        array $env = [],
        int $timeout = 30
    ): string {
        $process = new Process($command, $cwd, $env ?: null, null, $timeout);

        try {
            $process->mustRun();
            return trim($process->getOutput());
        } catch (ProcessFailedException $e) {
            $process = $e->getProcess();
            Log::error('Process failed', [
                'command' => implode(' ', $command),
                'cwd' => $cwd,
                'exit_code' => $process->getExitCode(),
                'stdout' => $process->getOutput(),
                'stderr' => $process->getErrorOutput(),
            ]);
            throw $e;
        }
    }

    public function validateEnvironment(array $requirements, string $cwd): array
    {
        $errors = [];
        $warnings = [];

        foreach ($requirements as $requirement) {
            $result = $this->checkRequirement($requirement, $cwd);
            if ($result['valid'] === false) {
                $errors[] = $result['message'];
            } elseif (!empty($result['warning'])) {
                $warnings[] = $result['warning'];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    public function checkRequirement(array $requirement, string $cwd): array
    {
        $type = $requirement['type'];
        $value = $requirement['value'] ?? null;

        switch ($type) {
            case 'command_exists':
                $result = shell_exec('command -v ' . escapeshellarg($value) . ' 2>/dev/null');
                if (empty($result)) {
                    return [
                        'valid' => false,
                        'message' => "Command '{$value}' tidak ditemukan di sistem",
                    ];
                }
                return ['valid' => true];

            case 'file_exists':
                $path = $cwd . '/' . $value;
                if (!file_exists($path)) {
                    return [
                        'valid' => false,
                        'message' => "File '{$value}' tidak ditemukan di {$cwd}",
                    ];
                }
                return ['valid' => true];

            case 'directory_exists':
                if (!is_dir($cwd)) {
                    return [
                        'valid' => false,
                        'message' => "Direktori '{$cwd}' tidak ada",
                    ];
                }
                return ['valid' => true];

            case 'directory_writable':
                if (!is_writable($cwd)) {
                    return [
                        'valid' => false,
                        'message' => "Direktori '{$cwd}' tidak writable",
                    ];
                }
                return ['valid' => true];

            case 'file_writable':
                $path = $cwd . '/' . $value;
                if (file_exists($path) && !is_writable($path)) {
                    return [
                        'valid' => false,
                        'message' => "File '{$value}' tidak writable",
                    ];
                }
                return ['valid' => true];

            case 'php_extension':
                if (!extension_loaded($value)) {
                    return [
                        'valid' => false,
                        'message' => "PHP extension '{$value}' tidak terinstall",
                    ];
                }
                return ['valid' => true];

            case 'php_version':
                $minVersion = $value;
                if (version_compare(PHP_VERSION, $minVersion, '<')) {
                    return [
                        'valid' => false,
                        'message' => "PHP version minimal {$minVersion}, saat ini: " . PHP_VERSION,
                    ];
                }
                return ['valid' => true];

            case 'disk_space':
                $required = $value * 1024 * 1024; // MB to bytes
                $free = disk_free_space($cwd);
                if ($free < $required) {
                    return [
                        'valid' => false,
                        'message' => "Disk space tidak mencukupi. Required: {$value}MB, Available: " . round($free / 1024 / 1024, 2) . 'MB',
                    ];
                }
                return ['valid' => true];

            default:
                return ['valid' => true];
        }
    }

    protected function handleProcessFailure(
        ?Process $process,
        Deployment $deployment,
        string $step,
        string $command,
        string $cwd,
        int $duration,
        string $stdout,
        string $stderr
    ): void {
        $exitCode = $process ? $process->getExitCode() : 1;
        $actualStderr = $process ? $process->getErrorOutput() : $stderr;

        // Build detailed error message
        $errorMessage = $this->buildErrorMessage($step, $command, $cwd, $exitCode, $stdout, $actualStderr, $duration);

        // Update deployment error details
        $deployment->update([
            'error_details' => $errorMessage,
        ]);

        // Log to Laravel log
        Log::error("Deployment step failed: {$step}", [
            'step' => $step,
            'command' => $command,
            'cwd' => $cwd,
            'exit_code' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $actualStderr,
            'duration_ms' => $duration,
        ]);

        // Create failed log entry
        $deployment->logs()->create([
            'level' => 'error',
            'step' => $step,
            'message' => "Step gagal: {$step}",
            'command' => $command,
            'working_directory' => $cwd,
            'exit_code' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $actualStderr,
            'duration_ms' => $duration,
            'status' => 'failed',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        throw new \RuntimeException($errorMessage);
    }

    protected function buildErrorMessage(
        string $step,
        string $command,
        string $cwd,
        int $exitCode,
        string $stdout,
        string $stderr,
        int $duration
    ): string {
        $message = "Step '{$step}' gagal dengan exit code {$exitCode}\n\n";
        $message .= "Command: {$command}\n";
        $message .= "Working Directory: {$cwd}\n";
        $message .= "Duration: {$duration}ms\n\n";

        if (!empty($stderr)) {
            $message .= "STDERR:\n" . $this->formatOutput($stderr) . "\n\n";
        }

        if (!empty($stdout)) {
            $message .= "STDOUT:\n" . $this->formatOutput($stdout) . "\n";
        }

        return $message;
    }

    protected function formatOutput(string $output): string
    {
        // Trim and limit output to prevent overly long messages
        $output = trim($output);
        $maxLength = 5000;

        if (strlen($output) > $maxLength) {
            return substr($output, 0, $maxLength) . "\n\n... (output truncated, see logs for full details)";
        }

        return $output;
    }

    protected function logOutput(Deployment $deployment, string $step, string $level, string $message): void
    {
        if (empty(trim($message))) {
            return;
        }

        $deployment->logs()->create([
            'level' => $level,
            'step' => $step,
            'message' => $message,
            'status' => 'running',
        ]);
    }
}