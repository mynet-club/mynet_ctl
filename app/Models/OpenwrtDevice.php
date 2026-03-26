<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpenwrtDevice extends Model
{
    protected $table = 'openwrt_devices';

    protected $guarded = [];

    protected $casts = [
        'wifi_chips'              => 'array',
        'cpu_has_fpu'             => 'boolean',
        'cpu_has_dsp'             => 'boolean',
        'cpu_has_neon'            => 'boolean',
        'cpu_has_vfp'             => 'boolean',
        'has_uart'                => 'boolean',
        'has_jtag'                => 'boolean',
        'has_gpio'                => 'boolean',
        'has_poe_in'              => 'boolean',
        'has_poe_out'             => 'boolean',
        'wifi_has_2g'             => 'boolean',
        'wifi_has_5g'             => 'boolean',
        'wifi_has_6g'             => 'boolean',
        'wifi_is_wifi6'           => 'boolean',
        'wifi_is_wifi7'           => 'boolean',
        'wifi_antenna_detachable' => 'boolean',
        'has_sata'                => 'boolean',
        'has_m2'                  => 'boolean',
        'has_pcie'                => 'boolean',
        'has_sdcard'              => 'boolean',
        'has_sim_slot'            => 'boolean',
        'has_lte_modem'           => 'boolean',
        'has_5g_modem'            => 'boolean',
        'has_bluetooth'           => 'boolean',
        'has_zigbee'              => 'boolean',
        'has_zwave'               => 'boolean',
        'has_gps'                 => 'boolean',
        'power_dc_v'              => 'decimal:1',
        'release_year'            => 'integer',
    ];

    public function firmware(): HasMany
    {
        return $this->hasMany(OpenwrtDeviceFirmware::class, 'device_id');
    }

    /**
     * 主要固件条目（is_primary = true 且 status = 1）
     */
    public function primaryFirmware(): HasMany
    {
        return $this->firmware()->where('is_primary', true)->where('status', 1);
    }

    /**
     * 返回 "品牌 型号 版本" 拼接的完整名称
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->brand} {$this->model} {$this->version}");
    }
}
