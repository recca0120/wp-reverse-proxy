<?php

namespace ReverseProxy\Http;

// Mock curl functions in the same namespace
function curl_init()
{
    return CurlHttpClientTestHelper::$mockHandle;
}

function curl_setopt_array($ch, $options)
{
    CurlHttpClientTestHelper::$options = $options;

    return true;
}

function curl_setopt($ch, $option, $value)
{
    CurlHttpClientTestHelper::$options[$option] = $value;

    return true;
}

function curl_exec($ch)
{
    return CurlHttpClientTestHelper::$response;
}

function curl_error($ch)
{
    return CurlHttpClientTestHelper::$error;
}

function curl_getinfo($ch, $option = null)
{
    if ($option === CURLINFO_HEADER_SIZE) {
        return CurlHttpClientTestHelper::$headerSize;
    }
    if ($option === CURLINFO_HTTP_CODE) {
        return CurlHttpClientTestHelper::$statusCode;
    }

    return null;
}

function curl_close($ch)
{
    return true;
}

class CurlHttpClientTestHelper
{
    public static $mockHandle = 'mock_handle';

    public static $options = [];

    public static $response = '';

    public static $error = '';

    public static $headerSize = 0;

    public static $statusCode = 200;

    public static function reset()
    {
        self::$options = [];
        self::$response = '';
        self::$error = '';
        self::$headerSize = 0;
        self::$statusCode = 200;
    }

    public static function setResponse(int $statusCode, array $headers, string $body)
    {
        $headerString = "HTTP/1.1 {$statusCode} OK\r\n";
        foreach ($headers as $name => $value) {
            $headerString .= "{$name}: {$value}\r\n";
        }
        $headerString .= "\r\n";

        self::$statusCode = $statusCode;
        self::$headerSize = strlen($headerString);
        self::$response = $headerString.$body;
    }

    public static function setError(string $error)
    {
        self::$response = false;
        self::$error = $error;
    }
}

namespace ReverseProxy\Tests\Unit;

use Nyholm\Psr7\Request;
use PHPUnit\Framework\TestCase;
use ReverseProxy\Http\CurlHttpClient;
use ReverseProxy\Http\CurlHttpClientTestHelper;
use ReverseProxy\Http\NetworkException;

class CurlHttpClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CurlHttpClientTestHelper::reset();
    }

    public function test_it_sends_get_request()
    {
        CurlHttpClientTestHelper::setResponse(200, ['Content-Type' => 'application/json'], '{"success":true}');

        $client = new CurlHttpClient;
        $request = new Request('GET', 'https://example.com/api');

        $response = $client->sendRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('{"success":true}', (string) $response->getBody());
    }

    public function test_it_sends_post_request_with_body()
    {
        CurlHttpClientTestHelper::setResponse(201, [], '');

        $client = new CurlHttpClient;
        $request = new Request('POST', 'https://example.com/api', [
            'Content-Type' => 'application/json',
        ], '{"data":"test"}');

        $response = $client->sendRequest($request);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('{"data":"test"}', CurlHttpClientTestHelper::$options[CURLOPT_POSTFIELDS]);
    }

    public function test_it_throws_exception_on_curl_error()
    {
        CurlHttpClientTestHelper::setError('Connection timed out');

        $this->expectException(NetworkException::class);
        $this->expectExceptionMessage('Connection timed out');

        $client = new CurlHttpClient;
        $request = new Request('GET', 'https://example.com/api');

        $client->sendRequest($request);
    }

    public function test_it_does_not_follow_redirects()
    {
        CurlHttpClientTestHelper::setResponse(302, ['Location' => 'https://example.com/new'], '');

        $client = new CurlHttpClient;
        $request = new Request('GET', 'https://example.com/old');

        $client->sendRequest($request);

        $this->assertFalse(CurlHttpClientTestHelper::$options[CURLOPT_FOLLOWLOCATION]);
    }

    public function test_it_uses_custom_timeout()
    {
        CurlHttpClientTestHelper::setResponse(200, [], '');

        $client = new CurlHttpClient(['timeout' => 60]);
        $request = new Request('GET', 'https://example.com/api');

        $client->sendRequest($request);

        $this->assertEquals(60, CurlHttpClientTestHelper::$options[CURLOPT_TIMEOUT]);
    }

    public function test_it_prepares_headers_correctly()
    {
        CurlHttpClientTestHelper::setResponse(200, [], '');

        $client = new CurlHttpClient;
        $request = new Request('GET', 'https://example.com/api', [
            'Accept' => 'application/json',
            'X-Custom' => 'value',
        ]);

        $client->sendRequest($request);

        $headers = CurlHttpClientTestHelper::$options[CURLOPT_HTTPHEADER];
        $this->assertContains('Accept: application/json', $headers);
        $this->assertContains('X-Custom: value', $headers);
    }

    public function test_it_parses_multiple_headers_with_same_name()
    {
        $headerString = "HTTP/1.1 200 OK\r\nSet-Cookie: a=1\r\nSet-Cookie: b=2\r\n\r\n";
        CurlHttpClientTestHelper::$statusCode = 200;
        CurlHttpClientTestHelper::$headerSize = strlen($headerString);
        CurlHttpClientTestHelper::$response = $headerString.'body';

        $client = new CurlHttpClient;
        $request = new Request('GET', 'https://example.com/api');

        $response = $client->sendRequest($request);

        $this->assertEquals(['a=1', 'b=2'], $response->getHeader('Set-Cookie'));
    }
}
