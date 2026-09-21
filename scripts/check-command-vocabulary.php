<?php

/**
 * 校验 back 声明的远程命令是否都是 agent 认识的（CPI-4 的防复发起手）。
 *
 * 为什么需要它：命令名的唯一真值来源是 agent 的 `conf/command.{linux,openwrt}.json`
 * ——agent 的 CommandExecutor 严格按该文件匹配，未定义即返回"未识别的命令"，
 * **且只在节点本地日志里留一行**。从 back 侧完全看不出来，于是在 back 里写错命令名
 * 的后果是"功能静默失效"。历史上就漂成过一整套（mynet.boot / mynet.reboot /
 * mynet.shutdown / mynet.regenConfig / mynet.reloadConfigs / plugin.proxy.reload），
 * 其中 mynet.reboot 让"重启节点"整整失效。
 *
 * 用法（默认按同级目录推断两个仓，可覆盖）：
 *   php scripts/check-command-vocabulary.php
 *   php scripts/check-command-vocabulary.php --back=/path/to/back --agent=/path/to/agent
 *
 * 退出码：0 通过；1 有 back 单方面声明、agent 不认的命令（阻断）；2 用法/环境错误。
 */

$opts = getopt('', ['back::', 'agent::']);

$root = dirname(__DIR__, 2); // ctl/scripts -> ctl -> lyt.com
$back  = rtrim($opts['back']  ?? $root . '/mynet_back-integration', '/');
$agent = rtrim($opts['agent'] ?? $root . '/mynet_agent-integration', '/');

foreach (['back' => $back, 'agent' => $agent] as $name => $dir) {
    if (!is_dir($dir)) {
        fwrite(STDERR, "[错误] {$name} 目录不存在: {$dir}\n");
        exit(2);
    }
}

// ── agent 侧真值：conf/command.*.json 的命令键 ──
$meta = ['version', 'platform', 'description'];
$agentCommands = [];
foreach (glob($agent . '/conf/command.*.json') ?: [] as $file) {
    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data)) {
        fwrite(STDERR, "[错误] 解析失败: {$file}\n");
        exit(2);
    }
    foreach (array_keys($data) as $k) {
        if (str_starts_with($k, '_') || in_array($k, $meta, true)) {
            continue;
        }
        $agentCommands[$k][] = basename($file);
    }
}
$agentCommands = array_keys($agentCommands);
sort($agentCommands);

if ($agentCommands === []) {
    fwrite(STDERR, "[错误] 没从 {$agent}/conf/command.*.json 读到任何命令\n");
    exit(2);
}

// ── back 侧声明：真跑它的代码，不解析源码 ──
$probe = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo json_encode(App\Modules\Monitor\Models\MonitorRtCmd::getAvailableCommands());
PHP;
$cmd = 'cd ' . escapeshellarg($back) . ' && php -r ' . escapeshellarg($probe) . ' 2>/dev/null';
$backCommands = json_decode((string) shell_exec($cmd), true);

if (!is_array($backCommands)) {
    fwrite(STDERR, "[错误] 没能从 back 取到 getAvailableCommands()（试着手动在 {$back} 里跑一次）\n");
    exit(2);
}
sort($backCommands);

$unknown = array_values(array_diff($backCommands, $agentCommands));
$notExposed = array_values(array_diff($agentCommands, $backCommands));

printf("agent conf 命令 : %d 条（%s）\n", count($agentCommands), 'conf/command.*.json');
printf("back 声明命令   : %d 条\n\n", count($backCommands));

if ($notExposed !== []) {
    // 不算错：链内子步骤（mynetd.*）本来就不该让用户直接选
    echo "· back 未暴露（agent 有、back 不声明）: " . implode(', ', $notExposed) . "\n";
}

if ($unknown !== []) {
    echo "\n❌ back 声明了 agent 不认识的命令 " . count($unknown) . " 个：" . implode(', ', $unknown) . "\n";
    echo "   这些命令下发给节点只会得到「未识别的命令」，且 back 侧看不出失败。\n";
    exit(1);
}

echo "\n✅ back 声明的命令 agent 全部认识\n";
exit(0);
