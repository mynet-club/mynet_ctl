<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('scripts', function (Blueprint $table) {
            // 关联的产品版本，NULL = 通用最新版（不绑定特定 release）
            $table->string('target_version', 32)->nullable()->after('version')
                ->comment('关联产品版本，NULL=通用最新');
        });

        // 重建唯一键：同名脚本可以有多个 target_version
        Schema::table('scripts', function (Blueprint $table) {
            $table->dropUnique('scripts_name_version_platform_unique');
            $table->unique(['name', 'platform', 'target_version'], 'uk_name_platform_target');
        });
    }

    public function down(): void
    {
        Schema::table('scripts', function (Blueprint $table) {
            $table->dropUnique('uk_name_platform_target');
            $table->unique(['name', 'version', 'platform']);
            $table->dropColumn('target_version');
        });
    }
};
