<?php

namespace App\Domain\Release\Services;

/**
 * Release Domain Service
 *
 * 纯业务逻辑：版本号校验、平台/架构矩阵、命名规则
 * 不依赖框架或数据库
 *
 * 本文件是全平台命名规范的唯一真实来源 (SSOT)
 * 所有项目（mynet_tui, gnb, utc, client, proxy 等）编译脚本/workflow 均应与此对齐
 */
class ReleaseDomainService
{
    // ═══════════════════════════════════════════════════════════════════════
    // 产品
    // ═══════════════════════════════════════════════════════════════════════
    const PRODUCTS = [
        'mynet_agent' => ['name' => 'MyNet Agent', 'alias' => null, 'lang' => 'go', 'desc' => '节点侧全套（mynetd + mynet + mynet_gateway）'],
        'tui' => ['name' => 'MyNet TUI',    'alias' => 'mynet', 'lang' => 'go',   'desc' => 'Go 终端客户端（旧版，对外别名 mynet）'],
        'gnb' => ['name' => 'GNB',          'alias' => null,    'lang' => 'c',    'desc' => 'GNB P2P 组网核心'],
        'utc' => ['name' => 'UTC',          'alias' => null,    'lang' => 'c',    'desc' => 'UDP→TCP 隧道转换器'],
        'client' => ['name' => 'MyNet Client', 'alias' => null,    'lang' => 'node', 'desc' => 'Node.js 跨平台客户端（计划中）'],
        'proxy' => ['name' => 'MyNet Proxy',  'alias' => null,    'lang' => 'node', 'desc' => 'Mihomo 代理管理器'],
        'lua' => ['name' => 'MyNet Lua',    'alias' => null,    'lang' => 'lua',  'desc' => 'OpenWrt LuCI 插件（.ipk）'],
    ];

    // ═══════════════════════════════════════════════════════════════════════
    // 平台
    // ═══════════════════════════════════════════════════════════════════════
    const PLATFORMS = [
        'linux' => ['label' => 'Linux',          'ext' => 'tgz'],
        'openwrt' => ['label' => 'OpenWrt',        'ext' => 'tgz'],
        'openwrt-sdk' => ['label' => 'OpenWrt SDK',    'ext' => 'tgz'],
        'darwin' => ['label' => 'macOS',          'ext' => 'tgz'],
        'windows' => ['label' => 'Windows',        'ext' => 'zip'],
        'freebsd' => ['label' => 'FreeBSD',        'ext' => 'tgz'],
        'openbsd' => ['label' => 'OpenBSD',        'ext' => 'tgz'],
    ];

    // ═══════════════════════════════════════════════════════════════════════
    // 架构
    //
    // 命名规则：
    //   1. 默认 hard-float，不加后缀: armv7, arm64, mipsel
    //   2. soft-float 才加 -softfp: armv7-softfp, arm64-softfp, mipsel-softfp
    //   3. 所有组件统一使用同一个 arch 标识
    // ═══════════════════════════════════════════════════════════════════════
    // ═══════════════════════════════════════════════════════════════════════
    // 产物类型（artifact kind）—— §4.3：原生安装包与自更新归档各自有 kind，
    // 不只按扩展名猜。这里是**声明表**（唯一 SSOT）；登记时按扩展名映射一次并落库，
    // 读取端用落库值，不再运行时推断。
    // ═══════════════════════════════════════════════════════════════════════
    const ARTIFACT_KINDS = [
        'installer' => ['exe', 'dmg', 'pkg', 'ipk'],
        'archive' => ['tgz', 'zip', 'tar.gz'],
    ];

