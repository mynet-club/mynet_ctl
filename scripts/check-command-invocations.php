<?php

/**
 * 校验 agent 的 conf/command.*.json 里**每条命令的调用**是否真被 CLI 支持。
 *
 * 为什么需要（与 check-command-vocabulary.php 是同一类问题、方向相反）：
 *   那份脚本管「back 声明的命令名 agent 认不认」；这份管
 *   「agent 自己 conf 里声明的调用，CLI 做不做得到」。
 *
 * 实测过的真实故障（CPI-13）：conf 里 `mynet.gnb.refresh.address` 调
 * `bin/mynet refresh-address`，而该子命令**全仓零实现** —— 声明了却做不到，
 * 一执行就是 unknown command；`refresh-routes` 也不认 conf 传的 `--vpn-type`。
 * 这类失效在节点上表现为"命令执行失败"，但看不出是"根本没人实现"。
 *
 * 用法（默认按同级目录推断两个仓，可覆盖）：
 *   php scripts/check-command-invocations.php
 *   php scripts/check-command-invocations.php --agent=/path/to/agent
 *
 * 退出码：0 通过（可能含已知未决项）；1 有新的不一致；2 用法/环境错误。
 */

$opts = getopt('', ['agent::']);

$root = dirname(__DIR__, 2); // ctl/scripts -> ctl -> lyt.com
$agent = rtrim($opts['agent'] ?? $root . '/mynet_agent-integration', '/');

if (!is_dir($agent)) {
    fwrite(STDERR, "[错误] agent 目录不存在: {$agent}\n");
    exit(2);
}
if (!is_file($agent . '/conf/command.linux.json')) {
    fwrite(STDERR, "[错误] 找不到 {$agent}/conf/command.linux.json\n");
    exit(2);
}

/**
 * 已知未决项：conf 声明了、但当前确实做不到，且原因已记录在案。
 *
 * 刻意逐条列出而不是整体忽略某个前缀——这样"新出现的"不一致仍会被抓到，
 * 而已知项每次运行都会打印出来，不会被忘掉。
 * key = conf 里的命令名，value = 台账条目 + 为什么暂时接受。
 */
$knownOpen = [
    'plugin.proxy.start' => 'CPI-13：mynet_proxy 没有 start/stop/restart 子命令；且 spec/control-plane.md §7 正下架 proxy，需先定退役节奏',
    'plugin.proxy.stop' => '同上（CPI-13）',
    'plugin.proxy.restart' => '同上（CPI-13）',
    // openwrt 的 mynet.start/stop/restart 调 `mynet start`（本仓 CLI 无此子命令），
    // 但 linux 用的是 scripts/mynetd；两平台服务管理方式不同，
    // openwrt 很可能由打包提供 shell 包装器 —— mynet_openwrt 不在工作区，无法判定真伪。
    'mynet.start' => 'CPI-13：openwrt 侧调用为 `mynet start`（非本仓 CLI 子命令）；需 mynet_openwrt 才能判定',
    'mynet.stop' => 'CPI-13：同 mynet.start',
    'mynet.restart' => 'CPI-13：同 mynet.start',
];

// ── 构建 CLI（真跑它的 --help，比解析源码可靠）──
$tmp = sys_get_temp_dir() . '/mynet-confcheck-' . getmypid();
@mkdir($tmp, 0o700, true);

$bins = [];
foreach (['mynet' => '.', 'mynet_proxy' => './cmd/mynet_proxy'] as $name => $pkg) {
    $out = $tmp . '/' . $name;
    $cmd = sprintf(
        'cd %s && go build -o %s %s 2>&1',
        escapeshellarg($agent),
        escapeshellarg($out),
        escapeshellarg($pkg)
    );
    exec($cmd, $lines, $rc);
    if ($rc !== 0) {
        fwrite(STDERR, "[错误] 构建 {$name} 失败：\n" . implode("\n", $lines) . "\n");
        exit(2);
    }
    $bins[$name] = $out;
}

/** 取子命令的 --help 输出；子命令不存在时返回 null。 */
function helpOf(string $bin, string $sub): ?string
{
    $out = [];
    $rc = 0;
    exec(sprintf('%s %s --help 2>&1', escapeshellarg($bin), escapeshellarg($sub)), $out, $rc);
    return $rc === 0 ? implode("\n", $out) : null;
}

$newProblems = [];
$known = [];
$notCovered = [];
$ok = 0;

foreach (glob($agent . '/conf/command.*.json') ?: [] as $file) {
    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data)) {
        fwrite(STDERR, "[错误] 解析失败: {$file}\n");
        exit(2);
    }
    $platform = basename($file);

    foreach ($data as $name => $spec) {
        if (str_starts_with($name, '_') || in_array($name, ['version', 'platform', 'description'], true)) {
            continue;
        }
        $command = $spec['command'] ?? '';
        $args = $spec['args'] ?? [];

        // 只有本仓 CLI 才归本检查管：scripts/mynetd 是脚本，handler/chain 型没有 command
        $base = $command !== '' ? basename($command) : '';
        if (!isset($bins[$base])) {
            $notCovered[] = sprintf('  %-30s (%s) — 非本仓 CLI，未覆盖', $name, $command !== '' ? $command : '无 command（handler/chain）');
            continue;
        }
        if ($args === []) {
            $notCovered[] = sprintf('  %-30s — 没有子命令参数', $name);
            continue;
        }

        $sub = $args[0];
        $help = helpOf($bins[$base], $sub);
        $bad = [];
        if ($help === null) {
            $bad[] = "子命令不存在: {$base} {$sub}";
        } else {
            foreach ($args as $a) {
                if (str_starts_with($a, '--') && !str_contains($help, $a)) {
                    $bad[] = "不认参数 {$a}";
                }
            }
        }

        if ($bad === []) {
            $ok++;
            continue;
        }

        $label = sprintf('  %-30s [%s] %s', $name, $platform, implode('；', $bad));
        if (isset($knownOpen[$name])) {
            $known[] = $label . '  ← ' . $knownOpen[$name];
        } else {
            $newProblems[] = $label;
        }
    }
}

echo "conf 命令调用核对（真跑 CLI 的 --help）\n\n";
printf("  通过        : %d 条\n", $ok);
printf("  已知未决    : %d 条\n", count($known));
printf("  未覆盖      : %d 条（非本仓 CLI / 无子命令）\n", count($notCovered));

if ($known !== []) {
    echo "\n已知未决（已入台账，非新增问题）:\n" . implode("\n", $known) . "\n";
}
if ($notCovered !== []) {
    echo "\n未覆盖:\n" . implode("\n", $notCovered) . "\n";
}

if ($newProblems !== []) {
    echo "\n❌ 新发现 " . count($newProblems) . " 条「conf 声明了但 CLI 做不到」的调用：\n"
        . implode("\n", $newProblems) . "\n";
    echo "\n   这类失效在节点上只表现为「命令执行失败」，看不出是「根本没人实现」。\n";
    echo "   要么补实现、要么从 conf 里去掉——不要留着。\n";
    exit(1);
}

echo "\n✅ 没有新增不一致\n";
exit(0);
