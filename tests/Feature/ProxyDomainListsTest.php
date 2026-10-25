<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProxyDomainListsTest extends TestCase
{
    private string $listDir;

    protected function setUp(): void
    {
        parent::setUp();
        $storage = sys_get_temp_dir().'/ctl-domain-lists-'.bin2hex(random_bytes(8));
        $this->app->useStoragePath($storage);
        $this->listDir = $storage.'/app/public/proxy-lists';
        mkdir($this->listDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->listDir.'/*') as $file) {
            unlink($file);
        }
        rmdir($this->listDir);
        rmdir(dirname($this->listDir));
        rmdir(dirname($this->listDir, 2));
        rmdir(dirname($this->listDir, 3));
        parent::tearDown();
    }

    public function test_domain_lists_are_public_text_at_distribution_path(): void
    {
        foreach (['proxy-domains.txt' => "github.com\n", 'direct-domains.txt' => "*.cn\n", 'ad-domains.txt' => "ads.example.com\n", 'malware-domains.txt' => "bad.example.com\n"] as $name => $body) {
            file_put_contents($this->listDir.'/'.$name, $body);
            $this->get('/d/plugins/mp/'.$name)
                ->assertOk()
                ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
                ->assertContent($body);
        }
    }

    public function test_missing_and_unapproved_lists_are_not_served(): void
    {
        $this->get('/d/plugins/mp/proxy-domains.txt')->assertNotFound();
        file_put_contents($this->listDir.'/private.txt', 'private');
        $this->get('/d/plugins/mp/private.txt')->assertNotFound();
    }
}
