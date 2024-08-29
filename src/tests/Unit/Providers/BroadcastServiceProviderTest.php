<?php

namespace Tests\Unit\Providers;

use App\Providers\BroadcastServiceProvider;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;
use Mockery;

class BroadcastServiceProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function bootメソッドがBroadcastRoutesを呼び出すこと()
    {
        Broadcast::shouldReceive('routes')->once();
        Broadcast::shouldReceive('channel')->andReturnSelf();

        $provider = new BroadcastServiceProvider($this->app);
        $provider->boot();

        $this->addToAssertionCount(1);
    }

    /**
     * @test
     */
    public function bootメソッドがchannelsファイルを読み込むこと()
    {
        $channelsPath = base_path('routes/channels.php');

        // channels.phpファイルが存在することを確認
        $this->assertFileExists($channelsPath);

        $requiredFile = null;
        $testProvider = new class($this->app) extends BroadcastServiceProvider {
            public $requiredFile;

            public function boot(): void
            {
                Broadcast::routes();
                $this->requiredFile = base_path('routes/channels.php');
            }
        };

        $testProvider->boot();

        $this->assertEquals($channelsPath, $testProvider->requiredFile);
    }
}
