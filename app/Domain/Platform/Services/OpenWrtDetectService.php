<?php

namespace App\Domain\Platform\Services;

/**
 * OpenWrtDetectService
 *
 * 纯业务逻辑：根据 OpenWrt 系统信息判断 CTL 兼容的架构标识（arch），
 * 以及识别硬件型号（SoC/CPU）。
 *
 * 调用者传入：
 *   - $distribArch   来自 /etc/openwrt_release 的 DISTRIB_ARCH  (如 mipsel_24kc)
 *   - $distribTarget 来自 /etc/openwrt_release 的 DISTRIB_TARGET (如 ramips/mt7621)
 *
 * 返回：
 *   - arch      CTL 兼容的架构标识（与 ReleaseDomainService::ARCHITECTURES 对齐）
 *   - soc       芯片型号（人类可读）
 *   - cpu       CPU 描述
 *   - libc      推断的 libc (musl | uclibc | unknown)
 *   - note      额外说明
 *   - confidence  "exact" | "arch_only" | "target_only" | "fallback"
 */
class OpenWrtDetectService
{
    /**
     * DISTRIB_ARCH → arch 精确映射表
     *
     * 键名：/etc/openwrt_release 中 DISTRIB_ARCH 的值
     * 值：CTL 的 arch 标识（与 releases 表 arch 字段保持一致）
     *
     * 命名规则与 arch_list.txt 保持同步：
     *   - 默认 hard-float 不加后缀
     *   - soft-float 加 -softfp
     */
    private const ARCH_MAP = [
        // ── x86 ──────────────────────────────────────────────────────────────
        'x86_64' => ['arch' => 'amd64',         'note' => 'x86 64-bit'],
        'i386_pentium4' => ['arch' => 'i386',          'note' => 'x86 32-bit (Pentium4+)'],
        'i386_pentium' => ['arch' => 'i386',          'note' => 'x86 32-bit'],
        'i386_geode' => ['arch' => 'i386',          'note' => 'x86 32-bit (Geode GX/LX)'],

        // ── ARM 64-bit (aarch64) ─────────────────────────────────────────────
        'aarch64_cortex-a53' => ['arch' => 'arm64',         'note' => 'ARMv8-A 64-bit (Cortex-A53)，常见于 MT7622, IPQ6018, BCM2837'],
        'aarch64_cortex-a55' => ['arch' => 'arm64',         'note' => 'ARMv8-A 64-bit (Cortex-A55)，常见于 MT7981, MT7986'],
        'aarch64_cortex-a72' => ['arch' => 'arm64',         'note' => 'ARMv8-A 64-bit (Cortex-A72)，常见于 BCM2711 (RPi 4), Marvell CN9130'],
        'aarch64_cortex-a73' => ['arch' => 'arm64',         'note' => 'ARMv8-A 64-bit (Cortex-A73)，常见于 MT7988 Filogic 880'],
        'aarch64_generic' => ['arch' => 'arm64',         'note' => 'ARMv8-A 64-bit (generic)'],

        // ── ARM 32-bit hard-float (vfp/neon 特性时使用 hard-float 编译) ─────
        'arm_cortex-a7_neon-vfpv4' => ['arch' => 'armv7',         'note' => 'ARMv7-A hard-float (Cortex-A7 NEON)，常见于 MT7623, IPQ4018/4019'],
        'arm_cortex-a7_neon' => ['arch' => 'armv7',         'note' => 'ARMv7-A hard-float (Cortex-A7 NEON)'],
        'arm_cortex-a9_vfpv3-d16' => ['arch' => 'armv7',         'note' => 'ARMv7-A hard-float (Cortex-A9 VFPv3)，常见于 Marvell Armada 370/38x'],
        'arm_cortex-a15_neon-vfpv4' => ['arch' => 'armv7',         'note' => 'ARMv7-A hard-float (Cortex-A15)，常见于 Exynos 5'],
        'arm_cortex-a5_vfpv4' => ['arch' => 'armv7',         'note' => 'ARMv7-A hard-float (Cortex-A5)'],
        'arm_cortex-a8_vfpv3' => ['arch' => 'armv7',         'note' => 'ARMv7-A hard-float (Cortex-A8)'],
        'arm_mpcore' => ['arch' => 'armv7',         'note' => 'ARMv6K MPCore'],

        // ── ARM 32-bit soft-float ─────────────────────────────────────────────
        'arm_cortex-a7' => ['arch' => 'armv7-softfp',  'note' => 'ARMv7-A soft-float (Cortex-A7)'],
        'arm_cortex-a9' => ['arch' => 'armv7-softfp',  'note' => 'ARMv7-A soft-float (Cortex-A9)'],
        'arm_arm1176jzf-s_vfp' => ['arch' => 'armv7-softfp',  'note' => 'ARMv6 (BCM2835, 旧版 RPi 1)'],
        'arm_xscale' => ['arch' => 'armv7-softfp',  'note' => 'Intel XScale ARMv5TE'],
        'arm_fa526' => ['arch' => 'armv7-softfp',  'note' => 'Faraday FA526 ARMv4T'],

        // ── MIPS 32-bit Big-Endian (soft-float) ──────────────────────────────
        'mips_24kc' => ['arch' => 'mips',          'note' => 'MIPS 32-bit BE (24Kc)，常见于 Atheros AR7161/AR9344/QCA953x'],
        'mips_24kec' => ['arch' => 'mips',          'note' => 'MIPS 32-bit BE (24KEc DSP)，常见于 BCM5354, RT2880'],
        'mips_74kc' => ['arch' => 'mips',          'note' => 'MIPS 32-bit BE (74Kc)，常见于旧版 AR9xxx big-endian 路由'],
        'mips_mips32' => ['arch' => 'mips',          'note' => 'MIPS 32-bit BE (generic MIPS32)'],
        'mips_octeon' => ['arch' => 'mips64',        'note' => 'MIPS 64-bit BE (Cavium Octeon)'],

        // ── MIPS 32-bit Little-Endian (soft-float，OpenWrt 默认 musl-sf) ────
        'mipsel_24kc' => ['arch' => 'mipsel',        'note' => 'MIPS 32-bit LE (24Kc)，常见于 MT7620/MT7621/MT7628/MT7688'],
        'mipsel_24kec' => ['arch' => 'mipsel',        'note' => 'MIPS 32-bit LE (24KEc DSP)'],
        'mipsel_74kc' => ['arch' => 'mipsel',        'note' => 'MIPS 32-bit LE (74Kc)'],
        'mipsel_mips32' => ['arch' => 'mipsel',        'note' => 'MIPS 32-bit LE (generic MIPS32)，常见于 BCM63xx/BCM47xx'],

        // ── MIPS 64-bit ───────────────────────────────────────────────────────
        'mips64_octeonplus' => ['arch' => 'mips64',        'note' => 'MIPS 64-bit BE (Octeon+)，Cavium 网络处理器'],
        'mips64el_generic' => ['arch' => 'mips64el',      'note' => 'MIPS 64-bit LE (generic)'],
        'mips64_loongson2' => ['arch' => 'mips64',        'note' => 'MIPS 64-bit BE (Loongson 2E/2F)'],

        // ── RISC-V ────────────────────────────────────────────────────────────
        'riscv64_riscv64' => ['arch' => 'riscv64',       'note' => 'RISC-V 64-bit (RV64GC)'],
    ];

