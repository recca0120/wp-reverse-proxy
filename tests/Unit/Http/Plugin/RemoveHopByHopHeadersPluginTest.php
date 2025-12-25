<?php

namespace ReverseProxy\Tests\Unit\Http\Plugin;

use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ReverseProxy\Http\Plugin\RemoveHopByHopHeadersPlugin;

class RemoveHopByHopHeadersPluginTest extends TestCase
{
    public function test_it_removes_hop_by_hop_headers_from_response()
    {
        $plugin = new RemoveHopByHopHeadersPlugin;
        $response = new Response(200, [
            'Content-Type' => 'application/json',
            'Transfer-Encoding' => 'chunked',
            'Connection' => 'keep-alive',
            'Keep-Alive' => 'timeout=5',
        ]);

        $filtered = $plugin->filterResponse($response);

        $this->assertTrue($filtered->hasHeader('Content-Type'));
        $this->assertFalse($filtered->hasHeader('Transfer-Encoding'));
        $this->assertFalse($filtered->hasHeader('Connection'));
        $this->assertFalse($filtered->hasHeader('Keep-Alive'));
    }

    public function test_it_removes_all_hop_by_hop_headers()
    {
        $plugin = new RemoveHopByHopHeadersPlugin;
        $response = new Response(200, [
            'Transfer-Encoding' => 'chunked',
            'Connection' => 'keep-alive',
            'Keep-Alive' => 'timeout=5',
            'Proxy-Authenticate' => 'Basic',
            'Proxy-Authorization' => 'Basic xyz',
            'TE' => 'trailers',
            'Trailer' => 'Expires',
            'Upgrade' => 'websocket',
        ]);

        $filtered = $plugin->filterResponse($response);

        $this->assertEmpty($filtered->getHeaders());
    }

    public function test_it_preserves_content_headers()
    {
        $plugin = new RemoveHopByHopHeadersPlugin;
        $response = new Response(200, [
            'Content-Type' => 'text/html',
            'Content-Length' => '1234',
            'Cache-Control' => 'no-cache',
            'X-Custom-Header' => 'custom-value',
        ]);

        $filtered = $plugin->filterResponse($response);

        $this->assertEquals('text/html', $filtered->getHeaderLine('Content-Type'));
        $this->assertEquals('1234', $filtered->getHeaderLine('Content-Length'));
        $this->assertEquals('no-cache', $filtered->getHeaderLine('Cache-Control'));
        $this->assertEquals('custom-value', $filtered->getHeaderLine('X-Custom-Header'));
    }

    public function test_it_is_case_insensitive()
    {
        $plugin = new RemoveHopByHopHeadersPlugin;
        $response = new Response(200, [
            'TRANSFER-ENCODING' => 'chunked',
            'connection' => 'keep-alive',
            'Keep-Alive' => 'timeout=5',
        ]);

        $filtered = $plugin->filterResponse($response);

        $this->assertEmpty($filtered->getHeaders());
    }

    public function test_it_does_not_modify_request()
    {
        $plugin = new RemoveHopByHopHeadersPlugin;
        $request = new Request('GET', 'http://example.com', [
            'Accept' => 'application/json',
        ]);

        $filtered = $plugin->filterRequest($request);

        $this->assertSame($request, $filtered);
    }
}