    const ARCHITECTURES = [
        // x86
        'amd64' => ['family' => 'x86',   'bits' => 64, 'goarch' => 'amd64',   'goarm' => null, 'label' => 'x86 64-bit'],
        'i386' => ['family' => 'x86',   'bits' => 32, 'goarch' => '386',     'goarm' => null, 'label' => 'x86 32-bit'],
        // ARM 64-bit
        'arm64' => ['family' => 'arm',   'bits' => 64, 'goarch' => 'arm64',   'goarm' => null, 'label' => 'ARM 64-bit (hard-float)'],
        'arm64-softfp' => ['family' => 'arm',   'bits' => 64, 'goarch' => 'arm64',   'goarm' => null, 'label' => 'ARM 64-bit (soft-float)'],
        // ARM 32-bit
        'armv7' => ['family' => 'arm',   'bits' => 32, 'goarch' => 'arm',     'goarm' => '7',  'label' => 'ARMv7 (hard-float)'],
        'armv7-hardfp' => ['family' => 'arm',   'bits' => 32, 'goarch' => 'arm',     'goarm' => '7',  'label' => 'ARMv7 (explicit hard-float, GNB)'],
        'armv7-softfp' => ['family' => 'arm',   'bits' => 32, 'goarch' => 'arm',     'goarm' => '7',  'label' => 'ARMv7 (soft-float)'],
        'armv6-softfp' => ['family' => 'arm',   'bits' => 32, 'goarch' => 'arm',     'goarm' => '6',  'label' => 'ARMv6 (soft-float, GNB)'],
        // MIPS 64-bit
        'mips64' => ['family' => 'mips',  'bits' => 64, 'goarch' => 'mips64',  'goarm' => null, 'label' => 'MIPS 64-bit BE'],
        'mips64el' => ['family' => 'mips',  'bits' => 64, 'goarch' => 'mips64le', 'goarm' => null, 'label' => 'MIPS 64-bit LE'],
        // MIPS 32-bit
        'mips' => ['family' => 'mips',  'bits' => 32, 'goarch' => 'mips',    'goarm' => null, 'label' => 'MIPS 32-bit BE (hard-float)'],
        'mips-softfp' => ['family' => 'mips',  'bits' => 32, 'goarch' => 'mips',    'goarm' => null, 'label' => 'MIPS 32-bit BE (soft-float)'],
        'mipsel' => ['family' => 'mips',  'bits' => 32, 'goarch' => 'mipsle',  'goarm' => null, 'label' => 'MIPS 32-bit LE (hard-float)'],
        'mipsel-softfp' => ['family' => 'mips',  'bits' => 32, 'goarch' => 'mipsle',  'goarm' => null, 'label' => 'MIPS 32-bit LE (soft-float)'],
        'mipsel-muslsf' => ['family' => 'mips',  'bits' => 32, 'goarch' => 'mipsle',  'goarm' => null, 'label' => 'MIPS 32-bit LE (musl soft-float, GNB)'],
        'mipsel-sf' => ['family' => 'mips',  'bits' => 32, 'goarch' => 'mipsle',  'goarm' => null, 'label' => 'MIPS 32-bit LE (soft-float, GNB SDK)'],
        'mips-sf' => ['family' => 'mips',  'bits' => 32, 'goarch' => 'mips',    'goarm' => null, 'label' => 'MIPS 32-bit BE (soft-float, GNB SDK)'],
        // RISC-V
        'riscv64' => ['family' => 'riscv', 'bits' => 64, 'goarch' => 'riscv64', 'goarm' => null, 'label' => 'RISC-V 64-bit'],
        // 架构无关（纯脚本/LuCI 壳包，opkg 的 arch=all）
        'all' => ['family' => 'any',   'bits' => 0,  'goarch' => '',        'goarm' => null, 'label' => '架构无关 (all)'],
    ];

    // ═══════════════════════════════════════════════════════════════════════
    // 平台×架构 有效组合
    // ═══════════════════════════════════════════════════════════════════════
    const VALID_COMBINATIONS = [
        'linux' => ['amd64', 'arm64', 'armv7', 'armv7-hardfp', 'armv7-softfp', 'armv6-softfp', 'i386', 'mips', 'mips64', 'mipsel', 'mips64el', 'mips-softfp', 'mipsel-softfp', 'arm64-softfp', 'riscv64'],
        'openwrt' => ['amd64', 'arm64', 'armv7', 'armv7-hardfp', 'armv7-softfp', 'armv6-softfp', 'i386', 'mips', 'mips64', 'mipsel', 'mips64el', 'mips-softfp', 'mipsel-softfp', 'mipsel-muslsf', 'arm64-softfp', 'riscv64', 'all'],
        'openwrt-sdk' => ['amd64', 'arm64', 'armv7', 'mips', 'mips-sf', 'mipsel', 'mipsel-sf'],
        'darwin' => ['amd64', 'arm64'],
        'windows' => ['amd64', 'arm64'],
        'freebsd' => ['amd64', 'arm64'],
        'openbsd' => ['amd64', 'arm64'],
    ];

