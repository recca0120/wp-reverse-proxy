<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Routing;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Recca0120\ReverseProxy\Middleware\AllowMethods;
use Recca0120\ReverseProxy\Middleware\Caching;
use Recca0120\ReverseProxy\Middleware\Fallback;
use Recca0120\ReverseProxy\Middleware\IpFilter;
use Recca0120\ReverseProxy\Middleware\ProxyHeaders;
use Recca0120\ReverseProxy\Middleware\SetHost;
use Recca0120\ReverseProxy\Middleware\Timeout;
use Recca0120\ReverseProxy\Routing\MiddlewareFactory;
use Recca0120\ReverseProxy\Tests\Stubs\ArrayCache;

class MiddlewareFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        MiddlewareFactory::resetAliases();
        parent::tearDown();
    }

    public function test_create_middleware_by_alias(): void
    {
        $factory = new MiddlewareFactory();

        $middleware = $factory->create(['name' => 'ProxyHeaders']);

        $this->assertInstanceOf(ProxyHeaders::class, $middleware);
        $this->assertInstanceOf(MiddlewareInterface::class, $middleware);
    }

    public function test_create_middleware_by_full_class_name(): void
    {
        $factory = new MiddlewareFactory();

        $middleware = $factory->create(['name' => SetHost::class, 'args' => ['example.com']]);

        $this->assertInstanceOf(SetHost::class, $middleware);
    }

    public function test_create_middleware_with_args(): void
    {
        $factory = new MiddlewareFactory();

        $middleware = $factory->create([
            'name' => 'SetHost',
            'args' => ['custom.example.com'],
        ]);

        $this->assertInstanceOf(SetHost::class, $middleware);
    }

    public function test_create_middleware_with_options(): void
    {
        $factory = new MiddlewareFactory();

        $middleware = $factory->create([
            'name' => 'ProxyHeaders',
            'options' => ['clientIp' => '192.168.1.1'],
        ]);

        $this->assertInstanceOf(ProxyHeaders::class, $middleware);
    }

    public function test_create_middleware_with_single_option_value(): void
    {
        $factory = new MiddlewareFactory();

        $middleware = $factory->create([
            'name' => 'Timeout',
            'options' => 60,
        ]);

        $this->assertInstanceOf(Timeout::class, $middleware);
    }

    public function test_register_custom_alias(): void
    {
        $customMiddleware = new class () implements MiddlewareInterface {
            public function process(\Psr\Http\Message\ServerRequestInterface $request, callable $next): \Psr\Http\Message\ResponseInterface
            {
                return $next($request);
            }
        };

        MiddlewareFactory::registerAlias('CustomMiddleware', get_class($customMiddleware));

        $factory = new MiddlewareFactory();
        $middleware = $factory->create(['name' => 'CustomMiddleware']);

        $this->assertInstanceOf(get_class($customMiddleware), $middleware);
    }

    public function test_throws_exception_for_unknown_middleware(): void
    {
        $factory = new MiddlewareFactory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown middleware: NonExistentMiddleware');

        $factory->create(['name' => 'NonExistentMiddleware']);
    }

    public function test_get_aliases_returns_all_registered_aliases(): void
    {
        $factory = new MiddlewareFactory();

        $aliases = $factory->getAliases();

        $this->assertArrayHasKey('ProxyHeaders', $aliases);
        $this->assertArrayHasKey('SetHost', $aliases);
        $this->assertArrayHasKey('RewritePath', $aliases);
        $this->assertArrayHasKey('Timeout', $aliases);
    }

    public function test_create_middleware_from_string_format(): void
    {
        $factory = new MiddlewareFactory();

        $middleware = $factory->create('ProxyHeaders');

        $this->assertInstanceOf(ProxyHeaders::class, $middleware);
    }

    public function test_create_middleware_from_array_format_with_single_arg(): void
    {
        $factory = new MiddlewareFactory();

        $middleware = $factory->create(['SetHost', 'api.example.com']);

        $this->assertInstanceOf(SetHost::class, $middleware);
    }

    public function test_create_middleware_from_array_format_with_options_object(): void
    {
        $factory = new MiddlewareFactory();

        $middleware = $factory->create(['ProxyHeaders', ['clientIp' => '192.168.1.1']]);

        $this->assertInstanceOf(ProxyHeaders::class, $middleware);
    }

    public function test_create_middleware_from_array_format_with_multiple_args(): void
    {
        $factory = new MiddlewareFactory();

        // Timeout accepts single int, but testing the args spread mechanism
        $middleware = $factory->create(['Timeout', 30]);

        $this->assertInstanceOf(Timeout::class, $middleware);
    }

    public function test_create_middleware_from_colon_format(): void
    {
        $factory = new MiddlewareFactory();

        $middleware = $factory->create('SetHost:api.example.com');

        $this->assertInstanceOf(SetHost::class, $middleware);
    }

    public function test_create_middleware_from_colon_format_with_multiple_params(): void
    {
        $factory = new MiddlewareFactory();

        $middleware = $factory->create('Timeout:30');

        $this->assertInstanceOf(Timeout::class, $middleware);
    }

    public function test_create_many_from_array(): void
    {
        $factory = new MiddlewareFactory();

        $middlewares = $factory->createMany([
            'ProxyHeaders',
            'SetHost:api.example.com',
            ['Timeout', 30],
        ]);

        $this->assertCount(3, $middlewares);
        $this->assertInstanceOf(ProxyHeaders::class, $middlewares[0]);
        $this->assertInstanceOf(SetHost::class, $middlewares[1]);
        $this->assertInstanceOf(Timeout::class, $middlewares[2]);
    }

    public function test_create_many_from_pipe_separated_string(): void
    {
        $factory = new MiddlewareFactory();

        $middlewares = $factory->createMany('ProxyHeaders|SetHost:api.example.com|Timeout:30');

        $this->assertCount(3, $middlewares);
        $this->assertInstanceOf(ProxyHeaders::class, $middlewares[0]);
        $this->assertInstanceOf(SetHost::class, $middlewares[1]);
        $this->assertInstanceOf(Timeout::class, $middlewares[2]);
    }

    public function test_create_allow_methods_from_colon_format(): void
    {
        $factory = new MiddlewareFactory();

        $middleware = $factory->create('AllowMethods:GET,POST,PUT');

        $this->assertInstanceOf(AllowMethods::class, $middleware);
    }

    public function test_create_fallback_from_colon_format(): void
    {
        $factory = new MiddlewareFactory();

        $middleware = $factory->create('Fallback:404,500,502');

        $this->assertInstanceOf(Fallback::class, $middleware);
    }

    public function test_create_ip_filter_from_colon_format_with_mode(): void
    {
        $factory = new MiddlewareFactory();

        $middleware = $factory->create('IpFilter:allow,192.168.1.1,10.0.0.1');

        $this->assertInstanceOf(IpFilter::class, $middleware);
    }

    public function test_create_ip_filter_from_colon_format_without_mode(): void
    {
        $factory = new MiddlewareFactory();

        $middleware = $factory->create('IpFilter:192.168.1.1,10.0.0.1');

        $this->assertInstanceOf(IpFilter::class, $middleware);
    }

    public function test_create_ip_filter_deny_mode_from_colon_format(): void
    {
        $factory = new MiddlewareFactory();

        $middleware = $factory->create('IpFilter:deny,192.168.1.100');

        $this->assertInstanceOf(IpFilter::class, $middleware);
    }

    public function test_register_alias_multiple_times(): void
    {
        MiddlewareFactory::registerAlias('Alias1', ProxyHeaders::class);
        MiddlewareFactory::registerAlias('Alias2', SetHost::class);

        $factory = new MiddlewareFactory();
        $aliases = $factory->getAliases();

        $this->assertArrayHasKey('Alias1', $aliases);
        $this->assertArrayHasKey('Alias2', $aliases);
    }

    public function test_register_alias_with_array(): void
    {
        MiddlewareFactory::registerAlias([
            'MyProxy' => ProxyHeaders::class,
            'MyHost' => SetHost::class,
        ]);

        $factory = new MiddlewareFactory();
        $aliases = $factory->getAliases();

        $this->assertArrayHasKey('MyProxy', $aliases);
        $this->assertArrayHasKey('MyHost', $aliases);
        $this->assertEquals(ProxyHeaders::class, $aliases['MyProxy']);
        $this->assertEquals(SetHost::class, $aliases['MyHost']);
    }

    public function test_custom_alias_shared_across_instances(): void
    {
        MiddlewareFactory::registerAlias('SharedAlias', ProxyHeaders::class);

        $factory1 = new MiddlewareFactory();
        $factory2 = new MiddlewareFactory();

        $this->assertArrayHasKey('SharedAlias', $factory1->getAliases());
        $this->assertArrayHasKey('SharedAlias', $factory2->getAliases());
    }

    public function test_create_middleware_from_yaml_key_value_format(): void
    {
        $factory = new MiddlewareFactory();

        // YAML format: "SetHost: api.example.com" parses to ['SetHost' => 'api.example.com']
        $middleware = $factory->create(['SetHost' => 'api.example.com']);

        $this->assertInstanceOf(SetHost::class, $middleware);
    }

    public function test_create_middleware_from_yaml_key_value_format_with_array_options(): void
    {
        $factory = new MiddlewareFactory();

        // YAML format with options
        $middleware = $factory->create(['ProxyHeaders' => ['clientIp' => '192.168.1.1']]);

        $this->assertInstanceOf(ProxyHeaders::class, $middleware);
    }

    public function test_create_middleware_from_yaml_key_value_format_with_numeric_value(): void
    {
        $factory = new MiddlewareFactory();

        // YAML format: "Timeout: 30" parses to ['Timeout' => 30]
        $middleware = $factory->create(['Timeout' => 30]);

        $this->assertInstanceOf(Timeout::class, $middleware);
    }

    public function test_create_many_with_yaml_key_value_format(): void
    {
        $factory = new MiddlewareFactory();

        // Mixed formats including YAML key-value
        $middlewares = $factory->createMany([
            'ProxyHeaders',
            ['SetHost' => 'api.example.com'],
            ['Timeout' => 30],
        ]);

        $this->assertCount(3, $middlewares);
        $this->assertInstanceOf(ProxyHeaders::class, $middlewares[0]);
        $this->assertInstanceOf(SetHost::class, $middlewares[1]);
        $this->assertInstanceOf(Timeout::class, $middlewares[2]);
    }

    public function test_create_many_with_php_mixed_array_format(): void
    {
        $factory = new MiddlewareFactory();

        // PHP mixed array format
        $middlewares = $factory->createMany([
            'ProxyHeaders',
            'SetHost' => 'api.example.com',
            'Timeout' => 30,
        ]);

        $this->assertCount(3, $middlewares);
        $this->assertInstanceOf(ProxyHeaders::class, $middlewares[0]);
        $this->assertInstanceOf(SetHost::class, $middlewares[1]);
        $this->assertInstanceOf(Timeout::class, $middlewares[2]);
    }

    public function test_create_many_with_php_mixed_array_format_with_array_options(): void
    {
        $factory = new MiddlewareFactory();

        // PHP mixed array format with array options
        $middlewares = $factory->createMany([
            'ProxyHeaders' => ['clientIp' => '192.168.1.1'],
            'SetHost' => 'api.example.com',
        ]);

        $this->assertCount(2, $middlewares);
        $this->assertInstanceOf(ProxyHeaders::class, $middlewares[0]);
        $this->assertInstanceOf(SetHost::class, $middlewares[1]);
    }

    public function test_create_many_preserves_order(): void
    {
        $factory = new MiddlewareFactory();

        $middlewares = $factory->createMany([
            'SetHost' => 'api.example.com',
            'ProxyHeaders',
            'Timeout' => 30,
        ]);

        $this->assertCount(3, $middlewares);
        $this->assertInstanceOf(SetHost::class, $middlewares[0]);
        $this->assertInstanceOf(ProxyHeaders::class, $middlewares[1]);
        $this->assertInstanceOf(Timeout::class, $middlewares[2]);
    }

    public function test_inject_cache_into_caching_middleware(): void
    {
        $cache = new ArrayCache();
        $factory = new MiddlewareFactory($cache);
        $middleware = $factory->create('Caching:300');

        $request = new ServerRequest('GET', 'https://example.com/test');
        $middleware->process($request, function () {
            return new Response(200, [], 'OK');
        });

        $this->assertNotEmpty($cache->all());
    }

    public function test_inject_cache_into_circuit_breaker_middleware(): void
    {
        $cache = new ArrayCache();
        $factory = new MiddlewareFactory($cache);
        $middleware = $factory->create(['name' => 'CircuitBreaker', 'args' => ['my-service']]);

        $request = new ServerRequest('GET', 'https://example.com/test');
        $middleware->process($request, function () {
            return new Response(200, [], 'OK');
        });

        $this->assertNotEmpty($cache->all());
    }

    public function test_inject_cache_into_rate_limiting_middleware(): void
    {
        $cache = new ArrayCache();
        $factory = new MiddlewareFactory($cache);
        $middleware = $factory->create('RateLimiting:100,60');

        $request = new ServerRequest('GET', 'https://example.com/test', [], null, '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $middleware->process($request, function () {
            return new Response(200, [], 'OK');
        });

        $this->assertNotEmpty($cache->all());
    }

    public function test_factory_without_cache_creates_middleware(): void
    {
        $factory = new MiddlewareFactory();

        $middleware = $factory->create('Caching:300');

        $this->assertInstanceOf(Caching::class, $middleware);
    }
}
