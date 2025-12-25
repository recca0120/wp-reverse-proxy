<?php

namespace ReverseProxy\Tests\Unit\Middleware;

use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use ReverseProxy\Middleware\ErrorHandlingMiddleware;

class ErrorHandlingMiddlewareTest extends TestCase
{
    public function test_it_returns_response_when_no_exception()
    {
        $middleware = new ErrorHandlingMiddleware;
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $response = $middleware->process($request, function ($req) {
            return new Response(200, [], '{"success":true}');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"success":true}', (string) $response->getBody());
    }

    public function test_it_returns_502_when_network_exception_occurs()
    {
        $middleware = new ErrorHandlingMiddleware;
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $response = $middleware->process($request, function ($req) {
            throw new class(new Request('GET', 'https://example.com'), 'Connection refused') extends \Exception implements NetworkExceptionInterface
            {
                private $request;

                public function __construct(RequestInterface $request, string $message)
                {
                    parent::__construct($message);
                    $this->request = $request;
                }

                public function getRequest(): RequestInterface
                {
                    return $this->request;
                }
            };
        });

        $this->assertEquals(502, $response->getStatusCode());
    }

    public function test_it_returns_502_when_client_exception_occurs()
    {
        $middleware = new ErrorHandlingMiddleware;
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $response = $middleware->process($request, function ($req) {
            throw new class('Request timeout') extends \Exception implements ClientExceptionInterface {};
        });

        $this->assertEquals(502, $response->getStatusCode());
    }

    public function test_it_returns_json_error_body()
    {
        $middleware = new ErrorHandlingMiddleware;
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $response = $middleware->process($request, function ($req) {
            throw new class('Connection refused') extends \Exception implements ClientExceptionInterface {};
        });

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals(502, $body['status']);
        $this->assertStringContainsString('Bad Gateway', $body['error']);
        $this->assertStringContainsString('Connection refused', $body['error']);
    }

    public function test_it_sets_json_content_type_header()
    {
        $middleware = new ErrorHandlingMiddleware;
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $response = $middleware->process($request, function ($req) {
            throw new class('Error') extends \Exception implements ClientExceptionInterface {};
        });

        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function test_it_rethrows_non_client_exceptions()
    {
        $middleware = new ErrorHandlingMiddleware;
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Some other error');

        $middleware->process($request, function ($req) {
            throw new \RuntimeException('Some other error');
        });
    }
}
