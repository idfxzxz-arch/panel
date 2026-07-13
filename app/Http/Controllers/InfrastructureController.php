<?php

namespace App\Http\Controllers;

use App\Models\Deployment;
use App\Services\ProcessRunner;

class InfrastructureController extends Controller
{
    public function containers(ProcessRunner $runner)
    {
        $projects = auth()->user()->projects()->with('primaryDomain')->orderBy('name')->get();
        $containers = collect();
        try {
            $raw = $runner->capture(['docker', 'ps', '-a', '--format', '{{json .}}'], base_path());
            $containers = collect(preg_split('/\R/', $raw, -1, PREG_SPLIT_NO_EMPTY))
                ->map(fn ($line) => json_decode($line, true))->filter();
        } catch (\Throwable $exception) {
            report($exception);
        }

        return view('infrastructure.containers', compact('projects', 'containers'));
    }

    public function monitoring(ProcessRunner $runner)
    {
        $projectIds = auth()->user()->projects()->pluck('id');
        $deployments = Deployment::whereIn('project_id', $projectIds)->with('project')->latest()->limit(50)->get();
        $docker = ['available' => false, 'containers' => 0, 'running' => 0, 'images' => 0, 'version' => 'Unavailable'];
        try {
            $raw = $runner->capture(['docker', 'info', '--format', '{{json .}}'], base_path());
            $info = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $docker = [
                'available' => true,
                'containers' => $info['Containers'] ?? 0,
                'running' => $info['ContainersRunning'] ?? 0,
                'images' => $info['Images'] ?? 0,
                'version' => $info['ServerVersion'] ?? 'Unknown',
            ];
        } catch (\Throwable $exception) {
            report($exception);
        }

        return view('infrastructure.monitoring', compact('deployments', 'docker'));
    }
}
