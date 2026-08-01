<?php

namespace App\Http\Controllers;

use App\Jobs\DeployProject;
use App\Models\Project;
use App\Services\ProcessRunner;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProjectActionController extends Controller
{
    private function owner(Project $p): void
    {
        abort_unless($p->user_id === auth()->id(), 403);
    }

    public function deploy(Request $r, Project $project)
    {
        $this->owner($project);
        $d = $project->deployments()->create(['triggered_by' => $r->user()->id, 'trigger' => 'manual']);
        DeployProject::dispatch($d->id);

        return back()->with('success', 'Redeploy masuk antrean.');
    }

    public function lifecycle(Project $project, string $action)
    {
        $this->owner($project);
        abort_unless(in_array($action, ['start', 'stop', 'restart'], true), 404);
        try {
            $path = $project->path();
            $pidFile = $path . '/.serve_pid';

            if (in_array($action, ['stop', 'restart'])) {
                if (\Illuminate\Support\Facades\File::exists($pidFile)) {
                    $pid = trim(\Illuminate\Support\Facades\File::get($pidFile));
                    if (is_numeric($pid) && $pid > 0) {
                        exec("kill {$pid} > /dev/null 2>&1");
                        exec("pkill -P {$pid} > /dev/null 2>&1");
                    }
                    \Illuminate\Support\Facades\File::delete($pidFile);
                }
            }

            if (in_array($action, ['start', 'restart'])) {
                $port = $project->port;
                $cmd = "nohup php artisan serve --host=127.0.0.1 --port={$port} > storage/logs/serve.log 2>&1 & echo $!";
                $pid = trim(shell_exec("cd " . escapeshellarg($path) . " && " . $cmd));
                
                if (is_numeric($pid)) {
                    \Illuminate\Support\Facades\File::put($pidFile, $pid);
                } else {
                    throw new \RuntimeException("Failed to start process");
                }
            }

            $project->update(['status' => $action === 'stop' ? 'stopped' : 'running']);

            return back()->with('success', ucfirst($action).' berhasil.');
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['process' => 'Operasi proses gagal. Periksa log aplikasi.']);
        }
    }

    public function logs(Project $project)
    {
        $this->owner($project);
        try {
            $logFile = escapeshellarg($project->path() . '/storage/logs/serve.log');
            $lines = escapeshellarg((string) config('hosting.log_lines', 500));
            $logs = shell_exec("tail -n {$lines} {$logFile} 2>&1");
            if (trim($logs) === '' || str_contains($logs, 'No such file or directory')) {
                $logs = "Belum ada log.";
            }
        } catch (\Throwable $e) {
            $logs = 'Tidak dapat membaca log: '.$e->getMessage();
        }

        return response($logs)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function rotateWebhook(Project $project)
    {
        $this->owner($project);
        $secret = Str::random(64);
        $project->webhook()->firstOrFail()->update(['secret' => $secret]);

        return back()->with(['success' => 'Webhook secret dirotasi. Perbarui secret di GitHub.', 'webhook_secret' => $secret]);
    }
}
