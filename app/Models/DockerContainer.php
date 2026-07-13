<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DockerContainer extends Model
{
    protected $fillable = ['project_id', 'service', 'container_name', 'container_id', 'status', 'last_seen_at'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }
}
