<?php

namespace ReverseProxy\Http;

// Mock file_get_contents in the same namespace
function file_get_contents($filename, $use_include_path = false, $context = null)
{
    global $http_response_header;

    if (StreamHttpClientTestHelper::$shouldFail) {
        return false;
    }

    StreamHttpClientTestHelper::$lastContext = $context;
    $http_response_header = StreamHttpClientTestHelper::$responseHeaders;

    return StreamHttpClientTestHelper::$responseBody;
}

function stream_context_create($options = [])
{
    StreamHttpClientTestHelper::$contextOptions = $options;
    return 'mock_context';
}

function error_get_last()
{
    return StreamHttpClientTestHelper::$lastError;
}

class StreamHttpClientTestHelper
{
    public static $contextOptions = [];
    public static $lastContext = null;
    public static $responseHeaders = [];
    public static $responseBody = '';
    public static $shouldFail = false;
    public static $lastError = null;

    public static function reset()
    {
        self::$contextOptions = [];
        self::$lastContext = null;
        self::$responseHeaders = [];
        self::$responseBody = '';
        self::$shouldFail = false;
        self::$lastError = null;
    }

    public static function setResponse(int $statusCode, array $headers, string $body)
    {
        self::$responseHeaders = ["HTTP/1.1 {$statusCode} OK"];
        foreach ($headers as $name => $value) {
            self::$responseHeaders[] = "{$name}: {$value}";
        }
        self::$responseBody = $body;
    }

    public static function setError(string $message)
    {
        self::$shouldFail = true;
        self::$lastError = ['message' => $message];
    }
}

namespace ReverseProxy\Tests\Unit;

use Nyholm\Psr7\Request;
use PHPUnit\Framework\TestCase;
use ReverseProxy\Http\NetworkException;
use ReverseProxy\Http\StreamHttpClient;
use ReverseProxy\Http\StreamHttpClientTestHelper;

class StreamHttpClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        StreamHttpClientTestHelper::reset();
    }

    public function test_it_sends_get_request()
    {
        StreamHttpClientTestHelper::setResponse(200, ['Content-Type' => 'application/json'], '{"success":true}');

        $client = new StreamHttpClient();
        $request = new Request('GET', 'https://example.com/api');

        $response = $client->sendRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('{"success":true}', (string) $response->getBody());
    }

    public function test_it_sends_post_request_with_body()
    {
        StreamHttpClientTestHelper::setResponse(201, [], '');

        $client = new StreamHttpClient();
        $request = new Request('POST', 'https://example.com/api', [
            'Content-Type' => 'application/json',
        ], '{"data":"test"}');

        $response = $client->sendRequest($request);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('POST', StreamHttpClientTestHelper::$contextOptions['http']['method']);
        $this->assertEquals('{"data":"test"}', StreamHttpClientTestHelper::$contextOptions['http']['content']);
    }

    public function test_it_throws_exception_on_error()
    {
        StreamHttpClientTestHelper::setError('Connection timed out');

        $this->expectException(NetworkException::class);
        $this->expectExceptionMessage('Connection timed out');

        $client = new StreamHttpClient();
        $request = new Request('GET', 'https://example.com/api');

        $client->sendRequest($request);
    }

    public function test_it_does_not_follow_redirects()
    {
        StreamHttpClientTestHelper::setResponse(302, ['Location' => 'https://example.com/new'], '');

        $client = new StreamHttpClient();
        $request = new Request('GET', 'https://example.com/old');

        $response = $client->sendRequest($request);

        $this->assertEquals(0, StreamHttpClientTestHelper::$contextOptions['http']['follow_location']);
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function test_it_uses_custom_timeout()
    {
        StreamHttpClientTestHelper::setResponse(200, [], '');

        $client = new StreamHttpClient(['timeout' => 60]);
        $request = new Request('GET', 'https://example.com/api');

        $client->sendRequest($request);

        $this->assertEquals(60, StreamHttpClientTestHelper::$contextOptions['http']['timeout']);
    }

    public function test_it_prepares_headers_correctly()
    {
        StreamHttpClientTestHelper::setResponse(200, [], '');

        $client = new StreamHttpClient();
        $request = new Request('GET', 'https://example.com/api', [
            'Accept' => 'application/json',
            'X-Custom' => 'value',
        ]);

        $client->sendRequest($request);

        $headers = StreamHttpClientTestHelper::$contextOptions['http']['header'];
        $this->assertStringContainsString('Accept: application/json', $headers);
        $this->assertStringContainsString('X-Custom: value', $headers);
    }

    public function test_it_parses_multiple_headers_with_same_name()
    {
        StreamHttpClientTestHelper::$responseHeaders = [
            'HTTP/1.1 200 OK',
            'Set-Cookie: a=1',
            'Set-Cookie: b=2',
        ];
        StreamHttpClientTestHelper::$responseBody = 'body';

        $client = new StreamHttpClient();
        $request = new Request('GET', 'https://example.com/api');

        $response = $client->sendRequest($request);

        $this->assertEquals(['a=1', 'b=2'], $response->getHeader('Set-Cookie'));
    }

    public function test_it_ignores_errors_to_get_response_body()
    {
        StreamHttpClientTestHelper::setResponse(404, [], 'Not Found');

        $client = new StreamHttpClient();
        $request = new Request('GET', 'https://example.com/api');

        $response = $client->sendRequest($request);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertTrue(StreamHttpClientTestHelper::$contextOptions['http']['ignore_errors']);
    }
}
