<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->string('status')->default('creating'); // creating, completed, failed, restoring
            $table->string('path')->nullable(); // Storage path
            $table->string('filename');
            $table->unsignedBigInteger('size')->nullable(); // Size in bytes
            $table->string('checksum')->nullable(); // SHA256 checksum
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // For retention policy
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};