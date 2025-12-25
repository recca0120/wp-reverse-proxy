<?php

namespace ReverseProxy\Tests\Unit;

use Nyholm\Psr7\Request;
use PHPUnit\Framework\TestCase;
use ReverseProxy\Http\NetworkException;
use ReverseProxy\Http\WordPressHttpClient;
use WP_Error;

class WordPressHttpClientTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        remove_all_filters('pre_http_request');
    }

    public function test_it_sends_get_request()
    {
        add_filter('pre_http_request', function ($preempt, $args, $url) {
            $this->assertEquals('GET', $args['method']);
            $this->assertEquals('https://example.com/api', $url);

            return [
                'response' => ['code' => 200],
                'headers' => new \WpOrg\Requests\Utility\CaseInsensitiveDictionary(['Content-Type' => 'application/json']),
                'body' => '{"success":true}',
            ];
        }, 10, 3);

        $client = new WordPressHttpClient;
        $request = new Request('GET', 'https://example.com/api');

        $response = $client->sendRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('{"success":true}', (string) $response->getBody());
    }

    public function test_it_sends_post_request_with_body()
    {
        add_filter('pre_http_request', function ($preempt, $args, $url) {
            $this->assertEquals('POST', $args['method']);
            $this->assertEquals('{"data":"test"}', $args['body']);
            $this->assertEquals('application/json', $args['headers']['Content-Type']);

            return [
                'response' => ['code' => 201],
                'headers' => new \WpOrg\Requests\Utility\CaseInsensitiveDictionary([]),
                'body' => '',
            ];
        }, 10, 3);

        $client = new WordPressHttpClient;
        $request = new Request('POST', 'https://example.com/api', [
            'Content-Type' => 'application/json',
        ], '{"data":"test"}');

        $response = $client->sendRequest($request);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function test_it_throws_exception_on_wp_error()
    {
        add_filter('pre_http_request', function () {
            return new WP_Error('http_request_failed', 'Connection timed out');
        });

        $this->expectException(NetworkException::class);
        $this->expectExceptionMessage('Connection timed out');

        $client = new WordPressHttpClient;
        $request = new Request('GET', 'https://example.com/api');

        $client->sendRequest($request);
    }

    public function test_it_uses_custom_options()
    {
        add_filter('pre_http_request', function ($preempt, $args) {
            $this->assertEquals(60, $args['timeout']);

            return [
                'response' => ['code' => 200],
                'headers' => new \WpOrg\Requests\Utility\CaseInsensitiveDictionary([]),
                'body' => '',
            ];
        }, 10, 3);

        $client = new WordPressHttpClient(['timeout' => 60]);
        $request = new Request('GET', 'https://example.com/api');

        $client->sendRequest($request);
    }

    public function test_it_does_not_decompress_response()
    {
        add_filter('pre_http_request', function ($preempt, $args) {
            $this->assertFalse($args['decompress']);

            return [
                'response' => ['code' => 200],
                'headers' => new \WpOrg\Requests\Utility\CaseInsensitiveDictionary([]),
                'body' => '',
            ];
        }, 10, 3);

        $client = new WordPressHttpClient;
        $request = new Request('GET', 'https://example.com/api');

        $client->sendRequest($request);
    }

    public function test_it_does_not_follow_redirects()
    {
        add_filter('pre_http_request', function ($preempt, $args) {
            $this->assertEquals(0, $args['redirection']);

            return [
                'response' => ['code' => 302],
                'headers' => new \WpOrg\Requests\Utility\CaseInsensitiveDictionary(['Location' => 'https://example.com/new']),
                'body' => '',
            ];
        }, 10, 3);

        $client = new WordPressHttpClient;
        $request = new Request('GET', 'https://example.com/old');

        $response = $client->sendRequest($request);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('https://example.com/new', $response->getHeaderLine('Location'));
    }
}
