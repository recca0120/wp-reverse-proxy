<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Http\ServerRequestFactory;

class ServerRequestFactoryTest extends TestCase
{
    /** @var ServerRequestFactory */
    private $factory;

    protected function setUp(): void
    {
        $this->factory = new ServerRequestFactory(new Psr17Factory());
    }

    public function test_it_does_not_duplicate_content_type_header()
    {
        $serverParams = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/test',
            'HTTP_HOST' => 'example.com',
            'HTTP_CONTENT_TYPE' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ];

        $request = $this->factory->create($serverParams);

        $contentType = $request->getHeader('Content-Type');
        $this->assertCount(1, $contentType);
        $this->assertEquals('application/json', $contentType[0]);
    }

    public function test_it_does_not_duplicate_content_length_header()
    {
        $serverParams = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/test',
            'HTTP_HOST' => 'example.com',
            'HTTP_CONTENT_LENGTH' => '100',
            'CONTENT_LENGTH' => '100',
        ];

        $request = $this->factory->create($serverParams);

        $contentLength = $request->getHeader('Content-Length');
        $this->assertCount(1, $contentLength);
        $this->assertEquals('100', $contentLength[0]);
    }

    public function test_it_skips_empty_headers()
    {
        $serverParams = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/test',
            'HTTP_HOST' => 'example.com',
            'HTTP_COOKIE' => '',
            'HTTP_REFERER' => '',
        ];

        $request = $this->factory->create($serverParams);

        $this->assertFalse($request->hasHeader('Cookie'));
        $this->assertFalse($request->hasHeader('Referer'));
    }

    public function test_it_preserves_valid_headers()
    {
        $serverParams = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/test',
            'HTTP_HOST' => 'example.com',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer token',
            'HTTP_X_CUSTOM_HEADER' => 'custom-value',
        ];

        $request = $this->factory->create($serverParams);

        $this->assertEquals('example.com', $request->getHeaderLine('Host'));
        $this->assertEquals('application/json', $request->getHeaderLine('Accept'));
        $this->assertEquals('Bearer token', $request->getHeaderLine('Authorization'));
        $this->assertEquals('custom-value', $request->getHeaderLine('X-Custom-Header'));
    }

    public function test_it_creates_correct_uri()
    {
        $serverParams = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/users?page=1&limit=10',
            'HTTP_HOST' => 'example.com',
            'HTTPS' => 'on',
        ];

        $request = $this->factory->create($serverParams);

        $this->assertEquals('https://example.com/api/users?page=1&limit=10', (string) $request->getUri());
    }

    public function test_it_accepts_body_as_string()
    {
        $serverParams = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/test',
            'HTTP_HOST' => 'example.com',
        ];

        $request = $this->factory->create($serverParams, '{"name":"test"}');

        $this->assertEquals('{"name":"test"}', (string) $request->getBody());
    }

    public function test_it_accepts_body_as_callable()
    {
        $serverParams = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/test',
            'HTTP_HOST' => 'example.com',
        ];

        $request = $this->factory->create($serverParams, function () {
            return '{"name":"test"}';
        });

        $this->assertEquals('{"name":"test"}', (string) $request->getBody());
    }

    public function test_it_does_not_call_body_callable_for_get_request()
    {
        $serverParams = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/test',
            'HTTP_HOST' => 'example.com',
        ];

        $called = false;
        $request = $this->factory->create($serverParams, function () use (&$called) {
            $called = true;

            return 'body content';
        });

        $this->assertFalse($called);
        $this->assertEquals('', (string) $request->getBody());
    }

    public function test_it_calls_body_callable_for_post_request()
    {
        $serverParams = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/test',
            'HTTP_HOST' => 'example.com',
        ];

        $called = false;
        $request = $this->factory->create($serverParams, function () use (&$called) {
            $called = true;

            return 'body content';
        });

        $this->assertTrue($called);
        $this->assertEquals('body content', (string) $request->getBody());
    }

    public function test_it_calls_body_callable_for_put_request()
    {
        $serverParams = [
            'REQUEST_METHOD' => 'PUT',
            'REQUEST_URI' => '/api/test',
            'HTTP_HOST' => 'example.com',
        ];

        $called = false;
        $request = $this->factory->create($serverParams, function () use (&$called) {
            $called = true;

            return 'body content';
        });

        $this->assertTrue($called);
    }

    public function test_it_calls_body_callable_for_patch_request()
    {
        $serverParams = [
            'REQUEST_METHOD' => 'PATCH',
            'REQUEST_URI' => '/api/test',
            'HTTP_HOST' => 'example.com',
        ];

        $called = false;
        $request = $this->factory->create($serverParams, function () use (&$called) {
            $called = true;

            return 'body content';
        });

        $this->assertTrue($called);
    }
}
