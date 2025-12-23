<?php

namespace ReverseProxy\Tests\Unit\WordPress;

use Nyholm\Psr7\Request;
use ReverseProxy\ErrorHandlerInterface;
use ReverseProxy\WordPress\ErrorHandler;
use WP_UnitTestCase;

class ErrorHandlerTest extends WP_UnitTestCase
{
    public function test_it_implements_error_handler_interface()
    {
        $errorHandler = new ErrorHandler();

        $this->assertInstanceOf(ErrorHandlerInterface::class, $errorHandler);
    }

    public function test_it_triggers_wordpress_action_on_error()
    {
        $errorHandler = new ErrorHandler();
        $captured = [];

        add_action('reverse_proxy_error', function ($exception, $request) use (&$captured) {
            $captured = compact('exception', 'request');
        }, 10, 2);

        $exception = new \RuntimeException('Test error');
        $request = new Request('GET', 'https://example.com/api/users');

        $errorHandler->handle($exception, $request);

        $this->assertSame($exception, $captured['exception']);
        $this->assertSame($request, $captured['request']);
    }
}
