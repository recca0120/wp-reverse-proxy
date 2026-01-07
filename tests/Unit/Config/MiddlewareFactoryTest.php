<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Config;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Config\MiddlewareFactory;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;
use Recca0120\ReverseProxy\Middleware\ProxyHeaders;
use Recca0120\ReverseProxy\Middleware\SetHost;
use Recca0120\ReverseProxy\Middleware\Timeout;

class MiddlewareFactoryTest extends TestCase
{
    public function test_create_middleware_by_alias(): void
    {
        $factory = new MiddlewareFactory;

        $middleware = $factory->create(['name' => 'ProxyHeaders']);

        $this->assertInstanceOf(ProxyHeaders::class, $middleware);
        $this->assertInstanceOf(MiddlewareInterface::class, $middleware);
    }

    public function test_create_middleware_by_full_class_name(): void
    {
        $factory = new MiddlewareFactory;

        $middleware = $factory->create(['name' => SetHost::class, 'args' => ['example.com']]);

        $this->assertInstanceOf(SetHost::class, $middleware);
    }

    public function test_create_middleware_with_args(): void
    {
        $factory = new MiddlewareFactory;

        $middleware = $factory->create([
            'name' => 'SetHost',
            'args' => ['custom.example.com'],
        ]);

        $this->assertInstanceOf(SetHost::class, $middleware);
    }

    public function test_create_middleware_with_options(): void
    {
        $factory = new MiddlewareFactory;

        $middleware = $factory->create([
            'name' => 'ProxyHeaders',
            'options' => ['clientIp' => '192.168.1.1'],
        ]);

        $this->assertInstanceOf(ProxyHeaders::class, $middleware);
    }

    public function test_create_middleware_with_single_option_value(): void
    {
        $factory = new MiddlewareFactory;

        $middleware = $factory->create([
            'name' => 'Timeout',
            'options' => 60,
        ]);

        $this->assertInstanceOf(Timeout::class, $middleware);
    }

    public function test_register_custom_alias(): void
    {
        $factory = new MiddlewareFactory;

        $customMiddleware = new class implements MiddlewareInterface
        {
            public function process(\Psr\Http\Message\ServerRequestInterface $request, callable $next): \Psr\Http\Message\ResponseInterface
            {
                return $next($request);
            }
        };

        $factory->registerAlias('CustomMiddleware', get_class($customMiddleware));

        $middleware = $factory->create(['name' => 'CustomMiddleware']);

        $this->assertInstanceOf(get_class($customMiddleware), $middleware);
    }

    public function test_throws_exception_for_unknown_middleware(): void
    {
        $factory = new MiddlewareFactory;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown middleware: NonExistentMiddleware');

        $factory->create(['name' => 'NonExistentMiddleware']);
    }

    public function test_get_aliases_returns_all_registered_aliases(): void
    {
        $factory = new MiddlewareFactory;

        $aliases = $factory->getAliases();

        $this->assertArrayHasKey('ProxyHeaders', $aliases);
        $this->assertArrayHasKey('SetHost', $aliases);
        $this->assertArrayHasKey('RewritePath', $aliases);
        $this->assertArrayHasKey('Timeout', $aliases);
    }

    public function test_create_middleware_from_string_format(): void
    {
        $factory = new MiddlewareFactory;

        $middleware = $factory->create('ProxyHeaders');

        $this->assertInstanceOf(ProxyHeaders::class, $middleware);
    }

    public function test_create_middleware_from_array_format_with_single_arg(): void
    {
        $factory = new MiddlewareFactory;

        $middleware = $factory->create(['SetHost', 'api.example.com']);

        $this->assertInstanceOf(SetHost::class, $middleware);
    }

    public function test_create_middleware_from_array_format_with_options_object(): void
    {
        $factory = new MiddlewareFactory;

        $middleware = $factory->create(['ProxyHeaders', ['clientIp' => '192.168.1.1']]);

        $this->assertInstanceOf(ProxyHeaders::class, $middleware);
    }

    public function test_create_middleware_from_array_format_with_multiple_args(): void
    {
        $factory = new MiddlewareFactory;

        // Timeout accepts single int, but testing the args spread mechanism
        $middleware = $factory->create(['Timeout', 30]);

        $this->assertInstanceOf(Timeout::class, $middleware);
    }
}
