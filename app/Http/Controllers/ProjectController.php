<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Jobs\DeployProject;
use App\Models\Deployment;
use App\Models\Project;
use App\Services\CloudflareService;
use App\Services\GithubService;
use App\Services\ProcessRunner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function index(ProcessRunner $runner)
    {
        $projects = auth()->user()->projects()->with('primaryDomain')->latest()->get();
        $deployments = Deployment::query()
            ->whereHas('project', fn ($query) => $query->where('user_id', auth()->id()))
            ->with('project')->latest()->limit(8)->get();
        $successful = $deployments->where('status', 'succeeded')->count();
        $finished = $deployments->whereIn('status', ['succeeded', 'failed'])->count();
        $infra = ['available' => false, 'version' => 'offline', 'containers' => 0, 'running' => 0];
        try {
            $info = json_decode($runner->capture(['docker', 'info', '--format', '{{json .}}'], base_path()), true, 512, JSON_THROW_ON_ERROR);
            $infra = [
                'available' => true,
                'version' => $info['ServerVersion'] ?? 'unknown',
                'containers' => $info['Containers'] ?? 0,
                'running' => $info['ContainersRunning'] ?? 0,
            ];
        } catch (\Throwable $exception) {
            report($exception);
        }
        $allDeployments = Deployment::whereHas('project', fn ($query) => $query->where('user_id', auth()->id()))
            ->where('created_at', '>=', now()->subDays(11)->startOfDay())->get(['created_at']);
        $series = collect(range(11, 0))->map(function ($daysAgo) use ($allDeployments) {
            $date = now()->subDays($daysAgo);

            return ['label' => $date->format('d M'), 'count' => $allDeployments->filter(fn ($deployment) => $deployment->created_at->isSameDay($date))->count()];
        });
        $maximum = max(1, $series->max('count'));
        $trendPoints = $series->values()->map(fn ($point, $index) => round(($index / 11) * 320, 1).','.round(48 - (($point['count'] / $maximum) * 39), 1))->implode(' ');

        return view('projects.index', [
            'projects' => $projects,
            'deployments' => $deployments,
            'infra' => $infra,
            'series' => $series,
            'trendPoints' => $trendPoints,
            'stats' => [
                'total' => $projects->count(),
                'running' => $projects->where('status', 'running')->count(),
                'failed' => $projects->where('status', 'failed')->count(),
                'success_rate' => $finished > 0 ? (int) round(($successful / $finished) * 100) : 100,
            ],
        ]);
    }

    public function create(GithubService $github)
    {
        $account = auth()->user()->githubAccount;
        $repositories = collect();
        $githubError = null;
        if ($account) {
            try {
                $repositories = $github->repositories($account);
            } catch (\Throwable $exception) {
                report($exception);
                $githubError = 'Repository GitHub tidak dapat dimuat. Perbarui token di Integrations.';
            }
        }

        return view('projects.create', [
            'repositories' => $repositories,
            'githubAccount' => $account,
            'cloudflare' => auth()->user()->cloudflareIntegration,
            'githubError' => $githubError,
        ]);
    }

    public function applications()
    {
        $query = auth()->user()->projects()->with(['primaryDomain', 'deployments' => fn ($query) => $query->latest()->limit(1)]);
        if (in_array(request('type'), ['static', 'laravel', 'vite', 'wordpress'], true)) {
            $query->where('type', request('type'));
        }
        if (in_array(request('status'), ['pending', 'deploying', 'running', 'stopped', 'failed'], true)) {
            $query->where('status', request('status'));
        }
        $projects = $query->latest()->get();

        return view('projects.applications', compact('projects'));
    }

    public function store(StoreProjectRequest $request)
    {
        $secret = Str::random(64);
        $deployment = DB::transaction(function () use ($request, $secret) {
            $p = Project::create($request->safe()->except(['domain', 'subdomain']) + [
                'user_id' => $request->user()->id,
                'github_account_id' => $request->user()->githubAccount?->id,
            ]);
            $p->domains()->create(['domain' => $request->validated('domain'), 'ssl_enabled' => true, 'is_primary' => true]);
            $p->webhook()->create(['uuid' => (string) Str::uuid(), 'secret' => $secret]);

            return $p->deployments()->create(['triggered_by' => $request->user()->id, 'trigger' => 'initial']);
        });
        DeployProject::dispatch($deployment->id);

        return redirect()->route('projects.show', $deployment->project)->with(['success' => 'Project dibuat dan deploy masuk antrean.', 'webhook_secret' => $secret]);
    }

    public function show(Project $project)
    {
        $this->authorizeOwner($project);
        $project->load(['primaryDomain', 'webhook', 'environmentVariables', 'deployments' => fn ($q) => $q->latest()->limit(20)]);

        return view('projects.show', compact('project'));
    }

    public function destroy(Project $project, ProcessRunner $runner, CloudflareService $cloudflare)
    {
        $this->authorizeOwner($project);
        if (is_dir($project->path())) {
            try {
                $runner->capture(['docker', 'compose', '-p', $project->slug, 'down', '--remove-orphans'], $project->path());
            } catch (\Throwable $exception) {
                report($exception);

                return back()->withErrors(['project' => 'Container gagal dihentikan; project tidak dihapus untuk mencegah resource yatim.']);
            }
        }
        $project->load('domains');
        $integration = auth()->user()->cloudflareIntegration;
        if ($integration) {
            try {
                foreach ($project->domains as $domain) {
                    if ($domain->cloudflare_record_id) {
                        $cloudflare->deprovision($domain, $integration);
                    }
                }
            } catch (\Throwable $exception) {
                report($exception);

                return back()->withErrors(['cloudflare' => 'Container sudah dihentikan, tetapi project belum dihapus karena cleanup Cloudflare gagal.']);
            }
        }
        if (is_dir($project->path())) {
            File::deleteDirectory($project->path());
        }
        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Project dan resource Compose berhasil dihapus.');
    }

    private function authorizeOwner(Project $p): void
    {
        abort_unless($p->user_id === auth()->id(), 403);
    }
}
