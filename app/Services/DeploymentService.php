<?php

namespace App\Services;

use App\Models\Deployment;
use App\Models\Project;
use Illuminate\Support\Facades\File;
use Throwable;

class DeploymentService
{
    public function __construct(private ProcessRunner $runner, private CloudflareService $cloudflare) {}

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
            
            if ($project->user->cloudflareIntegration) {
                foreach ($project->domains as $domain) {
                    $this->cloudflare->provision($domain, $project->user->cloudflareIntegration);
                    $deployment->logs()->create(['level' => 'info', 'step' => 'cloudflare', 'message' => 'DNS dan Tunnel aktif: '.$domain->domain]);
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
                    $this->runner->run(['git', 'clone', '--branch', $project->branch, '--single-branch', '--depth', '1', $project->repository, $path], dirname($path), $deployment, 'clone', false, $gitEnv);
                } else {
                    $this->runner->run(['git', 'fetch', 'origin', $project->branch, '--depth', '1'], $path, $deployment, 'fetch', false, $gitEnv);
                    $this->runner->run(['git', 'reset', '--hard', 'FETCH_HEAD'], $path, $deployment, 'checkout');
                    $this->runner->run(['git', 'clean', '-fdx', '-e', '.env'], $path, $deployment, 'clean');
                }
            }
            
            // Write .env
            $this->writeEnv($project);
            $deployment->logs()->create(['level' => 'info', 'step' => 'env', 'message' => '.env file generated.']);
            
            $sha = $project->repository ? $this->runner->capture(['git', 'rev-parse', 'HEAD'], $path) : null;
            $deployment->update(['commit_sha' => $sha]);
            
            // Composer install
            $this->runner->run(['composer', 'install', '--no-dev', '--no-interaction', '--prefer-dist', '--optimize-autoloader'], $path, $deployment, 'composer');
            
            // Laravel commands
            $this->runner->run(['php', 'artisan', 'migrate', '--force'], $path, $deployment, 'migrate');
            $this->runner->run(['php', 'artisan', 'config:cache'], $path, $deployment, 'optimize');
            $this->runner->run(['php', 'artisan', 'route:cache'], $path, $deployment, 'route-cache');
            $this->runner->run(['php', 'artisan', 'view:cache'], $path, $deployment, 'view-cache');
            $this->runner->run(['php', 'artisan', 'storage:link', '--force'], $path, $deployment, 'storage-link');
            
            // Restart process
            $this->restartProcess($project, $deployment);
            
            $deployment->update(['status' => 'succeeded', 'finished_at' => now()]);
            $project->update(['status' => 'running', 'last_commit' => $sha, 'last_deployed_at' => now()]);
        } catch (Throwable $e) {
            $deployment->logs()->create(['level' => 'error', 'step' => 'deploy', 'message' => $e->getMessage()]);
            $deployment->update(['status' => 'failed', 'finished_at' => now()]);
            $project->update(['status' => 'failed']);
            throw $e;
        }
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
        $deployment->logs()->create(['level' => 'info', 'step' => 'serve', 'message' => "Starting artisan serve on port {$port}"]);
        
        // Run in background and save PID
        $cmd = "nohup php artisan serve --host=127.0.0.1 --port={$port} > storage/logs/serve.log 2>&1 & echo $!";
        $pid = trim(shell_exec("cd " . escapeshellarg($path) . " && " . $cmd));
        
        if (is_numeric($pid)) {
            File::put($pidFile, $pid);
            $deployment->logs()->create(['level' => 'info', 'step' => 'serve', 'message' => "Started with PID {$pid}"]);
        } else {
            throw new \RuntimeException("Failed to start artisan serve");
        }
    }
}
