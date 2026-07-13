<?php

namespace App\Http\Controllers;

use App\Models\EnvironmentVariable;
use App\Models\Project;
use App\Services\ProjectTemplateGenerator;
use Illuminate\Http\Request;

class EnvironmentVariableController extends Controller
{
    public function store(Request $request, Project $project, ProjectTemplateGenerator $templates)
    {
        abort_unless($project->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'variables' => ['required', 'array', 'max:3'],
            'variables.*.key' => ['required_with:variables.*.value', 'nullable', 'max:100', 'distinct', 'regex:/^[A-Z][A-Z0-9_]*$/'],
            'variables.*.value' => ['required_with:variables.*.key', 'nullable', 'string', 'max:10000'],
            'variables.*.is_build_time' => ['sometimes', 'boolean'],
        ]);

        $variables = collect($data['variables'])->filter(fn (array $variable) => filled($variable['key'] ?? null));
        if ($variables->isEmpty()) {
            return back()->withErrors(['variables' => 'Isi minimal satu environment variable.'])->withInput();
        }

        $variables->each(function (array $variable) use ($project) {
            $project->environmentVariables()->updateOrCreate(['key' => $variable['key']], [
                'value' => $variable['value'],
                'is_build_time' => (bool) ($variable['is_build_time'] ?? false),
            ]);
        });
        if (is_dir($project->path())) {
            $templates->writeEnv($project);
        }

        return back()->with('success', $variables->count().' environment variable disimpan. Redeploy agar perubahan aktif.');
    }

    public function destroy(Request $request, Project $project, EnvironmentVariable $variable, ProjectTemplateGenerator $templates)
    {
        abort_unless($project->user_id === $request->user()->id && $variable->project_id === $project->id, 403);
        $variable->delete();
        if (is_dir($project->path())) {
            $templates->writeEnv($project);
        }

        return back()->with('success', 'Environment variable dihapus.');
    }
}
