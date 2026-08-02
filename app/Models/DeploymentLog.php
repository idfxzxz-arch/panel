<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeploymentLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'deployment_id', 'level', 'step', 'message', 'created_at',
        'command', 'exit_code', 'stdout', 'stderr', 'working_directory',
        'duration_ms', 'status', 'started_at', 'finished_at'
    ];

    protected $casts = [
        'exit_code' => 'integer',
        'duration_ms' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
