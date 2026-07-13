<?php

namespace App\Services;

use App\Models\Deployment;
use RuntimeException;
use Symfony\Component\Process\Process;

class ProcessRunner
{
    public function run(array $command, string $cwd, Deployment $deployment, string $step, bool $sensitive = false, array $env = []): string
    {
        $display = $sensitive ? '[redacted command]' : implode(' ', array_map(fn ($v) => escapeshellarg((string) $v), $command));
        $deployment->logs()->create(['level' => 'info', 'step' => $step, 'message' => '$ '.$display]);
        $process = new Process($command, $cwd, $env ?: null, null, config('hosting.command_timeout'));
        $output = '';
        $process->run(function (string $type, string $buffer) use (&$output, $deployment, $step) {
            $output .= $buffer;
            $deployment->logs()->create(['level' => $type === Process::ERR ? 'error' : 'info', 'step' => $step, 'message' => rtrim($buffer)]);
        });
        if (! $process->isSuccessful()) {
            throw new RuntimeException("Langkah {$step} gagal dengan exit code {$process->getExitCode()}.");
        }

        return trim($output);
    }

    public function capture(array $command, string $cwd, array $env = [], int $timeout = 30): string
    {
        $process = new Process($command, $cwd, $env ?: null, null, $timeout);
        $process->mustRun();

        return trim($process->getOutput());
    }
}
