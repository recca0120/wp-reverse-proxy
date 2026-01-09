<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Middleware;

use Http\Client\Exception\NetworkException;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Middleware\Timeout;

class TimeoutTest extends TestCase
{
    public function test_it_passes_through_successful_requests()
    {
        $middleware = new Timeout(30);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $capturedRequest = null;
        $response = $middleware->process($request, function ($req) use (&$capturedRequest) {
            $capturedRequest = $req;

            return new Response(200, [], '{"data":"success"}');
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_it_adds_timeout_header_to_request()
    {
        $middleware = new Timeout(15);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $capturedRequest = null;
        $middleware->process($request, function ($req) use (&$capturedRequest) {
            $capturedRequest = $req;

            return new Response(200);
        });

        $this->assertEquals('15', $capturedRequest->getHeaderLine('X-Timeout'));
    }

    public function test_it_uses_default_timeout()
    {
        $middleware = new Timeout();
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $capturedRequest = null;
        $middleware->process($request, function ($req) use (&$capturedRequest) {
            $capturedRequest = $req;

            return new Response(200);
        });

        $this->assertEquals('30', $capturedRequest->getHeaderLine('X-Timeout'));
    }

    public function test_it_returns_504_on_timeout_exception()
    {
        $middleware = new Timeout(5);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $response = $middleware->process($request, function ($req) {
            throw new NetworkException('Connection timed out', new Request('GET', 'https://example.com'));
        });

        $this->assertEquals(504, $response->getStatusCode());
    }

    public function test_it_includes_timeout_info_in_504_response()
    {
        $middleware = new Timeout(5);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $response = $middleware->process($request, function ($req) {
            throw new NetworkException('Request timed out', new Request('GET', 'https://example.com'));
        });

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('Gateway Timeout', $body['error']);
        $this->assertEquals(504, $body['status']);
        $this->assertEquals(5, $body['timeout']);
    }

    public function test_it_rethrows_non_timeout_exceptions()
    {
        $middleware = new Timeout(30);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $this->expectException(NetworkException::class);

        $middleware->process($request, function ($req) {
            throw new NetworkException('Connection refused', new Request('GET', 'https://example.com'));
        });
    }

    public function test_it_recognizes_timed_out_message()
    {
        $middleware = new Timeout(5);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $response = $middleware->process($request, function ($req) {
            throw new NetworkException('Operation timed out after 5000 milliseconds', new Request('GET', 'https://example.com'));
        });

        $this->assertEquals(504, $response->getStatusCode());
    }

    public function test_it_has_correct_priority()
    {
        $middleware = new Timeout();

        $this->assertEquals(-60, $middleware->priority);
    }
}
