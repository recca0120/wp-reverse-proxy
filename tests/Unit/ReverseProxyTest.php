<?php

namespace ReverseProxy\Tests\Unit;

use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReverseProxy\ErrorHandlerInterface;
use ReverseProxy\ReverseProxy;

class ReverseProxyTest extends TestCase
{
    /** @var MockClient */
    private $mockClient;

    /** @var Psr17Factory */
    private $psr17Factory;

    /** @var ReverseProxy */
    private $reverseProxy;

    protected function setUp(): void
    {
        $this->mockClient = new MockClient();
        $this->psr17Factory = new Psr17Factory();
        $this->reverseProxy = new ReverseProxy(
            $this->mockClient,
            $this->psr17Factory,
            $this->psr17Factory
        );
    }

    public function test_it_returns_null_when_no_rules_match()
    {
        // Given: 不匹配的請求
        $request = new ServerRequest('GET', '/about');
        $rules = [
            ['source' => '/api/*', 'target' => 'https://backend.example.com'],
        ];

        // When
        $response = $this->reverseProxy->handle($request, $rules);

        // Then
        $this->assertNull($response);
    }

    public function test_it_proxies_matching_request()
    {
        // Given: Mock 後端回應
        $this->mockClient->addResponse(
            new Response(200, ['Content-Type' => 'application/json'], '{"message":"hello"}')
        );

        $request = new ServerRequest('GET', '/api/users');
        $rules = [
            ['source' => '/api/*', 'target' => 'https://backend.example.com'],
        ];

        // When
        $response = $this->reverseProxy->handle($request, $rules);

        // Then
        $this->assertNotNull($response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"message":"hello"}', (string) $response->getBody());

        // And: 驗證發送的請求
        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals('https://backend.example.com/api/users', (string) $lastRequest->getUri());
    }

    public function test_it_forwards_post_request_with_body()
    {
        // Given
        $this->mockClient->addResponse(new Response(201, [], '{"id":1}'));

        $body = '{"name":"John"}';
        $request = (new ServerRequest('POST', '/api/users'))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17Factory->createStream($body));

        $rules = [
            ['source' => '/api/*', 'target' => 'https://backend.example.com'],
        ];

        // When
        $response = $this->reverseProxy->handle($request, $rules);

        // Then
        $this->assertEquals(201, $response->getStatusCode());

        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals('POST', $lastRequest->getMethod());
        $this->assertEquals($body, (string) $lastRequest->getBody());
    }

    public function test_it_forwards_request_headers()
    {
        // Given
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $request = (new ServerRequest('GET', '/api/users'))
            ->withHeader('Authorization', 'Bearer token123')
            ->withHeader('X-Custom-Header', 'custom-value');

        $rules = [
            ['source' => '/api/*', 'target' => 'https://backend.example.com'],
        ];

        // When
        $this->reverseProxy->handle($request, $rules);

        // Then
        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals('Bearer token123', $lastRequest->getHeaderLine('Authorization'));
        $this->assertEquals('custom-value', $lastRequest->getHeaderLine('X-Custom-Header'));
    }

    public function test_it_preserves_query_string()
    {
        // Given
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $request = new ServerRequest('GET', '/api/users?page=2&limit=10');
        $rules = [
            ['source' => '/api/*', 'target' => 'https://backend.example.com'],
        ];

        // When
        $this->reverseProxy->handle($request, $rules);

        // Then
        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals(
            'https://backend.example.com/api/users?page=2&limit=10',
            (string) $lastRequest->getUri()
        );
    }

    public function test_it_rewrites_path()
    {
        // Given
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $request = new ServerRequest('GET', '/api/v1/users/123');
        $rules = [
            [
                'source' => '/api/v1/*',
                'target' => 'https://backend.example.com',
                'rewrite' => '/v1/$1',
            ],
        ];

        // When
        $this->reverseProxy->handle($request, $rules);

        // Then
        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals(
            'https://backend.example.com/v1/users/123',
            (string) $lastRequest->getUri()
        );
    }

    public function test_it_sets_host_header_to_target_by_default()
    {
        // Given
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $request = (new ServerRequest('GET', '/api/users'))
            ->withHeader('Host', 'original.example.com');
        $rules = [
            ['source' => '/api/*', 'target' => 'https://backend.example.com'],
        ];

        // When
        $this->reverseProxy->handle($request, $rules);

        // Then
        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals('backend.example.com', $lastRequest->getHeaderLine('Host'));
    }

    public function test_it_preserves_original_host_when_configured()
    {
        // Given
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $request = (new ServerRequest('GET', '/api/users'))
            ->withHeader('Host', 'original.example.com');
        $rules = [
            [
                'source' => '/api/*',
                'target' => 'https://backend.example.com',
                'preserve_host' => true,
            ],
        ];

        // When
        $this->reverseProxy->handle($request, $rules);

        // Then
        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals('original.example.com', $lastRequest->getHeaderLine('Host'));
    }

    public function test_it_matches_first_rule()
    {
        // Given
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $request = new ServerRequest('GET', '/api/v2/users');
        $rules = [
            ['source' => '/api/v2/*', 'target' => 'https://api-v2.example.com'],
            ['source' => '/api/*', 'target' => 'https://api.example.com'],
        ];

        // When
        $this->reverseProxy->handle($request, $rules);

        // Then
        $lastRequest = $this->mockClient->getLastRequest();
        $this->assertEquals(
            'https://api-v2.example.com/api/v2/users',
            (string) $lastRequest->getUri()
        );
    }
}
