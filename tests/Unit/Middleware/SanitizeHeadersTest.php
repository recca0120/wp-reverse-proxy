<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Middleware\SanitizeHeaders;

class SanitizeHeadersTest extends TestCase
{
    public function test_it_passes_request_to_next_middleware()
    {
        $middleware = new SanitizeHeaders;
        $request = new ServerRequest('GET', 'http://example.com');

        $response = $middleware->process($request, function ($req) {
            return new Response(200, [], 'response body');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('response body', (string) $response->getBody());
    }

    public function test_it_removes_br_from_accept_encoding()
    {
        $middleware = new SanitizeHeaders;
        $request = new ServerRequest('GET', 'http://example.com', [
            'Accept-Encoding' => 'gzip, deflate, br',
        ]);

        $capturedRequest = null;
        $middleware->process($request, function ($req) use (&$capturedRequest) {
            $capturedRequest = $req;

            return new Response(200);
        });

        $this->assertEquals('gzip, deflate', $capturedRequest->getHeaderLine('Accept-Encoding'));
    }

    public function test_it_removes_br_when_only_encoding()
    {
        $middleware = new SanitizeHeaders;
        $request = new ServerRequest('GET', 'http://example.com', [
            'Accept-Encoding' => 'br',
        ]);

        $capturedRequest = null;
        $middleware->process($request, function ($req) use (&$capturedRequest) {
            $capturedRequest = $req;

            return new Response(200);
        });

        $this->assertFalse($capturedRequest->hasHeader('Accept-Encoding'));
    }

    public function test_it_removes_br_at_beginning()
    {
        $middleware = new SanitizeHeaders;
        $request = new ServerRequest('GET', 'http://example.com', [
            'Accept-Encoding' => 'br, gzip, deflate',
        ]);

        $capturedRequest = null;
        $middleware->process($request, function ($req) use (&$capturedRequest) {
            $capturedRequest = $req;

            return new Response(200);
        });

        $this->assertEquals('gzip, deflate', $capturedRequest->getHeaderLine('Accept-Encoding'));
    }

    public function test_it_removes_br_in_middle()
    {
        $middleware = new SanitizeHeaders;
        $request = new ServerRequest('GET', 'http://example.com', [
            'Accept-Encoding' => 'gzip, br, deflate',
        ]);

        $capturedRequest = null;
        $middleware->process($request, function ($req) use (&$capturedRequest) {
            $capturedRequest = $req;

            return new Response(200);
        });

        $this->assertEquals('gzip, deflate', $capturedRequest->getHeaderLine('Accept-Encoding'));
    }

    public function test_it_preserves_other_encodings()
    {
        $middleware = new SanitizeHeaders;
        $request = new ServerRequest('GET', 'http://example.com', [
            'Accept-Encoding' => 'gzip, deflate',
        ]);

        $capturedRequest = null;
        $middleware->process($request, function ($req) use (&$capturedRequest) {
            $capturedRequest = $req;

            return new Response(200);
        });

        $this->assertEquals('gzip, deflate', $capturedRequest->getHeaderLine('Accept-Encoding'));
    }

    public function test_it_handles_missing_accept_encoding()
    {
        $middleware = new SanitizeHeaders;
        $request = new ServerRequest('GET', 'http://example.com');

        $capturedRequest = null;
        $middleware->process($request, function ($req) use (&$capturedRequest) {
            $capturedRequest = $req;

            return new Response(200);
        });

        $this->assertFalse($capturedRequest->hasHeader('Accept-Encoding'));
    }

    public function test_it_removes_hop_by_hop_headers_from_response()
    {
        $middleware = new SanitizeHeaders;
        $request = new ServerRequest('GET', 'http://example.com');

        $response = $middleware->process($request, function () {
            return new Response(200, [
                'Content-Type' => 'application/json',
                'Transfer-Encoding' => 'chunked',
                'Connection' => 'keep-alive',
                'Keep-Alive' => 'timeout=5',
            ]);
        });

        $this->assertTrue($response->hasHeader('Content-Type'));
        $this->assertFalse($response->hasHeader('Transfer-Encoding'));
        $this->assertFalse($response->hasHeader('Connection'));
        $this->assertFalse($response->hasHeader('Keep-Alive'));
    }

    public function test_it_removes_all_hop_by_hop_headers()
    {
        $middleware = new SanitizeHeaders;
        $request = new ServerRequest('GET', 'http://example.com');

        $response = $middleware->process($request, function () {
            return new Response(200, [
                'Transfer-Encoding' => 'chunked',
                'Connection' => 'keep-alive',
                'Keep-Alive' => 'timeout=5',
                'Proxy-Authenticate' => 'Basic',
                'Proxy-Authorization' => 'Basic xyz',
                'TE' => 'trailers',
                'Trailer' => 'Expires',
                'Upgrade' => 'websocket',
            ]);
        });

        $this->assertEmpty($response->getHeaders());
    }

    public function test_it_preserves_content_headers()
    {
        $middleware = new SanitizeHeaders;
        $request = new ServerRequest('GET', 'http://example.com');

        $response = $middleware->process($request, function () {
            return new Response(200, [
                'Content-Type' => 'text/html',
                'Content-Length' => '1234',
                'Cache-Control' => 'no-cache',
                'X-Custom-Header' => 'custom-value',
            ]);
        });

        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('1234', $response->getHeaderLine('Content-Length'));
        $this->assertEquals('no-cache', $response->getHeaderLine('Cache-Control'));
        $this->assertEquals('custom-value', $response->getHeaderLine('X-Custom-Header'));
    }

    public function test_it_removes_hop_by_hop_headers_case_insensitively()
    {
        $middleware = new SanitizeHeaders;
        $request = new ServerRequest('GET', 'http://example.com');

        $response = $middleware->process($request, function () {
            return new Response(200, [
                'TRANSFER-ENCODING' => 'chunked',
                'connection' => 'keep-alive',
                'Keep-Alive' => 'timeout=5',
            ]);
        });

        $this->assertEmpty($response->getHeaders());
    }

    public function test_it_has_high_priority()
    {
        $middleware = new SanitizeHeaders;

        $this->assertEquals(-1000, $middleware->priority);
    }
}
