<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    protected $fillable = ['project_id', 'provider', 'uuid', 'secret', 'active', 'last_received_at'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'active' => 'boolean', 'last_received_at' => 'datetime'];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
