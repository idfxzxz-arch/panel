<?php

namespace App\Jobs;

use App\Models\Deployment;
use App\Services\DeploymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeployProject implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public int $tries = 1;

    public function __construct(public int $deploymentId) {}

    public function handle(DeploymentService $service): void
    {
        $service->deploy(Deployment::findOrFail($this->deploymentId));
    }
}
