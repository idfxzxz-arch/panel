<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Backup;
use App\Services\BackupService;
use Illuminate\Support\Facades\Log;

class BackupRestoreCommand extends Command
{
    protected $signature = 'backup:restore
                            {backup : Backup ID}
                            {--user= : User ID who is restoring the backup}';

    protected $description = 'Restore a project from a backup';

    public function handle(BackupService $backupService): int
    {
        $backupId = $this->argument('backup');
        $userId = $this->option('user');

        // Find backup
        $backup = Backup::find($backupId);

        if (!$backup) {
            $this->error("Backup not found: {$backupId}");
            return self::FAILURE;
        }

        if (!$backup->fileExists()) {
            $this->error("Backup file not found for backup ID: {$backupId}");
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

        $project = $backup->project;
        $this->info("Restoring backup for project: {$project->name} ({$project->slug})");
        $this->warn("This will overwrite all current project data with the backup data!");
        $this->warn("Backup created at: {$backup->created_at}");

        if (!$this->confirm('Are you sure you want to continue?')) {
            $this->info('Restore cancelled.');
            return self::SUCCESS;
        }

        try {
            $result = $backupService->restoreBackup($backup, $user);

            if ($result) {
                $this->info("Backup restored successfully!");
                $this->line("Project: {$project->name} ({$project->slug})");
                $this->line("Backup ID: {$backup->id}");
                $this->line("Status: {$backup->status}");
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Restore failed: " . $e->getMessage());
            Log::error("Restore failed for backup {$backup->id}: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}