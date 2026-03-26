<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * openwrt_device_firmware — OpenWrt 设备固件/ROM 支持表
 *
 * 存储：固件/构建系统相关元数据（target、arch、ctl_arch、OpenWrt 版本支持等）
 * 一行 = 一个设备 × 一个 target/arch 组合（大多数设备只有一行）
 *
 * 为什么单独一张表：
 *   OpenWrt 的 target/subtarget 是"构建系统概念"（ROM），不是纯硬件分类。
 *   少数设备有多个 target（如同一硬件有 generic 和 nand 变体），
 *   或不同 OpenWrt 版本下 arch 发生变化，故与硬件解耦更灵活。
 *
 * 数据来源：downloads.openwrt.org/releases/{ver}/targets/{target}/{sub}/profiles.json
 *   - arch_packages (顶层) → distrib_arch = DISTRIB_ARCH
 *   - target (顶层)        → distrib_target = DISTRIB_TARGET
 *   - profiles[id].supported_devices → board_name 列表（设备树 compatible）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('openwrt_device_firmware', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')
                ->constrained('openwrt_devices')
                ->cascadeOnDelete()
                ->comment('关联 openwrt_devices.id');

            // ── OpenWrt 构建分类 ─────────────────────────────────────────────
            $table->string('openwrt_target', 32)->default('')
                ->comment('OpenWrt build target，如 ramips / ath79 / mediatek / x86');
            $table->string('openwrt_subtarget', 32)->default('')
                ->comment('OpenWrt subtarget，如 mt7621 / generic / filogic / 64');
            $table->string('distrib_target', 64)->default('')
                ->comment('target/subtarget 合并，= DISTRIB_TARGET，如 ramips/mt7621');
            $table->string('distrib_arch', 64)->default('')
                ->comment('= arch_packages = DISTRIB_ARCH，如 mipsel_24kc / aarch64_cortex-a53');

            // ── 设备标识（固件侧） ─────────────────────────────────────────────
            $table->json('supported_devices')->nullable()
                ->comment('DT compatible 字符串列表（/tmp/sysinfo/board_name 来源），如 ["xiaomi,mi-router-4a-gigabit"]');

            // ── OpenWrt 版本支持 ──────────────────────────────────────────────
            $table->string('openwrt_since_rev', 16)->default('')
                ->comment('最初支持的 SVN revision，如 r12345');
            $table->string('openwrt_since_rel', 16)->default('')
                ->comment('最初支持的发布版本，如 17.01 / 21.02');
            $table->string('openwrt_until_rel', 16)->default('')
                ->comment('最后支持版本，空字符串表示仍在维护（EOL 时填写）');
            $table->string('openwrt_wiki_url', 512)->default('')
                ->comment('设备 wiki 页面 URL');
            $table->string('openwrt_image_url', 512)->default('')
                ->comment('固件下载页面 URL');
            $table->string('openwrt_techdata_url', 512)->default('')
                ->comment('技术参数页面 URL（ToH techdata）');

            // ── Bootloader / 刷机 ─────────────────────────────────────────────
            $table->string('bootloader', 32)->default('')
                ->comment('引导程序类型: U-Boot / CFE / RedBoot / GRUB / EFI / Breed / PandoraBox');
            $table->string('bootloader_version', 32)->default('');
            $table->boolean('has_tftp_recovery')->default(false)
                ->comment('支持 TFTP 恢复刷机');
            $table->boolean('has_web_recovery')->default(false)
                ->comment('支持 Web 界面恢复刷机');
            $table->string('flash_layout', 32)->default('')
                ->comment('分区布局: single / dual / ubi / squashfs+jffs2 / ext4');

            // ── CTL 架构映射（核心字段，用于下载推荐） ─────────────────────────
            $table->string('ctl_arch', 32)->default('')
                ->comment('CTL arch 标识，与 releases.arch 字段一致，如 mipsel / armv7 / arm64');
            $table->string('libc', 16)->default('musl')
                ->comment('libc: musl | uclibc | glibc');
            $table->string('float_abi', 8)->default('soft')
                ->comment('浮点 ABI: hard | soft');
            $table->string('sdk_toolchain', 64)->default('')
                ->comment('SDK/工具链标识，如 musl-gcc / uclibc-gcc');
            $table->string('cross_compile_prefix', 64)->default('')
                ->comment('交叉编译前缀，如 mipsel-openwrt-linux-musl-');

            // ── 硬件加速（固件侧 —— 取决于 kmod 支持）──────────────────────────
            $table->boolean('has_hw_crypto')->default(false)
                ->comment('有硬件加密加速 kmod（AES/SHA 等）');
            $table->boolean('has_hw_nat')->default(false)
                ->comment('有硬件 NAT Flow Offload 支持');
            $table->boolean('has_hw_accel')->default(false)
                ->comment('有专用数据包加速引擎（SFE/PPE/NSS 等）');
            $table->string('hw_accel_note', 128)->default('')
                ->comment('加速说明，如 MT7621 MIPS DSP HW NAT via kmod-mt7621-nf-hw-offload');

            // ── 支持状态 ──────────────────────────────────────────────────────
            $table->string('support_status', 16)->default('supported')
                ->comment('supported | eol | testing | broken | unknown');
            $table->boolean('is_primary')->default(true)
                ->comment('是否为该设备的推荐/主要 firmware 条目（多 target 时标记主条目）');
            $table->text('notes')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();

            // ── 唯一键 + 索引 ─────────────────────────────────────────────────
            $table->unique(
                ['device_id', 'distrib_target', 'distrib_arch'],
                'uk_device_target_arch'
            );
            $table->index('distrib_target');
            $table->index('distrib_arch');
            $table->index('ctl_arch');
            $table->index('support_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('openwrt_device_firmware');
    }
};
