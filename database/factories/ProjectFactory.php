<?php

namespace Database\Factories;

use App\Models\GithubAccount;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word;
        $slug = Str::slug($name);

        return [
            'user_id' => User::factory(),
            'github_account_id' => GithubAccount::factory(),
            'name' => $name,
            'slug' => $slug,
            'port' => $this->faker->optional()->numberBetween(3000, 9999),
            'repository' => $this->faker->optional()->url,
            'branch' => 'main',
            'status' => 'running',
            'last_commit' => $this->faker->optional()->sha1,
            'last_deployed_at' => $this->faker->optional()->dateTimeThisYear,
        ];
    }
}
