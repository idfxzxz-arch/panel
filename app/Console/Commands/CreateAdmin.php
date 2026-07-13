<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdmin extends Command
{
    protected $signature = 'hosting:create-admin {email?}';

    protected $description = 'Create or update the panel administrator';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Email');
        $password = $this->secret('Password (min 12 characters)');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen((string) $password) < 12) {
            $this->error('Email tidak valid atau password kurang dari 12 karakter.');

            return self::FAILURE;
        }User::updateOrCreate(['email' => $email], ['name' => 'Administrator', 'password' => Hash::make($password)]);
        $this->info('Administrator siap.');

        return self::SUCCESS;
    }
}
