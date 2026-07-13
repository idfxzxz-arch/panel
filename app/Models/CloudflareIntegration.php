<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CloudflareIntegration extends Model
{
    protected $fillable = ['user_id', 'account_id', 'zone_id', 'tunnel_id', 'zone_name', 'api_token', 'tunnel_token', 'verified_at'];

    protected $hidden = ['api_token', 'tunnel_token'];

    protected function casts(): array
    {
        return ['api_token' => 'encrypted', 'tunnel_token' => 'encrypted', 'verified_at' => 'datetime'];
    }
}
