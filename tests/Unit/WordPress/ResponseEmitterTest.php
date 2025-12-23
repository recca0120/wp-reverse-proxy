<?php

namespace ReverseProxy\Tests\Unit\WordPress;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ReverseProxy\WordPress\ResponseEmitter;

class ResponseEmitterTest extends TestCase
{
    public function test_it_filters_transfer_encoding_header()
    {
        $emitter = new ResponseEmitter();
        $response = new Response(200, [
            'Content-Type' => 'application/json',
            'Transfer-Encoding' => 'chunked',
        ], '{"data":"test"}');

        $headers = $emitter->getHeadersToEmit($response);

        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertArrayNotHasKey('Transfer-Encoding', $headers);
    }

    public function test_it_filters_content_encoding_header()
    {
        $emitter = new ResponseEmitter();
        $response = new Response(200, [
            'Content-Type' => 'application/json',
            'Content-Encoding' => 'gzip',
        ], '{"data":"test"}');

        $headers = $emitter->getHeadersToEmit($response);

        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertArrayNotHasKey('Content-Encoding', $headers);
    }

    public function test_it_filters_connection_headers()
    {
        $emitter = new ResponseEmitter();
        $response = new Response(200, [
            'Content-Type' => 'text/html',
            'Connection' => 'keep-alive',
            'Keep-Alive' => 'timeout=5',
        ], '<html></html>');

        $headers = $emitter->getHeadersToEmit($response);

        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertArrayNotHasKey('Connection', $headers);
        $this->assertArrayNotHasKey('Keep-Alive', $headers);
    }

    public function test_it_recalculates_content_length()
    {
        $emitter = new ResponseEmitter();
        $body = '{"data":"test"}';
        $response = new Response(200, [
            'Content-Type' => 'application/json',
            'Content-Length' => '999', // Wrong length
        ], $body);

        $headers = $emitter->getHeadersToEmit($response);

        $this->assertEquals([(string) strlen($body)], $headers['Content-Length']);
    }

    public function test_it_preserves_safe_headers()
    {
        $emitter = new ResponseEmitter();
        $response = new Response(200, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache',
            'X-Custom-Header' => 'custom-value',
            'Set-Cookie' => ['session=abc', 'token=xyz'],
        ], '{}');

        $headers = $emitter->getHeadersToEmit($response);

        $this->assertEquals(['application/json'], $headers['Content-Type']);
        $this->assertEquals(['no-cache'], $headers['Cache-Control']);
        $this->assertEquals(['custom-value'], $headers['X-Custom-Header']);
        $this->assertEquals(['session=abc', 'token=xyz'], $headers['Set-Cookie']);
    }

    public function test_it_filters_headers_case_insensitively()
    {
        $emitter = new ResponseEmitter();
        $response = new Response(200, [
            'transfer-encoding' => 'chunked',
            'CONTENT-ENCODING' => 'gzip',
            'Content-Type' => 'text/plain',
        ], 'test');

        $headers = $emitter->getHeadersToEmit($response);

        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertArrayNotHasKey('transfer-encoding', $headers);
        $this->assertArrayNotHasKey('CONTENT-ENCODING', $headers);
    }
}
