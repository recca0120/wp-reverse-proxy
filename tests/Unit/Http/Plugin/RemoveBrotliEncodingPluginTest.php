<?php

namespace ReverseProxy\Tests\Unit\Http\Plugin;

use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ReverseProxy\Http\Plugin\RemoveBrotliEncodingPlugin;

class RemoveBrotliEncodingPluginTest extends TestCase
{
    public function test_it_removes_br_from_accept_encoding()
    {
        $plugin = new RemoveBrotliEncodingPlugin;
        $request = new Request('GET', 'http://example.com', [
            'Accept-Encoding' => 'gzip, deflate, br',
        ]);

        $filtered = $plugin->filterRequest($request);

        $this->assertEquals('gzip, deflate', $filtered->getHeaderLine('Accept-Encoding'));
    }

    public function test_it_removes_br_when_only_encoding()
    {
        $plugin = new RemoveBrotliEncodingPlugin;
        $request = new Request('GET', 'http://example.com', [
            'Accept-Encoding' => 'br',
        ]);

        $filtered = $plugin->filterRequest($request);

        $this->assertFalse($filtered->hasHeader('Accept-Encoding'));
    }

    public function test_it_removes_br_at_beginning()
    {
        $plugin = new RemoveBrotliEncodingPlugin;
        $request = new Request('GET', 'http://example.com', [
            'Accept-Encoding' => 'br, gzip, deflate',
        ]);

        $filtered = $plugin->filterRequest($request);

        $this->assertEquals('gzip, deflate', $filtered->getHeaderLine('Accept-Encoding'));
    }

    public function test_it_removes_br_in_middle()
    {
        $plugin = new RemoveBrotliEncodingPlugin;
        $request = new Request('GET', 'http://example.com', [
            'Accept-Encoding' => 'gzip, br, deflate',
        ]);

        $filtered = $plugin->filterRequest($request);

        $this->assertEquals('gzip, deflate', $filtered->getHeaderLine('Accept-Encoding'));
    }

    public function test_it_preserves_other_encodings()
    {
        $plugin = new RemoveBrotliEncodingPlugin;
        $request = new Request('GET', 'http://example.com', [
            'Accept-Encoding' => 'gzip, deflate',
        ]);

        $filtered = $plugin->filterRequest($request);

        $this->assertEquals('gzip, deflate', $filtered->getHeaderLine('Accept-Encoding'));
    }

    public function test_it_handles_missing_accept_encoding()
    {
        $plugin = new RemoveBrotliEncodingPlugin;
        $request = new Request('GET', 'http://example.com');

        $filtered = $plugin->filterRequest($request);

        $this->assertFalse($filtered->hasHeader('Accept-Encoding'));
    }

    public function test_it_does_not_modify_response()
    {
        $plugin = new RemoveBrotliEncodingPlugin;
        $response = new Response(200, ['Content-Type' => 'application/json']);

        $filtered = $plugin->filterResponse($response);

        $this->assertSame($response, $filtered);
    }
}
