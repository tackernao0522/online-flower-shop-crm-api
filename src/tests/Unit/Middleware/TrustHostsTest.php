<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\TrustHosts;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TrustHostsTest extends TestCase
{
    private TrustHosts $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new TrustHosts($this->app);
    }

    /**
     * @test
     */
    function hosts配列が正しい形式で返されること()
    {
        $hosts = $this->middleware->hosts();

        $this->assertIsArray($hosts);
        $this->assertCount(1, $hosts);
        $this->assertStringContainsString('^(.+\.)?', $hosts[0]);
    }

    /**
     * @test
     */
    function アプリケーションURLのサブドメインが正しく信頼されること()
    {
        Config::set('app.url', 'https://example.com');

        $hosts = $this->middleware->hosts();

        $this->assertEquals(['^(.+\.)?example\.com$'], $hosts);
    }

    /**
     * @test
     */
    function アプリケーションURLがIPアドレスの場合に正しく処理されること()
    {
        Config::set('app.url', 'http://192.168.1.1');

        $hosts = $this->middleware->hosts();

        $this->assertEquals(['^(.+\.)?192\.168\.1\.1$'], $hosts);
    }
}
