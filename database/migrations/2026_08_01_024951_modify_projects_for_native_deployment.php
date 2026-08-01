<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('docker_containers');
        
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'type')) {
                $table->dropColumn('type');
            }
            $table->unsignedInteger('port')->nullable()->after('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
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

        Schema::table('projects', function (Blueprint $table) {
            $table->enum('type', ['static', 'laravel', 'vite', 'wordpress'])->default('laravel')->after('slug');
            $table->dropColumn('port');
        });
    }
};
