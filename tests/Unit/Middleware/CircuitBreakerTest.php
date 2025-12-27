<?php

namespace ReverseProxy\Tests\Unit\Middleware;

use Http\Client\Exception\NetworkException;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use ReverseProxy\Middleware\CircuitBreaker;

class CircuitBreakerTest extends TestCase
{
    /** @var CacheInterface&\PHPUnit\Framework\MockObject\MockObject */
    private $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = $this->createMock(CacheInterface::class);
    }

    public function test_it_allows_request_when_circuit_is_closed()
    {
        $this->cache->method('get')->willReturn(null);
        $this->cache->method('set')->willReturn(true);

        $middleware = new CircuitBreaker('test-service', 3, 60, [500, 503], $this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_it_returns_503_when_circuit_is_open()
    {
        $this->cache->method('get')->willReturn([
            'status' => CircuitBreaker::STATE_OPEN,
            'failures' => 5,
            'reset_at' => time() + 60,
        ]);

        $middleware = new CircuitBreaker('test-service', 3, 60, [500, 503], $this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertFalse($called);
        $this->assertEquals(503, $response->getStatusCode());
    }

    public function test_it_opens_circuit_after_threshold_failures()
    {
        $callCount = 0;
        $this->cache->method('get')->willReturnCallback(function () use (&$callCount) {
            return [
                'status' => CircuitBreaker::STATE_CLOSED,
                'failures' => 2, // Already 2 failures
                'reset_at' => 0,
            ];
        });

        $capturedState = null;
        $this->cache->method('set')->willReturnCallback(function ($key, $data) use (&$capturedState) {
            $capturedState = $data;

            return true;
        });

        $middleware = new CircuitBreaker('test-service', 3, 60, [500, 503], $this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $middleware->process($request, function ($req) {
            return new Response(503);
        });

        $this->assertEquals(CircuitBreaker::STATE_OPEN, $capturedState['status']);
    }

    public function test_it_resets_failure_count_on_success()
    {
        $this->cache->method('get')->willReturn([
            'status' => CircuitBreaker::STATE_CLOSED,
            'failures' => 2,
            'reset_at' => 0,
        ]);

        $capturedState = null;
        $this->cache->method('set')->willReturnCallback(function ($key, $data) use (&$capturedState) {
            $capturedState = $data;

            return true;
        });

        $middleware = new CircuitBreaker('test-service', 3, 60, [500, 503], $this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $middleware->process($request, function ($req) {
            return new Response(200);
        });

        $this->assertEquals(CircuitBreaker::STATE_CLOSED, $capturedState['status']);
        $this->assertEquals(0, $capturedState['failures']);
    }

    public function test_it_transitions_to_half_open_after_timeout()
    {
        $this->cache->method('get')->willReturn([
            'status' => CircuitBreaker::STATE_OPEN,
            'failures' => 5,
            'reset_at' => time() - 10, // Timeout passed
        ]);

        $capturedState = null;
        $this->cache->method('set')->willReturnCallback(function ($key, $data) use (&$capturedState) {
            $capturedState = $data;

            return true;
        });

        $middleware = new CircuitBreaker('test-service', 3, 60, [500, 503], $this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $called = false;
        $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertTrue($called);
    }

    public function test_it_counts_network_errors_as_failures()
    {
        $this->cache->method('get')->willReturn([
            'status' => CircuitBreaker::STATE_CLOSED,
            'failures' => 0,
            'reset_at' => 0,
        ]);

        $capturedState = null;
        $this->cache->method('set')->willReturnCallback(function ($key, $data) use (&$capturedState) {
            $capturedState = $data;

            return true;
        });

        $middleware = new CircuitBreaker('test-service', 3, 60, [500, 503], $this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        try {
            $middleware->process($request, function ($req) {
                throw new NetworkException('Connection failed', new Request('GET', 'https://example.com'));
            });
        } catch (NetworkException $e) {
            // Expected
        }

        $this->assertEquals(1, $capturedState['failures']);
    }

    public function test_it_uses_custom_failure_status_codes()
    {
        $this->cache->method('get')->willReturn(null);

        $capturedState = null;
        $this->cache->method('set')->willReturnCallback(function ($key, $data) use (&$capturedState) {
            $capturedState = $data;

            return true;
        });

        $middleware = new CircuitBreaker('test-service', 3, 60, [429], $this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $middleware->process($request, function ($req) {
            return new Response(429);
        });

        $this->assertEquals(1, $capturedState['failures']);
    }

    public function test_circuit_open_response_contains_service_name()
    {
        $this->cache->method('get')->willReturn([
            'status' => CircuitBreaker::STATE_OPEN,
            'failures' => 5,
            'reset_at' => time() + 60,
        ]);

        $middleware = new CircuitBreaker('my-api-service', 3, 60, [500, 503], $this->cache);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $response = $middleware->process($request, function ($req) {
            return new Response(200);
        });

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('my-api-service', $body['service']);
        $this->assertEquals('Circuit breaker is open', $body['error']);
    }
}
