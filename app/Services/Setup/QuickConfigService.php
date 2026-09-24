<?php

namespace App\Services\Setup;

use RuntimeException;
use ZipArchive;

/**
 * 快配服务 — 独立生成 GNB 节点配置（不依赖 mynet_back）
 *
 * 用户输入节点数量、子网、端口等参数，本地生成 Ed25519 密钥对和全部配置文件，
 * 打包为单个 ZIP 下载。适用于快速搭建 GNB 组网的场景。
 */
class QuickConfigService
{
    /**
     * GNB node.conf 默认配置
     */
    private const NODE_DEFAULTS = [
        'ifname' => 'gnb_tun',
        'mtu' => 1450,
        'multi_socket' => 'on',
        'direct_forwarding' => 'on',
        'unified_forwarding' => 'auto',
        'standard_forwarding' => 'on',
        'address_secure' => 'on',
        'forward' => 'on',
        'daemon' => 'on',
        'es_argv' => '--upnp',
        'passcode' => '12345678',
        'crypto' => 'xor',
    ];

    /**
     * snake_case → kebab-case 字段名映射（与 GNB 一致）
     */
    private const FIELD_MAP = [
        'nodeid' => 'nodeid',
        'ifname' => 'ifname',
        'listen' => 'listen',
        'mtu' => 'mtu',
        'multi_socket' => 'multi-socket',
        'direct_forwarding' => 'direct-forwarding',
        'unified_forwarding' => 'unified-forwarding',
        'standard_forwarding' => 'standard-forwarding',
        'address_secure' => 'address-secure',
        'forward' => 'forward',
        'daemon' => 'daemon',
        'es_argv' => 'es-argv',
        'passcode' => 'passcode',
        'crypto' => 'crypto',
    ];

    /**
     * 批量生成节点配置包（合并为单个 ZIP）
     *
     * @param  array  $params  用户输入参数
     *                         - nodes: array [{ name, ip, port, is_index, public_ip? }]
     *                         - subnet_mask: string (默认 255.255.255.0)
     *                         - external_index?: string (外部索引地址 ip:port，当无节点做 index 时)
     * @return array{path: string, filename: string}
     */
    public function generateBatch(array $params): array
    {
        $nodes = $params['nodes'] ?? [];
        $subnetMask = $params['subnet_mask'] ?? '255.255.255.0';
        $externalIndex = $params['external_index'] ?? null;

        if (empty($nodes)) {
            throw new RuntimeException('至少需要一个节点');
        }

        // 为每个节点生成 Ed25519 密钥对
        $nodeKeys = [];
        foreach ($nodes as &$node) {
            $keyPair = sodium_crypto_sign_keypair();
            $publicKey = bin2hex(sodium_crypto_sign_publickey($keyPair));
            $privateKey = bin2hex(sodium_crypto_sign_secretkey($keyPair));
            $nodeKeys[$node['node_id']] = [
                'public' => $publicKey,
                'private' => $privateKey,
            ];
        }
        unset($node);

        // 生成 address.conf（所有节点共享相同的索引列表）
        $addressConf = $this->generateAddressConf($nodes, $externalIndex);

        // 打包
        $tempPath = tempnam(sys_get_temp_dir(), 'mynet_quick_');
        $zip = new ZipArchive;
        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('无法创建 ZIP 文件');
        }

        foreach ($nodes as $node) {
            $nodeId = $node['node_id'];
            $nodeName = $node['name'];
            $prefix = "{$nodeName}_{$nodeId}";

            // node.conf
            $nodeConf = $this->generateNodeConf($node);
            $zip->addFromString("{$prefix}/conf/{$nodeId}/node.conf", $nodeConf);

            // address.conf
            $zip->addFromString("{$prefix}/conf/{$nodeId}/address.conf", $addressConf);

            // route.conf
            $routeConf = $this->generateRouteConf($nodes, $subnetMask);
            $zip->addFromString("{$prefix}/conf/{$nodeId}/route.conf", $routeConf);

            // conf/{nodeId}/ed25519/ — 所有节点公钥
            foreach ($nodeKeys as $peerId => $peerKey) {
                $zip->addFromString(
                    "{$prefix}/conf/{$nodeId}/ed25519/{$peerId}.public",
                    $peerKey['public']
                );
            }

            // conf/{nodeId}/security/ — 自身私钥 + 公钥
            $zip->addFromString(
                "{$prefix}/conf/{$nodeId}/security/{$nodeId}.private",
                $nodeKeys[$nodeId]['private']
            );
            $zip->addFromString(
                "{$prefix}/conf/{$nodeId}/security/{$nodeId}.public",
                $nodeKeys[$nodeId]['public']
            );

            // setup.sh
            $setupSh = $this->generateSetupScript($nodeId, $nodeName);
            $zip->addFromString("{$prefix}/setup.sh", $setupSh);

            // README.txt
            $readme = $this->generateReadme($node, $nodes, $externalIndex);
            $zip->addFromString("{$prefix}/README.txt", $readme);
        }

        $zip->close();

        $count = count($nodes);
        $filename = "mynet_quickconfig_{$count}nodes.zip";

