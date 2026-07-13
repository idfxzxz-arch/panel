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

    public function lifecycle(Project $project, string $action, ProcessRunner $runner)
    {
        $this->owner($project);
        abort_unless(in_array($action, ['start', 'stop', 'restart'], true), 404);
        try {
            $runner->capture(['docker', 'compose', '-p', $project->slug, $action], $project->path());
            $project->update(['status' => $action === 'stop' ? 'stopped' : 'running']);

            return back()->with('success', ucfirst($action).' berhasil.');
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['docker' => 'Operasi Docker gagal. Periksa daemon dan log aplikasi.']);
        }
    }

    public function logs(Project $project, ProcessRunner $runner)
    {
        $this->owner($project);
        try {
            $logs = $runner->capture(['docker', 'compose', '-p', $project->slug, 'logs', '--no-color', '--tail', (string) config('hosting.log_lines')], $project->path());
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
