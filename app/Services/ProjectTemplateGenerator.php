<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\File;

class ProjectTemplateGenerator
{
    public function writeEnv(Project $project): void
    {
        $envContent = $this->generateEnvContent($project);
        $envPath = $project->path() . '/.env';

        if (!is_dir($project->path())) {
            return;
        }

        File::put($envPath, $envContent);
    }

    protected function generateEnvContent(Project $project): string
    {
        $lines = [
            'APP_NAME=' . $project->name,
            'APP_ENV=production',
            'APP_KEY=',
            'APP_DEBUG=false',
            'APP_URL=' . $project->url,
        ];

        foreach ($project->environmentVariables as $variable) {
            $lines[] = $variable->key . '=' . $variable->value;
        }

        return implode("\n", $lines);
    }
}