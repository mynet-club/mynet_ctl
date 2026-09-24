<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plugin extends Model
{
    protected $table = 'plugins';

    protected $primaryKey = 'id';

    protected $fillable = [
        'plugin_id', 'name', 'display_name', 'category', 'version',
        'platform', 'arch', 'description', 'filename', 'file_path',
        'file_size', 'sha256', 'download_url', 'dependencies', 'metadata',
        'download_cnt', 'uploaded_by', 'status',
    ];

    protected $casts = [
        'plugin_id' => 'integer',
        'file_size' => 'integer',
        'download_cnt' => 'integer',
        'dependencies' => 'array',
        'metadata' => 'array',
        'status' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where(function ($q) use ($platform) {
            $q->where('platform', $platform)->orWhere('platform', 'all');
        });
    }
}
