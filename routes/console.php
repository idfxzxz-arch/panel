<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

// Backup commands
Artisan::command('backup:create {project} {--user=}', function ($project, $user = null) {
    Artisan::call('backup:create', [
        'project' => $project,
        '--user' => $user
    ]);
    $this->info(Artisan::output());
})->purpose('Create a backup for a project');

Artisan::command('backup:restore {backup} {--user=}', function ($backup, $user = null) {
    Artisan::call('backup:restore', [
        'backup' => $backup,
        '--user' => $user
    ]);
    $this->info(Artisan::output());
})->purpose('Restore a project from a backup');

Artisan::command('backup:cleanup {--days=30}', function ($days = 30) {
    Artisan::call('backup:cleanup', [
        '--days' => $days
    ]);
    $this->info(Artisan::output());
})->purpose('Cleanup old backups based on retention policy');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
