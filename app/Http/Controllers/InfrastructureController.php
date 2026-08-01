<?php

namespace App\Http\Controllers;

use App\Models\Deployment;
use App\Services\ProcessRunner;

class InfrastructureController extends Controller
{
    public function monitoring()
    {
        $projectIds = auth()->user()->projects()->pluck('id');
        $deployments = Deployment::whereIn('project_id', $projectIds)->with('project')->latest()->limit(50)->get();
        
        $host = [
            'uptime' => shell_exec('uptime -p') ?? 'Unknown',
            'load' => sys_getloadavg(),
            'memory' => 'Check host memory',
            'disk' => 'Check host disk',
        ];

        return view('infrastructure.monitoring', compact('deployments', 'host'));
    }
}
