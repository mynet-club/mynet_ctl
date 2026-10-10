<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('releases', 'kind')) {
            Schema::table('releases', function (Blueprint $table) {
                // 产物类型（**声明式**，不从扩展名运行时猜）：installer | archive
                //   installer = 原生安装包（exe/pkg/dmg/ipk）
                //   archive   = 自更新/分发归档（tgz/zip/tar.gz）
                // 见 spec/lifecycle-contract.md §4.3
                $table->string('kind', 16)->nullable()->after('variant')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('releases', 'kind')) {
            Schema::table('releases', function (Blueprint $table) {
                $table->dropColumn('kind');
            });
        }
    }
};
