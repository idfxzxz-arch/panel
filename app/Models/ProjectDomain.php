<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDomain extends Model
{
    protected $fillable = ['project_id', 'domain', 'cloudflare_record_id', 'cloudflare_status', 'ssl_enabled', 'is_primary'];

    protected function casts(): array
    {
        return ['ssl_enabled' => 'boolean', 'is_primary' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
