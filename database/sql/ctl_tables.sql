-- =============================================================================
-- MyNet CTL 数据库表结构参考文档
-- 职责: 软件分发管理（二进制版本、插件、安装脚本）
-- 说明: 表结构由 database/migrations/ 管理，此文件同步维护以供参考和初始化
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. releases 表 - 软件版本发布（tui/utc/gnb 等二进制）
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `releases` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `release_id`   BIGINT UNSIGNED NOT NULL COMMENT '16位全局唯一ID',
    `product`      VARCHAR(32)  NOT NULL COMMENT 'tui | utc | gnb',
    `version`      VARCHAR(32)  NOT NULL COMMENT '语义版本号，如 v1.3.0',
    `platform`     VARCHAR(32)  NOT NULL COMMENT 'linux | openwrt | darwin | windows',
    `arch`         VARCHAR(32)  NOT NULL COMMENT 'amd64 | arm64 | armv7 | mips | mips64 | i386 | riscv64',
    `variant`      VARCHAR(32)  NOT NULL DEFAULT '' COMMENT '变体后缀，如 softfp、musl，空串表示默认',
    `filename`     VARCHAR(255) NOT NULL COMMENT '存储文件名，如 mynet_linux_amd64_v1.3.0.tgz',
    `file_path`    VARCHAR(512) NOT NULL COMMENT '相对于 storage/app 的路径',
    `file_size`    BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '字节数',
    `sha256`       CHAR(64)     NOT NULL DEFAULT '' COMMENT '文件 SHA256 哈希',
    `download_url` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '对外下载 URL（CDN 或直链）',
    `changelog`    TEXT         NULL COMMENT '此版本更新说明（Markdown）',
    `is_stable`    TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=稳定版, 0=预发布',
    `is_latest`    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '同 product+platform+arch 下的最新版本标记',
    `download_cnt` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '下载次数统计',
    `uploaded_by`  BIGINT UNSIGNED NULL COMMENT '上传者用户ID',
    `status`       TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=可用, 0=禁用/下架',
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_release_id` (`release_id`),
    UNIQUE KEY `uk_product_version_platform_arch_variant` (`product`, `version`, `platform`, `arch`, `variant`),
    INDEX `idx_product_platform_arch` (`product`, `platform`, `arch`),
    INDEX `idx_product_latest` (`product`, `is_latest`, `is_stable`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='软件版本发布记录（tui/utc/gnb 等）';

-- -----------------------------------------------------------------------------
-- 2. plugins 表 - 插件管理
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `plugins` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `plugin_id`    BIGINT UNSIGNED NOT NULL COMMENT '16位全局唯一ID',
    `name`         VARCHAR(64)  NOT NULL COMMENT '插件标识名，如 proxy、dns、nat',
    `display_name` VARCHAR(128) NOT NULL COMMENT '显示名称',
    `category`     VARCHAR(32)  NOT NULL DEFAULT 'general' COMMENT '分类: proxy | dns | service | tool',
    `version`      VARCHAR(32)  NOT NULL COMMENT '版本号',
    `platform`     VARCHAR(32)  NOT NULL COMMENT 'linux | openwrt | darwin | all',
    `arch`         VARCHAR(32)  NOT NULL DEFAULT 'all' COMMENT '目标架构，all 表示通用',
    `description`  TEXT         NULL COMMENT '插件功能描述',
    `filename`     VARCHAR(255) NOT NULL,
    `file_path`    VARCHAR(512) NOT NULL,
    `file_size`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `sha256`       CHAR(64)     NOT NULL DEFAULT '',
    `download_url` VARCHAR(512) NOT NULL DEFAULT '',
    `dependencies` JSON         NULL COMMENT '依赖的其他插件或最低版本要求',
    `metadata`     JSON         NULL COMMENT '插件元数据（配置说明、权限要求等）',
    `download_cnt` INT UNSIGNED NOT NULL DEFAULT 0,
    `uploaded_by`  BIGINT UNSIGNED NULL,
    `status`       TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=可用, 0=禁用',
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_plugin_id` (`plugin_id`),
    UNIQUE KEY `uk_name_version_platform_arch` (`name`, `version`, `platform`, `arch`),
    INDEX `idx_category_platform` (`category`, `platform`),
    INDEX `idx_name_status` (`name`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='插件发布管理';

-- -----------------------------------------------------------------------------
-- 3. scripts 表 - 安装/服务脚本管理
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `scripts` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `script_id`    BIGINT UNSIGNED NOT NULL COMMENT '16位全局唯一ID',
    `name`         VARCHAR(64)  NOT NULL COMMENT '脚本标识，如 install、service-manager、setup-dns',
    `display_name` VARCHAR(128) NOT NULL COMMENT '显示名称',
    `category`     VARCHAR(32)  NOT NULL DEFAULT 'install' COMMENT 'install | service | setup | upgrade | tool',
    `platform`     VARCHAR(32)  NOT NULL DEFAULT 'linux' COMMENT '目标平台',
    `version`      VARCHAR(32)  NOT NULL DEFAULT 'latest',
    `target_version` VARCHAR(32) NULL COMMENT '关联产品版本（如 v1.4.0），NULL=通用最新',
    `description`  TEXT         NULL COMMENT '脚本说明',
    `content`      LONGTEXT     NOT NULL COMMENT '脚本内容（Shell/Python 等）',
    `lang`         VARCHAR(16)  NOT NULL DEFAULT 'bash' COMMENT 'bash | python | sh',
    `checksum`     CHAR(64)     NOT NULL DEFAULT '' COMMENT '内容 SHA256',
    `is_standalone` TINYINT(1)  NOT NULL DEFAULT 1 COMMENT '1=可独立运行脚本, 0=需要组合使用',
    `download_url` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '对外直链或 CDN',
    `download_cnt` INT UNSIGNED NOT NULL DEFAULT 0,
    `uploaded_by`  BIGINT UNSIGNED NULL,
    `status`       TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_script_id` (`script_id`),
    UNIQUE KEY `uk_name_platform_target` (`name`, `platform`, `target_version`),
    INDEX `idx_category_platform` (`category`, `platform`),
    INDEX `idx_target_version` (`target_version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='安装与服务脚本管理';

-- -----------------------------------------------------------------------------
-- 4. download_logs 表 - 下载记录（用于统计、限速、审计）
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `download_logs` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `resource_type` VARCHAR(16)  NOT NULL COMMENT 'release | plugin | script',
    `resource_id`  BIGINT UNSIGNED NOT NULL COMMENT '对应资源的业务ID',
    `ip`           VARCHAR(45)  NOT NULL DEFAULT '' COMMENT '客户端 IP',
    `user_agent`   VARCHAR(512) NOT NULL DEFAULT '',
    `referer`      VARCHAR(512) NOT NULL DEFAULT '',
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_resource` (`resource_type`, `resource_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='下载记录日志';

-- -----------------------------------------------------------------------------
-- 5. api_tokens 表 - API 访问令牌（CI/CD 上传用）
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_tokens` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `token_id`     BIGINT UNSIGNED NOT NULL,
    `name`         VARCHAR(128) NOT NULL COMMENT '令牌名称/用途说明',
    `token_hash`   CHAR(64)     NOT NULL COMMENT 'SHA256 哈希存储',
    `abilities`    JSON         NOT NULL COMMENT '权限列表，如 ["release:upload","plugin:upload"]',
    `last_used_at` TIMESTAMP    NULL,
    `expires_at`   TIMESTAMP    NULL,
    `status`       TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_token_id` (`token_id`),
    UNIQUE KEY `uk_token_hash` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='API 访问令牌（供 CI/CD 上传使用）';

-- -----------------------------------------------------------------------------
-- 6. openwrt_devices 表 — 纯硬件事实（SoC、内存、接口、WiFi、物理规格）
--    一行 = 一个物理设备型号变体，如 Xiaomi Mi Router 4A Gigabit Edition
--    数据来源: downloads.openwrt.org profiles.json + OpenWrt ToH CSV 人工补充
--    注意: target/arch（固件/ROM 概念）移至 openwrt_device_firmware，不在本表
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `openwrt_devices` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- 设备标识
    `toh_pid`         VARCHAR(32)  NULL UNIQUE COMMENT 'OpenWrt Table of Hardware 唯一 ID',
    `brand`           VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '设备品牌，如 Xiaomi / TP-Link / GL.iNet',
    `model`           VARCHAR(128) NOT NULL DEFAULT '' COMMENT '设备型号，如 Mi Router 4A',
    `version`         VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '硬件版本/变体，如 v1.0 / Gigabit Edition',
    `device_slug`     VARCHAR(200) NULL UNIQUE COMMENT 'URL 友好标识（由 openwrt_id slug 生成）',
    `board_name`      VARCHAR(128) NULL UNIQUE COMMENT '/tmp/sysinfo/board_name 的值，如 xiaomi,mi-router-4a-gigabit',
    `openwrt_id`      VARCHAR(128) NULL UNIQUE COMMENT 'OpenWrt image profile ID，如 xiaomi_mi-router-4a-gigabit',
    `device_type`     VARCHAR(32)  NOT NULL DEFAULT 'router'
                      COMMENT '设备类型: router / ap / switch / sbc / cpe / industrial',
    `availability`    VARCHAR(16)  NOT NULL DEFAULT 'unknown'
                      COMMENT 'available / discontinued / unknown',
    `oem_url`         VARCHAR(512) NOT NULL DEFAULT '' COMMENT 'OEM 官方产品页面 URL',
    `release_year`    YEAR         NULL COMMENT '产品发布年份',

    -- SoC / CPU
    `soc_name`        VARCHAR(128) NOT NULL DEFAULT '' COMMENT 'SoC 完整型号，如 MediaTek MT7621A',
    `soc_vendor`      VARCHAR(64)  NOT NULL DEFAULT '' COMMENT 'SoC 厂商：MediaTek / Qualcomm Atheros / Broadcom / Marvell',
    `soc_arch_family` VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'CPU 架构族: MIPS / ARM / x86 / RISC-V',
    `soc_bits`        TINYINT UNSIGNED NOT NULL DEFAULT 0  COMMENT 'CPU 位宽：32 / 64',
    `cpu_cores`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `cpu_threads`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `cpu_freq_mhz`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `cpu_freq_max_mhz` SMALLINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '最大睿频 MHz（动态调频）',
    `cpu_process_nm`  TINYINT UNSIGNED NOT NULL DEFAULT 0   COMMENT '制程 nm，如 28/12/7',
    `cpu_l2_cache_kb` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `cpu_has_fpu`     TINYINT(1) NOT NULL DEFAULT 0  COMMENT '有硬件 FPU（影响 soft/hard-float ABI）',
    `cpu_has_dsp`     TINYINT(1) NOT NULL DEFAULT 0  COMMENT '有 DSP 模块，如 MIPS DSPr2',
    `cpu_has_neon`    TINYINT(1) NOT NULL DEFAULT 0  COMMENT '有 NEON/SIMD（ARM）',
    `cpu_has_vfp`     TINYINT(1) NOT NULL DEFAULT 0  COMMENT '有 VFP 浮点（ARM）',
    `cpu_extra_flags` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '额外 CPU 特性，空格分隔，如 "mips16 dsp dsp2"',

    -- 内存 / 存储
    `ram_mb`          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `ram_type`        VARCHAR(16)  NOT NULL DEFAULT '' COMMENT 'DDR / DDR2 / DDR3 / LPDDR3 / LPDDR4',
    `flash_mb`        SMALLINT UNSIGNED NOT NULL DEFAULT 0  COMMENT 'Boot Flash MB（NOR/NAND）',
    `flash_type`      VARCHAR(16)  NOT NULL DEFAULT '' COMMENT 'NOR / SPI-NOR / NAND / SPI-NAND / eMMC',
    `emmc_gb`         INT UNSIGNED NOT NULL DEFAULT 0  COMMENT '板载 eMMC GB（独立于 Boot Flash）',

    -- 调试接口 / GPIO
    `has_uart`        TINYINT(1) NOT NULL DEFAULT 0  COMMENT '有 UART 串口焊盘',
    `uart_info`       VARCHAR(64) NOT NULL DEFAULT '' COMMENT '串口信息，如 115200/8N1 3.3V TTL',
    `has_jtag`        TINYINT(1) NOT NULL DEFAULT 0,
    `has_gpio`        TINYINT(1) NOT NULL DEFAULT 0,
    `gpio_count`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `led_count`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `button_count`    TINYINT UNSIGNED NOT NULL DEFAULT 0,

    -- 以太网
    `eth_ports`       TINYINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '以太网口总数',
    `eth_wan_ports`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `eth_lan_ports`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `eth_100m_ports`  TINYINT UNSIGNED NOT NULL DEFAULT 0  COMMENT 'Fast Ethernet 100Mbps 口',
    `eth_1g_ports`    TINYINT UNSIGNED NOT NULL DEFAULT 0  COMMENT 'Gigabit 1GbE 口',
    `eth_2_5g_ports`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `eth_5g_ports`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `eth_10g_ports`   TINYINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '10GbE 铜缆口',
    `sfp_ports`       TINYINT UNSIGNED NOT NULL DEFAULT 0  COMMENT 'SFP 光口',
    `sfp_plus_ports`  TINYINT UNSIGNED NOT NULL DEFAULT 0  COMMENT 'SFP+ 10G 光口',
    `eth_switch_chip` VARCHAR(64)  NOT NULL DEFAULT ''     COMMENT '交换芯片，如 MediaTek MT7530',
    `has_poe_in`      TINYINT(1) NOT NULL DEFAULT 0  COMMENT '支持 PoE 供电输入（802.3af/at/bt）',
    `has_poe_out`     TINYINT(1) NOT NULL DEFAULT 0  COMMENT '支持 PoE 供电输出',

    -- WiFi
    `wifi_chips`      JSON NULL  COMMENT 'WiFi 芯片列表，如 ["MT7603E","MT7612E"]',
    `wifi_has_2g`     TINYINT(1) NOT NULL DEFAULT 0,
    `wifi_2g_spec`    VARCHAR(64) NOT NULL DEFAULT '' COMMENT '如 802.11n 300Mbps 2T2R',
    `wifi_has_5g`     TINYINT(1) NOT NULL DEFAULT 0,
    `wifi_5g_spec`    VARCHAR(64) NOT NULL DEFAULT '' COMMENT '如 802.11ac 867Mbps 2T2R',
    `wifi_has_6g`     TINYINT(1) NOT NULL DEFAULT 0,
    `wifi_6g_spec`    VARCHAR(64) NOT NULL DEFAULT '',
    `wifi_is_wifi6`   TINYINT(1) NOT NULL DEFAULT 0  COMMENT '802.11ax',
    `wifi_is_wifi7`   TINYINT(1) NOT NULL DEFAULT 0  COMMENT '802.11be',
    `wifi_max_speed_mbps` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `wifi_antennas`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `wifi_antenna_detachable` TINYINT(1) NOT NULL DEFAULT 0,
    `wifi_mu_mimo`    VARCHAR(8)  NOT NULL DEFAULT '' COMMENT 'MU-MIMO，如 2x2 / 4x4',

    -- USB / 扩展接口
    `usb2_ports`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `usb3_ports`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `usbc_ports`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `has_sata`        TINYINT(1) NOT NULL DEFAULT 0,
    `sata_ports`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `has_m2`          TINYINT(1) NOT NULL DEFAULT 0,
    `m2_type`         VARCHAR(32) NOT NULL DEFAULT '' COMMENT '如 M.2 2242 PCIe / M.2 2280 NVMe',
    `has_pcie`        TINYINT(1) NOT NULL DEFAULT 0,
    `pcie_spec`       VARCHAR(32) NOT NULL DEFAULT '' COMMENT '如 PCIe 2.0 x1',
    `has_sdcard`      TINYINT(1) NOT NULL DEFAULT 0,
    `has_sim_slot`    TINYINT(1) NOT NULL DEFAULT 0,
    `sim_slots`       TINYINT UNSIGNED NOT NULL DEFAULT 0,

    -- 蜂窝 / 无线扩展
    `has_lte_modem`   TINYINT(1) NOT NULL DEFAULT 0,
    `has_5g_modem`    TINYINT(1) NOT NULL DEFAULT 0,
    `modem_model`     VARCHAR(64) NOT NULL DEFAULT '' COMMENT '如 Quectel EC25 / EM06',
    `has_bluetooth`   TINYINT(1) NOT NULL DEFAULT 0,
    `bluetooth_version` VARCHAR(8) NOT NULL DEFAULT '' COMMENT '如 4.2 / 5.0',
    `has_zigbee`      TINYINT(1) NOT NULL DEFAULT 0,
    `has_zwave`       TINYINT(1) NOT NULL DEFAULT 0,
    `has_gps`         TINYINT(1) NOT NULL DEFAULT 0,

    -- 电源 / 物理规格
    `power_dc_v`      DECIMAL(4,1) NOT NULL DEFAULT 0  COMMENT '供电电压 V，如 12.0 / 5.0 / 48.0（PoE）',
    `power_max_w`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `form_factor`     VARCHAR(32) NOT NULL DEFAULT 'router'
                      COMMENT 'router / ap / switch / sbc / cpe / industrial / pod / rack',
    `size_mm_l`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `size_mm_w`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `size_mm_h`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `weight_g`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    -- 元数据
    `tags`            VARCHAR(255) NOT NULL DEFAULT '' COMMENT '逗号分隔标签，如 gigabit,wifi6,usb3,poe',
    `notes`           TEXT NULL         COMMENT '备注：刷机注意、已知问题、参考链接等',
    `status`          TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_device_slug`  (`device_slug`),
    UNIQUE KEY `uk_board_name`   (`board_name`),
    UNIQUE KEY `uk_openwrt_id`   (`openwrt_id`),
    INDEX `idx_brand_model`      (`brand`, `model`),
    INDEX `idx_soc_name`         (`soc_name`),
    INDEX `idx_soc_vendor`       (`soc_vendor`),
    INDEX `idx_device_type`      (`device_type`),
    INDEX `idx_availability`     (`availability`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='OpenWrt 设备硬件事实表（SoC/内存/接口/WiFi/物理）';

-- -----------------------------------------------------------------------------
-- 7. openwrt_device_firmware 表 — 固件/ROM 支持元数据
--    一行 = 一个设备 × 一个 target/arch 组合（大多数设备只有一行）
--    拆表原因: target/subtarget 是 OpenWrt 构建系统概念（ROM）；
--              极少数设备有多个 target，或不同 OpenWrt 版本下 arch 发生变化。
--    数据来源: downloads.openwrt.org/releases/{ver}/targets/{tgt}/{sub}/profiles.json
--      arch_packages (顶层) = DISTRIB_ARCH
--      target        (顶层) = DISTRIB_TARGET
--      profiles[id].supported_devices = board_name 候选列表
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `openwrt_device_firmware` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `device_id`       BIGINT UNSIGNED NOT NULL COMMENT '关联 openwrt_devices.id',

    -- OpenWrt 构建分类
    `openwrt_target`    VARCHAR(32) NOT NULL DEFAULT '' COMMENT '如 ramips / ath79 / mediatek / x86',
    `openwrt_subtarget` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '如 mt7621 / generic / filogic / 64',
    `distrib_target`    VARCHAR(64) NOT NULL DEFAULT '' COMMENT '= DISTRIB_TARGET，如 ramips/mt7621',
    `distrib_arch`      VARCHAR(64) NOT NULL DEFAULT '' COMMENT '= arch_packages = DISTRIB_ARCH，如 mipsel_24kc',

    -- 设备标识（固件侧）
    `supported_devices` JSON NULL COMMENT 'DT compatible 字符串列表（/tmp/sysinfo/board_name 来源）',

    -- OpenWrt 版本支持
    `openwrt_since_rev` VARCHAR(16) NOT NULL DEFAULT '' COMMENT '最初支持的 SVN revision',
    `openwrt_since_rel` VARCHAR(16) NOT NULL DEFAULT '' COMMENT '最初支持的发布版本，如 17.01',
    `openwrt_until_rel` VARCHAR(16) NOT NULL DEFAULT '' COMMENT '最后支持版本（EOL 时填写，空=仍支持）',
    `openwrt_wiki_url`  VARCHAR(512) NOT NULL DEFAULT '' COMMENT '设备 wiki 页面 URL',
    `openwrt_image_url` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '固件下载页面 URL',
    `openwrt_techdata_url` VARCHAR(512) NOT NULL DEFAULT '' COMMENT 'ToH 技术参数页面 URL',

    -- Bootloader / 刷机
    `bootloader`        VARCHAR(32) NOT NULL DEFAULT ''
                        COMMENT 'U-Boot / CFE / RedBoot / GRUB / EFI / Breed / PandoraBox',
    `bootloader_version` VARCHAR(32) NOT NULL DEFAULT '',
    `has_tftp_recovery` TINYINT(1) NOT NULL DEFAULT 0,
    `has_web_recovery`  TINYINT(1) NOT NULL DEFAULT 0,
    `flash_layout`      VARCHAR(32) NOT NULL DEFAULT ''
                        COMMENT 'single / dual / ubi / squashfs+jffs2 / ext4',

    -- CTL 架构映射（关键字段，驱动 tui/gnb/utc 下载推荐）
    `ctl_arch`          VARCHAR(32) NOT NULL DEFAULT ''
                        COMMENT 'CTL arch，与 releases.arch 一致，如 mipsel / armv7 / arm64',
    `libc`              VARCHAR(16) NOT NULL DEFAULT 'musl' COMMENT 'musl | uclibc | glibc',
    `float_abi`         VARCHAR(8)  NOT NULL DEFAULT 'soft' COMMENT 'hard | soft',
    `sdk_toolchain`     VARCHAR(64) NOT NULL DEFAULT '' COMMENT '如 musl-gcc / uclibc-gcc',
    `cross_compile_prefix` VARCHAR(64) NOT NULL DEFAULT ''
                        COMMENT '如 mipsel-openwrt-linux-musl-',

    -- 硬件加速（固件侧，取决于 kmod 支持）
    `has_hw_crypto`     TINYINT(1) NOT NULL DEFAULT 0 COMMENT '硬件加密加速 kmod',
    `has_hw_nat`        TINYINT(1) NOT NULL DEFAULT 0 COMMENT '硬件 NAT Flow Offload',
    `has_hw_accel`      TINYINT(1) NOT NULL DEFAULT 0 COMMENT '专用数据包加速引擎（SFE/PPE/NSS）',
    `hw_accel_note`     VARCHAR(128) NOT NULL DEFAULT '',

    -- 状态
    `support_status`    VARCHAR(16) NOT NULL DEFAULT 'supported'
                        COMMENT 'supported | eol | testing | broken | unknown',
    `is_primary`        TINYINT(1) NOT NULL DEFAULT 1
                        COMMENT '是否为该设备的推荐/主要固件条目',
    `notes`             TEXT NULL,
    `status`            TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_device_target_arch` (`device_id`, `distrib_target`, `distrib_arch`),
    INDEX `idx_distrib_target`   (`distrib_target`),
    INDEX `idx_distrib_arch`     (`distrib_arch`),
    INDEX `idx_ctl_arch`         (`ctl_arch`),
    INDEX `idx_support_status`   (`support_status`),
    CONSTRAINT `fk_odf_device` FOREIGN KEY (`device_id`)
        REFERENCES `openwrt_devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='OpenWrt 固件/ROM 支持表（target/arch/ctl_arch/版本支持）';
