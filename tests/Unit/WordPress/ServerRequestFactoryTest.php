<?php

namespace ReverseProxy\Tests\Unit\WordPress;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use ReverseProxy\WordPress\ServerRequestFactory;

class ServerRequestFactoryTest extends TestCase
{
    /** @var array */
    private $originalServer;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
    }

    public function test_it_does_not_duplicate_content_type_header()
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/test',
            'HTTP_HOST' => 'example.com',
            'HTTP_CONTENT_TYPE' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ];

        $factory = new ServerRequestFactory(new Psr17Factory);
        $request = $factory->createFromGlobals();

        $contentType = $request->getHeader('Content-Type');
        $this->assertCount(1, $contentType);
        $this->assertEquals('application/json', $contentType[0]);
    }

    public function test_it_does_not_duplicate_content_length_header()
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/test',
            'HTTP_HOST' => 'example.com',
            'HTTP_CONTENT_LENGTH' => '100',
            'CONTENT_LENGTH' => '100',
        ];

        $factory = new ServerRequestFactory(new Psr17Factory);
        $request = $factory->createFromGlobals();

        $contentLength = $request->getHeader('Content-Length');
        $this->assertCount(1, $contentLength);
        $this->assertEquals('100', $contentLength[0]);
    }

    public function test_it_skips_empty_headers()
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/test',
            'HTTP_HOST' => 'example.com',
            'HTTP_COOKIE' => '',
            'HTTP_REFERER' => '',
        ];

        $factory = new ServerRequestFactory(new Psr17Factory);
        $request = $factory->createFromGlobals();

        $this->assertFalse($request->hasHeader('Cookie'));
        $this->assertFalse($request->hasHeader('Referer'));
    }

    public function test_it_preserves_valid_headers()
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/test',
            'HTTP_HOST' => 'example.com',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer token',
            'HTTP_X_CUSTOM_HEADER' => 'custom-value',
        ];

        $factory = new ServerRequestFactory(new Psr17Factory);
        $request = $factory->createFromGlobals();

        $this->assertEquals('example.com', $request->getHeaderLine('Host'));
        $this->assertEquals('application/json', $request->getHeaderLine('Accept'));
        $this->assertEquals('Bearer token', $request->getHeaderLine('Authorization'));
        $this->assertEquals('custom-value', $request->getHeaderLine('X-Custom-Header'));
    }

    public function test_it_creates_correct_uri()
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/users?page=1&limit=10',
            'HTTP_HOST' => 'example.com',
            'HTTPS' => 'on',
        ];

        $factory = new ServerRequestFactory(new Psr17Factory);
        $request = $factory->createFromGlobals();

        $this->assertEquals('https://example.com/api/users?page=1&limit=10', (string) $request->getUri());
    }
}
