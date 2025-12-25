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

    public function test_it_auto_decodes_gzip_response_by_default()
    {
        $client = new CurlClient;
        $request = new Request('GET', $this->getServerUrl('/gzip'), [
            'Accept-Encoding' => 'gzip',
        ]);

        $response = $client->sendRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEmpty($response->getHeaderLine('Content-Encoding'));

        // Body should be automatically decompressed
        $body = json_decode((string) $response->getBody(), true);
        $this->assertStringContainsString('gzip', $body['accept_encoding']);
    }

    public function test_it_preserves_gzip_response_when_decode_content_is_false()
    {
        $client = new CurlClient(['decode_content' => false]);
        $request = new Request('GET', $this->getServerUrl('/gzip'), [
            'Accept-Encoding' => 'gzip',
        ]);

        $response = $client->sendRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('gzip', $response->getHeaderLine('Content-Encoding'));

        // Verify body is gzip compressed (starts with gzip magic number 1f8b)
        $body = (string) $response->getBody();
        $this->assertStringStartsWith("\x1f\x8b", $body);
    }

    public function test_it_auto_decodes_deflate_response_by_default()
    {
        $client = new CurlClient;
        $request = new Request('GET', $this->getServerUrl('/deflate'), [
            'Accept-Encoding' => 'deflate',
        ]);

        $response = $client->sendRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEmpty($response->getHeaderLine('Content-Encoding'));

        // Body should be automatically decompressed
        $body = json_decode((string) $response->getBody(), true);
        $this->assertStringContainsString('deflate', $body['accept_encoding']);
    }

    public function test_it_preserves_deflate_response_when_decode_content_is_false()
    {
        $client = new CurlClient(['decode_content' => false]);
        $request = new Request('GET', $this->getServerUrl('/deflate'), [
            'Accept-Encoding' => 'deflate',
        ]);

        $response = $client->sendRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('deflate', $response->getHeaderLine('Content-Encoding'));

        // Verify body is deflate compressed
        $body = (string) $response->getBody();
        $decompressed = json_decode(gzinflate($body), true);
        $this->assertStringContainsString('deflate', $decompressed['accept_encoding']);
    }

    public function test_it_handles_lowercase_content_encoding_header()
    {
        $client = new CurlClient;
        $request = new Request('GET', $this->getServerUrl('/gzip-lowercase'), [
            'Accept-Encoding' => 'gzip',
        ]);

        $response = $client->sendRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEmpty($response->getHeaderLine('Content-Encoding'));
        $this->assertEmpty($response->getHeaderLine('content-encoding'));

        // Body should be automatically decompressed
        $body = json_decode((string) $response->getBody(), true);
        $this->assertStringContainsString('gzip', $body['accept_encoding']);
    }

    public function test_it_respects_connect_timeout_option()
    {
        // Use a non-routable IP to test connection timeout
        $client = new CurlClient(['connect_timeout' => 1, 'timeout' => 30]);
        $request = new Request('GET', 'http://10.255.255.1/');

        $this->expectException(NetworkException::class);

        $start = microtime(true);
        try {
            $client->sendRequest($request);
        } finally {
            $elapsed = microtime(true) - $start;
            // Should timeout around 1 second, not 30 seconds
            $this->assertLessThan(5, $elapsed);
        }
    }

    public function test_it_respects_proxy_option()
    {
        // Test that proxy option is accepted (will fail to connect but shouldn't error on option)
        $client = new CurlClient(['proxy' => 'http://127.0.0.1:9999', 'timeout' => 1]);
        $request = new Request('GET', $this->getServerUrl('/'));

        $this->expectException(NetworkException::class);

        $client->sendRequest($request);
    }
}
