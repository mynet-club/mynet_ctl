<?php

namespace Tests\Feature;

use Tests\TestCase;

class TipsKbEndpointTest extends TestCase
{
    public function test_tips_kb_is_served_at_d_kb(): void
    {
        $response = $this->get('/d/kb/tips-kb.json');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json; charset=utf-8');
        $response->assertHeader('X-KB-Checksum');
        $response->assertHeader('X-KB-Version');

        $doc = json_decode($response->getContent(), true);
        $this->assertIsArray($doc);
        $this->assertArrayHasKey('version', $doc);
        $this->assertArrayHasKey('generated_at', $doc);
        $this->assertSame('zh-CN', $doc['lang']);
        $this->assertNotEmpty($doc['chunks']);
    }

    public function test_checksum_sidecar_matches_body(): void
    {
        $body = $this->get('/d/kb/tips-kb.json');
        $sum = $this->get('/d/kb/tips-kb.json.sha256');

        $body->assertOk();
        $sum->assertOk();
        $sum->assertHeader('Content-Type', 'text/plain; charset=utf-8');

        $expected = hash('sha256', $body->getContent());
        $this->assertStringStartsWith($expected, $sum->getContent());
    }

    public function test_if_none_match_returns_304(): void
    {
        $body = $this->get('/d/kb/tips-kb.json');
        $sha = hash('sha256', $body->getContent());

        $this->get('/d/kb/tips-kb.json', ['If-None-Match' => '"'.$sha.'"'])
            ->assertStatus(304);
    }

    public function test_stale_if_none_match_returns_200(): void
    {
        $this->get('/d/kb/tips-kb.json', ['If-None-Match' => '"'.str_repeat('0', 64).'"'])
            ->assertOk();
    }

    public function test_unknown_kb_name_is_404(): void
    {
        $this->get('/d/kb/nope.json')->assertNotFound();
        $this->get('/d/kb/tips-kb.sh')->assertNotFound();
    }
}
