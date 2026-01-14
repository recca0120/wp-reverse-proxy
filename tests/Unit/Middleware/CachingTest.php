<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Middleware\Caching;
use Recca0120\ReverseProxy\Tests\Stubs\ArrayCache;

class CachingTest extends TestCase
{
    /** @var ArrayCache */
    private $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new ArrayCache();
    }

    public function test_returns_cached_response_on_hit()
    {
        $uri = 'https://example.com/api/users';
        $cacheKey = 'rp_cache_' . md5($uri);
        $this->cache->set($cacheKey, [
            'status' => 200,
            'headers' => ['Content-Type' => ['application/json']],
            'body' => '{"cached":true}',
            'protocol' => '1.1',
            'reason' => 'OK',
        ]);

        $middleware = new Caching(300);
        $middleware->setCache($this->cache);
        $request = new ServerRequest('GET', $uri);

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertFalse($called);
        $this->assertEquals('HIT', $response->getHeaderLine('X-Cache'));
        $this->assertEquals('{"cached":true}', (string) $response->getBody());
    }

    public function test_calls_next_on_cache_miss()
    {
        $middleware = new Caching(300);
        $middleware->setCache($this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200, [], '{"fresh":true}');
        });

        $this->assertTrue($called);
        $this->assertEquals('MISS', $response->getHeaderLine('X-Cache'));
    }

    public function test_caches_200_responses()
    {
        $middleware = new Caching(300);
        $middleware->setCache($this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $middleware->process($request, function ($req) {
            return new Response(200, [], '{"data":"test"}');
        });

        $this->assertNotEmpty($this->cache->all());
    }

    public function test_does_not_cache_non_200_responses()
    {
        $middleware = new Caching(300);
        $middleware->setCache($this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $middleware->process($request, function ($req) {
            return new Response(404);
        });

        $this->assertEmpty($this->cache->all());
    }

    public function test_does_not_cache_post_requests()
    {
        $middleware = new Caching(300);
        $middleware->setCache($this->cache);
        $request = new ServerRequest('POST', 'https://example.com/api/users');

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertTrue($called);
        $this->assertFalse($response->hasHeader('X-Cache'));
        $this->assertEmpty($this->cache->all());
    }

    public function test_does_not_cache_responses_with_no_cache_header()
    {
        $middleware = new Caching(300);
        $middleware->setCache($this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $middleware->process($request, function ($req) {
            return new Response(200, ['Cache-Control' => 'no-cache']);
        });

        $this->assertEmpty($this->cache->all());
    }

    public function test_does_not_cache_responses_with_no_store_header()
    {
        $middleware = new Caching(300);
        $middleware->setCache($this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $middleware->process($request, function ($req) {
            return new Response(200, ['Cache-Control' => 'no-store']);
        });

        $this->assertEmpty($this->cache->all());
    }

    public function test_does_not_cache_private_responses()
    {
        $middleware = new Caching(300);
        $middleware->setCache($this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $middleware->process($request, function ($req) {
            return new Response(200, ['Cache-Control' => 'private']);
        });

        $this->assertEmpty($this->cache->all());
    }

    public function test_caches_head_requests()
    {
        $middleware = new Caching(300);
        $middleware->setCache($this->cache);
        $request = new ServerRequest('HEAD', 'https://example.com/api/users');

        $middleware->process($request, function ($req) {
            return new Response(200);
        });

        $this->assertNotEmpty($this->cache->all());
    }

    public function test_uses_custom_ttl()
    {
        $middleware = new Caching(600);
        $middleware->setCache($this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $middleware->process($request, function ($req) {
            return new Response(200);
        });

        $this->assertNotEmpty($this->cache->all());
    }
}