        return [
            'path' => $tempPath,
            'filename' => $filename,
        ];
    }

    /**
     * 生成 node.conf 纯文本
     */
    private function generateNodeConf(array $node): string
    {
        $config = array_merge(self::NODE_DEFAULTS, [
            'nodeid' => $node['node_id'],
            'listen' => $node['port'] ?? 9001,
        ]);

        if (! empty($node['ifname'])) {
            $config['ifname'] = $node['ifname'];
        }

        $lines = ['# GNB Node Configuration', "# Node: {$node['name']} (ID: {$node['node_id']})", ''];

        foreach ($config as $key => $value) {
            $confKey = self::FIELD_MAP[$key] ?? str_replace('_', '-', $key);
            $lines[] = "{$confKey} {$value}";
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * 生成 address.conf（索引节点列表）
     *
     * 格式: i|0|{ip}|{port}
     */
    private function generateAddressConf(array $nodes, ?string $externalIndex): string
    {
        $lines = ['# GNB Index Services Configuration', '# Format: i|0|ip|port', ''];

        $hasIndex = false;

        foreach ($nodes as $node) {
            if (! empty($node['is_index']) && ! empty($node['public_ip'])) {
                $port = $node['port'] ?? 9001;
                $lines[] = "i|0|{$node['public_ip']}|{$port}";
                $hasIndex = true;
            }
        }

        if (! $hasIndex && $externalIndex) {
            // 外部索引格式: ip:port
            $parts = explode(':', $externalIndex);
            $ip = $parts[0];
            $port = $parts[1] ?? '9001';
            $lines[] = "i|0|{$ip}|{$port}";
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * 生成 route.conf（所有节点的路由表）
     *
     * 格式: {nodeId}|{ip}|{mask}
     */
    private function generateRouteConf(array $nodes, string $subnetMask): string
    {
        $lines = ['# GNB Node Route Configuration', '# Format: {nodeId}|{ipAddress}|{subnetMask}', ''];

        foreach ($nodes as $node) {
            $lines[] = "{$node['node_id']}|{$node['ip']}|{$subnetMask}";
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * 生成 setup.sh 部署脚本
     */
    private function generateSetupScript(int $nodeId, string $nodeName): string
    {
        $ctlBase = rtrim(config('app.url'), '/');

        return <<<BASH
#!/bin/sh
# MyNet 节点配置部署脚本（快配生成）
# 节点: {$nodeName} (ID: {$nodeId})
#
# 用法: chmod +x setup.sh && ./setup.sh [安装目录]

set -e

INSTALL_DIR="\${1:-/opt/mynet}"
NODE_ID="{$nodeId}"

echo "=== MyNet 节点配置部署 ==="
echo "节点: {$nodeName} (ID: {$nodeId})"
echo "安装目录: \$INSTALL_DIR"
echo ""

# 创建目录结构
mkdir -p "\$INSTALL_DIR/conf/\$NODE_ID/ed25519"
mkdir -p "\$INSTALL_DIR/conf/\$NODE_ID/security"

# 复制配置文件
SCRIPT_DIR="\$(cd "\$(dirname "\$0")" && pwd)"

cp -rv "\$SCRIPT_DIR/conf/\$NODE_ID/"* "\$INSTALL_DIR/conf/\$NODE_ID/" 2>/dev/null || true

echo ""
echo "✅ 配置文件已部署到 \$INSTALL_DIR"
echo ""
echo "下一步:"
echo "  1. 下载 GNB 二进制: {$ctlBase}/downloads"
echo "  2. 启动 GNB: gnb -c \$INSTALL_DIR/conf/\$NODE_ID"
echo ""
BASH;
    }

    /**
     * 生成 README.txt
     */
    private function generateReadme(array $node, array $allNodes, ?string $externalIndex): string
    {
        $ctlBase = rtrim(config('app.url'), '/');
        $wwwUrl = rtrim(config('services.mynet_urls.www'), '/');
        $date = now()->toIso8601String();
        $nodeId = $node['node_id'];
        $nodeName = $node['name'];

        $nodeList = '';
        foreach ($allNodes as $n) {
            $role = (! empty($n['is_index']) && ! empty($n['public_ip'])) ? ' [INDEX]' : '';
            $nodeList .= "    {$n['node_id']}  {$n['name']}  {$n['ip']}  :{$n['port']}{$role}\n";
        }
        if ($externalIndex) {
            $nodeList .= "    (外部索引)  {$externalIndex}\n";
        }

        return <<<README
========================================
  MyNet 快配 — 节点配置包
========================================

节点名称: {$nodeName}
节点 ID:   {$nodeId}
生成时间:  {$date}
生成来源:  {$ctlBase}
模式:      快配（离线生成，密钥由 CTL 服务端生成）

## 网络拓扑

{$nodeList}
## 目录结构

  conf/
    {$nodeId}/
      node.conf            — GNB 节点运行配置
      address.conf         — 索引节点地址列表（格式: i|0|ip|port）
      route.conf           — 路由配置表（格式: nodeId|ip|mask）
      ed25519/             — 公钥目录
        {$nodeId}.public   — 节点 Ed25519 公钥
        *.public           — 所有对等节点公钥
      security/            — 私钥 + 公钥目录
        {$nodeId}.private  — 节点 Ed25519 私钥（⚠️ 请妥善保管）
        {$nodeId}.public   — 节点 Ed25519 公钥

## 快速部署

    chmod +x setup.sh
    ./setup.sh /opt/mynet

## 安装 GNB 二进制

  访问下载页面: {$ctlBase}/downloads

## 启动 GNB

  gnb -c /opt/mynet/conf/{$nodeId}

## 文档

  官网: {$wwwUrl}
  分发中心: {$ctlBase}

README;
    }

    /**
     * 生成随机节点 ID（16位数字，符合 GNB nodeid 范围）
     */
    public static function generateNodeId(): int
    {
        return random_int(1000000000000000, 9007199254740991);
    }
}
