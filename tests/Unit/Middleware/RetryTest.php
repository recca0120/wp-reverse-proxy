<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Middleware;

use Http\Client\Exception\NetworkException;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Middleware\Retry;

class RetryTest extends TestCase
{
    public function test_it_returns_immediately_on_success()
    {
        $middleware = new Retry(3);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $callCount = 0;
        $response = $middleware->process($request, function ($req) use (&$callCount) {
            $callCount++;

            return new Response(200);
        });

        $this->assertEquals(1, $callCount);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_it_retries_on_503_status()
    {
        $middleware = new Retry(3);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $callCount = 0;
        $response = $middleware->process($request, function ($req) use (&$callCount) {
            $callCount++;
            if ($callCount < 3) {
                return new Response(503);
            }

            return new Response(200);
        });

        $this->assertEquals(3, $callCount);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_it_retries_on_network_exception()
    {
        $middleware = new Retry(3);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $callCount = 0;
        $response = $middleware->process($request, function ($req) use (&$callCount) {
            $callCount++;
            if ($callCount < 3) {
                throw new NetworkException('Connection failed', new Request('GET', 'https://example.com'));
            }

            return new Response(200);
        });

        $this->assertEquals(3, $callCount);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_it_does_not_retry_on_4xx_errors()
    {
        $middleware = new Retry(3);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $callCount = 0;
        $response = $middleware->process($request, function ($req) use (&$callCount) {
            $callCount++;

            return new Response(404);
        });

        $this->assertEquals(1, $callCount);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_it_does_not_retry_post_requests_by_default()
    {
        $middleware = new Retry(3);
        $request = new ServerRequest('POST', 'https://example.com/api/users');

        $callCount = 0;
        $response = $middleware->process($request, function ($req) use (&$callCount) {
            $callCount++;

            return new Response(503);
        });

        $this->assertEquals(1, $callCount);
        $this->assertEquals(503, $response->getStatusCode());
    }

    public function test_it_can_retry_custom_methods()
    {
        $middleware = new Retry(3, ['GET', 'POST', 'PUT']);
        $request = new ServerRequest('POST', 'https://example.com/api/users');

        $callCount = 0;
        $response = $middleware->process($request, function ($req) use (&$callCount) {
            $callCount++;
            if ($callCount < 2) {
                return new Response(503);
            }

            return new Response(200);
        });

        $this->assertEquals(2, $callCount);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_it_returns_last_response_after_max_retries()
    {
        $middleware = new Retry(3);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $callCount = 0;
        $response = $middleware->process($request, function ($req) use (&$callCount) {
            $callCount++;

            return new Response(503, [], 'Service Unavailable');
        });

        $this->assertEquals(3, $callCount);
        $this->assertEquals(503, $response->getStatusCode());
    }

    public function test_it_throws_after_max_retries_on_network_error()
    {
        $middleware = new Retry(3);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $this->expectException(NetworkException::class);

        $middleware->process($request, function ($req) {
            throw new NetworkException('Connection failed', new Request('GET', 'https://example.com'));
        });
    }

    public function test_it_can_use_custom_retryable_status_codes()
    {
        $middleware = new Retry(3, ['GET'], [500, 502]);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $callCount = 0;
        $response = $middleware->process($request, function ($req) use (&$callCount) {
            $callCount++;

            return new Response(503); // 503 is not in the list
        });

        $this->assertEquals(1, $callCount);
        $this->assertEquals(503, $response->getStatusCode());
    }

    public function test_method_matching_is_case_insensitive()
    {
        $middleware = new Retry(3, ['get', 'post']);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $callCount = 0;
        $response = $middleware->process($request, function ($req) use (&$callCount) {
            $callCount++;
            if ($callCount < 2) {
                return new Response(503);
            }

            return new Response(200);
        });

        $this->assertEquals(2, $callCount);
    }
}
