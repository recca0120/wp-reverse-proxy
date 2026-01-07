<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Recca0120\ReverseProxy\Middleware\Caching;

class CachingTest extends TestCase
{
    /** @var CacheInterface&\PHPUnit\Framework\MockObject\MockObject */
    private $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = $this->createMock(CacheInterface::class);
    }

    public function test_it_returns_cached_response_on_hit()
    {
        $cachedData = [
            'status' => 200,
            'headers' => ['Content-Type' => ['application/json']],
            'body' => '{"cached":true}',
            'protocol' => '1.1',
            'reason' => 'OK',
        ];
        $this->cache->method('get')->willReturn($cachedData);

        $middleware = new Caching(300, $this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertFalse($called);
        $this->assertEquals('HIT', $response->getHeaderLine('X-Cache'));
        $this->assertEquals('{"cached":true}', (string) $response->getBody());
    }

    public function test_it_calls_next_on_cache_miss()
    {
        $this->cache->method('get')->willReturn(null);
        $this->cache->method('set')->willReturn(true);

        $middleware = new Caching(300, $this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200, [], '{"fresh":true}');
        });

        $this->assertTrue($called);
        $this->assertEquals('MISS', $response->getHeaderLine('X-Cache'));
    }

    public function test_it_caches_200_responses()
    {
        $this->cache->method('get')->willReturn(null);
        $this->cache->expects($this->once())->method('set');

        $middleware = new Caching(300, $this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $middleware->process($request, function ($req) {
            return new Response(200, [], '{"data":"test"}');
        });
    }

    public function test_it_does_not_cache_non_200_responses()
    {
        $this->cache->method('get')->willReturn(null);
        $this->cache->expects($this->never())->method('set');

        $middleware = new Caching(300, $this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $middleware->process($request, function ($req) {
            return new Response(404);
        });
    }

    public function test_it_does_not_cache_post_requests()
    {
        $this->cache->expects($this->never())->method('get');
        $this->cache->expects($this->never())->method('set');

        $middleware = new Caching(300, $this->cache);
        $request = new ServerRequest('POST', 'https://example.com/api/users');

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertTrue($called);
        $this->assertFalse($response->hasHeader('X-Cache'));
    }

    public function test_it_does_not_cache_responses_with_no_cache_header()
    {
        $this->cache->method('get')->willReturn(null);
        $this->cache->expects($this->never())->method('set');

        $middleware = new Caching(300, $this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $middleware->process($request, function ($req) {
            return new Response(200, ['Cache-Control' => 'no-cache']);
        });
    }

    public function test_it_does_not_cache_responses_with_no_store_header()
    {
        $this->cache->method('get')->willReturn(null);
        $this->cache->expects($this->never())->method('set');

        $middleware = new Caching(300, $this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $middleware->process($request, function ($req) {
            return new Response(200, ['Cache-Control' => 'no-store']);
        });
    }

    public function test_it_does_not_cache_private_responses()
    {
        $this->cache->method('get')->willReturn(null);
        $this->cache->expects($this->never())->method('set');

        $middleware = new Caching(300, $this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $middleware->process($request, function ($req) {
            return new Response(200, ['Cache-Control' => 'private']);
        });
    }

    public function test_it_caches_head_requests()
    {
        $this->cache->method('get')->willReturn(null);
        $this->cache->expects($this->once())->method('set');

        $middleware = new Caching(300, $this->cache);
        $request = new ServerRequest('HEAD', 'https://example.com/api/users');

        $middleware->process($request, function ($req) {
            return new Response(200);
        });
    }

    public function test_it_uses_custom_ttl()
    {
        $this->cache->method('get')->willReturn(null);
        $this->cache->expects($this->once())
            ->method('set')
            ->with($this->anything(), $this->anything(), $this->equalTo(600));

        $middleware = new Caching(600, $this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $middleware->process($request, function ($req) {
            return new Response(200);
        });
    }
}
