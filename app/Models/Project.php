<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    protected $fillable = ['user_id', 'github_account_id', 'name', 'slug', 'type', 'repository', 'branch', 'status', 'last_commit', 'last_deployed_at'];

    protected function casts(): array
    {
        return ['last_deployed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function githubAccount(): BelongsTo
    {
        return $this->belongsTo(GithubAccount::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(ProjectDomain::class);
    }

    public function primaryDomain(): HasOne
    {
        return $this->hasOne(ProjectDomain::class)->where('is_primary', true);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    public function environmentVariables(): HasMany
    {
        return $this->hasMany(EnvironmentVariable::class);
    }

    public function webhook(): HasOne
    {
        return $this->hasOne(Webhook::class);
    }

    public function path(): string
    {
        return rtrim(config('hosting.apps_path'), '/').'/'.$this->slug;
    }
}
