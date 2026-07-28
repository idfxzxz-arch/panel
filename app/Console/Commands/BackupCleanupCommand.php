<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BackupService;
use Illuminate\Support\Facades\Log;

class BackupCleanupCommand extends Command
{
    protected $signature = 'backup:cleanup
                            {--days=30 : Number of days to keep backups}';

    protected $description = 'Cleanup old backups based on retention policy';

    public function handle(BackupService $backupService): int
    {
        $daysToKeep = (int) $this->option('days');

        if ($daysToKeep < 1) {
            $this->error("Invalid number of days: {$daysToKeep}");
            return self::FAILURE;
        }

        $this->info("Cleaning up backups older than {$daysToKeep} days...");

        try {
            $deletedCount = $backupService->cleanupOldBackups($daysToKeep);

            $this->info("Cleanup completed. Deleted {$deletedCount} old backups.");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Cleanup failed: " . $e->getMessage());
            Log::error("Backup cleanup failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}