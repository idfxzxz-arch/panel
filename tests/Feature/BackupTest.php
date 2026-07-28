<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\EnvironmentVariable;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_creation(): void
    {
        Storage::fake('backups');

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        EnvironmentVariable::factory()->create(['project_id' => $project->id]);

        $response = $this->actingAs($user)
            ->post(route('projects.backups.store', $project));

        $response->assertRedirect(route('projects.show', $project));
        $response->assertSessionHas('success');

        $this->assertCount(1, $project->backups);
        $backup = $project->backups->first();

        $this->assertEquals('completed', $backup->status);
        $this->assertNotNull($backup->filename);
        $this->assertNotNull($backup->size);
        $this->assertNotNull($backup->checksum);
    }

    public function test_backup_listing(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        Backup::factory()->count(3)->create(['project_id' => $project->id]);

        $response = $this->actingAs($user)
            ->get(route('projects.show', $project));

        $response->assertStatus(200);
        $response->assertViewHas('project', function ($p) use ($project) {
            return $p->id === $project->id && $p->backups->count() === 3;
        });
    }

    public function test_backup_index_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        Backup::factory()->count(7)->create(['project_id' => $project->id]);

        $response = $this->actingAs($user)
            ->get(route('projects.backups.index', $project));

        $response->assertStatus(200);
        $response->assertViewHas('backups');
    }

    public function test_backup_deletion(): void
    {
        Storage::fake('backups');

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $backup = Backup::factory()->create(['project_id' => $project->id]);

        // Create a fake backup file
        $path = $project->slug . '/' . $backup->filename;
        Storage::disk('backups')->put($path, 'test content');

        $response = $this->actingAs($user)
            ->delete(route('projects.backups.destroy', [$project, $backup]));

        $response->assertRedirect(route('projects.show', $project));
        $response->assertSessionHas('success');

        $this->assertCount(0, $project->backups);
        $this->assertFalse(Storage::disk('backups')->exists($path));
    }

    public function test_backup_download(): void
    {
        Storage::fake('backups');

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $backup = Backup::factory()->create(['project_id' => $project->id]);

        // Create a fake backup file
        $path = $project->slug . '/' . $backup->filename;
        Storage::disk('backups')->put($path, 'test content');

        $response = $this->actingAs($user)
            ->get(route('projects.backups.download', [$project, $backup]));

        $response->assertDownload($backup->filename);
    }

    public function test_unauthorized_access(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user1->id]);
        $backup = Backup::factory()->create(['project_id' => $project->id]);

        // Try to access another user's backup
        $response = $this->actingAs($user2)
            ->get(route('projects.backups.index', $project));

        $response->assertStatus(403);
    }

    public function test_backup_creation_command(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $this->artisan('backup:create', ['project' => $project->id, '--user' => $user->id])
            ->assertExitCode(0);

        $this->assertCount(1, $project->backups);
    }

    public function test_backup_cleanup_command(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        // Create backups with different expiration dates
        $oldBackup = Backup::factory()->create([
            'project_id' => $project->id,
            'created_at' => now()->subDays(60),
            'expires_at' => now()->subDays(30)
        ]);

        $newBackup = Backup::factory()->create([
            'project_id' => $project->id,
            'created_at' => now(),
            'expires_at' => now()->addDays(30)
        ]);

        $this->artisan('backup:cleanup', ['--days' => 30])
            ->assertExitCode(0);

        $this->assertCount(1, $project->backups);
        $this->assertTrue($project->backups->contains($newBackup));
        $this->assertFalse($project->backups->contains($oldBackup));
    }
}