    /**
     * OpenWrt DISTRIB_TARGET → SoC 信息映射
     *
     * 格式: 'target/subtarget' => [soc, cpu, arch_hint]
     * arch_hint: 辅助确认 arch（当 DISTRIB_ARCH 缺失时作为 fallback）
     */
    private const TARGET_MAP = [
        // ── MediaTek / Ralink MIPS LE (ramips) ─────────────────────────────
        'ramips/mt7621' => ['soc' => 'MediaTek MT7621A',                 'cpu' => 'MIPS 32-bit LE 880MHz dual-core',   'arch_hint' => 'mipsel'],
        'ramips/mt7620' => ['soc' => 'MediaTek MT7620A/N',               'cpu' => 'MIPS 24Kc 580MHz',                  'arch_hint' => 'mipsel'],
        'ramips/mt7628' => ['soc' => 'MediaTek MT7628/MT7688',           'cpu' => 'MIPS 24Kc 580MHz',                  'arch_hint' => 'mipsel'],
        'ramips/rt305x' => ['soc' => 'Ralink RT305x',                    'cpu' => 'MIPS 24Kc 384MHz',                  'arch_hint' => 'mipsel'],
        'ramips/rt3883' => ['soc' => 'Ralink RT3883',                    'cpu' => 'MIPS 74Kc 500MHz',                  'arch_hint' => 'mipsel'],

        // ── Qualcomm Atheros MIPS BE (ath79) ────────────────────────────────
        'ath79/generic' => ['soc' => 'Qualcomm Atheros AR71xx/AR9xxx/QCA95xx', 'cpu' => 'MIPS 24Kc 400-750MHz',        'arch_hint' => 'mips'],
        'ath79/nand' => ['soc' => 'Qualcomm Atheros AR71xx (NAND)',   'cpu' => 'MIPS 24Kc',                         'arch_hint' => 'mips'],
        'ath79/mikrotik' => ['soc' => 'Qualcomm Atheros (MikroTik)',      'cpu' => 'MIPS 24Kc/74Kc 600-720MHz',         'arch_hint' => 'mips'],
        'ar71xx/generic' => ['soc' => 'Atheros AR71xx (legacy target)',   'cpu' => 'MIPS 24Kc',                         'arch_hint' => 'mips'],

        // ── Qualcomm IPQ ARM ─────────────────────────────────────────────────
        'ipq40xx/generic' => ['soc' => 'Qualcomm IPQ4018/4019/4028/4029', 'cpu' => 'ARM Cortex-A7 quad-core 716MHz',    'arch_hint' => 'armv7'],
        'ipq806x/generic' => ['soc' => 'Qualcomm IPQ8065/8064',           'cpu' => 'ARM Cortex-A15 dual-core 1.4GHz',   'arch_hint' => 'armv7'],
        'ipq807x/generic' => ['soc' => 'Qualcomm IPQ8074/8072/8070',      'cpu' => 'ARM Cortex-A53 quad-core 2GHz',     'arch_hint' => 'arm64'],
        'ipq60xx/generic' => ['soc' => 'Qualcomm IPQ6018/6028',           'cpu' => 'ARM Cortex-A53 quad-core 1.8GHz',   'arch_hint' => 'arm64'],

        // ── MediaTek ARM ─────────────────────────────────────────────────────
        'mediatek/mt7622' => ['soc' => 'MediaTek MT7622',                 'cpu' => 'ARM Cortex-A53 dual-core 1.35GHz',  'arch_hint' => 'arm64'],
        'mediatek/mt7623' => ['soc' => 'MediaTek MT7623',                 'cpu' => 'ARM Cortex-A7 quad-core 1.3GHz',    'arch_hint' => 'armv7'],
        'mediatek/mt7981' => ['soc' => 'MediaTek MT7981B',                'cpu' => 'ARM Cortex-A53 dual-core 1.3GHz',   'arch_hint' => 'arm64'],
        'mediatek/mt7986' => ['soc' => 'MediaTek MT7986 (Filogic 830)',   'cpu' => 'ARM Cortex-A53 quad-core 2GHz',     'arch_hint' => 'arm64'],
        'mediatek/mt7988' => ['soc' => 'MediaTek MT7988 (Filogic 880)',   'cpu' => 'ARM Cortex-A73 quad-core 1.8GHz',   'arch_hint' => 'arm64'],

        // ── Broadcom ─────────────────────────────────────────────────────────
        'bcm27xx/bcm2708' => ['soc' => 'Broadcom BCM2835 (RPi 1)',        'cpu' => 'ARM1176JZF-S 700MHz',               'arch_hint' => 'armv7-softfp'],
        'bcm27xx/bcm2709' => ['soc' => 'Broadcom BCM2836 (RPi 2)',        'cpu' => 'ARM Cortex-A7 quad-core 900MHz',    'arch_hint' => 'armv7'],
        'bcm27xx/bcm2710' => ['soc' => 'Broadcom BCM2837 (RPi 3)',        'cpu' => 'ARM Cortex-A53 quad-core 1.2GHz',   'arch_hint' => 'arm64'],
        'bcm27xx/bcm2711' => ['soc' => 'Broadcom BCM2711 (RPi 4/CM4)',    'cpu' => 'ARM Cortex-A72 quad-core 1.8GHz',   'arch_hint' => 'arm64'],
        'bcm47xx/generic' => ['soc' => 'Broadcom BCM47xx (MIPS)',         'cpu' => 'MIPS 32-bit LE',                    'arch_hint' => 'mipsel'],
        'bcm63xx/generic' => ['soc' => 'Broadcom BCM63xx',                'cpu' => 'MIPS 32-bit BE',                    'arch_hint' => 'mips'],
        'bcm53xx/generic' => ['soc' => 'Broadcom BCM5301x/4709x',        'cpu' => 'ARM Cortex-A9',                     'arch_hint' => 'armv7'],

        // ── Marvell ──────────────────────────────────────────────────────────
        'mvebu/cortexa9' => ['soc' => 'Marvell Armada 370/375/38x/XP',  'cpu' => 'ARM Cortex-A9 1-1.6GHz',            'arch_hint' => 'armv7'],
        'mvebu/cortexa72' => ['soc' => 'Marvell Armada 7040/8040',        'cpu' => 'ARM Cortex-A72 quad-core 1.3GHz',   'arch_hint' => 'arm64'],
        'kirkwood/generic' => ['soc' => 'Marvell Kirkwood 88F6281',        'cpu' => 'ARM Feroceon 88FR131 1.2GHz',       'arch_hint' => 'armv7-softfp'],

        // ── Allwinner / Rockchip / NXP ───────────────────────────────────────
        'sunxi/cortexa7' => ['soc' => 'Allwinner H2+/H3/H2',            'cpu' => 'ARM Cortex-A7 quad-core 1-1.2GHz',  'arch_hint' => 'armv7'],
        'sunxi/cortexa53' => ['soc' => 'Allwinner H5/H6/A64',            'cpu' => 'ARM Cortex-A53 quad-core 1.3GHz',   'arch_hint' => 'arm64'],
        'rockchip/armv8' => ['soc' => 'Rockchip RK3328/RK3399/RK3566',  'cpu' => 'ARM Cortex-A53/A72 quad-core',      'arch_hint' => 'arm64'],
        'imx/cortexa7' => ['soc' => 'NXP i.MX6UL/ULL',                'cpu' => 'ARM Cortex-A7 528MHz',              'arch_hint' => 'armv7'],
        'imx/cortexa9' => ['soc' => 'NXP i.MX6',                      'cpu' => 'ARM Cortex-A9 792MHz',              'arch_hint' => 'armv7'],

        // ── x86 ─────────────────────────────────────────────────────────────
        'x86/64' => ['soc' => 'x86 64-bit (PC/虚拟机/软路由)',   'cpu' => 'Intel/AMD x86-64',                  'arch_hint' => 'amd64'],
        'x86/generic' => ['soc' => 'x86 32-bit (旧 PC/软路由)',        'cpu' => 'Intel/AMD x86',                     'arch_hint' => 'i386'],
        'x86/geode' => ['soc' => 'AMD Geode LX (PCEngines ALIX)',   'cpu' => 'AMD Geode LX 500MHz',               'arch_hint' => 'i386'],

        // ── MIPS 64-bit ──────────────────────────────────────────────────────
        'octeon/generic' => ['soc' => 'Cavium Octeon / Octeon+',         'cpu' => 'MIPS 64-bit 500MHz-1.5GHz',         'arch_hint' => 'mips64'],

        // ── RISC-V ───────────────────────────────────────────────────────────
        'sifive/generic' => ['soc' => 'SiFive Freedom U500/U700',        'cpu' => 'RISC-V 64-bit RV64GC',              'arch_hint' => 'riscv64'],
        'starfive/generic' => ['soc' => 'StarFive JH7100/JH7110',          'cpu' => 'RISC-V 64-bit dual/quad-core',      'arch_hint' => 'riscv64'],
    ];

