<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Middleware\RateLimiting;
use Recca0120\ReverseProxy\Tests\Stubs\ArrayCache;

class RateLimitingTest extends TestCase
{
    /** @var ArrayCache */
    private $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new ArrayCache();
    }

    public function test_allows_request_within_limit()
    {
        $middleware = new RateLimiting(10, 60);
        $middleware->setCache($this->cache);
        $request = $this->createRequest();

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_adds_rate_limit_headers()
    {
        $middleware = new RateLimiting(10, 60);
        $middleware->setCache($this->cache);
        $request = $this->createRequest();

        $response = $middleware->process($request, function ($req) {
            return new Response(200);
        });

        $this->assertEquals('10', $response->getHeaderLine('X-RateLimit-Limit'));
        $this->assertTrue($response->hasHeader('X-RateLimit-Remaining'));
        $this->assertTrue($response->hasHeader('X-RateLimit-Reset'));
    }

    public function test_returns_429_when_limit_exceeded()
    {
        $cacheKey = 'rp_rate_' . md5('192.168.1.100');
        $this->cache->set($cacheKey, [
            'window_start' => time(),
            'count' => 10,
        ]);

        $middleware = new RateLimiting(10, 60);
        $middleware->setCache($this->cache);
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

    public function test_resets_counter_after_window_expires()
    {
        $cacheKey = 'rp_rate_' . md5('192.168.1.100');
        $oldWindowStart = time() - 120; // 2 minutes ago
        $this->cache->set($cacheKey, [
            'window_start' => $oldWindowStart,
            'count' => 100,
        ]);

        $middleware = new RateLimiting(10, 60);
        $middleware->setCache($this->cache);
        $request = $this->createRequest();

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_uses_custom_key_generator()
    {
        $middleware = new RateLimiting(10, 60, function ($request) {
            return 'custom-key';
        });
        $middleware->setCache($this->cache);
        $request = $this->createRequest();

        $middleware->process($request, function ($req) {
            return new Response(200);
        });

        $expectedKey = 'rp_rate_' . md5('custom-key');
        $this->assertTrue($this->cache->has($expectedKey));
    }

    public function test_returns_json_error_body_on_429()
    {
        $cacheKey = 'rp_rate_' . md5('192.168.1.100');
        $this->cache->set($cacheKey, [
            'window_start' => time(),
            'count' => 10,
        ]);

        $middleware = new RateLimiting(10, 60);
        $middleware->setCache($this->cache);
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
        $cacheKey = 'rp_rate_' . md5('192.168.1.100');
        $this->cache->set($cacheKey, [
            'window_start' => time(),
            'count' => 5,
        ]);

        $middleware = new RateLimiting(10, 60);
        $middleware->setCache($this->cache);
        $request = $this->createRequest();

        $response = $middleware->process($request, function ($req) {
            return new Response(200);
        });

        $this->assertEquals('4', $response->getHeaderLine('X-RateLimit-Remaining'));
    }

    private function createRequest(string $ip = '192.168.1.100'): ServerRequest
    {
        return new ServerRequest('GET', 'https://example.com/api/users', [], null, '1.1', ['REMOTE_ADDR' => $ip]);
    }
}
