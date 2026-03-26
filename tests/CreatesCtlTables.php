<?php

namespace Tests;

use Illuminate\Support\Facades\DB;

/**
 * Creates the core CTL tables (releases, plugins, scripts, api_tokens)
 * in the SQLite test database. These tables don't have Laravel migrations.
 */
trait CreatesCtlTables
{
    protected function createCtlTables(): void
    {
        DB::statement('CREATE TABLE IF NOT EXISTS releases (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            release_id INTEGER NOT NULL,
            product VARCHAR(32) NOT NULL,
            version VARCHAR(32) NOT NULL,
            platform VARCHAR(32) NOT NULL,
            arch VARCHAR(32) NOT NULL,
            variant VARCHAR(32) NOT NULL DEFAULT "",
            filename VARCHAR(255) NOT NULL,
            file_path VARCHAR(512) NOT NULL,
            file_size INTEGER NOT NULL DEFAULT 0,
            sha256 CHAR(64) NOT NULL DEFAULT "",
            download_url VARCHAR(512) NOT NULL DEFAULT "",
            changelog TEXT NULL,
            is_stable INTEGER NOT NULL DEFAULT 1,
            is_latest INTEGER NOT NULL DEFAULT 0,
            download_cnt INTEGER NOT NULL DEFAULT 0,
            uploaded_by INTEGER NULL,
            status INTEGER NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');

        DB::statement('CREATE TABLE IF NOT EXISTS plugins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            plugin_id INTEGER NOT NULL,
            name VARCHAR(64) NOT NULL,
            display_name VARCHAR(128) NOT NULL,
            category VARCHAR(32) NOT NULL DEFAULT "general",
            version VARCHAR(32) NOT NULL,
            platform VARCHAR(32) NOT NULL,
            arch VARCHAR(32) NOT NULL DEFAULT "all",
            description TEXT NULL,
            filename VARCHAR(255) NOT NULL,
            file_path VARCHAR(512) NOT NULL,
            file_size INTEGER NOT NULL DEFAULT 0,
            sha256 CHAR(64) NOT NULL DEFAULT "",
            download_url VARCHAR(512) NOT NULL DEFAULT "",
            dependencies TEXT NULL,
            metadata TEXT NULL,
            download_cnt INTEGER NOT NULL DEFAULT 0,
            uploaded_by INTEGER NULL,
            status INTEGER NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
    }

    protected function seedTestReleases(): void
    {
        $releases = [
            ['release_id' => 1001, 'product' => 'gnb', 'version' => 'v1.6.0', 'platform' => 'linux', 'arch' => 'amd64', 'filename' => 'gnb_linux_amd64_v1.6.0.tgz', 'file_path' => 'releases/gnb_linux_amd64_v1.6.0.tgz', 'file_size' => 1024000, 'sha256' => str_repeat('a', 64), 'is_stable' => 1, 'is_latest' => 1],
            ['release_id' => 1002, 'product' => 'gnb', 'version' => 'v1.6.0', 'platform' => 'linux', 'arch' => 'arm64', 'filename' => 'gnb_linux_arm64_v1.6.0.tgz', 'file_path' => 'releases/gnb_linux_arm64_v1.6.0.tgz', 'file_size' => 980000, 'sha256' => str_repeat('b', 64), 'is_stable' => 1, 'is_latest' => 1],
            ['release_id' => 1003, 'product' => 'tui', 'version' => 'v1.4.0', 'platform' => 'linux', 'arch' => 'amd64', 'filename' => 'mynet_linux_amd64_v1.4.0.tgz', 'file_path' => 'releases/mynet_linux_amd64_v1.4.0.tgz', 'file_size' => 2048000, 'sha256' => str_repeat('c', 64), 'is_stable' => 1, 'is_latest' => 1],
            ['release_id' => 1004, 'product' => 'utc', 'version' => 'v0.9.1', 'platform' => 'linux', 'arch' => 'amd64', 'filename' => 'utc_linux_amd64_v0.9.1.tgz', 'file_path' => 'releases/utc_linux_amd64_v0.9.1.tgz', 'file_size' => 512000, 'sha256' => str_repeat('d', 64), 'is_stable' => 1, 'is_latest' => 1],
            ['release_id' => 1005, 'product' => 'proxy', 'version' => 'v1.0.0', 'platform' => 'linux', 'arch' => 'amd64', 'filename' => 'proxy_linux_amd64_v1.0.0.tgz', 'file_path' => 'releases/proxy_linux_amd64_v1.0.0.tgz', 'file_size' => 768000, 'sha256' => str_repeat('e', 64), 'is_stable' => 1, 'is_latest' => 1],
        ];

        foreach ($releases as $r) {
            DB::table('releases')->insert(array_merge($r, [
                'status' => 1,
                'variant' => '',
                'download_url' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