    /**
     * 根据 OpenWrt 系统信息推断 CTL 架构标识
     *
     * @param  string  $distribArch  DISTRIB_ARCH 值（如 "mipsel_24kc"）
     * @param  string  $distribTarget  DISTRIB_TARGET 值（如 "ramips/mt7621"），可为空
     * @return array{
     *   arch: string,
     *   soc: string,
     *   cpu: string,
     *   libc: string,
     *   note: string,
     *   confidence: string,
     *   target_map: array|null
     * }
     */
    public function detect(string $distribArch, string $distribTarget = ''): array
    {
        $distribArch = strtolower(trim($distribArch));
        $distribTarget = strtolower(trim($distribTarget));

        $result = [
            'arch' => '',
            'soc' => '未知',
            'cpu' => '未知',
            'libc' => $this->inferLibc($distribArch),
            'note' => '',
            'confidence' => 'fallback',
            'target_info' => null,
        ];

        // 1. 从 ARCH_MAP 精确匹配
        if (isset(self::ARCH_MAP[$distribArch])) {
            $map = self::ARCH_MAP[$distribArch];
            $result['arch'] = $map['arch'];
            $result['note'] = $map['note'];
            $result['confidence'] = 'exact';
        }

        // 2. 从 TARGET_MAP 补充 SoC 信息（并在精确匹配失败时作 arch fallback）
        if ($distribTarget && isset(self::TARGET_MAP[$distribTarget])) {
            $tmap = self::TARGET_MAP[$distribTarget];
            $result['soc'] = $tmap['soc'];
            $result['cpu'] = $tmap['cpu'];
            $result['target_info'] = $tmap;

            if ($result['arch'] === '') {
                $result['arch'] = $tmap['arch_hint'];
                $result['confidence'] = 'target_only';
            }
        }

        // 3. 前缀 fallback（去掉 CPU 型号后缀再匹配）
        if ($result['arch'] === '' && $distribArch !== '') {
            $result['arch'] = $this->fallbackByPrefix($distribArch);
            $result['confidence'] = $result['arch'] !== '' ? 'arch_only' : 'fallback';
        }

        // 4. 最终兜底
        if ($result['arch'] === '') {
            $result['arch'] = 'amd64';
            $result['note'] = '无法识别架构，已默认使用 amd64，请手动确认';
        }

        return $result;
    }

