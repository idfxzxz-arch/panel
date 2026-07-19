<?php

namespace App\Services;

use App\Models\Deployment;
use Illuminate\Support\Facades\File;
use Throwable;

class DeploymentService
{
    public function __construct(private ProcessRunner $runner, private ProjectTemplateGenerator $templates, private CloudflareService $cloudflare) {}

    public function deploy(Deployment $deployment): void
    {
        $project = $deployment->project()->with(['primaryDomain', 'domains', 'environmentVariables', 'githubAccount', 'user.cloudflareIntegration'])->firstOrFail();
        $deployment->update(['status' => 'running', 'started_at' => now()]);
        $project->update(['status' => 'deploying']);
        try {
            $path = $project->path();
            File::ensureDirectoryExists(dirname($path));
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
            } elseif ($project->type === 'wordpress') {
                File::ensureDirectoryExists($path);
                $deployment->logs()->create(['level' => 'info', 'step' => 'source', 'message' => 'WordPress resmi digunakan tanpa repository Git.']);
            }
            $this->templates->generate($project);
            $sha = $project->repository ? $this->runner->capture(['git', 'rev-parse', 'HEAD'], $path) : null;
            $deployment->update(['commit_sha' => $sha]);
            $this->runner->run(['docker', 'compose', '-p', $project->slug, 'build', '--pull'], $path, $deployment, 'build');
            $this->runner->run(['docker', 'compose', '-p', $project->slug, 'up', '-d', '--remove-orphans'], $path, $deployment, 'start');
            if ($project->type === 'laravel') {
                $this->runner->run(['docker', 'compose', '-p', $project->slug, 'exec', '-T', 'app', 'php', 'artisan', 'migrate', '--force'], $path, $deployment, 'migrate');
                $this->runner->run(['docker', 'compose', '-p', $project->slug, 'exec', '-T', 'app', 'php', 'artisan', 'config:cache'], $path, $deployment, 'optimize');
                $this->runner->run(['docker', 'compose', '-p', $project->slug, 'exec', '-T', 'app', 'php', 'artisan', 'storage:link', '--force'], $path, $deployment, 'storage-link');
            }
            $deployment->update(['status' => 'succeeded', 'finished_at' => now()]);
            $project->update(['status' => 'running', 'last_commit' => $sha, 'last_deployed_at' => now()]);
        } catch (Throwable $e) {
            $deployment->logs()->create(['level' => 'error', 'step' => 'deploy', 'message' => $e->getMessage()]);
            $deployment->update(['status' => 'failed', 'finished_at' => now()]);
            $project->update(['status' => 'failed']);
            throw $e;
        }
    }
}
