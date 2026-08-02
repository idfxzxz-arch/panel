<?php

namespace App\Services;

use App\Models\Deployment;
use App\Models\Project;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeploymentService
{
    public function __construct(
        private ProcessRunner $runner,
        private CloudflareService $cloudflare
    ) {}

    public function deploy(Deployment $deployment): void
    {
        $project = $deployment->project()->with(['primaryDomain', 'domains', 'environmentVariables', 'githubAccount', 'user.cloudflareIntegration'])->firstOrFail();

        $deployment->update(['status' => 'running', 'started_at' => now()]);
        $project->update(['status' => 'deploying']);

        try {
            $path = $project->path();
            File::ensureDirectoryExists(dirname($path));

            // Assign port if not set
            if (!$project->port) {
                $maxPort = Project::max('port') ?? 8000;
                $project->update(['port' => $maxPort + 1]);
            }

            // Pre-deployment validation
            $this->validatePreDeployment($project, $path);

            if ($project->user->cloudflareIntegration) {
                foreach ($project->domains as $domain) {
                    $this->cloudflare->provision($domain, $project->user->cloudflareIntegration);
                    $deployment->logs()->create([
                        'level' => 'info',
                        'step' => 'cloudflare',
                        'message' => 'DNS dan Tunnel aktif: '.$domain->domain,
                        'status' => 'success',
                        'started_at' => now(),
                        'finished_at' => now(),
                    ]);
                }
            }

            $gitEnv = $project->githubAccount ? [
                'GIT_CONFIG_COUNT' => '1',
                'GIT_CONFIG_KEY_0' => 'http.extraHeader',
                'GIT_CONFIG_VALUE_0' => 'Authorization: Basic '.base64_encode($project->githubAccount->username.':'.$project->githubAccount->token),
            ] : [];

            if ($project->repository) {
                if (! File::isDirectory($path.'/.git')) {
                    if (File::exists($path)) {
                        File::deleteDirectory($path);
                    }
                    $this->runStep(
                        $deployment,
                        'clone',
                        fn() => $this->runner->run(
                            ['git', 'clone', '--branch', $project->branch, '--single-branch', '--depth', '1', $project->repository, $path],
                            dirname($path),
                            $deployment,
                            'clone',
                            false,
                            $gitEnv
                        )
                    );
                } else {
                    $this->runStep(
                        $deployment,
                        'fetch',
                        fn() => $this->runner->run(
                            ['git', 'fetch', 'origin', $project->branch, '--depth', '1'],
                            $path,
                            $deployment,
                            'fetch',
                            false,
                            $gitEnv
                        )
                    );
                    $this->runStep(
                        $deployment,
                        'checkout',
                        fn() => $this->runner->run(
                            ['git', 'reset', '--hard', 'FETCH_HEAD'],
                            $path,
                            $deployment,
                            'checkout'
                        )
                    );
                    $this->runStep(
                        $deployment,
                        'clean',
                        fn() => $this->runner->run(
                            ['git', 'clean', '-fdx', '-e', '.env'],
                            $path,
                            $deployment,
                            'clean'
                        )
                    );
                }
            }

            // Write .env
            $this->writeEnv($project);
            $deployment->logs()->create([
                'level' => 'info',
                'step' => 'env',
                'message' => '.env file generated.',
                'status' => 'success',
                'started_at' => now(),
                'finished_at' => now(),
            ]);

            $sha = $project->repository
                ? $this->runner->capture(['git', 'rev-parse', 'HEAD'], $path)
                : null;
            $deployment->update(['commit_sha' => $sha]);

            // Composer install with validation
            $this->runStep(
                $deployment,
                'composer',
                fn() => $this->runner->run(
                    ['composer', 'install', '--no-dev', '--no-interaction', '--prefer-dist', '--optimize-autoloader'],
                    $path,
                    $deployment,
                    'composer'
                )
            );

            // Laravel commands
            $this->runStep(
                $deployment,
                'migrate',
                fn() => $this->runner->run(
                    ['php', 'artisan', 'migrate', '--force'],
                    $path,
                    $deployment,
                    'migrate'
                )
            );

            $this->runStep(
                $deployment,
                'config_cache',
                fn() => $this->runner->run(
                    ['php', 'artisan', 'config:cache'],
                    $path,
                    $deployment,
                    'optimize'
                )
            );

            $this->runStep(
                $deployment,
                'route_cache',
                fn() => $this->runner->run(
                    ['php', 'artisan', 'route:cache'],
                    $path,
                    $deployment,
                    'route-cache'
                )
            );

            $this->runStep(
                $deployment,
                'view_cache',
                fn() => $this->runner->run(
                    ['php', 'artisan', 'view:cache'],
                    $path,
                    $deployment,
                    'view-cache'
                )
            );

            $this->runStep(
                $deployment,
                'storage_link',
                fn() => $this->runner->run(
                    ['php', 'artisan', 'storage:link', '--force'],
                    $path,
                    $deployment,
                    'storage-link'
                )
            );

            // Restart process
            $this->restartProcess($project, $deployment);

            $deployment->update(['status' => 'succeeded', 'finished_at' => now()]);
            $project->update(['status' => 'running', 'last_commit' => $sha, 'last_deployed_at' => now()]);

        } catch (Throwable $e) {
            $this->handleDeploymentFailure($deployment, $project, $e);
        }
    }

    private function runStep(Deployment $deployment, string $step, callable $callback): void
    {
        $startTime = now();

        try {
            // Mark step as running
            $deployment->logs()->create([
                'level' => 'info',
                'step' => $step,
                'message' => "Starting step: {$step}",
                'status' => 'running',
                'started_at' => $startTime,
            ]);

            // Execute the step
            $callback();

            // Mark step as success
            $deployment->logs()->create([
                'level' => 'info',
                'step' => $step,
                'message' => "Step completed: {$step}",
                'status' => 'success',
                'started_at' => $startTime,
                'finished_at' => now(),
            ]);

        } catch (Throwable $e) {
            // Mark step as failed
            $deployment->logs()->create([
                'level' => 'error',
                'step' => $step,
                'message' => "Step failed: {$step} - " . $e->getMessage(),
                'status' => 'failed',
                'started_at' => $startTime,
                'finished_at' => now(),
            ]);

            throw $e;
        }
    }

    private function validatePreDeployment(Project $project, string $path): void
    {
        $requirements = [
            ['type' => 'command_exists', 'value' => 'git'],
            ['type' => 'command_exists', 'value' => 'composer'],
            ['type' => 'command_exists', 'value' => 'php'],
            ['type' => 'command_exists', 'value' => 'npm'],
            ['type' => 'command_exists', 'value' => 'docker'],
            ['type' => 'directory_exists', 'value' => dirname($path)],
            ['type' => 'php_extension', 'value' => 'pdo'],
            ['type' => 'php_extension', 'value' => 'mbstring'],
            ['type' => 'php_extension', 'value' => 'openssl'],
            ['type' => 'php_extension', 'value' => 'tokenizer'],
            ['type' => 'php_extension', 'value' => 'fileinfo'],
            ['type' => 'php_version', 'value' => '8.1'],
            ['type' => 'disk_space', 'value' => 100], // 100MB minimum
        ];

        // Check if repository exists
        if ($project->repository) {
            $requirements[] = ['type' => 'file_exists', 'value' => 'composer.json'];
        }

        $validation = $this->runner->validateEnvironment($requirements, $path);

        if (!$validation['valid']) {
            $errorMessages = implode("\n", $validation['errors']);
            Log::error("Pre-deployment validation failed for project {$project->slug}", [
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
            ]);

            throw new \RuntimeException("Validasi pre-deployment gagal:\n" . $errorMessages);
        }

        // Log warnings if any
        if (!empty($validation['warnings'])) {
            foreach ($validation['warnings'] as $warning) {
                Log::warning("Deployment warning for project {$project->slug}: {$warning}");
            }
        }
    }

    private function handleDeploymentFailure(Deployment $deployment, Project $project, Throwable $e): void
    {
        $errorMessage = $e->getMessage();
        $failedLog = $deployment->getFailedLog();

        if ($failedLog) {
            $errorMessage = $this->buildFullErrorMessage($failedLog, $errorMessage);
        }

        $deployment->update([
            'status' => 'failed',
            'finished_at' => now(),
            'error_details' => $errorMessage,
        ]);

        $project->update(['status' => 'failed']);

        Log::error("Deployment failed for project {$project->slug}", [
            'deployment_id' => $deployment->id,
            'error' => $errorMessage,
            'exception' => $e,
        ]);
    }

    private function buildFullErrorMessage(DeploymentLog $log, string $baseMessage): string
    {
        $message = $baseMessage . "\n\n";

        if ($log->command) {
            $message .= "Command: {$log->command}\n";
        }
        if ($log->working_directory) {
            $message .= "Working Directory: {$log->working_directory}\n";
        }
        if ($log->exit_code) {
            $message .= "Exit Code: {$log->exit_code}\n";
        }
        if ($log->duration_ms) {
            $message .= "Duration: {$log->duration_ms}ms\n";
        }
        if ($log->stderr) {
            $message .= "\nSTDERR:\n{$log->stderr}\n";
        }
        if ($log->stdout) {
            $message .= "\nSTDOUT:\n{$log->stdout}\n";
        }

        return $message;
    }

    private function writeEnv(Project $project): void
    {
        $lines = $project->environmentVariables()->get()->map(function ($variable) {
            $value = str_replace(['\\', "\n", "\r", '"'], ['\\\\', '\\n', '', '\\"'], $variable->value);
            return $variable->key.'="'.$value.'"';
        });

        // Ensure APP_URL is set
        $hasAppUrl = $project->environmentVariables()->where('key', 'APP_URL')->exists();
        if (!$hasAppUrl && $project->primaryDomain) {
            $lines->push('APP_URL="https://'.$project->primaryDomain->domain.'"');
        }

        File::put($project->path().'/.env', $lines->implode("\n").($lines->isNotEmpty() ? "\n" : ''));
        @chmod($project->path().'/.env', 0600);
    }

    private function restartProcess(Project $project, Deployment $deployment): void
    {
        $path = $project->path();
        $pidFile = $path . '/.serve_pid';

        // Stop if running
        if (File::exists($pidFile)) {
            $pid = trim(File::get($pidFile));
            if (is_numeric($pid) && $pid > 0) {
                exec("kill {$pid} > /dev/null 2>&1");
                // also kill child processes if any
                exec("pkill -P {$pid} > /dev/null 2>&1");
            }
            File::delete($pidFile);
        }

        // Start native php artisan serve
        $port = $project->port;
        $deployment->logs()->create([
            'level' => 'info',
            'step' => 'serve',
            'message' => "Starting artisan serve on port {$port}",
            'status' => 'running',
            'started_at' => now(),
        ]);

        // Run in background and save PID
        $cmd = "nohup php artisan serve --host=127.0.0.1 --port={$port} > storage/logs/serve.log 2>&1 & echo $!";
        $pid = trim(shell_exec("cd " . escapeshellarg($path) . " && " . $cmd));

        if (is_numeric($pid)) {
            File::put($pidFile, $pid);
            $deployment->logs()->create([
                'level' => 'info',
                'step' => 'serve',
                'message' => "Started with PID {$pid}",
                'command' => $cmd,
                'working_directory' => $path,
                'status' => 'success',
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        } else {
            throw new \RuntimeException("Failed to start artisan serve");
        }
    }
}