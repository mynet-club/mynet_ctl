<?php

namespace App\Services\Setup;

use App\Services\Release\ReleaseService;
use RuntimeException;
use ZipArchive;

/**
 * 节点配置包服务
 *
 * 通过 mynet_back API 获取节点的全部配置文件，打包为 ZIP 供用户一键下载。
 *
 * 仅配置模式（默认）:
 *   conf/
 *     {nodeId}/
 *       node.conf          — GNB 节点配置
 *       address.conf       — 索引节点 / 对等节点地址列表
 *       route.conf         — 路由配置
 *       ed25519/           — 公钥目录（GNB 标准目录结构）
 *         {nodeId}.public  — 节点 Ed25519 公钥
 *         {peerNodeId}.public — 对等节点公钥
 *       security/          — 私钥 + 公钥目录
 *         {nodeId}.private — 节点私钥（如 API 不提供则跳过）
 *         {nodeId}.public  — 节点公钥
 *   README.txt             — 使用说明
 *
 * 含软件包模式（传入 platform + arch）:
 *   上述全部 + setup.sh/setup.ps1（含平台/架构对应的二进制下载命令）
 */
class ConfigPackService
{
    public function __construct(
        private readonly MynetBackClient $backClient,
        private readonly ReleaseService $releaseService,
    ) {}

    private function wwwUrl(): string
    {
        return rtrim(config('services.mynet_urls.www'), '/');
    }

    private function guiUrl(): string
    {
        return rtrim(config('services.mynet_urls.gui'), '/');
    }

    /**
     * 生成节点配置包 ZIP 并返回临时文件路径
     *
     * @param  string  $token  用户 Sanctum Token
     * @param  int  $nodeId  节点 ID
     * @param  string|null  $platform  目标平台（null = 仅配置）
     * @param  string|null  $arch  目标架构（null = 仅配置）
     * @return array{path: string, filename: string, node_name: string}
     */
    public function generatePack(string $token, int $nodeId, ?string $platform = null, ?string $arch = null, ?int $zoneId = null): array
    {
        // 1. 获取节点基本信息
        $nodeDetail = $this->backClient->getNodeDetail($token, $zoneId ?? 0, $nodeId);
        $node = $nodeDetail['data'] ?? $nodeDetail;
        $nodeName = $node['node_name'] ?? "node_{$nodeId}";

        // 2. 获取所有配置 + 密钥（优先使用 config-bundle API，失败则降级为分散请求）
        [$configs, $keys] = $this->fetchViaBundle($token, $zoneId ?? 0, $nodeId);

        // 2b. R8：BundleEnvelope schema v2（与在线更新 / 离线 last-known-good 同一契约）
        $bundleJson = '';
        try {
            $envelope = $this->backClient->getBundleEnvelope($token, $zoneId ?? 0, $nodeId);
            $bundleJson = json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
        } catch (\Throwable) {
            // 无 Envelope 时仍产出旧 files/keys 布局，安装包不因 R8 而不可用
        }

        // 3. 生成辅助文件
        $readme = $this->generateReadme($nodeId, $nodeName, $platform, $arch);

        // 4. 打包 ZIP
        $zipPath = $this->createZip($nodeId, $nodeName, $configs, $keys, $readme, $platform, $arch, $bundleJson);

        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $nodeName);
        $filename = "mynet_node_{$nodeId}_{$safeName}.zip";

