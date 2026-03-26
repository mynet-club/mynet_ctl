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
        // releases
        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('release_id')->unique();
            $table->string('product', 32);
            $table->string('version', 32);
            $table->string('platform', 32);
            $table->string('arch', 32);
            $table->string('variant', 32)->default('');
            $table->string('filename', 255);
            $table->string('file_path', 512);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->char('sha256', 64)->default('');
            $table->string('download_url', 512)->default('');
            $table->text('changelog')->nullable();
            $table->boolean('is_stable')->default(true);
            $table->boolean('is_latest')->default(false);
            $table->unsignedInteger('download_cnt')->default(0);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['product', 'version', 'platform', 'arch', 'variant']);
            $table->index(['product', 'platform', 'arch']);
            $table->index(['product', 'is_latest', 'is_stable']);
        });

        // plugins
        Schema::create('plugins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plugin_id')->unique();
            $table->string('name', 64);
            $table->string('display_name', 128);
            $table->string('category', 32)->default('general');
            $table->string('version', 32);
            $table->string('platform', 32);
            $table->string('arch', 32)->default('all');
            $table->text('description')->nullable();
            $table->string('filename', 255);
            $table->string('file_path', 512);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->char('sha256', 64)->default('');
            $table->string('download_url', 512)->default('');
            $table->json('dependencies')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('download_cnt')->default(0);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['name', 'version', 'platform', 'arch']);
            $table->index(['category', 'platform']);
        });

        // scripts
        Schema::create('scripts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('script_id')->unique();
            $table->string('name', 64);
            $table->string('display_name', 128);
            $table->string('category', 32)->default('install');
            $table->string('platform', 32)->default('linux');
            $table->string('version', 32)->default('latest');
            $table->text('description')->nullable();
            $table->longText('content');
            $table->string('lang', 16)->default('bash');
            $table->char('checksum', 64)->default('');
            $table->boolean('is_standalone')->default(true);
            $table->string('download_url', 512)->default('');
            $table->unsignedInteger('download_cnt')->default(0);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['name', 'version', 'platform']);
            $table->index(['category', 'platform']);
        });

        // download_logs
        Schema::create('download_logs', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type', 16);
            $table->unsignedBigInteger('resource_id');
            $table->string('ip', 45)->default('');
            $table->string('user_agent', 512)->default('');
            $table->string('referer', 512)->default('');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['resource_type', 'resource_id']);
            $table->index('created_at');
        });

        // api_tokens
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('token_id')->unique();
            $table->string('name', 128);
            $table->char('token_hash', 64)->unique();
            $table->json('abilities');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
        Schema::dropIfExists('download_logs');
        Schema::dropIfExists('scripts');
        Schema::dropIfExists('plugins');
        Schema::dropIfExists('releases');
    }
};
