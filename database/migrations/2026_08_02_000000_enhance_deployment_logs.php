<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployment_logs', function (Blueprint $table) {
            $table->string('command')->nullable()->after('step');
            $table->integer('exit_code')->nullable()->after('command');
            $table->longText('stdout')->nullable()->after('exit_code');
            $table->longText('stderr')->nullable()->after('stdout');
            $table->string('working_directory')->nullable()->after('stderr');
            $table->unsignedBigInteger('duration_ms')->nullable()->after('working_directory');
            $table->enum('status', ['pending', 'running', 'success', 'failed'])->default('running')->after('level');
            $table->timestamp('started_at')->nullable()->after('status');
            $table->timestamp('finished_at')->nullable()->after('started_at');
        });

        Schema::table('deployments', function (Blueprint $table) {
            $table->longText('error_details')->nullable()->after('finished_at');
        });
    }

    public function down(): void
    {
        Schema::table('deployment_logs', function (Blueprint $table) {
            $table->dropColumn(['command', 'exit_code', 'stdout', 'stderr', 'working_directory', 'duration_ms', 'status', 'started_at', 'finished_at']);
        });

        Schema::table('deployments', function (Blueprint $table) {
            $table->dropColumn(['error_details']);
        });
    }
};