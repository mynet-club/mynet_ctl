-- =============================================================================
-- MyNet CTL 数据库表结构
-- 职责: 软件分发管理（二进制版本、插件、安装脚本）
-- 注意: 禁止创建 migrations，表结构由此文件统一管理
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
    UNIQUE KEY `uk_name_version_platform` (`name`, `version`, `platform`),
    INDEX `idx_category_platform` (`category`, `platform`)
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
