<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Middleware\IpFilter;

class IpFilterTest extends TestCase
{
    public function test_allow_mode_allows_whitelisted_ip()
    {
        $middleware = IpFilter::allow(['192.168.1.100', '10.0.0.1']);
        $request = new ServerRequest('GET', 'https://example.com/api/users', [], null, '1.1', ['REMOTE_ADDR' => '192.168.1.100']);

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_allow_mode_blocks_non_whitelisted_ip()
    {
        $middleware = IpFilter::allow(['192.168.1.100']);
        $request = new ServerRequest('GET', 'https://example.com/api/users', [], null, '1.1', ['REMOTE_ADDR' => '192.168.1.200']);

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertFalse($called);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_deny_mode_blocks_blacklisted_ip()
    {
        $middleware = IpFilter::deny(['192.168.1.100']);
        $request = new ServerRequest('GET', 'https://example.com/api/users', [], null, '1.1', ['REMOTE_ADDR' => '192.168.1.100']);

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertFalse($called);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_deny_mode_allows_non_blacklisted_ip()
    {
        $middleware = IpFilter::deny(['192.168.1.100']);
        $request = new ServerRequest('GET', 'https://example.com/api/users', [], null, '1.1', ['REMOTE_ADDR' => '192.168.1.200']);

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_it_supports_cidr_notation()
    {
        $middleware = IpFilter::allow(['192.168.1.0/24']);
        $request = new ServerRequest('GET', 'https://example.com/api/users', [], null, '1.1', ['REMOTE_ADDR' => '192.168.1.50']);

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertTrue($called);
    }

    public function test_cidr_blocks_ip_outside_range()
    {
        $middleware = IpFilter::allow(['192.168.1.0/24']);
        $request = new ServerRequest('GET', 'https://example.com/api/users', [], null, '1.1', ['REMOTE_ADDR' => '192.168.2.1']);

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertFalse($called);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_it_returns_json_error_response()
    {
        $middleware = IpFilter::allow(['192.168.1.100']);
        $request = new ServerRequest('GET', 'https://example.com/api/users', [], null, '1.1', ['REMOTE_ADDR' => '192.168.1.200']);

        $response = $middleware->process($request, function ($req) {
            return new Response(200);
        });

        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('Forbidden', $body['error']);
        $this->assertEquals(403, $body['status']);
    }

    public function test_it_supports_multiple_cidr_ranges()
    {
        $middleware = IpFilter::allow(['192.168.1.0/24', '10.0.0.0/8']);
        $request = new ServerRequest('GET', 'https://example.com/api/users', [], null, '1.1', ['REMOTE_ADDR' => '10.0.0.50']);

        $called = false;
        $response = $middleware->process($request, function ($req) use (&$called) {
            $called = true;

            return new Response(200);
        });

        $this->assertTrue($called);
    }
}
