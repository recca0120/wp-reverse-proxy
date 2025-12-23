<?php

namespace ReverseProxy\Tests\Unit;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use ReverseProxy\Middleware\SetHostMiddleware;

class SetHostMiddlewareTest extends TestCase
{
    public function test_it_sets_host_header()
    {
        $middleware = new SetHostMiddleware('api.example.com');
        $request = new ServerRequest('GET', 'https://localhost/api/users');

        $response = $middleware->process($request, function ($req) {
            $this->assertEquals('api.example.com', $req->getHeaderLine('Host'));

            return new Response(200);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_it_overrides_existing_host_header()
    {
        $middleware = new SetHostMiddleware('new-host.com');
        $request = (new ServerRequest('GET', 'https://localhost/api/users'))
            ->withHeader('Host', 'old-host.com');

        $middleware->process($request, function ($req) {
            $this->assertEquals('new-host.com', $req->getHeaderLine('Host'));

            return new Response(200);
        });
    }
}
