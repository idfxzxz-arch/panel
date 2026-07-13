<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeploymentLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['deployment_id', 'level', 'step', 'message', 'created_at'];
}
