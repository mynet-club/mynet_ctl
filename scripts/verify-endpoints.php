<?php
/**
 * 验证所有 CTL API 端点
 * 用法: php scripts/verify-endpoints.php
 */

$base = 'http://127.0.0.1:8002/api/v1';

$checks = [
    ['GET', 'apps',                     "{$base}/apps"],
    ['GET', 'mynet/manifest',             "{$base}/mynet/manifest"],
    ['GET', 'gnb/manifest',               "{$base}/gnb/manifest"],
    ['GET', 'utc/manifest',               "{$base}/utc/manifest"],
    ['GET', 'releases',                  "{$base}/releases"],
    ['GET', 'releases?product=tui',      "{$base}/releases?product=tui"],
    ['GET', 'releases/latest',           "{$base}/releases/latest?product=tui&platform=linux&arch=amd64"],
    ['GET', 'scripts',                   "{$base}/scripts"],
    ['GET', 'plugins',                   "{$base}/plugins"],
    ['GET', 'detect/openwrt',            "{$base}/detect/openwrt?distrib_arch=mipsel_24kc&distrib_target=ramips/mt7621"],
    ['GET', 'detect/arch-map',           "{$base}/detect/arch-map"],
    ['GET', 'detect/openwrt.sh',         "{$base}/detect/openwrt.sh"],
    ['GET', 'home page',                 "http://127.0.0.1:8002/"],
    ['GET', 'downloads page',            "http://127.0.0.1:8002/downloads"],
    ['GET', 'openwrt page',              "http://127.0.0.1:8002/openwrt"],
    ['GET', 'tools page',                "http://127.0.0.1:8002/tools"],
    ['GET', 'setup page',                "http://127.0.0.1:8002/setup"],
];

$pass = 0;
$fail = 0;

foreach ($checks as [$method, $label, $url]) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $ok = ($code >= 200 && $code < 300);
    $icon = $ok ? "\u{2713}" : "\u{2717}";

    $detail = '';
    $data = json_decode($resp, true);
    if (is_array($data)) {
        if (isset($data['apps']) && is_array($data['apps'])) {
            $detail = count($data['apps']) . ' apps';
        } elseif (isset($data['data']) && is_array($data['data'])) {
            $detail = count($data['data']) . ' items';
        } elseif (isset($data['latest_stable'])) {
            $detail = "latest={$data['latest_stable']}";
        } elseif (isset($data['ctl_arch'])) {
            $detail = "arch={$data['ctl_arch']} conf={$data['confidence']}";
        } elseif (isset($data['arch_map'])) {
            $detail = count($data['arch_map']) . ' arch mappings';
        }
    } elseif ($code === 200 && strlen($resp) > 0) {
        // Non-JSON (shell script, HTML)
        $detail = strlen($resp) . ' bytes';
    }

    echo "  [{$code}] {$icon} {$label}" . ($detail ? " -> {$detail}" : '') . "\n";
    $ok ? $pass++ : $fail++;
}

echo "\n=== {$pass} passed, {$fail} failed ===\n";
