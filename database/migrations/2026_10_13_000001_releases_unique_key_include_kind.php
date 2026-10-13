<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D4-B 双件发布：releases 唯一键纳入 kind。
 *
 * 旧键 (product, version, platform, arch, variant) 同版本只能存一件 ——
 * installer 与 archive（自更新 zip/tgz）互斥，`mynet update` 没归档可取。
 * 新键加 kind 后同一 tuple 可并存 installer + archive 两件。
 *
 * 迁移仅生成 DDL/预检，**不自动对生产库执行**。重复件（同 filename）
 * 由应用层拒绝；历史数据若已有冲突，迁移应失败停止而不是静默去重。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->dropUnique(['product', 'version', 'platform', 'arch', 'variant']);
            $table->unique(
                ['product', 'version', 'platform', 'arch', 'variant', 'kind'],
                'releases_tuple_kind_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->dropUnique('releases_tuple_kind_unique');
            $table->unique(['product', 'version', 'platform', 'arch', 'variant']);
        });
    }
};
