<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Middleware;

use Http\Client\Exception\NetworkException;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Middleware\CircuitBreaker;
use Recca0120\ReverseProxy\Tests\Stubs\ArrayCache;

class CircuitBreakerTest extends TestCase
{
    /** @var ArrayCache */
    private $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new ArrayCache();
    }

    public function test_allows_request_when_circuit_is_closed()
    {
        $middleware = new CircuitBreaker('test-service', 3, 60, [500, 503]);
        $middleware->setCache($this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_returns_503_when_circuit_is_open()
    {
        $cacheKey = 'rp_cb_' . md5('test-service');
        $this->cache->set($cacheKey, [
            'status' => CircuitBreaker::STATE_OPEN,
            'failures' => 5,
            'reset_at' => time() + 60,
        ]);

        $middleware = new CircuitBreaker('test-service', 3, 60, [500, 503]);
        $middleware->setCache($this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertFalse($called);
        $this->assertEquals(503, $response->getStatusCode());
    }

    public function test_opens_circuit_after_threshold_failures()
    {
        $cacheKey = 'rp_cb_' . md5('test-service');
        $this->cache->set($cacheKey, [
            'status' => CircuitBreaker::STATE_CLOSED,
            'failures' => 2, // Already 2 failures
            'reset_at' => 0,
        ]);

        $middleware = new CircuitBreaker('test-service', 3, 60, [500, 503]);
        $middleware->setCache($this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $middleware->process($request, function ($req) {
            return new Response(503);
        });

        $cachedState = $this->cache->get($cacheKey);
        $this->assertEquals(CircuitBreaker::STATE_OPEN, $cachedState['status']);
    }

    public function test_resets_failure_count_on_success()
    {
        $cacheKey = 'rp_cb_' . md5('test-service');
        $this->cache->set($cacheKey, [
            'status' => CircuitBreaker::STATE_CLOSED,
            'failures' => 2,
            'reset_at' => 0,
        ]);

        $middleware = new CircuitBreaker('test-service', 3, 60, [500, 503]);
        $middleware->setCache($this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $middleware->process($request, function ($req) {
            return new Response(200);
        });

        $cachedState = $this->cache->get($cacheKey);
        $this->assertEquals(CircuitBreaker::STATE_CLOSED, $cachedState['status']);
        $this->assertEquals(0, $cachedState['failures']);
    }

    public function test_transitions_to_half_open_after_timeout()
    {
        $cacheKey = 'rp_cb_' . md5('test-service');
        $this->cache->set($cacheKey, [
            'status' => CircuitBreaker::STATE_OPEN,
            'failures' => 5,
            'reset_at' => time() - 10, // Timeout passed
        ]);

        $middleware = new CircuitBreaker('test-service', 3, 60, [500, 503]);
        $middleware->setCache($this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $called = false;
        $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertTrue($called);
    }

    public function test_counts_network_errors_as_failures()
    {
        $cacheKey = 'rp_cb_' . md5('test-service');
        $this->cache->set($cacheKey, [
            'status' => CircuitBreaker::STATE_CLOSED,
            'failures' => 0,
            'reset_at' => 0,
        ]);

        $middleware = new CircuitBreaker('test-service', 3, 60, [500, 503]);
        $middleware->setCache($this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        try {
            $middleware->process($request, function ($req) {
                throw new NetworkException('Connection failed', new Request('GET', 'https://example.com'));
            });
        } catch (NetworkException $e) {
            // Expected
        }

        $cachedState = $this->cache->get($cacheKey);
        $this->assertEquals(1, $cachedState['failures']);
    }

    public function test_uses_custom_failure_status_codes()
    {
        $middleware = new CircuitBreaker('test-service', 3, 60, [429]);
        $middleware->setCache($this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $middleware->process($request, function ($req) {
            return new Response(429);
        });

        $cacheKey = 'rp_cb_' . md5('test-service');
        $cachedState = $this->cache->get($cacheKey);
        $this->assertEquals(1, $cachedState['failures']);
    }

    public function test_circuit_open_response_contains_service_name()
    {
        $cacheKey = 'rp_cb_' . md5('my-api-service');
        $this->cache->set($cacheKey, [
            'status' => CircuitBreaker::STATE_OPEN,
            'failures' => 5,
            'reset_at' => time() + 60,
        ]);

        $middleware = new CircuitBreaker('my-api-service', 3, 60, [500, 503]);
        $middleware->setCache($this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $response = $middleware->process($request, function ($req) {
            return new Response(200);
        });

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('my-api-service', $body['service']);
        $this->assertEquals('Circuit breaker is open', $body['error']);
    }
}
