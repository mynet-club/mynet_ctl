#!/usr/bin/env node
/**
 * import_openwrt_forever.mjs
 *
 * 无限循环从 downloads.openwrt.org 导入 OpenWrt 设备数据。
 * 控制请求频率，自动跳过已入库的 target，支持多版本覆盖。
 *
 * 用法:
 *   node scripts/import_openwrt_forever.mjs           # 无限循环
 *   node scripts/import_openwrt_forever.mjs --once    # 跑一轮就退出
 *   node scripts/import_openwrt_forever.mjs --force   # 强制重新导入
 *   node scripts/import_openwrt_forever.mjs --version=24.10.6
 *
 * 日志: storage/logs/openwrt_import.log
 */

import { execFileSync, execSync } from 'child_process';
import { createWriteStream, existsSync, mkdirSync, writeFileSync, readFileSync, unlinkSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

// ── 配置 ──────────────────────────────────────────────────────────────────────
const __dir    = dirname(fileURLToPath(import.meta.url));
const CTL_DIR  = join(__dir, '..');
const LOG_FILE = join(CTL_DIR, 'storage/logs/openwrt_import.log');
const LOCK_FILE = '/tmp/openwrt_import_node.lock';
const CDN_BASE  = 'https://downloads.openwrt.org/releases';
const UA        = 'Mozilla/5.0 (compatible; mynet-ctl/1.0; +https://mynet.club)';

// 请求间隔（ms）—— 慢一点，对 CDN 友好
const DELAY_BETWEEN_REQUESTS = 8000;
// 目录枚举时每个子目录请求前的间隔（ms）
const DELAY_DIR_LIST = 2000;
// 每个版本跑完后等待（ms）
const DELAY_BETWEEN_VERSIONS = 60_000;
// 全部版本跑完一轮后等待（ms，默认6小时）
const DELAY_BETWEEN_ROUNDS   = 6 * 60 * 60 * 1000;
// 每个 target 至少有多少设备才算"已完成"，少于此数则重试
const MIN_DEVICES = 1;

let VERSIONS = ['25.12.1', '24.10.6', '23.05.3'];
let ONCE     = false;
let FORCE    = false;

// parse CLI flags
for (const arg of process.argv.slice(2)) {
    if (arg === '--once')          ONCE  = true;
    else if (arg === '--force')    FORCE = true;
    else if (arg.startsWith('--version=')) VERSIONS = [arg.slice('--version='.length)];
}
// ─────────────────────────────────────────────────────────────────────────────

// ── 初始化 ────────────────────────────────────────────────────────────────────
mkdirSync(dirname(LOG_FILE), { recursive: true });

// lock file
if (existsSync(LOCK_FILE)) {
    const pid = readFileSync(LOCK_FILE, 'utf8').trim();
    try {
        process.kill(Number(pid), 0);
        console.error(`已有实例在运行 (PID=${pid})，退出。`);
        process.exit(1);
    } catch {
        // 旧进程已死，继续
    }
}
writeFileSync(LOCK_FILE, String(process.pid));
const cleanup = () => { try { unlinkSync(LOCK_FILE); } catch {} };
process.on('exit', cleanup);
process.on('SIGINT', () => { cleanup(); process.exit(0); });
process.on('SIGTERM', () => { cleanup(); process.exit(0); });

// ── 工具函数 ──────────────────────────────────────────────────────────────────
const logStream = createWriteStream(LOG_FILE, { flags: 'a' });

function log(msg) {
    const line = `[${new Date().toISOString().replace('T', ' ').slice(0, 19)}] ${msg}`;
    console.log(line);
    logStream.write(line + '\n');
}

function sleep(ms) {
    return new Promise(r => setTimeout(r, ms));
}

/**
 * 带重试的 fetch，返回 Response 或 null
 */
async function fetchWithRetry(url, retries = 3, delayMs = 3000) {
    for (let i = 1; i <= retries; i++) {
        try {
            const res = await fetch(url, {
                headers: { 'User-Agent': UA },
                signal: AbortSignal.timeout(20_000),
            });
            if (res.ok) return res;
            // 429 / 503：等更久
            if (res.status === 429 || res.status === 503) {
                log(`  [warn] ${url} → HTTP ${res.status}，等待 ${delayMs * i}ms 后重试 (${i}/${retries})`);
                await sleep(delayMs * i);
            } else {
                return null; // 4xx 不重试
            }
        } catch (err) {
            if (i < retries) {
                log(`  [warn] fetch 失败 (${i}/${retries}): ${err.message}`);
                await sleep(delayMs);
            }
        }
    }
    return null;
}

/**
 * 从 CDN 目录页面解析出子目录名列表
 */
async function listDirs(url) {
    await sleep(DELAY_DIR_LIST); // 每次目录请求前等待，避免连续触发限流
    const res = await fetchWithRetry(url);
    if (!res) return [];
    const html = await res.text();
    // 匹配: href="dirname/"  (只有纯名称，不含 /)
    const matches = [...html.matchAll(/href="([a-z0-9_][a-z0-9_-]*)\/"/g)];
    return matches.map(m => m[1]);
}

/**
 * 枚举某版本下所有 {top}/{sub} target
 */
async function enumerateTargets(version) {
    const base  = `${CDN_BASE}/${version}/targets`;
    const tops  = await listDirs(`${base}/`);
    log(`  枚举到 ${tops.length} 个顶层 target 目录`);

    const results = [];
    for (const top of tops) {
        const subs = await listDirs(`${base}/${top}/`);
        for (const sub of subs) {
            results.push(`${top}/${sub}`);
        }
        await sleep(500);
    }
    return results;
}

/**
 * 查询 DB 中某 distrib_target 的设备数（通过 artisan tinker）
 */
function dbDeviceCount(target) {
    try {
        const out = execFileSync(
            'php',
            [
                join(CTL_DIR, 'artisan'),
                'tinker',
                '--execute',
                `echo App\\Models\\OpenwrtDeviceFirmware::where('distrib_target','${target}')->count();`,
            ],
            { cwd: CTL_DIR, timeout: 15_000, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] }
        );
        const last = out.trim().split('\n').pop().trim();
        return parseInt(last, 10) || 0;
    } catch {
        return 0;
    }
}

