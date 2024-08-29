<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\RedirectIfAuthenticated;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class RedirectIfAuthenticatedTest extends TestCase
{
    use RefreshDatabase;

    private RedirectIfAuthenticated $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new RedirectIfAuthenticated();
    }

    /**
     * @test
     */
    function 認証済みユーザーがリダイレクトされること()
    {
        Auth::shouldReceive('guard')->once()->with(null)->andReturn($guard = \Mockery::mock());
        $guard->shouldReceive('check')->once()->andReturn(true);

        $request = Request::create('/login', 'GET');
        $response = $this->middleware->handle($request, function () {});

        $this->assertEquals('http://localhost' . RouteServiceProvider::HOME, $response->getTargetUrl());
    }

    /**
     * @test
     */
    function 未認証ユーザーがリダイレクトされないこと()
    {
        Auth::shouldReceive('guard')->once()->with(null)->andReturn($guard = \Mockery::mock());
        $guard->shouldReceive('check')->once()->andReturn(false);

        $request = Request::create('/login', 'GET');
        $response = $this->middleware->handle($request, function () {
            return response('passed');
        });

        $this->assertEquals('passed', $response->getContent());
    }

    /**
     * @test
     */
    function 複数のガードを処理できること()
    {
        Auth::shouldReceive('guard')->twice()->andReturn($guard = \Mockery::mock());
        $guard->shouldReceive('check')->twice()->andReturn(false, true);

        $request = Request::create('/login', 'GET');
        $response = $this->middleware->handle($request, function () {}, 'web', 'api');

        $this->assertEquals('http://localhost' . RouteServiceProvider::HOME, $response->getTargetUrl());
    }
}
