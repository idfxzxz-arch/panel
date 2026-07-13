<?php

namespace App\Http\Controllers;

use App\Models\Deployment;

class DeploymentController extends Controller
{
    public function show(Deployment $deployment)
    {
        abort_unless($deployment->project->user_id === auth()->id(), 403);
        $deployment->load(['project', 'logs']);

        return view('deployments.show', compact('deployment'));
    }
}
