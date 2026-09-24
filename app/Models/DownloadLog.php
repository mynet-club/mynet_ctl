<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DownloadLog extends Model
{
    protected $table = 'download_logs';

    public $timestamps = false;

    protected $fillable = [
        'resource_type', 'resource_id', 'ip', 'user_agent', 'referer', 'created_at',
    ];

    protected $casts = [
        'resource_id' => 'integer',
        'created_at' => 'datetime',
    ];
}
