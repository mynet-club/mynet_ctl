<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * openwrt_devices — OpenWrt 设备硬件事实表
 *
 * 存储：纯硬件信息（SoC、内存、接口、WiFi、物理规格）
 * 一行 = 一个物理设备型号变体（如 Xiaomi Mi Router 4A Gigabit Edition）
 *
 * 注意：target/arch（固件/ROM 概念）存入 openwrt_device_firmware，不在本表。
 * 数据来源：downloads.openwrt.org profiles.json + OpenWrt ToH CSV 人工补充
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('openwrt_devices', function (Blueprint $table) {
            $table->id();

            // ── 设备标识 ──────────────────────────────────────────────────────
            $table->string('toh_pid', 32)->nullable()->unique()
                ->comment('OpenWrt Table of Hardware 唯一 ID');
            $table->string('brand', 64)->default('')
                ->comment('设备品牌，如 Xiaomi / TP-Link / GL.iNet');
            $table->string('model', 128)->default('')
                ->comment('设备型号，如 Mi Router 4A');
            $table->string('version', 64)->default('')
                ->comment('硬件版本/变体，如 v1.0 / Gigabit Edition / 16M Flash');
            $table->string('device_slug', 200)->nullable()->unique()
                ->comment('URL 友好标识（由 openwrt_id 生成），如 xiaomi-mi-router-4a-gigabit');
            $table->string('openwrt_id', 128)->nullable()->unique()
                ->comment('OpenWrt image profile ID，如 xiaomi_mi-router-4a-gigabit');
            $table->string('device_type', 32)->default('router')
                ->comment('设备类型: router / ap / switch / sbc / cpe / industrial');
            $table->string('availability', 16)->default('unknown')
                ->comment('available / discontinued / unknown');
            $table->string('oem_url', 512)->default('')
                ->comment('OEM 官方产品页面 URL');
            $table->year('release_year')->nullable()
                ->comment('产品发布年份');

            // ── SoC / CPU ─────────────────────────────────────────────────────
            $table->string('soc_name', 128)->default('')
                ->comment('SoC 完整型号，如 MediaTek MT7621A');
            $table->string('soc_vendor', 64)->default('')
                ->comment('SoC 厂商，如 MediaTek / Qualcomm Atheros / Broadcom / Marvell');
            $table->string('soc_arch_family', 32)->default('')
                ->comment('CPU 架构族: MIPS / ARM / x86 / RISC-V');
            $table->tinyInteger('soc_bits')->unsigned()->default(0)
                ->comment('CPU 位宽：32 / 64');
            $table->tinyInteger('cpu_cores')->unsigned()->default(0);
            $table->tinyInteger('cpu_threads')->unsigned()->default(0);
            $table->smallInteger('cpu_freq_mhz')->unsigned()->default(0);
            $table->smallInteger('cpu_freq_max_mhz')->unsigned()->default(0)
                ->comment('最大睿频 MHz（动态调频时）');
            $table->tinyInteger('cpu_process_nm')->unsigned()->default(0)
                ->comment('制程 nm，如 28 / 12 / 7');
            $table->smallInteger('cpu_l2_cache_kb')->unsigned()->default(0);
            $table->boolean('cpu_has_fpu')->default(false)
                ->comment('有硬件 FPU（影响 soft/hard-float ABI 选择）');
            $table->boolean('cpu_has_dsp')->default(false)
                ->comment('有 DSP 模块，如 MIPS DSPr2');
            $table->boolean('cpu_has_neon')->default(false)
                ->comment('有 NEON/SIMD（ARM）');
            $table->boolean('cpu_has_vfp')->default(false)
                ->comment('有 VFP 浮点（ARM）');
            $table->string('cpu_extra_flags', 128)->default('')
                ->comment('额外 CPU 特性标志，空格分隔，如 "mips16 dsp dsp2"');

            // ── 内存 / 存储 ───────────────────────────────────────────────────
            $table->smallInteger('ram_mb')->unsigned()->default(0);
            $table->string('ram_type', 16)->default('')
                ->comment('DDR / DDR2 / DDR3 / LPDDR3 / LPDDR4');
            $table->smallInteger('flash_mb')->unsigned()->default(0)
                ->comment('Boot Flash 大小 MB（NOR/NAND）');
            $table->string('flash_type', 16)->default('')
                ->comment('NOR / SPI-NOR / NAND / SPI-NAND / eMMC');
            $table->integer('emmc_gb')->unsigned()->default(0)
                ->comment('板载 eMMC 容量 GB（独立于 Boot Flash）');

            // ── 调试接口 / GPIO ───────────────────────────────────────────────
            $table->boolean('has_uart')->default(false)
                ->comment('有 UART/串口焊盘');
            $table->string('uart_info', 64)->default('')
                ->comment('串口信息，如 115200/8N1 3.3V TTL');
            $table->boolean('has_jtag')->default(false);
            $table->boolean('has_gpio')->default(false);
            $table->tinyInteger('gpio_count')->unsigned()->default(0);
            $table->tinyInteger('led_count')->unsigned()->default(0);
            $table->tinyInteger('button_count')->unsigned()->default(0);

            // ── 以太网 ────────────────────────────────────────────────────────
            $table->tinyInteger('eth_ports')->unsigned()->default(0)
                ->comment('以太网口总数');
            $table->tinyInteger('eth_wan_ports')->unsigned()->default(0);
            $table->tinyInteger('eth_lan_ports')->unsigned()->default(0);
            $table->tinyInteger('eth_100m_ports')->unsigned()->default(0)
                ->comment('Fast Ethernet 100Mbps 口');
            $table->tinyInteger('eth_1g_ports')->unsigned()->default(0)
                ->comment('Gigabit 1GbE 口');
            $table->tinyInteger('eth_2_5g_ports')->unsigned()->default(0);
            $table->tinyInteger('eth_5g_ports')->unsigned()->default(0);
            $table->tinyInteger('eth_10g_ports')->unsigned()->default(0)
                ->comment('10GbE 铜缆口');
            $table->tinyInteger('sfp_ports')->unsigned()->default(0)
                ->comment('SFP 光口');
            $table->tinyInteger('sfp_plus_ports')->unsigned()->default(0)
                ->comment('SFP+ 10G 光口');
            $table->string('eth_switch_chip', 64)->default('')
                ->comment('交换芯片，如 MediaTek MT7530 / Realtek RTL8367');
            $table->boolean('has_poe_in')->default(false)
                ->comment('支持 PoE 供电输入（802.3af/at/bt）');
            $table->boolean('has_poe_out')->default(false)
                ->comment('支持 PoE 供电输出');

            // ── WiFi ─────────────────────────────────────────────────────────
            $table->json('wifi_chips')->nullable()
                ->comment('WiFi 芯片列表，如 ["MT7603E","MT7612E"]');
            $table->boolean('wifi_has_2g')->default(false);
            $table->string('wifi_2g_spec', 64)->default('')
                ->comment('如 802.11n 300Mbps 2T2R');
            $table->boolean('wifi_has_5g')->default(false);
            $table->string('wifi_5g_spec', 64)->default('')
                ->comment('如 802.11ac 867Mbps 2T2R');
            $table->boolean('wifi_has_6g')->default(false);
            $table->string('wifi_6g_spec', 64)->default('');
            $table->boolean('wifi_is_wifi6')->default(false)
                ->comment('802.11ax');
            $table->boolean('wifi_is_wifi7')->default(false)
                ->comment('802.11be');
            $table->smallInteger('wifi_max_speed_mbps')->unsigned()->default(0);
            $table->tinyInteger('wifi_antennas')->unsigned()->default(0);
            $table->boolean('wifi_antenna_detachable')->default(false);
            $table->string('wifi_mu_mimo', 8)->default('')
                ->comment('MU-MIMO，如 2x2 / 4x4');

            // ── USB / 扩展接口 ────────────────────────────────────────────────
            $table->tinyInteger('usb2_ports')->unsigned()->default(0);
            $table->tinyInteger('usb3_ports')->unsigned()->default(0);
            $table->tinyInteger('usbc_ports')->unsigned()->default(0);
            $table->boolean('has_sata')->default(false);
            $table->tinyInteger('sata_ports')->unsigned()->default(0);
            $table->boolean('has_m2')->default(false);
            $table->string('m2_type', 32)->default('')
                ->comment('如 M.2 2242 PCIe / M.2 2280 NVMe');
            $table->boolean('has_pcie')->default(false);
            $table->string('pcie_spec', 32)->default('')
                ->comment('如 PCIe 2.0 x1');
            $table->boolean('has_sdcard')->default(false);
            $table->boolean('has_sim_slot')->default(false);
            $table->tinyInteger('sim_slots')->unsigned()->default(0);

            // ── 蜂窝 / 无线扩展 ──────────────────────────────────────────────
            $table->boolean('has_lte_modem')->default(false);
            $table->boolean('has_5g_modem')->default(false);
            $table->string('modem_model', 64)->default('')
                ->comment('如 Quectel EC25 / EM06');
            $table->boolean('has_bluetooth')->default(false);
            $table->string('bluetooth_version', 8)->default('')
                ->comment('如 4.2 / 5.0');
            $table->boolean('has_zigbee')->default(false);
            $table->boolean('has_zwave')->default(false);
            $table->boolean('has_gps')->default(false);

            // ── 电源 / 物理规格 ───────────────────────────────────────────────
            $table->decimal('power_dc_v', 4, 1)->default(0)
                ->comment('供电电压 V，如 12.0 / 5.0 / 48.0（PoE）');
            $table->tinyInteger('power_max_w')->unsigned()->default(0);
            $table->string('form_factor', 32)->default('router')
                ->comment('router / ap / switch / sbc / cpe / industrial / pod / rack');
            $table->smallInteger('size_mm_l')->unsigned()->default(0);
            $table->smallInteger('size_mm_w')->unsigned()->default(0);
            $table->smallInteger('size_mm_h')->unsigned()->default(0);
            $table->smallInteger('weight_g')->unsigned()->default(0);

            // ── 元数据 ────────────────────────────────────────────────────────
            $table->string('tags', 255)->default('')
                ->comment('逗号分隔标签，如 gigabit,wifi6,usb3,poe');
            $table->text('notes')->nullable()
                ->comment('备注：刷机注意、已知问题、参考链接等');
            $table->tinyInteger('status')->default(1);
            $table->timestamps();

            // ── 索引 ──────────────────────────────────────────────────────────
            $table->index(['brand', 'model']);
            $table->index('soc_vendor');
            $table->index('soc_name');
            $table->index('device_type');
            $table->index('availability');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('openwrt_devices');
    }
};