    /**
     * 返回完整的 ARCH_MAP（供 /api/v2/detect/arch-map 使用）
     */
    public function getArchMap(): array
    {
        return self::ARCH_MAP;
    }

    /**
     * 返回完整的 TARGET_MAP（供前端展示或 shell 脚本离线使用）
     */
    public function getTargetMap(): array
    {
        return self::TARGET_MAP;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * 通过前缀 fallback：去掉 CPU 型号后缀后再次尝试匹配
     * 例如 "mipsel_24kc_dsp" → "mipsel_24kc" → "mipsel_24kc" (exact) → 'mipsel'
     * 例如 "aarch64_cortex-a35" → prefix "aarch64" → 'arm64'
     */
    private function fallbackByPrefix(string $arch): string
    {
        // 先尝试去掉末尾 underscore 段依次重试
        $parts = explode('_', $arch);
        for ($i = count($parts) - 1; $i >= 1; $i--) {
            $attempt = implode('_', array_slice($parts, 0, $i));
            if (isset(self::ARCH_MAP[$attempt])) {
                return self::ARCH_MAP[$attempt]['arch'];
            }
        }

        // 仅用第一个字段（arch family）做宽松匹配
        $family = $parts[0];

        return match ($family) {
            'aarch64' => 'arm64',
            'arm' => 'armv7-softfp',   // 无 FPU 信息时保守选 soft-float
            'mipsel' => 'mipsel',
            'mips' => 'mips',
            'mips64el' => 'mips64el',
            'mips64' => 'mips64',
            'x86_64' => 'amd64',
            'i386' => 'i386',
            'riscv64' => 'riscv64',
            default => '',
        };
    }

    /**
     * 推断 libc 类型（OpenWrt 18.06+ 默认 musl，更早期为 uclibc）
     * 仅作参考，不影响 arch 选择（构建时已静态链接或使用 muslsf）
     */
    private function inferLibc(string $distribArch): string
    {
        // musl-softfp 变体标记
        if (str_contains($distribArch, 'muslsf') || str_contains($distribArch, 'musl')) {
            return 'musl';
        }

        // OpenWrt 18.06+ 全面切换到 musl；旧版 uclibc 基本停更
        // DISTRIB_ARCH 本身不携带 libc 信息，默认返回 musl（现代 OpenWrt）
        return 'musl';
    }
}
