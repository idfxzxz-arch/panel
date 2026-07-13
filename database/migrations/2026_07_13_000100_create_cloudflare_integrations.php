<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloudflare_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('account_id', 32);
            $table->string('zone_id', 32);
            $table->uuid('tunnel_id');
            $table->string('zone_name');
            $table->text('api_token');
            $table->text('tunnel_token')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
        Schema::table('project_domains', function (Blueprint $table) {
            $table->string('cloudflare_record_id', 32)->nullable()->after('domain');
            $table->string('cloudflare_status')->nullable()->after('cloudflare_record_id');
        });
    }

    public function down(): void
    {
        Schema::table('project_domains', function (Blueprint $table) {
            $table->dropColumn(['cloudflare_record_id', 'cloudflare_status']);
        });
        Schema::dropIfExists('cloudflare_integrations');
    }
};
