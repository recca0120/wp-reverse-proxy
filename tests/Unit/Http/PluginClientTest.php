<?php

namespace ReverseProxy\Tests\Unit\Http;

use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReverseProxy\Http\PluginClient;
use ReverseProxy\Http\PluginInterface;

class PluginClientTest extends TestCase
{
    public function test_it_sends_request_without_plugins()
    {
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn(new Response(200, [], 'response body'));

        $client = new PluginClient($mockClient);
        $response = $client->sendRequest(new Request('GET', 'http://example.com'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('response body', (string) $response->getBody());
    }

    public function test_it_applies_plugins_to_request()
    {
        $plugin = new class implements PluginInterface
        {
            public function filterRequest(RequestInterface $request): RequestInterface
            {
                return $request->withHeader('X-Plugin', 'applied');
            }

            public function filterResponse(ResponseInterface $response): ResponseInterface
            {
                return $response;
            }
        };

        $capturedRequest = null;
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->expects($this->once())
            ->method('sendRequest')
            ->willReturnCallback(function ($request) use (&$capturedRequest) {
                $capturedRequest = $request;

                return new Response(200);
            });

        $client = new PluginClient($mockClient, [$plugin]);
        $client->sendRequest(new Request('GET', 'http://example.com'));

        $this->assertEquals('applied', $capturedRequest->getHeaderLine('X-Plugin'));
    }

    public function test_it_applies_plugins_to_response()
    {
        $plugin = new class implements PluginInterface
        {
            public function filterRequest(RequestInterface $request): RequestInterface
            {
                return $request;
            }

            public function filterResponse(ResponseInterface $response): ResponseInterface
            {
                return $response->withHeader('X-Filtered', 'yes');
            }
        };

        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('sendRequest')->willReturn(new Response(200));

        $client = new PluginClient($mockClient, [$plugin]);
        $response = $client->sendRequest(new Request('GET', 'http://example.com'));

        $this->assertEquals('yes', $response->getHeaderLine('X-Filtered'));
    }

    public function test_it_applies_multiple_plugins_in_order()
    {
        $plugin1 = new class implements PluginInterface
        {
            public function filterRequest(RequestInterface $request): RequestInterface
            {
                return $request->withHeader('X-Order', $request->getHeaderLine('X-Order').'1');
            }

            public function filterResponse(ResponseInterface $response): ResponseInterface
            {
                return $response->withHeader('X-Order', $response->getHeaderLine('X-Order').'1');
            }
        };

        $plugin2 = new class implements PluginInterface
        {
            public function filterRequest(RequestInterface $request): RequestInterface
            {
                return $request->withHeader('X-Order', $request->getHeaderLine('X-Order').'2');
            }

            public function filterResponse(ResponseInterface $response): ResponseInterface
            {
                return $response->withHeader('X-Order', $response->getHeaderLine('X-Order').'2');
            }
        };

        $capturedRequest = null;
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('sendRequest')
            ->willReturnCallback(function ($request) use (&$capturedRequest) {
                $capturedRequest = $request;

                return new Response(200);
            });

        $client = new PluginClient($mockClient, [$plugin1, $plugin2]);
        $response = $client->sendRequest(new Request('GET', 'http://example.com'));

        // Request: plugin1 -> plugin2 (order: 12)
        $this->assertEquals('12', $capturedRequest->getHeaderLine('X-Order'));

        // Response: plugin2 -> plugin1 (reverse order: 21)
        $this->assertEquals('21', $response->getHeaderLine('X-Order'));
    }
}
