<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;

class Backup extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'created_by',
        'status',
        'path',
        'filename',
        'size',
        'checksum',
        'completed_at',
        'expires_at'
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the full storage path for the backup file
     */
    public function getFullPath(): string
    {
        return $this->path ? Storage::disk('backups')->path($this->path . '/' . $this->filename) : '';
    }

    /**
     * Check if backup file exists
     */
    public function fileExists(): bool
    {
        return $this->path && Storage::disk('backups')->exists($this->path . '/' . $this->filename);
    }

    /**
     * Calculate and update file size
     */
    public function updateFileSize(): void
    {
        if ($this->fileExists()) {
            $size = Storage::disk('backups')->size($this->path . '/' . $this->filename);
            $this->update(['size' => $size]);
        }
    }

    /**
     * Calculate and update checksum
     */
    public function updateChecksum(): void
    {
        if ($this->fileExists()) {
            $filePath = $this->getFullPath();
            $checksum = hash_file('sha256', $filePath);
            $this->update(['checksum' => $checksum]);
        }
    }
}