    // ═══════════════════════════════════════════════════════════════════════
    // uname -m → arch 映射（用于检测脚本）
    // ═══════════════════════════════════════════════════════════════════════
    const UNAME_MAP = [
        'x86_64' => 'amd64',
        'amd64' => 'amd64',
        'i386' => 'i386',
        'i686' => 'i386',
        'aarch64' => 'arm64',
        'arm64' => 'arm64',
        'armv7l' => 'armv7',
        'mips64' => 'mips64',
        'mips64el' => 'mips64el',
        'mips' => 'mips',
        'mipsel' => 'mipsel',
        'riscv64' => 'riscv64',
    ];

    // ═══════════════════════════════════════════════════════════════════════
    // 统一文件命名格式
    //
    //   {product}_{platform}_{arch}_{version}.{ext}
    //
    //   product  = mynet_agent / tui / gnb / utc / client / proxy / lua
    //   platform = linux / openwrt / openwrt-sdk / darwin / windows / freebsd / openbsd
    //   arch     = amd64 / arm64 / armv7-softfp / mipsel / ...
    //   version  = v1.0.0 / v1.6.0.a（允许 . 和 - 分隔预发布标识）
    //   ext      = tgz (default) / zip (Windows) / ipk (OpenWrt Lua)
    //
    // 特殊：
    //   tui 对外别名 mynet → 文件名用 mynet 而非 tui
    //   例: mynet_linux_amd64_v0.9.8.10.tgz
    //       gnb_openwrt_mipsel-softfp_v1.6.0.a.tgz
    //       mynet_agent_linux_amd64_v1.0.0.tgz
    //
    // ⚠️ agent 侧产物名必须用 **ctl 的 arch 名**：Go 的 `mipsle` 在这里叫 `mipsel`
    //    （见 ARCHITECTURES 表），写错会被 validateArch() 拒收。
    // ═══════════════════════════════════════════════════════════════════════

    public function validateProduct(string $product): bool
    {
        return array_key_exists($product, self::PRODUCTS);
    }

    /** 扩展名 → 产物类型（未登记则 ''）。登记时调用一次并落库。 */
    public function artifactKindForExt(string $ext): string
    {
        $ext = strtolower(ltrim($ext, '.'));

        foreach (self::ARTIFACT_KINDS as $kind => $exts) {
            if (in_array($ext, $exts, true)) {
                return $kind;
            }
        }

        return '';
    }

    public function validatePlatform(string $platform): bool
    {
        return array_key_exists($platform, self::PLATFORMS);
    }

    public function validateArch(string $arch): bool
    {
        return array_key_exists($arch, self::ARCHITECTURES);
    }

    public function validateCombination(string $platform, string $arch): bool
    {
        return in_array($arch, self::VALID_COMBINATIONS[$platform] ?? [], true);
    }

    /**
     * 获取产品对外名称（tui → mynet，其他不变）
     */
    public function getProductFilenamePrefix(string $product): string
    {
        return self::PRODUCTS[$product]['alias'] ?? $product;
    }

    /**
     * 获取平台默认扩展名
     */
    public function getDefaultExtension(string $platform): string
    {
        return self::PLATFORMS[$platform]['ext'] ?? 'tgz';
    }

    /**
     * 生成标准文件名
     *
     * 格式: {name}_{platform}_{arch}_{version}.{ext}
     * 示例: mynet_linux_amd64_v1.3.0.tgz  /  gnb_windows_amd64_v1.6.0.zip
     */
    public function generateFilename(string $product, string $platform, string $arch, string $version, string $ext = ''): string
    {
        $name = $this->getProductFilenamePrefix($product);
        $ext = $ext ?: $this->getDefaultExtension($platform);

        return "{$name}_{$platform}_{$arch}_{$version}.{$ext}";
    }

