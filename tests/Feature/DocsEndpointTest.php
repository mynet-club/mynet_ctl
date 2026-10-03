<?php

namespace Tests\Feature;

use Tests\TestCase;

class DocsEndpointTest extends TestCase
{
    public function test_skill_kit_is_served_at_d_docs(): void
    {
        $response = $this->get('/d/docs/skill-kit.md');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/markdown; charset=utf-8');
        $response->assertHeader('X-Doc-Version', '1.0.0');
        $this->assertStringContainsString('mynet-manage-skill-kit', $response->getContent());
        $this->assertStringContainsString('自升级', $response->getContent());
    }

    public function test_skill_kit_checksum_matches_body(): void
    {
        $body = $this->get('/d/docs/skill-kit.md');
        $sum = $this->get('/d/docs/skill-kit.md.sha256');

        $body->assertOk();
        $sum->assertOk();

        $expected = hash('sha256', $body->getContent());
        $this->assertStringStartsWith($expected, $sum->getContent());
    }

    public function test_unknown_doc_is_404(): void
    {
        $this->get('/d/docs/nope.md')->assertNotFound();
        $this->get('/d/docs/skill-kit.sh')->assertNotFound();
    }

    public function test_checksum_route_is_not_confused_with_product_download(): void
    {
        // product 通配只吃 tgz/zip 等，docs/*.md 必须走 DocsController
        $this->get('/d/docs/skill-kit.md')->assertOk();
    }
}
