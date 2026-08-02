<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deployment extends Model
{
    protected $fillable = ['project_id', 'triggered_by', 'trigger', 'status', 'commit_sha', 'started_at', 'finished_at', 'error_details'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DeploymentLog::class);
    }

    public function getFailedLog(): ?DeploymentLog
    {
        return $this->logs()
            ->where('status', 'failed')
            ->orderBy('created_at', 'desc')
            ->first();
    }
}
