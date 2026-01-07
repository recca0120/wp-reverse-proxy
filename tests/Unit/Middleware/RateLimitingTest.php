<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Recca0120\ReverseProxy\Middleware\RateLimiting;

class RateLimitingTest extends TestCase
{
    /** @var CacheInterface&\PHPUnit\Framework\MockObject\MockObject */
    private $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = $this->createMock(CacheInterface::class);
    }

    private function createRequest(string $ip = '192.168.1.100'): ServerRequest
    {
        return new ServerRequest('GET', 'https://example.com/api/users', [], null, '1.1', ['REMOTE_ADDR' => $ip]);
    }

    public function test_it_allows_request_within_limit()
    {
        $this->cache->method('get')->willReturn(null);
        $this->cache->method('set')->willReturn(true);

        $middleware = new RateLimiting(10, 60, null, $this->cache);
        $request = $this->createRequest();

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_it_adds_rate_limit_headers()
    {
        $this->cache->method('get')->willReturn(null);
        $this->cache->method('set')->willReturn(true);

        $middleware = new RateLimiting(10, 60, null, $this->cache);
        $request = $this->createRequest();

        $response = $middleware->process($request, function ($req) {
            return new Response(200);
        });

        $this->assertEquals('10', $response->getHeaderLine('X-RateLimit-Limit'));
        $this->assertTrue($response->hasHeader('X-RateLimit-Remaining'));
        $this->assertTrue($response->hasHeader('X-RateLimit-Reset'));
    }

    public function test_it_returns_429_when_limit_exceeded()
    {
        $this->cache->method('get')->willReturn([
            'window_start' => time(),
            'count' => 10,
        ]);
        $this->cache->method('set')->willReturn(true);

        $middleware = new RateLimiting(10, 60, null, $this->cache);
        $request = $this->createRequest();

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertFalse($called);
        $this->assertEquals(429, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Retry-After'));
    }

    public function test_it_resets_counter_after_window_expires()
    {
        $oldWindowStart = time() - 120; // 2 minutes ago
        $this->cache->method('get')->willReturn([
            'window_start' => $oldWindowStart,
            'count' => 100,
        ]);
        $this->cache->method('set')->willReturn(true);

        $middleware = new RateLimiting(10, 60, null, $this->cache);
        $request = $this->createRequest();

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_it_uses_custom_key_generator()
    {
        $this->cache->method('get')->willReturn(null);
        $this->cache->expects($this->once())
            ->method('set')
            ->with($this->equalTo(md5('custom-key')));

        $middleware = new RateLimiting(10, 60, function ($request) {
            return 'custom-key';
        }, $this->cache);
        $request = $this->createRequest();

        $middleware->process($request, function ($req) {
            return new Response(200);
        });
    }

    public function test_it_returns_json_error_body_on_429()
    {
        $this->cache->method('get')->willReturn([
            'window_start' => time(),
            'count' => 10,
        ]);
        $this->cache->method('set')->willReturn(true);

        $middleware = new RateLimiting(10, 60, null, $this->cache);
        $request = $this->createRequest();

        $response = $middleware->process($request, function ($req) {
            return new Response(200);
        });

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('Too Many Requests', $body['error']);
        $this->assertEquals(429, $body['status']);
        $this->assertArrayHasKey('retry_after', $body);
    }

    public function test_remaining_count_decreases_with_each_request()
    {
        $this->cache->method('get')->willReturn([
            'window_start' => time(),
            'count' => 5,
        ]);
        $this->cache->method('set')->willReturn(true);

        $middleware = new RateLimiting(10, 60, null, $this->cache);
        $request = $this->createRequest();

        $response = $middleware->process($request, function ($req) {
            return new Response(200);
        });

        $this->assertEquals('4', $response->getHeaderLine('X-RateLimit-Remaining'));
    }
}
