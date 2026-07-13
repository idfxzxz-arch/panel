<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectDomain;
use App\Services\CloudflareService;
use App\Services\ProjectTemplateGenerator;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function index()
    {
        $projects = auth()->user()->projects()->with('domains')->orderBy('name')->get();

        return view('infrastructure.domains', compact('projects'));
    }

    public function store(Request $request, ProjectTemplateGenerator $templates, CloudflareService $cloudflare)
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'domain' => ['required', 'max:253', 'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', 'unique:project_domains,domain'],
        ]);
        $project = $request->user()->projects()->findOrFail($data['project_id']);
        $domain = $project->domains()->create(['domain' => strtolower(rtrim($data['domain'], '.')), 'ssl_enabled' => true, 'is_primary' => false]);
        if ($request->user()->cloudflareIntegration) {
            try {
                $cloudflare->provision($domain, $request->user()->cloudflareIntegration);
            } catch (\Throwable $exception) {
                report($exception);
                $domain->update(['cloudflare_status' => 'failed']);

                return back()->withErrors(['cloudflare' => 'Domain tersimpan, tetapi provisioning Cloudflare gagal. Redeploy untuk mencoba kembali.']);
            }
        }
        $this->regenerateIfDeployed($project, $templates);

        return back()->with('success', 'Domain ditambahkan. Redeploy project untuk mengaktifkan routing dan SSL.');
    }

    public function destroy(Request $request, ProjectDomain $domain, ProjectTemplateGenerator $templates, CloudflareService $cloudflare)
    {
        $project = Project::where('user_id', $request->user()->id)->findOrFail($domain->project_id);
        abort_if($domain->is_primary, 422, 'Domain utama tidak dapat dihapus.');
        $integration = $request->user()->cloudflareIntegration;
        if ($integration && $domain->cloudflare_record_id) {
            try {
                $cloudflare->deprovision($domain, $integration);
            } catch (\Throwable $exception) {
                report($exception);

                return back()->withErrors(['cloudflare' => 'Domain belum dihapus karena cleanup Cloudflare gagal.']);
            }
        }
        $domain->delete();
        $this->regenerateIfDeployed($project, $templates);

        return back()->with('success', 'Domain dihapus. Redeploy project untuk memperbarui routing.');
    }

    private function regenerateIfDeployed(Project $project, ProjectTemplateGenerator $templates): void
    {
        if (is_dir($project->path())) {
            $project->load(['primaryDomain', 'domains', 'environmentVariables']);
            $templates->generate($project);
        }
    }
}