        return [
            'path' => $zipPath,
            'filename' => $filename,
            'node_name' => $nodeName,
        ];
    }

    /**
     * 优先使用 config-bundle API 获取配置 + 密钥，失败则降级为分散请求
     *
     * @return array{0: array<string, string>, 1: array<string, string>} [configs, keys]
     */
    private function fetchViaBundle(string $token, int $zoneId, int $nodeId): array
    {
        try {
            $bundle = $this->backClient->getConfigBundle($token, $zoneId, $nodeId);

            $configs = $bundle['files'] ?? [];
            $keys = [];

            // Flatten ed25519 keys: { nodeId => pubkey_hex } → { "nodeId.public" => pubkey_hex }
            foreach ($bundle['keys']['ed25519'] ?? [] as $id => $pubkey) {
                $keys["{$id}.public"] = $pubkey;
            }

            // Security keys
            $sec = $bundle['keys']['security'] ?? [];
            if (! empty($sec['private'])) {
                $keys["{$nodeId}.private"] = $sec['private'];
            }
            if (! empty($sec['public'])) {
                $keys["{$nodeId}.public"] = $sec['public'];
            }

            return [$configs, $keys];
        } catch (RuntimeException) {
            // Fallback to legacy multi-request
            $configs = $this->fetchAllConfigs($token, $zoneId, $nodeId);
            $keys = $this->fetchKeys($token, $zoneId, $nodeId, $configs['route.conf'] ?? '');

            return [$configs, $keys];
        }
    }

    /**
     * 获取所有配置文件内容（legacy: 分散请求模式）
     */
    private function fetchAllConfigs(string $token, int $zoneId, int $nodeId): array
    {
        $configs = [];

        // node.conf — v2: /nodes/{id}/configuration?section=vpn&render_conf=1
        // node.conf 是核心配置，获取失败应中断整个流程
        $configs['node.conf'] = $this->backClient->getNodeConfig($token, $zoneId, $nodeId, true);

        // address.conf — Zone 级 /zones/services/indexes?render_conf=1（格式: i|0|ip|port）
        try {
            $configs['address.conf'] = $this->backClient->getZoneIndexServices($token, $zoneId, true);
        } catch (RuntimeException $e) {
            $configs['address.conf'] = "# 获取 address.conf 失败: {$e->getMessage()}\n";
        }

        // route.conf — /route/node/{nodeId}?render_conf=1（格式: nodeId|ip|mask）
        try {
            $configs['route.conf'] = $this->backClient->getRouteConfig($token, $zoneId, $nodeId, true);
        } catch (RuntimeException $e) {
            $configs['route.conf'] = "# 获取 route.conf 失败: {$e->getMessage()}\n";
        }

        return $configs;
    }

    /**
     * 获取密钥文件（自身 + 对等节点公钥）
     *
     * @param  string  $routeConf  route.conf 纯文本（用于提取对等节点 ID）
     */
    private function fetchKeys(string $token, int $zoneId, int $nodeId, string $routeConf = ''): array
    {
        $keys = [];

        // 自身密钥
        try {
            $keysData = $this->backClient->getNodeKeys($token, $zoneId, $nodeId);
            $data = $keysData['data'] ?? $keysData;

            if (! empty($data['public_key'])) {
                $keys["{$nodeId}.public"] = $data['public_key'];
            }
            if (! empty($data['private_key'])) {
                $keys["{$nodeId}.private"] = $data['private_key'];
            }
        } catch (RuntimeException) {
            // 密钥获取失败不阻断整个打包流程
        }

        // 从 route.conf 提取对等节点 ID，批量获取公钥
        $peerNodeIds = $this->extractPeerNodeIds($routeConf, $nodeId);
        if (! empty($peerNodeIds)) {
            try {
                $routerKeysData = $this->backClient->getRouterKeys($token, $zoneId, $nodeId, $peerNodeIds);
                $routerKeys = $routerKeysData['data']['keys'] ?? $routerKeysData['keys'] ?? [];

                foreach ($routerKeys as $keyEntry) {
                    $peerNodeId = $keyEntry['node_id'] ?? null;
                    $pubKey = $keyEntry['public_key'] ?? null;
                    if ($peerNodeId && $pubKey) {
                        $keys["{$peerNodeId}.public"] = $pubKey;
                    }
                }
            } catch (RuntimeException) {
                // 对等密钥获取失败不阻断整个打包流程
            }
        }

        return $keys;
    }

    /**
     * 从 route.conf 文本中提取对等节点 ID
     *
     * route.conf 格式: {nodeId}|{ip}|{mask}
     */
    private function extractPeerNodeIds(string $routeConf, int $selfNodeId): array
    {
        $peerIds = [];
        foreach (explode("\n", $routeConf) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = explode('|', $line);
            if (count($parts) >= 2) {
                $id = (int) $parts[0];
                if ($id > 0 && $id !== $selfNodeId && ! in_array($id, $peerIds, true)) {
                    $peerIds[] = $id;
                }
            }
        }

        return $peerIds;
    }

    /**
     * 创建 ZIP 归档
     */
    private function createZip(
        int $nodeId,
        string $nodeName,
        array $configs,
        array $keys,
        string $readme,
        ?string $platform,
        ?string $arch,
        string $bundleJson = '',
    ): string {
        $tempPath = tempnam(sys_get_temp_dir(), 'mynet_pack_');

        $zip = new ZipArchive;
        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('无法创建 ZIP 文件');
        }

        // R8：bundle.json = BundleEnvelope schema v2（三条链同一契约）
        if ($bundleJson !== '') {
            $zip->addFromString('bundle.json', $bundleJson);
        }

        // conf/{nodeId}/ — 配置文件
        foreach ($configs as $name => $content) {
            $zip->addFromString("conf/{$nodeId}/{$name}", $content);
        }

        // conf/{nodeId}/ed25519/ — 公钥（自身 + 对等节点）
        foreach ($keys as $name => $content) {
            if (str_ends_with($name, '.public')) {
                $zip->addFromString("conf/{$nodeId}/ed25519/{$name}", $content);
            }
        }

        // conf/{nodeId}/security/ — 私钥 + 公钥
        if (isset($keys["{$nodeId}.private"])) {
            $zip->addFromString("conf/{$nodeId}/security/{$nodeId}.private", $keys["{$nodeId}.private"]);
        }
        if (isset($keys["{$nodeId}.public"])) {
            $zip->addFromString("conf/{$nodeId}/security/{$nodeId}.public", $keys["{$nodeId}.public"]);
        }

        // 含软件包模式：添加部署脚本
        if ($platform && $arch) {
            if ($platform === 'windows') {
                $zip->addFromString('setup.ps1', $this->generateWindowsSetupScript($nodeId, $nodeName, $arch));
            } else {
                $zip->addFromString('setup.sh', $this->generateSetupScript($nodeId, $nodeName, $platform, $arch));
            }
        }

        $zip->addFromString('README.txt', $readme);

        $zip->close();

        return $tempPath;
    }

    /**
     * 生成 Linux/macOS/OpenWrt 自动部署脚本
     */
    private function generateSetupScript(int $nodeId, string $nodeName, string $platform, string $arch): string
    {
        $ctlBase = rtrim(config('app.url'), '/');
        $defaultDir = $platform === 'openwrt' ? '/etc/mynet' : '/opt/mynet';

        return <<<BASH
#!/bin/sh
# MyNet 节点配置部署脚本
# 节点: {$nodeName} (ID: {$nodeId})
# 平台: {$platform} / 架构: {$arch}
#
# 用法: chmod +x setup.sh && ./setup.sh [安装目录]

set -e

INSTALL_DIR="\${1:-{$defaultDir}}"
NODE_ID="{$nodeId}"
CTL_BASE="{$ctlBase}"
PLATFORM="{$platform}"
ARCH="{$arch}"

echo "=== MyNet 节点配置部署 ==="
echo "节点: {$nodeName} (ID: {$nodeId})"
echo "平台: {$platform} / 架构: {$arch}"
echo "安装目录: \$INSTALL_DIR"
echo ""

# 创建目录结构
mkdir -p "\$INSTALL_DIR/conf/\$NODE_ID/ed25519"
mkdir -p "\$INSTALL_DIR/conf/\$NODE_ID/security"

# 复制配置文件
SCRIPT_DIR="\$(cd "\$(dirname "\$0")" && pwd)"

if [ -d "\$SCRIPT_DIR/conf/\$NODE_ID" ]; then
    cp -rv "\$SCRIPT_DIR/conf/\$NODE_ID/"* "\$INSTALL_DIR/conf/\$NODE_ID/" 2>/dev/null || true
fi

echo ""
echo "✅ 配置文件已部署到 \$INSTALL_DIR"
echo ""

echo "下一步:"
echo "  1. 下载 GNB: 访问 {$ctlBase}/downloads 选择 {$platform}/{$arch}"
echo "  2. 启动 GNB: gnb -c \$INSTALL_DIR/conf/\$NODE_ID"
echo ""
BASH;
    }

    /**
     * 生成 Windows PowerShell 部署脚本
     */
    private function generateWindowsSetupScript(int $nodeId, string $nodeName, string $arch): string
    {
        return <<<PS1
# MyNet 节点配置部署脚本 (Windows)
# 节点: {$nodeName} (ID: {$nodeId})
# 架构: {$arch}

\$installDir = if (\$args[0]) { \$args[0] } else { "\$env:ProgramFiles\\MyNet" }

Write-Host "=== MyNet 节点配置部署 ===" -ForegroundColor Cyan
Write-Host "安装目录: \$installDir"

# 创建目录
New-Item -ItemType Directory -Force -Path "\$installDir\conf\{$nodeId}\ed25519" | Out-Null
New-Item -ItemType Directory -Force -Path "\$installDir\conf\{$nodeId}\security" | Out-Null

# 复制配置（递归）
\$scriptDir = Split-Path -Parent \$MyInvocation.MyCommand.Path
Copy-Item "\$scriptDir\conf\{$nodeId}\*" "\$installDir\conf\{$nodeId}\" -Recurse -Force -ErrorAction SilentlyContinue

Write-Host "`n✅ 配置文件已部署到 \$installDir" -ForegroundColor Green
Write-Host "启动 GNB: gnb -c \$installDir\conf\{$nodeId}"
PS1;
    }

    /**
     * 生成 README
     */
    private function generateReadme(int $nodeId, string $nodeName, ?string $platform, ?string $arch): string
    {
        $ctlBase = rtrim(config('app.url'), '/');
        $date = now()->toIso8601String();

        $targetLine = ($platform && $arch)
            ? "目标平台:  {$platform} / {$arch}"
            : '目标平台:  通用（仅配置文件）';

        $setupSection = ($platform && $arch)
            ? $this->readmeSetupSection($platform)
            : $this->readmeGenericSetupSection($nodeId);

        return <<<README
========================================
  MyNet 节点配置包
========================================

节点名称: {$nodeName}
节点 ID:   {$nodeId}
{$targetLine}
生成时间:  {$date}
生成来源:  {$ctlBase}

## 目录结构

  conf/
    {$nodeId}/
      node.conf            — GNB 节点运行配置
      address.conf         — 索引节点地址列表（格式: i|0|ip|port）
      route.conf           — 路由配置表（格式: nodeId|ip|mask）
      ed25519/             — 公钥目录
        {$nodeId}.public   — 节点 Ed25519 公钥
        {peerNodeId}.public — 对等节点公钥
      security/            — 私钥 + 公钥目录
        {$nodeId}.private  — 节点私钥
        {$nodeId}.public   — 节点公钥
{$setupSection}

## 安装 GNB 二进制

  访问下载页面: {$ctlBase}/downloads

## 启动 GNB

  gnb -c /opt/mynet/conf/{$nodeId}

## 文档

  官网: {$this->wwwUrl()}
  用户中心: {$this->guiUrl()}
  分发中心: {$ctlBase}

README;
    }

    private function readmeSetupSection(string $platform): string
    {
        if ($platform === 'windows') {
            return <<<'SECTION'

## 快速部署

  Windows:
    powershell -ExecutionPolicy Bypass -File setup.ps1
SECTION;
        }

        $dir = $platform === 'openwrt' ? '/etc/mynet' : '/opt/mynet';

        return <<<SECTION

## 快速部署

    chmod +x setup.sh
    ./setup.sh {$dir}
SECTION;
    }

    /**
     * README “仅配置”模式的部署说明
     */
    private function readmeGenericSetupSection(int $nodeId): string
    {
        return <<<SECTION

## 部署配置

  将 conf/ 目录复制到 GNB 安装目录即可（ed25519/ 和 security/ 已在 conf/{$nodeId}/ 内）。

  示例（Linux）:
    cp -r conf/ /opt/mynet/

  示例（OpenWrt）:
    cp -r conf/ /etc/mynet/
SECTION;
    }
}
