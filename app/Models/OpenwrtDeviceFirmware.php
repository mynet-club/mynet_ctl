<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpenwrtDeviceFirmware extends Model
{
    protected $table = 'openwrt_device_firmware';

    protected $guarded = [];

    protected $casts = [
        'supported_devices'  => 'array',
        'has_tftp_recovery'  => 'boolean',
        'has_web_recovery'   => 'boolean',
        'has_hw_crypto'      => 'boolean',
        'has_hw_nat'         => 'boolean',
        'has_hw_accel'       => 'boolean',
        'is_primary'         => 'boolean',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(OpenwrtDevice::class, 'device_id');
    }
}
