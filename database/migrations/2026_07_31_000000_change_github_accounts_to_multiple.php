<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ubah foreign key di github_accounts untuk support multiple accounts per user
        Schema::table('github_accounts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });

        // Ubah foreign key di projects untuk nullOnDelete
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['github_account_id']);
            $table->foreign('github_account_id')
                  ->references('id')
                  ->on('github_accounts')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Kembalikan ke struktur asli
        Schema::table('github_accounts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['github_account_id']);
            $table->foreign('github_account_id')
                  ->references('id')
                  ->on('github_accounts')
                  ->nullOnDelete();
        });
    }
};