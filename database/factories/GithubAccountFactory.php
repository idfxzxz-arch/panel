<?php

namespace Database\Factories;

use App\Models\GithubAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GithubAccountFactory extends Factory
{
    protected $model = GithubAccount::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'username' => $this->faker->userName,
            'token' => Str::random(40),
        ];
    }
}