    /**
     * 从文件名反解析各字段
     *
     * @return array{product:string, platform:string, arch:string, version:string, ext:string}|null
     */
    public function parseFilename(string $filename): ?array
    {
        // Pattern: {name}_{platform}_{arch}_{version}.{ext}
        // arch 可含连字符 (armv7-softfp)，version 以 v 开头
        //
        // name 允许下划线：产物前缀的 SSOT 是 `mynet_agent_*`
        // （mynet_agent 侧的 guard C3 强制，AGENTS.md §1.5），
        // 而 `mynet_agent` 含下划线 —— 原先的 `[a-z0-9]+` 会让
        // mynet_agent_linux_amd64_v1.0.0.tgz 解析失败、下载 404。
        //
        // platform 改为**显式枚举**：name 放开下划线后，若 platform 仍是
        // `[a-z][a-z0-9-]*`，正则要靠回溯猜「name 到哪结束、platform 从哪开始」，
        // 可能把 `mynet_agent` 切成 name=mynet + platform=agent。
        // 枚举后切分唯一。注意 openwrt-sdk 必须排在 openwrt 之前。
        //
        // 扩展名白名单含**原生安装器**（exe/dmg/pkg）：desktop 侧的 Windows 安装器、
        // macOS pkg/dmg 与自更新归档（tgz/zip）是不同 artifact kind，都要能反解析
        // （否则 buildDownloadUrl 落到无前缀 fallback）。
        if (! preg_match('/^([a-z0-9_]+)_(linux|openwrt-sdk|openwrt|darwin|windows|freebsd|openbsd)_([a-z0-9\-]+)_(v[0-9a-z.\-]+)\.(tgz|zip|tar\.gz|ipk|exe|dmg|pkg)$/i', $filename, $m)) {
            return null;
        }

        $name = $m[1];
        $platform = $m[2];
        $arch = $m[3];
        $version = $m[4];
        $ext = $m[5];

        // Reverse alias: mynet → tui
        $product = $name;
        foreach (self::PRODUCTS as $key => $meta) {
            if (($meta['alias'] ?? null) === $name) {
                $product = $key;
                break;
            }
        }

        $kind = $this->artifactKindForExt($ext);

        return compact('product', 'name', 'platform', 'arch', 'version', 'ext', 'kind');
    }

    /**
     * 校验版本号格式
     *
     * 允许: v1.0.0, v1.6.0.a, v0.9.8.10, v1.0.0-beta.1
     */
    public function validateVersion(string $version): bool
    {
        return (bool) preg_match('/^v?\d+\.\d+\.\d+([.\-][a-zA-Z0-9.]+)?$/', $version);
    }

    /**
     * 比较版本号大小，返回 1/-1/0
     */
    public function compareVersions(string $a, string $b): int
    {
        return version_compare(ltrim($a, 'v'), ltrim($b, 'v'));
    }

    /**
     * 导出完整平台/架构/命名约定（供 API 端点使用）
     */
    public function exportConventions(): array
    {
        $productList = [];
        foreach (self::PRODUCTS as $key => $meta) {
            $productList[] = array_merge(['id' => $key], $meta);
        }

        $platformList = [];
        foreach (self::PLATFORMS as $key => $meta) {
            $platformList[$key] = array_merge($meta, [
                'architectures' => self::VALID_COMBINATIONS[$key] ?? [],
            ]);
        }

        $archList = [];
        foreach (self::ARCHITECTURES as $key => $meta) {
            $archList[] = array_merge(['id' => $key], $meta);
        }

        return [
            'naming' => [
                'pattern' => '{product}_{platform}_{arch}_{version}.{ext}',
                'examples' => [
                    'mynet_linux_amd64_v0.9.8.10.tgz',
                    'gnb_openwrt_mipsel-softfp_v1.6.0.a.tgz',
                    'gnb_windows_amd64_v1.6.0.zip',
                ],
                'rules' => [
                    'default_ext_tgz' => 'tgz 用于 linux/openwrt/openwrt-sdk/darwin/freebsd/openbsd',
                    'windows_ext_zip' => 'zip 用于 windows',
                    'softfp_suffix' => '默认 hard-float 不加后缀，soft-float 加 -softfp',
                    'tui_alias' => 'tui 产品对外文件名前缀使用 mynet（历史兼容）',
                    'version_prefix_v' => '版本号始终带 v 前缀',
                ],
            ],
            'products' => $productList,
            'platforms' => $platformList,
            'architectures' => $archList,
            'uname_map' => self::UNAME_MAP,
        ];
    }
}
