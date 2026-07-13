<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GithubAccount extends Model
{
    protected $fillable = ['user_id', 'username', 'token'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['token' => 'encrypted'];
    }
}
