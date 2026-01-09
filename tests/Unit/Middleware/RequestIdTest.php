<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Middleware\RequestId;
use Yoast\PHPUnitPolyfills\Polyfills\AssertionRenames;

class RequestIdTest extends TestCase
{
    use AssertionRenames;

    public function test_it_generates_request_id_when_not_present()
    {
        $middleware = new RequestId();
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $capturedRequest = null;
        $response = $middleware->process($request, function ($req) use (&$capturedRequest) {
            $capturedRequest = $req;

            return new Response(200);
        });

        $this->assertTrue($capturedRequest->hasHeader('X-Request-ID'));
        $this->assertTrue($response->hasHeader('X-Request-ID'));
        $this->assertEquals(
            $capturedRequest->getHeaderLine('X-Request-ID'),
            $response->getHeaderLine('X-Request-ID')
        );
    }

    public function test_it_preserves_existing_request_id()
    {
        $middleware = new RequestId();
        $existingId = 'existing-request-id-123';
        $request = (new ServerRequest('GET', 'https://example.com/api/users'))
            ->withHeader('X-Request-ID', $existingId);

        $capturedRequest = null;
        $response = $middleware->process($request, function ($req) use (&$capturedRequest) {
            $capturedRequest = $req;

            return new Response(200);
        });

        $this->assertEquals($existingId, $capturedRequest->getHeaderLine('X-Request-ID'));
        $this->assertEquals($existingId, $response->getHeaderLine('X-Request-ID'));
    }

    public function test_it_uses_custom_header_name()
    {
        $middleware = new RequestId('X-Correlation-ID');
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $capturedRequest = null;
        $response = $middleware->process($request, function ($req) use (&$capturedRequest) {
            $capturedRequest = $req;

            return new Response(200);
        });

        $this->assertTrue($capturedRequest->hasHeader('X-Correlation-ID'));
        $this->assertTrue($response->hasHeader('X-Correlation-ID'));
        $this->assertFalse($response->hasHeader('X-Request-ID'));
    }

    public function test_generated_id_is_uuid_v4_format()
    {
        $middleware = new RequestId();
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $response = $middleware->process($request, function ($req) {
            return new Response(200);
        });

        $requestId = $response->getHeaderLine('X-Request-ID');

        // UUID v4 格式驗證
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $requestId
        );
    }

    public function test_each_request_gets_unique_id()
    {
        $middleware = new RequestId();

        $ids = [];
        for ($i = 0; $i < 10; $i++) {
            $request = new ServerRequest('GET', 'https://example.com/api/users');
            $response = $middleware->process($request, function ($req) {
                return new Response(200);
            });
            $ids[] = $response->getHeaderLine('X-Request-ID');
        }

        $this->assertEquals(count($ids), count(array_unique($ids)));
    }
}
