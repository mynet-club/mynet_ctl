<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 首页（ctl 的 Web 控制台）可访问。
     *
     * ⚠️ 本测试依赖**已构建的前端资源**（`public/build/manifest.json`，
     * 由 `npm run build` 产出）。未构建时页面渲染会抛
     * "Vite manifest not found" —— 那是环境缺件，不是 ctl 坏了，
     * 故**显式跳过并说明要跑什么**，而不是让它静默失败、把门禁染红
     * （门禁常红等于门禁失效）。
     *
     * 只测页面可达性；`/d/` 分发端点与 manifest 的结构另有
     * ManifestEndpointTest 覆盖。
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        if (! file_exists(public_path('build/manifest.json'))) {
            $this->markTestSkipped('前端未构建：先跑 npm install && npm run build（生成 public/build/manifest.json）');
        }

        $this->get('/')->assertStatus(200);
    }
}
