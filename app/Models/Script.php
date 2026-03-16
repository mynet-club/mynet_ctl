<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Script extends Model
{
    protected $table = 'scripts';
    protected $primaryKey = 'id';

    protected $fillable = [
        'script_id', 'name', 'display_name', 'category', 'platform',
        'version', 'description', 'content', 'lang', 'checksum',
        'is_standalone', 'download_url', 'download_cnt', 'uploaded_by', 'status',
    ];

    protected $casts = [
        'script_id'    => 'integer',
        'download_cnt' => 'integer',
        'is_standalone'=> 'boolean',
        'status'       => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeStandalone($query)
    {
        return $query->where('is_standalone', 1);
    }
}
