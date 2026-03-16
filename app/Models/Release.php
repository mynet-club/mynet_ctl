<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Release extends Model
{
    protected $table = 'releases';
    protected $primaryKey = 'id';

    protected $fillable = [
        'release_id', 'product', 'version', 'platform', 'arch', 'variant',
        'filename', 'file_path', 'file_size', 'sha256', 'download_url',
        'changelog', 'is_stable', 'is_latest', 'download_cnt',
        'uploaded_by', 'status',
    ];

    protected $casts = [
        'release_id'   => 'integer',
        'file_size'    => 'integer',
        'download_cnt' => 'integer',
        'is_stable'    => 'boolean',
        'is_latest'    => 'boolean',
        'status'       => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeLatest($query)
    {
        return $query->where('is_latest', 1);
    }

    public function scopeStable($query)
    {
        return $query->where('is_stable', 1);
    }

    public function scopeForProduct($query, string $product)
    {
        return $query->where('product', $product);
    }
}
