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
            'key' => ['required', 'max:100', 'regex:/^[A-Z][A-Z0-9_]*$/'],
            'value' => ['present', 'string', 'max:10000'],
            'is_build_time' => ['sometimes', 'boolean'],
        ]);
        $project->environmentVariables()->updateOrCreate(['key' => $data['key']], [
            'value' => $data['value'], 'is_build_time' => $request->boolean('is_build_time'),
        ]);
        if (is_dir($project->path())) {
            $templates->writeEnv($project);
        }

        return back()->with('success', 'Environment variable disimpan. Redeploy agar perubahan aktif.');
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
