<?php

namespace ReverseProxy\Tests\Unit\WordPress;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReverseProxy\ReverseProxy;
use ReverseProxy\Rule;
use ReverseProxy\WordPress\Plugin;
use ReverseProxy\WordPress\ServerRequestFactory;

class PluginTest extends TestCase
{
    public function test_it_does_nothing_when_no_rules_match()
    {
        // Given: ReverseProxy returns null (no match)
        $reverseProxy = $this->createMock(ReverseProxy::class);
        $reverseProxy->method('handle')->willReturn(null);

        $request = new ServerRequest('GET', '/about');
        $serverRequestFactory = $this->createMock(ServerRequestFactory::class);
        $serverRequestFactory->method('createFromGlobals')->willReturn($request);

        $plugin = new Plugin($reverseProxy, $serverRequestFactory);

        // When: handle is called
        $result = $plugin->handle([]);

        // Then: should return null
        $this->assertNull($result);
    }

    public function test_it_returns_response_when_rule_matches()
    {
        // Given: ReverseProxy returns a response
        $expectedResponse = new Response(200, [], '{"message":"hello"}');

        $reverseProxy = $this->createMock(ReverseProxy::class);
        $reverseProxy->method('handle')->willReturn($expectedResponse);

        $request = new ServerRequest('GET', '/api/users');
        $serverRequestFactory = $this->createMock(ServerRequestFactory::class);
        $serverRequestFactory->method('createFromGlobals')->willReturn($request);

        $plugin = new Plugin($reverseProxy, $serverRequestFactory);

        // When: handle is called with rules
        $rules = [new Rule('/api/*', 'https://backend.example.com')];
        $result = $plugin->handle($rules);

        // Then: should return the response
        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals('{"message":"hello"}', (string) $result->getBody());
    }

    public function test_it_passes_rules_to_reverse_proxy()
    {
        // Given: ReverseProxy that captures the rules
        $capturedRules = null;
        $reverseProxy = $this->createMock(ReverseProxy::class);
        $reverseProxy->method('handle')
            ->willReturnCallback(function ($request, $rules) use (&$capturedRules) {
                $capturedRules = $rules;
                return null;
            });

        $request = new ServerRequest('GET', '/api/users');
        $serverRequestFactory = $this->createMock(ServerRequestFactory::class);
        $serverRequestFactory->method('createFromGlobals')->willReturn($request);

        $plugin = new Plugin($reverseProxy, $serverRequestFactory);

        // When: handle is called with specific rules
        $rules = [
            new Rule('/api/*', 'https://backend.example.com'),
            new Rule('/v2/*', 'https://v2.example.com'),
        ];
        $plugin->handle($rules);

        // Then: rules should be passed to ReverseProxy
        $this->assertCount(2, $capturedRules);
        $this->assertSame($rules, $capturedRules);
    }

    public function test_it_passes_server_request_to_reverse_proxy()
    {
        // Given: ReverseProxy that captures the request
        $capturedRequest = null;
        $reverseProxy = $this->createMock(ReverseProxy::class);
        $reverseProxy->method('handle')
            ->willReturnCallback(function ($request, $rules) use (&$capturedRequest) {
                $capturedRequest = $request;
                return null;
            });

        $expectedRequest = new ServerRequest('POST', '/api/users');
        $serverRequestFactory = $this->createMock(ServerRequestFactory::class);
        $serverRequestFactory->method('createFromGlobals')->willReturn($expectedRequest);

        $plugin = new Plugin($reverseProxy, $serverRequestFactory);

        // When: handle is called
        $plugin->handle([]);

        // Then: request from factory should be passed to ReverseProxy
        $this->assertSame($expectedRequest, $capturedRequest);
    }

    public function test_emit_outputs_response_body()
    {
        // Given: a response with body
        $response = new Response(200, [], '{"message":"hello"}');

        $reverseProxy = $this->createMock(ReverseProxy::class);
        $serverRequestFactory = $this->createMock(ServerRequestFactory::class);

        $plugin = new Plugin($reverseProxy, $serverRequestFactory);

        // When: emit is called
        ob_start();
        $plugin->emit($response);
        $output = ob_get_clean();

        // Then: body should be output
        $this->assertEquals('{"message":"hello"}', $output);
    }

    public function test_create_returns_plugin_instance()
    {
        // When: create is called
        $plugin = Plugin::create();

        // Then: should return Plugin instance
        $this->assertInstanceOf(Plugin::class, $plugin);
    }
}
