<?php

namespace Database\Factories;

use App\Models\Backup;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BackupFactory extends Factory
{
    protected $model = Backup::class;

    public function definition(): array
    {
        $project = Project::factory()->create();
        $timestamp = now()->format('Ymd_His');
        $filename = "backup_{$project->slug}_{$timestamp}.zip";

        return [
            'project_id' => $project->id,
            'created_by' => User::factory(),
            'status' => 'completed',
            'path' => $project->slug,
            'filename' => $filename,
            'size' => $this->faker->numberBetween(1024, 10485760), // 1KB to 10MB
            'checksum' => Str::random(64),
            'completed_at' => now(),
            'expires_at' => now()->addDays(30),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'creating',
            'completed_at' => null,
            'expires_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'completed_at' => now(),
            'expires_at' => null,
        ]);
    }

    public function restoring(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'restoring',
            'completed_at' => null,
        ]);
    }
}