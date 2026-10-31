<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * 蒸馏产物 tips-kb.json 的契约自检（spec/tips-kb-contract.md §2 + §4）。
 * 产物由 scripts/tips-kb/build_tips_kb.py 生成；md 是源，手改产物不算源变更。
 */
class TipsKbArtifactTest extends TestCase
{
    /** spec §4.3 的 v1 计划 id 集（25 条）；增删 chunk 须同步改 spec 与本表。 */
    private const PLANNED_IDS = [
        'tc-overview', 'tc-collect-site', 'tc-step-a-config', 'tc-step-b-keys',
        'tc-step-c-adjacency', 'tc-step-d-routing', 'tc-step-e-firewall', 'tc-peering-zip',
        'tc-count-diagnosis', 'tc-platform-quickref', 'tc-decision-tree',
        'nc-check-model', 'nc-q1-large-packet', 'nc-q2-stop-failed', 'nc-q3-old-pubkey',
        'nc-q4-restore-backup', 'nc-q5-peering-items', 'nc-q6-commands', 'nc-product-rules',
        'help-what-is-mynet', 'help-basic-concepts', 'help-registration', 'help-login',
        'help-create-ip-pool', 'help-general-faq',
    ];

    /** 脱敏红线（spec §2.4）：内网地址/生产主机/密钥文件名不进公开载荷。 */
    private const BANNED_LITERALS = [
        '10.182.236', '10.255.', '192.168.0.2', '172.26.', '52.221.', '18.141.',
        '52.77.', 'Aws.pem',
    ];

    private const ID_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    private array $doc;

    protected function setUp(): void
    {
        $path = __DIR__.'/../../scripts/tips-kb/tips-kb.json';
        $this->assertFileExists($path);
        $doc = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($doc, 'tips-kb.json 必须是合法 JSON');
        $this->doc = $doc;
    }

    public function test_manifest_shape(): void
    {
        $this->assertSame(
            ['version', 'generated_at', 'lang', 'chunks'],
            array_keys($this->doc),
            'manifest 只允许 version/generated_at/lang/chunks（schema additionalProperties=false）'
        );
        $this->assertNotSame('', $this->doc['version']);
        $this->assertNotSame('', $this->doc['generated_at']);
        $this->assertSame('zh-CN', $this->doc['lang']);
        $this->assertIsArray($this->doc['chunks']);
    }

    public function test_chunk_ids_match_distillation_plan(): void
    {
        $ids = array_column($this->doc['chunks'], 'id');
        $this->assertSame(self::PLANNED_IDS, $ids, 'chunk id 集/顺序须与 spec §4.3 一致');
    }

    public function test_chunks_obey_field_contract(): void
    {
        foreach ($this->doc['chunks'] as $chunk) {
            $id = $chunk['id'] ?? '?';
            $this->assertMatchesRegularExpression(self::ID_PATTERN, $id, "id 不合法: {$id}");

            $this->assertNotEmpty($chunk['title'] ?? '', "{$id}: title 缺失");
            $this->assertArrayHasKey('tags', $chunk, "{$id}: tags 缺失");
            $this->assertGreaterThanOrEqual(1, count($chunk['tags']), "{$id}: tags 至少 1 个");
            $this->assertLessThanOrEqual(8, count($chunk['tags']), "{$id}: tags 至多 8 个");
            foreach ($chunk['tags'] as $tag) {
                $this->assertMatchesRegularExpression(self::ID_PATTERN, $tag, "{$id}: tag 不合法 {$tag}");
            }

            $body = $chunk['body'] ?? '';
            $this->assertNotSame('', trim($body), "{$id}: body 为空");
            $this->assertLessThanOrEqual(1500, mb_strlen($body), "{$id}: body 超 1500 字须拆条");
            $this->assertSame(
                hash('sha256', $body),
                $chunk['sha256'] ?? '',
                "{$id}: sha256 与 body 不符"
            );

            $file = $chunk['source']['file'] ?? '';
            $this->assertTrue(
                str_starts_with($file, 'mynet_ctl/docs/') || str_starts_with($file, 'mynet_gui/docs/'),
                "{$id}: source.file 须为 workspace 相对的 docs 源，实际 {$file}"
            );
            $this->assertNotSame('', $chunk['source']['section'] ?? '', "{$id}: source.section 缺失");

            foreach (self::BANNED_LITERALS as $banned) {
                $this->assertStringNotContainsString($banned, $body, "{$id}: body 含脱敏红线 {$banned}");
                $this->assertStringNotContainsString($banned, $chunk['title'], "{$id}: title 含脱敏红线 {$banned}");
            }
            $this->assertFalse(
                str_contains($body, 'BEGIN') && str_contains($body, 'PRIVATE') && str_contains($body, 'KEY'),
                "{$id}: body 含私钥材料"
            );
        }
    }
}
