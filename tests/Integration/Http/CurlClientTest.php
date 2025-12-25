<?php

namespace ReverseProxy\Tests\Integration\Http;

use Nyholm\Psr7\Request;
use ReverseProxy\Exceptions\NetworkException;
use ReverseProxy\Http\CurlClient;

class CurlClientTest extends HttpClientTestCase
{
    public function test_it_sends_get_request()
    {
        $client = new CurlClient;
        $request = new Request('GET', $this->getServerUrl('/api/test'));

        $response = $client->sendRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('GET', $body['method']);
        $this->assertEquals('/api/test', $body['uri']);
    }

    public function test_it_sends_post_request_with_body()
    {
        $client = new CurlClient;
        $request = new Request('POST', $this->getServerUrl('/api/test'), [
            'Content-Type' => 'application/json',
        ], '{"data":"test"}');

        $response = $client->sendRequest($request);

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('POST', $body['method']);
        $this->assertEquals('{"data":"test"}', $body['body']);
    }

    public function test_it_throws_exception_on_connection_error()
    {
        $this->expectException(NetworkException::class);

        $client = new CurlClient(['timeout' => 1]);
        $request = new Request('GET', 'http://localhost:59999/not-exist');

        $client->sendRequest($request);
    }

    public function test_it_does_not_follow_redirects()
    {
        $client = new CurlClient;
        $request = new Request('GET', $this->getServerUrl('/redirect'));

        $response = $client->sendRequest($request);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/redirected', $response->getHeaderLine('Location'));
    }

    public function test_it_handles_error_status_codes()
    {
        $client = new CurlClient;

        $response404 = $client->sendRequest(new Request('GET', $this->getServerUrl('/status/404')));
        $this->assertEquals(404, $response404->getStatusCode());

        $response500 = $client->sendRequest(new Request('GET', $this->getServerUrl('/status/500')));
        $this->assertEquals(500, $response500->getStatusCode());
    }

    public function test_it_sends_request_headers()
    {
        $client = new CurlClient;
        $request = new Request('GET', $this->getServerUrl('/'), [
            'Accept' => 'application/json',
            'X-Custom-Header' => 'custom-value',
        ]);

        $response = $client->sendRequest($request);
        $body = json_decode((string) $response->getBody(), true);

        $this->assertEquals('application/json', $body['headers']['ACCEPT']);
        $this->assertEquals('custom-value', $body['headers']['X-CUSTOM-HEADER']);
    }

    public function test_it_receives_response_headers()
    {
        $client = new CurlClient;
        $request = new Request('GET', $this->getServerUrl('/headers'));

        $response = $client->sendRequest($request);

        $this->assertEquals('test-value', $response->getHeaderLine('X-Custom-Header'));
    }

    public function test_it_handles_multiple_headers_with_same_name()
    {
        $client = new CurlClient;
        $request = new Request('GET', $this->getServerUrl('/headers'));

        $response = $client->sendRequest($request);

        $cookies = $response->getHeader('Set-Cookie');
        $this->assertCount(2, $cookies);
        $this->assertContains('a=1', $cookies);
        $this->assertContains('b=2', $cookies);
    }

    public function test_it_respects_timeout_option()
    {
        $client = new CurlClient(['timeout' => 1]);
        $request = new Request('GET', $this->getServerUrl('/delay'));

        $this->expectException(NetworkException::class);

        $client->sendRequest($request);
    }
}
