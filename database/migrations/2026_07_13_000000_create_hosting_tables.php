<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('username')->nullable();
            $table->text('token');
            $table->timestamps();
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('github_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('type', ['static', 'laravel', 'vite']);
            $table->string('repository');
            $table->string('branch')->default('main');
            $table->enum('status', ['pending', 'deploying', 'running', 'stopped', 'failed'])->default('pending');
            $table->string('last_commit', 40)->nullable();
            $table->timestamp('last_deployed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('project_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->boolean('ssl_enabled')->default(true);
            $table->boolean('is_primary')->default(true);
            $table->timestamps();
        });
        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('triggered_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->enum('trigger', ['manual', 'webhook', 'initial'])->default('manual');
            $table->enum('status', ['queued', 'running', 'succeeded', 'failed'])->default('queued');
            $table->string('commit_sha', 40)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
        Schema::create('deployment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deployment_id')->constrained()->cascadeOnDelete();
            $table->string('level')->default('info');
            $table->string('step');
            $table->longText('message');
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('environment_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('value');
            $table->boolean('is_build_time')->default(false);
            $table->timestamps();
            $table->unique(['project_id', 'key']);
        });
        Schema::create('docker_containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('service');
            $table->string('container_name');
            $table->string('container_id')->nullable();
            $table->string('status')->default('unknown');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'service']);
        });
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('github');
            $table->string('uuid')->unique();
            $table->text('secret');
            $table->boolean('active')->default(true);
            $table->timestamp('last_received_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['webhooks', 'docker_containers', 'environment_variables', 'deployment_logs', 'deployments', 'project_domains', 'projects', 'github_accounts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
