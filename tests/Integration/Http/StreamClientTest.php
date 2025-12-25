<?php

namespace ReverseProxy\Tests\Integration\Http;

use Nyholm\Psr7\Request;
use ReverseProxy\Exceptions\NetworkException;
use ReverseProxy\Http\StreamClient;

class StreamClientTest extends HttpClientTestCase
{
    public function test_it_sends_get_request()
    {
        $client = new StreamClient;
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
        $client = new StreamClient;
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

        $client = new StreamClient(['timeout' => 1]);
        $request = new Request('GET', 'http://localhost:59999/not-exist');

        $client->sendRequest($request);
    }

    public function test_it_does_not_follow_redirects()
    {
        $client = new StreamClient;
        $request = new Request('GET', $this->getServerUrl('/redirect'));

        $response = $client->sendRequest($request);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/redirected', $response->getHeaderLine('Location'));
    }

    public function test_it_handles_error_status_codes()
    {
        $client = new StreamClient;

        $response404 = $client->sendRequest(new Request('GET', $this->getServerUrl('/status/404')));
        $this->assertEquals(404, $response404->getStatusCode());

        $response500 = $client->sendRequest(new Request('GET', $this->getServerUrl('/status/500')));
        $this->assertEquals(500, $response500->getStatusCode());
    }

    public function test_it_sends_request_headers()
    {
        $client = new StreamClient;
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
        $client = new StreamClient;
        $request = new Request('GET', $this->getServerUrl('/headers'));

        $response = $client->sendRequest($request);

        $this->assertEquals('test-value', $response->getHeaderLine('X-Custom-Header'));
    }

    public function test_it_handles_multiple_headers_with_same_name()
    {
        $client = new StreamClient;
        $request = new Request('GET', $this->getServerUrl('/headers'));

        $response = $client->sendRequest($request);

        $cookies = $response->getHeader('Set-Cookie');
        $this->assertCount(2, $cookies);
        $this->assertContains('a=1', $cookies);
        $this->assertContains('b=2', $cookies);
    }

    public function test_it_respects_timeout_option()
    {
        $client = new StreamClient(['timeout' => 1]);
        $request = new Request('GET', $this->getServerUrl('/delay'));

        $this->expectException(NetworkException::class);

        $client->sendRequest($request);
    }

    public function test_it_resolves_hostname_to_specific_ip()
    {
        $client = new StreamClient([
            'resolve' => ['test.example.com:'.self::$serverPort.':127.0.0.1'],
        ]);

        $request = new Request('GET', 'http://test.example.com:'.self::$serverPort.'/api/test', [
            'Host' => 'test.example.com',
        ]);

        $response = $client->sendRequest($request);

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('/api/test', $body['uri']);
        $this->assertEquals('test.example.com', $body['headers']['HOST']);
    }
}
