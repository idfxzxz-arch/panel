<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ZipArchive;
use Carbon\Carbon;

class BackupService
{
    protected ProcessRunner $processRunner;
    protected string $backupDisk = 'backups';
    protected string $backupPath;

    public function __construct(ProcessRunner $processRunner)
    {
        $this->processRunner = $processRunner;
        $this->backupPath = env('HOSTING_BACKUP_PATH', '/opt/myhosting-panel/backups');
    }

    /**
     * Create a backup for a project
     */
    public function createBackup(Project $project, ?User $user = null): Backup
    {
        // Create backup record
        $backup = Backup::create([
            'project_id' => $project->id,
            'created_by' => $user?->id,
            'status' => 'creating',
            'filename' => $this->generateBackupFilename($project),
            'path' => $project->slug,
        ]);

        try {
            // Create temporary directory
            $tempDir = $this->createTempDirectory($project->slug);

            // Backup database
            $this->backupDatabase($project, $tempDir);

            // Backup project files
            $this->backupProjectFiles($project, $tempDir);

            // Backup environment variables
            $this->backupEnvironmentVariables($project, $tempDir);

            // Create zip archive
            $zipPath = $this->createZipArchive($project, $tempDir, $backup->filename);

            // Store backup file
            $this->storeBackupFile($project, $backup, $zipPath);

            // Update backup record
            $backup->update([
                'status' => 'completed',
                'completed_at' => now(),
                'expires_at' => $this->calculateExpirationDate(),
            ]);

            // Cleanup temporary files
            $this->cleanupTempDirectory($tempDir);

            return $backup;

        } catch (\Exception $e) {
            Log::error("Backup failed for project {$project->id}: " . $e->getMessage());

            $backup->update([
                'status' => 'failed',
                'completed_at' => now(),
            ]);

            // Cleanup temporary files if they exist
            if (isset($tempDir) && is_dir($tempDir)) {
                $this->cleanupTempDirectory($tempDir);
            }

            throw $e;
        }
    }

    /**
     * Restore a backup
     */
    public function restoreBackup(Backup $backup, ?User $user = null): bool
    {
        if (!$backup->fileExists()) {
            throw new \RuntimeException("Backup file not found");
        }

        $backup->update(['status' => 'restoring']);

        try {
            $project = $backup->project;
            $tempDir = $this->createTempDirectory($project->slug . '_restore');

            // Extract backup
            $this->extractBackup($backup, $tempDir);

            // Restore database
            $this->restoreDatabase($project, $tempDir);

            // Restore project files
            $this->restoreProjectFiles($project, $tempDir);

            // Restore environment variables
            $this->restoreEnvironmentVariables($project, $tempDir);

            // Update backup status
            $backup->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Cleanup temporary files
            $this->cleanupTempDirectory($tempDir);

            return true;

        } catch (\Exception $e) {
            Log::error("Restore failed for backup {$backup->id}: " . $e->getMessage());

            $backup->update(['status' => 'failed']);
            throw $e;
        }
    }

    /**
     * Cleanup old backups based on retention policy
     */
    public function cleanupOldBackups(int $daysToKeep = 30): int
    {
        $cutoffDate = Carbon::now()->subDays($daysToKeep);
        $deletedCount = 0;

        $oldBackups = Backup::where('expires_at', '<', $cutoffDate)
            ->orWhere('created_at', '<', $cutoffDate->subDays(7)) // Keep at least 7 days even if expires_at is null
            ->get();

        foreach ($oldBackups as $backup) {
            try {
                if ($backup->fileExists()) {
                    Storage::disk($this->backupDisk)->delete($backup->path . '/' . $backup->filename);
                }
                $backup->delete();
                $deletedCount++;
            } catch (\Exception $e) {
                Log::error("Failed to delete backup {$backup->id}: " . $e->getMessage());
            }
        }

        return $deletedCount;
    }

    /**
     * Generate backup filename
     */
    protected function generateBackupFilename(Project $project): string
    {
        $timestamp = now()->format('Ymd_His');
        return "backup_{$project->slug}_{$timestamp}.zip";
    }

    /**
     * Create temporary directory for backup process
     */
    protected function createTempDirectory(string $prefix): string
    {
        $tempDir = sys_get_temp_dir() . '/' . $prefix . '_' . Str::random(10);

        if (!mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
            throw new \RuntimeException("Failed to create temporary directory: {$tempDir}");
        }

        return $tempDir;
    }

