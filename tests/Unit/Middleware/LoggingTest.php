<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;
use Recca0120\ReverseProxy\Middleware\Logging;

class LoggingTest extends TestCase
{
    public function test_it_logs_request_before_calling_next()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                [$this->stringContains('Proxying request'), $this->anything()],
                [$this->stringContains('Proxy response'), $this->anything()]
            );

        $middleware = new Logging($logger);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $middleware->process($request, function ($req) {
            return new Response(200);
        });
    }

    public function test_it_logs_request_method_and_target()
    {
        $loggedContext = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context) use (&$loggedContext) {
                if (strpos($message, 'Proxying request') !== false) {
                    $loggedContext = $context;
                }
            });

        $middleware = new Logging($logger);
        $request = new ServerRequest('POST', 'https://example.com/api/users');

        $middleware->process($request, function ($req) {
            return new Response(201);
        });

        $this->assertEquals('POST', $loggedContext['method']);
        $this->assertStringContainsString('example.com/api/users', $loggedContext['target']);
    }

    public function test_it_logs_response_status_code()
    {
        $loggedContext = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context) use (&$loggedContext) {
                if (strpos($message, 'response') !== false) {
                    $loggedContext = $context;
                }
            });

        $middleware = new Logging($logger);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $middleware->process($request, function ($req) {
            return new Response(201);
        });

        $this->assertEquals(201, $loggedContext['status']);
    }

    public function test_it_returns_response_from_next()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $middleware = new Logging($logger);
        $request = new ServerRequest('GET', 'https://example.com/api/users');
        $expectedResponse = new Response(200, [], '{"data":"test"}');

        $response = $middleware->process($request, function ($req) use ($expectedResponse) {
            return $expectedResponse;
        });

        $this->assertSame($expectedResponse, $response);
    }

    public function test_it_logs_error_when_exception_occurs()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Proxying request'), $this->anything());
        $logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Connection refused'),
                $this->anything()
            );

        $middleware = new Logging($logger);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $this->expectException(ClientExceptionInterface::class);

        $middleware->process($request, function ($req) {
            throw new class('Connection refused') extends \Exception implements ClientExceptionInterface {};
        });
    }

    public function test_it_rethrows_exception_after_logging()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $middleware = new Logging($logger);
        $request = new ServerRequest('GET', 'https://example.com/api/users');

        $this->expectException(ClientExceptionInterface::class);
        $this->expectExceptionMessage('Connection refused');

        $middleware->process($request, function ($req) {
            throw new class('Connection refused') extends \Exception implements ClientExceptionInterface {};
        });
    }
}
