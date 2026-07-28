<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Models\Project;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BackupController extends Controller
{
    public function __construct(private BackupService $backupService)
    {
    }

    /**
     * Display a listing of backups for a project
     */
    public function index(Project $project)
    {
        $this->authorizeOwner($project);

        $backups = $project->backups()
            ->with('createdBy')
            ->latest()
            ->paginate(10);

        return view('backups.index', [
            'project' => $project,
            'backups' => $backups
        ]);
    }

    /**
     * Create a new backup for a project
     */
    public function store(Project $project)
    {
        $this->authorizeOwner($project);

        try {
            $backup = $this->backupService->createBackup($project, auth()->user());

            return redirect()->route('projects.show', $project)
                ->with('success', "Backup created successfully: {$backup->filename}");

        } catch (\Exception $e) {
            Log::error("Backup creation failed: " . $e->getMessage());

            return redirect()->route('projects.show', $project)
                ->with('error', "Backup failed: " . $e->getMessage());
        }
    }

    /**
     * Restore a backup for a project
     */
    public function restore(Project $project, Backup $backup)
    {
        $this->authorizeOwner($project);

        if ($backup->project_id !== $project->id) {
            abort(404);
        }

        if (!$backup->fileExists()) {
            return redirect()->route('projects.show', $project)
                ->with('error', "Backup file not found");
        }

        try {
            $result = $this->backupService->restoreBackup($backup, auth()->user());

            if ($result) {
                return redirect()->route('projects.show', $project)
                    ->with('success', "Backup restored successfully: {$backup->filename}");
            }

        } catch (\Exception $e) {
            Log::error("Backup restore failed: " . $e->getMessage());

            return redirect()->route('projects.show', $project)
                ->with('error', "Restore failed: " . $e->getMessage());
        }
    }

    /**
     * Delete a backup
     */
    public function destroy(Project $project, Backup $backup)
    {
        $this->authorizeOwner($project);

        if ($backup->project_id !== $project->id) {
            abort(404);
        }

        try {
            if ($backup->fileExists()) {
                \Storage::disk('backups')->delete($backup->path . '/' . $backup->filename);
            }
            $backup->delete();

            return redirect()->route('projects.show', $project)
                ->with('success', "Backup deleted successfully");

        } catch (\Exception $e) {
            Log::error("Backup deletion failed: " . $e->getMessage());

            return redirect()->route('projects.show', $project)
                ->with('error', "Failed to delete backup: " . $e->getMessage());
        }
    }

    /**
     * Download a backup file
     */
    public function download(Project $project, Backup $backup)
    {
        $this->authorizeOwner($project);

        if ($backup->project_id !== $project->id) {
            abort(404);
        }

        if (!$backup->fileExists()) {
            return redirect()->route('projects.show', $project)
                ->with('error', "Backup file not found");
        }

        return \Storage::disk('backups')->download($backup->path . '/' . $backup->filename);
    }

    /**
     * Authorize that the user owns the project
     */
    private function authorizeOwner(Project $project): void
    {
        abort_unless($project->user_id === auth()->id(), 403);
    }
}