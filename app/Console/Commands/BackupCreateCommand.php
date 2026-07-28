<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Project;
use App\Services\BackupService;
use Illuminate\Support\Facades\Log;

class BackupCreateCommand extends Command
{
    protected $signature = 'backup:create
                            {project : Project ID or slug}
                            {--user= : User ID who is creating the backup}';

    protected $description = 'Create a backup for a project';

    public function handle(BackupService $backupService): int
    {
        $projectIdentifier = $this->argument('project');
        $userId = $this->option('user');

        // Find project by ID or slug
        $project = Project::where('id', $projectIdentifier)
            ->orWhere('slug', $projectIdentifier)
            ->first();

        if (!$project) {
            $this->error("Project not found: {$projectIdentifier}");
            return self::FAILURE;
        }

        $user = null;
        if ($userId) {
            $user = \App\Models\User::find($userId);
            if (!$user) {
                $this->error("User not found: {$userId}");
                return self::FAILURE;
            }
        }

        $this->info("Creating backup for project: {$project->name} ({$project->slug})");

        try {
            $backup = $backupService->createBackup($project, $user);

            $this->info("Backup created successfully!");
            $this->line("Backup ID: {$backup->id}");
            $this->line("Filename: {$backup->filename}");
            $this->line("Size: " . format_bytes($backup->size));
            $this->line("Status: {$backup->status}");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Backup failed: " . $e->getMessage());
            Log::error("Backup failed for project {$project->id}: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}