/**
 * 下载 profiles.json，返回对象或 null
 */
async function fetchProfiles(version, target) {
    const url = `${CDN_BASE}/${version}/targets/${target}/profiles.json`;
    const res = await fetchWithRetry(url, 3, 3000);
    if (!res) return null;
    try {
        const json = await res.json();
        if (!json.profiles) return null;
        return json;
    } catch {
        return null;
    }
}

/**
 * 将 profiles.json 写到临时文件，运行 artisan openwrt:import-toh，返回导入数
 */
function importTarget(version, target, profilesJson) {
    const safe      = target.replace(/\//g, '_');
    const cacheDir  = join(CTL_DIR, 'storage/app/openwrt_cache');
    mkdirSync(cacheDir, { recursive: true });
    const cacheFile = join(cacheDir, `${version}_${safe}.json`);
    writeFileSync(cacheFile, JSON.stringify(profilesJson));

    try {
        const out = execFileSync(
            'php',
            [
                join(CTL_DIR, 'artisan'),
                'openwrt:import-toh',
                version,
                `--target=${target}`,
                `--file=${cacheFile}`,
            ],
            { cwd: CTL_DIR, timeout: 60_000, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] }
        );
        // 解析 "devices:N" from artisan output
        const m = out.match(/devices:(\d+)/);
        return m ? parseInt(m[1], 10) : 0;
    } catch (err) {
        log(`  [error] artisan import 失败: ${err.message?.slice(0, 200)}`);
        return 0;
    }
}

/**
 * 查询 DB 设备总数
 */
function dbTotalDevices() {
    try {
        const out = execFileSync(
            'php',
            [
                join(CTL_DIR, 'artisan'),
                'tinker',
                '--execute',
                `echo App\\Models\\OpenwrtDevice::count();`,
            ],
            { cwd: CTL_DIR, timeout: 15_000, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] }
        );
        return parseInt(out.trim().split('\n').pop().trim(), 10) || 0;
    } catch {
        return -1;
    }
}

// ── 主循环 ────────────────────────────────────────────────────────────────────
let round = 0;

while (true) {
    round++;
    log(`========= 第 ${round} 轮开始 =========`);

    let totalNew  = 0;
    let totalSkip = 0;
    let totalFail = 0;

    for (const version of VERSIONS) {
        log(`--- 版本 ${version} ---`);

        log(`枚举 ${version} 的所有 target...`);
        const targets = await enumerateTargets(version);
        log(`发现 ${targets.length} 个 target`);

        for (const target of targets) {
            // 检查 DB（非强制模式跳过已有数据的 target）
            if (!FORCE) {
                const cnt = dbDeviceCount(target);
                if (cnt >= MIN_DEVICES) {
                    log(`  [skip] ${target} (DB 中已有 ${cnt} 台设备)`);
                    totalSkip++;
                    continue;
                }
            }

            log(`  [fetch] ${version}/${target}...`);
            const profiles = await fetchProfiles(version, target);
            await sleep(DELAY_BETWEEN_REQUESTS);

            if (!profiles) {
                log(`  [fail]  ${version}/${target} — 获取失败（可能不存在于此版本）`);
                totalFail++;
                continue;
            }

            log(`  [import] ${version}/${target}...`);
            const imported = importTarget(version, target, profiles);
            log(`  [done]  ${version}/${target} — 导入 ${imported} 台设备`);
            totalNew += imported;

            await sleep(DELAY_BETWEEN_REQUESTS);
        }

        log(`版本 ${version} 完成。等待 ${DELAY_BETWEEN_VERSIONS / 1000}s...`);
        await sleep(DELAY_BETWEEN_VERSIONS);
    }

    log(`========= 第 ${round} 轮结束 =========`);
    log(`本轮新增/更新: ${totalNew}  跳过: ${totalSkip}  失败: ${totalFail}`);

    const total = dbTotalDevices();
    log(`当前 DB 设备总数: ${total}`);

    if (ONCE) {
        log('--once 模式，退出。');
        process.exit(0);
    }

    log(`等待 ${DELAY_BETWEEN_ROUNDS / 3600 / 1000}h 后开始下一轮...`);
    await sleep(DELAY_BETWEEN_ROUNDS);
}
