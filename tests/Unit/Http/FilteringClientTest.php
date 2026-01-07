<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Http;

use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Recca0120\ReverseProxy\Http\FilteringClient;

class FilteringClientTest extends TestCase
{
    public function test_it_sends_request_to_underlying_client()
    {
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn(new Response(200, [], 'response body'));

        $client = new FilteringClient($mockClient);
        $response = $client->sendRequest(new Request('GET', 'http://example.com'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('response body', (string) $response->getBody());
    }

    public function test_it_removes_br_from_accept_encoding()
    {
        $capturedRequest = null;
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('sendRequest')
            ->willReturnCallback(function ($request) use (&$capturedRequest) {
                $capturedRequest = $request;

                return new Response(200);
            });

        $client = new FilteringClient($mockClient);
        $client->sendRequest(new Request('GET', 'http://example.com', [
            'Accept-Encoding' => 'gzip, deflate, br',
        ]));

        $this->assertEquals('gzip, deflate', $capturedRequest->getHeaderLine('Accept-Encoding'));
    }

    public function test_it_removes_br_when_only_encoding()
    {
        $capturedRequest = null;
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('sendRequest')
            ->willReturnCallback(function ($request) use (&$capturedRequest) {
                $capturedRequest = $request;

                return new Response(200);
            });

        $client = new FilteringClient($mockClient);
        $client->sendRequest(new Request('GET', 'http://example.com', [
            'Accept-Encoding' => 'br',
        ]));

        $this->assertFalse($capturedRequest->hasHeader('Accept-Encoding'));
    }

    public function test_it_removes_br_at_beginning()
    {
        $capturedRequest = null;
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('sendRequest')
            ->willReturnCallback(function ($request) use (&$capturedRequest) {
                $capturedRequest = $request;

                return new Response(200);
            });

        $client = new FilteringClient($mockClient);
        $client->sendRequest(new Request('GET', 'http://example.com', [
            'Accept-Encoding' => 'br, gzip, deflate',
        ]));

        $this->assertEquals('gzip, deflate', $capturedRequest->getHeaderLine('Accept-Encoding'));
    }

    public function test_it_removes_br_in_middle()
    {
        $capturedRequest = null;
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('sendRequest')
            ->willReturnCallback(function ($request) use (&$capturedRequest) {
                $capturedRequest = $request;

                return new Response(200);
            });

        $client = new FilteringClient($mockClient);
        $client->sendRequest(new Request('GET', 'http://example.com', [
            'Accept-Encoding' => 'gzip, br, deflate',
        ]));

        $this->assertEquals('gzip, deflate', $capturedRequest->getHeaderLine('Accept-Encoding'));
    }

    public function test_it_preserves_other_encodings()
    {
        $capturedRequest = null;
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('sendRequest')
            ->willReturnCallback(function ($request) use (&$capturedRequest) {
                $capturedRequest = $request;

                return new Response(200);
            });

        $client = new FilteringClient($mockClient);
        $client->sendRequest(new Request('GET', 'http://example.com', [
            'Accept-Encoding' => 'gzip, deflate',
        ]));

        $this->assertEquals('gzip, deflate', $capturedRequest->getHeaderLine('Accept-Encoding'));
    }

    public function test_it_handles_missing_accept_encoding()
    {
        $capturedRequest = null;
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('sendRequest')
            ->willReturnCallback(function ($request) use (&$capturedRequest) {
                $capturedRequest = $request;

                return new Response(200);
            });

        $client = new FilteringClient($mockClient);
        $client->sendRequest(new Request('GET', 'http://example.com'));

        $this->assertFalse($capturedRequest->hasHeader('Accept-Encoding'));
    }

    public function test_it_removes_hop_by_hop_headers_from_response()
    {
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('sendRequest')
            ->willReturn(new Response(200, [
                'Content-Type' => 'application/json',
                'Transfer-Encoding' => 'chunked',
                'Connection' => 'keep-alive',
                'Keep-Alive' => 'timeout=5',
            ]));

        $client = new FilteringClient($mockClient);
        $response = $client->sendRequest(new Request('GET', 'http://example.com'));

        $this->assertTrue($response->hasHeader('Content-Type'));
        $this->assertFalse($response->hasHeader('Transfer-Encoding'));
        $this->assertFalse($response->hasHeader('Connection'));
        $this->assertFalse($response->hasHeader('Keep-Alive'));
    }

    public function test_it_removes_all_hop_by_hop_headers()
    {
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('sendRequest')
            ->willReturn(new Response(200, [
                'Transfer-Encoding' => 'chunked',
                'Connection' => 'keep-alive',
                'Keep-Alive' => 'timeout=5',
                'Proxy-Authenticate' => 'Basic',
                'Proxy-Authorization' => 'Basic xyz',
                'TE' => 'trailers',
                'Trailer' => 'Expires',
                'Upgrade' => 'websocket',
            ]));

        $client = new FilteringClient($mockClient);
        $response = $client->sendRequest(new Request('GET', 'http://example.com'));

        $this->assertEmpty($response->getHeaders());
    }

    public function test_it_preserves_content_headers()
    {
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('sendRequest')
            ->willReturn(new Response(200, [
                'Content-Type' => 'text/html',
                'Content-Length' => '1234',
                'Cache-Control' => 'no-cache',
                'X-Custom-Header' => 'custom-value',
            ]));

        $client = new FilteringClient($mockClient);
        $response = $client->sendRequest(new Request('GET', 'http://example.com'));

        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('1234', $response->getHeaderLine('Content-Length'));
        $this->assertEquals('no-cache', $response->getHeaderLine('Cache-Control'));
        $this->assertEquals('custom-value', $response->getHeaderLine('X-Custom-Header'));
    }

    public function test_it_removes_hop_by_hop_headers_case_insensitively()
    {
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('sendRequest')
            ->willReturn(new Response(200, [
                'TRANSFER-ENCODING' => 'chunked',
                'connection' => 'keep-alive',
                'Keep-Alive' => 'timeout=5',
            ]));

        $client = new FilteringClient($mockClient);
        $response = $client->sendRequest(new Request('GET', 'http://example.com'));

        $this->assertEmpty($response->getHeaders());
    }
}
