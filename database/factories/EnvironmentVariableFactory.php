<?php

namespace Database\Factories;

use App\Models\EnvironmentVariable;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnvironmentVariableFactory extends Factory
{
    protected $model = EnvironmentVariable::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'key' => $this->faker->unique()->word,
            'value' => $this->faker->word,
            'is_build_time' => $this->faker->boolean,
        ];
    }
}