<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Script extends Model
{
    protected $table = 'scripts';
    protected $primaryKey = 'id';

    protected $fillable = [
        'script_id', 'name', 'display_name', 'category', 'platform',
        'version', 'target_version', 'description', 'content', 'lang', 'checksum',
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

    /**
     * 通用版（target_version IS NULL）
     */
    public function scopeGeneric($query)
    {
        return $query->whereNull('target_version');
    }

    /**
     * 指定产品版本或通用
     */
    public function scopeForTargetVersion($query, ?string $targetVersion)
    {
        return $targetVersion
            ? $query->where('target_version', $targetVersion)
            : $query->whereNull('target_version');
    }
}