    /**
     * Cleanup temporary directory
     */
    protected function cleanupTempDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            $this->deleteDirectory($dir);
        }
    }

    /**
     * Delete directory recursively
     */
    protected function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $items = scandir($dir);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }

    /**
     * Backup database for the project
     */
    protected function backupDatabase(Project $project, string $tempDir): void
    {
        $dbConfig = config('database.connections.mysql');
        $dumpFile = $tempDir . '/database.sql';

        $command = [
            'mysqldump',
            '--user=' . escapeshellarg($dbConfig['username']),
            '--password=' . escapeshellarg($dbConfig['password']),
            '--host=' . escapeshellarg($dbConfig['host']),
            '--port=' . escapeshellarg($dbConfig['port'] ?? '3306'),
            $dbConfig['database'],
            '--result-file=' . $dumpFile,
        ];

        // Add tables to dump (only project-related tables)
        $tables = [
            'projects',
            'project_domains',
            'environment_variables',
            'docker_containers',
            'deployments',
            'deployment_logs',
            'webhooks'
        ];

        // Filter tables that exist and are related to this project
        foreach ($tables as $table) {
            $command[] = $table;
        }

        $this->processRunner->capture($command, getcwd());

        if (!file_exists($dumpFile) || filesize($dumpFile) === 0) {
            throw new \RuntimeException("Database backup failed");
        }
    }

    /**
     * Backup project files
     */
    protected function backupProjectFiles(Project $project, string $tempDir): void
    {
        $projectPath = env('HOSTING_APPS_PATH', '/opt/myhosting-panel/apps') . '/' . $project->slug;

        if (!is_dir($projectPath)) {
            return; // No files to backup
        }

        $filesDir = $tempDir . '/project_files';
        mkdir($filesDir, 0755, true);

        // Copy project files
        $this->copyDirectory($projectPath, $filesDir);
    }

    /**
     * Backup environment variables
     */
    protected function backupEnvironmentVariables(Project $project, string $tempDir): void
    {
        $envFile = $tempDir . '/environment_variables.txt';
        $variables = $project->environmentVariables()->get();

        $content = "Project: {$project->name}\n";
        $content .= "Slug: {$project->slug}\n";
        $content .= "Date: " . now()->toDateTimeString() . "\n\n";

        foreach ($variables as $variable) {
            $content .= "{$variable->key}={$variable->value}\n";
        }

        file_put_contents($envFile, $content);
    }

    /**
     * Create zip archive of backup files
     */
    protected function createZipArchive(Project $project, string $tempDir, string $filename): string
    {
        $zipPath = $tempDir . '/' . $filename;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Failed to create zip archive");
        }

        // Add files to zip
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tempDir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($tempDir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();

        if (!file_exists($zipPath)) {
            throw new \RuntimeException("Failed to create zip archive");
        }

        return $zipPath;
    }

    /**
     * Store backup file in permanent storage
     */
    protected function storeBackupFile(Project $project, Backup $backup, string $zipPath): void
    {
        $storagePath = $project->slug . '/' . $backup->filename;

        if (!Storage::disk($this->backupDisk)->put($storagePath, file_get_contents($zipPath))) {
            throw new \RuntimeException("Failed to store backup file");
        }

        // Update backup with file info
        $backup->updateFileSize();
        $backup->updateChecksum();
    }

    /**
     * Extract backup file
     */
    protected function extractBackup(Backup $backup, string $tempDir): void
    {
        $zipPath = $tempDir . '/' . $backup->filename;

        // Copy backup file to temp directory
        Storage::disk($this->backupDisk)->get($backup->path . '/' . $backup->filename);
        file_put_contents($zipPath, Storage::disk($this->backupDisk)->get($backup->path . '/' . $backup->filename));

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException("Failed to open zip archive");
        }

        $zip->extractTo($tempDir);
        $zip->close();
    }

    /**
     * Restore database from backup
     */
    protected function restoreDatabase(Project $project, string $tempDir): void
    {
        $dumpFile = $tempDir . '/database.sql';

        if (!file_exists($dumpFile)) {
            return; // No database backup to restore
        }

        $dbConfig = config('database.connections.mysql');

        $command = [
            'mysql',
            '--user=' . escapeshellarg($dbConfig['username']),
            '--password=' . escapeshellarg($dbConfig['password']),
            '--host=' . escapeshellarg($dbConfig['host']),
            '--port=' . escapeshellarg($dbConfig['port'] ?? '3306'),
            $dbConfig['database'],
            '<',
            $dumpFile
        ];

        $this->processRunner->capture($command, getcwd());
    }

    /**
     * Restore project files
     */
    protected function restoreProjectFiles(Project $project, string $tempDir): void
    {
        $filesDir = $tempDir . '/project_files';
        $projectPath = env('HOSTING_APPS_PATH', '/opt/myhosting-panel/apps') . '/' . $project->slug;

        if (!is_dir($filesDir)) {
            return; // No files to restore
        }

        // Clear existing project directory
        if (is_dir($projectPath)) {
            $this->deleteDirectory($projectPath);
        }

        // Create project directory
        mkdir($projectPath, 0755, true);

        // Copy files back
        $this->copyDirectory($filesDir, $projectPath);
    }

    /**
     * Restore environment variables
     */
    protected function restoreEnvironmentVariables(Project $project, string $tempDir): void
    {
        $envFile = $tempDir . '/environment_variables.txt';

        if (!file_exists($envFile)) {
            return; // No environment variables to restore
        }

        $content = file_get_contents($envFile);
        $lines = explode("\n", $content);

        // Skip header lines
        $startRestoring = false;
        foreach ($lines as $line) {
            if (empty(trim($line))) {
                $startRestoring = true;
                continue;
            }

            if (!$startRestoring) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                if (!empty($key)) {
                    $project->environmentVariables()->updateOrCreate(
                        ['key' => $key],
                        ['value' => $value]
                    );
                }
            }
        }
    }

    /**
     * Copy directory recursively
     */
    protected function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            return;
        }

        if (!mkdir($destination, 0755, true) && !is_dir($destination)) {
            throw new \RuntimeException("Failed to create directory: {$destination}");
        }

        $items = scandir($source);
        if ($items === false) {
            throw new \RuntimeException("Failed to scan directory: {$source}");
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $srcPath = $source . '/' . $item;
            $destPath = $destination . '/' . $item;

            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $destPath);
            } else {
                copy($srcPath, $destPath);
            }
        }
    }

    /**
     * Calculate expiration date for backup
     */
    protected function calculateExpirationDate(): Carbon
    {
        $retentionDays = (int) env('BACKUP_RETENTION_DAYS', 30);
        return Carbon::now()->addDays($retentionDays);
    }
}