<?php

namespace ReverseProxy\Tests\Unit;

use Nyholm\Psr7\Request;
use ReverseProxy\Http\WordPressHttpClient;
use WP_UnitTestCase;

class WordPressHttpClientTest extends WP_UnitTestCase
{
    private WordPressHttpClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new WordPressHttpClient();
    }

    public function test_it_implements_psr18_client_interface()
    {
        $this->assertInstanceOf(\Psr\Http\Client\ClientInterface::class, $this->client);
    }

    public function test_it_sends_get_request()
    {
        // Mock wp_remote_request
        add_filter('pre_http_request', function ($preempt, $args, $url) {
            $this->assertEquals('GET', $args['method']);
            $this->assertEquals('https://example.com/api/test', $url);

            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'headers' => ['content-type' => 'application/json'],
                'body' => '{"success":true}',
            ];
        }, 10, 3);

        $request = new Request('GET', 'https://example.com/api/test');
        $response = $this->client->sendRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('{"success":true}', (string) $response->getBody());
    }

    public function test_it_sends_post_request_with_body()
    {
        $requestBody = '{"name":"John"}';

        add_filter('pre_http_request', function ($preempt, $args, $url) use ($requestBody) {
            $this->assertEquals('POST', $args['method']);
            $this->assertEquals($requestBody, $args['body']);

            return [
                'response' => ['code' => 201, 'message' => 'Created'],
                'headers' => [],
                'body' => '{"id":1}',
            ];
        }, 10, 3);

        $request = new Request('POST', 'https://example.com/api/users', [], $requestBody);
        $response = $this->client->sendRequest($request);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('{"id":1}', (string) $response->getBody());
    }

    public function test_it_forwards_request_headers()
    {
        add_filter('pre_http_request', function ($preempt, $args, $url) {
            $this->assertEquals('Bearer token123', $args['headers']['Authorization']);
            $this->assertEquals('application/json', $args['headers']['Content-Type']);

            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'headers' => [],
                'body' => '',
            ];
        }, 10, 3);

        $request = new Request('GET', 'https://example.com/api/test', [
            'Authorization' => 'Bearer token123',
            'Content-Type' => 'application/json',
        ]);
        $this->client->sendRequest($request);
    }

    public function test_it_throws_exception_on_wp_error()
    {
        add_filter('pre_http_request', function () {
            return new \WP_Error('http_request_failed', 'Connection refused');
        });

        $this->expectException(\Psr\Http\Client\NetworkExceptionInterface::class);
        $this->expectExceptionMessage('Connection refused');

        $request = new Request('GET', 'https://example.com/api/test');
        $this->client->sendRequest($request);
    }
}
