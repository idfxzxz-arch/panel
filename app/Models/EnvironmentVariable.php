<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnvironmentVariable extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'key', 'value', 'is_build_time'];

    protected $hidden = ['value'];

    protected function casts(): array
    {
        return ['value' => 'encrypted', 'is_build_time' => 'boolean'];
    }
}
