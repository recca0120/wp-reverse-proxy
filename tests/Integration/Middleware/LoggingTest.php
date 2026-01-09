<?php

namespace Recca0120\ReverseProxy\Tests\Integration\Middleware;

use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Response;
use Recca0120\ReverseProxy\Routing\Route;
use WP_UnitTestCase;

class LoggingTest extends WP_UnitTestCase
{
    /** @var MockClient */
    private $mockClient;

    /** @var array */
    private $loggedMessages = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = new MockClient();
        $this->loggedMessages = [];

        add_filter('reverse_proxy_http_client', function () {
            return $this->mockClient;
        });

        add_filter('reverse_proxy_should_exit', '__return_false');

        add_action('reverse_proxy_log', function ($level, $message, $context) {
            $this->loggedMessages[] = [
                'level' => $level,
                'message' => $message,
                'context' => $context,
            ];
        }, 10, 3);
    }

    protected function tearDown(): void
    {
        remove_all_filters('reverse_proxy_routes');
        remove_all_filters('reverse_proxy_http_client');
        remove_all_filters('reverse_proxy_should_exit');
        remove_all_filters('reverse_proxy_response');
        remove_all_filters('reverse_proxy_default_middlewares');
        remove_all_actions('reverse_proxy_log');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        parent::tearDown();
    }

    public function test_it_logs_request_info()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com'),
        ]);
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $this->whenRequesting('/api/users');

        $requestLog = array_filter($this->loggedMessages, function ($log) {
            return $log['level'] === 'info' && strpos($log['message'], 'Proxying request') !== false;
        });

        $this->assertNotEmpty($requestLog);
    }

    public function test_it_logs_response_info()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com'),
        ]);
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $this->whenRequesting('/api/users');

        $responseLog = array_filter($this->loggedMessages, function ($log) {
            return $log['level'] === 'info' && strpos($log['message'], 'response') !== false;
        });

        $this->assertNotEmpty($responseLog);
    }

    public function test_it_logs_status_code()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com'),
        ]);
        $this->mockClient->addResponse(new Response(404, [], '{"error":"Not Found"}'));

        $this->whenRequesting('/api/missing');

        $responseLog = array_values(array_filter($this->loggedMessages, function ($log) {
            return strpos($log['message'], 'response') !== false;
        }));

        $this->assertNotEmpty($responseLog);
        $this->assertEquals(404, $responseLog[0]['context']['status']);
    }

    public function test_logging_is_enabled_by_default()
    {
        $this->givenRoutes([
            new Route('/api/*', 'https://backend.example.com'),
        ]);
        $this->mockClient->addResponse(new Response(200, [], '{}'));

        $this->whenRequesting('/api/users');

        $this->assertNotEmpty($this->loggedMessages);
    }

    private function givenRoutes(array $routes): void
    {
        add_filter('reverse_proxy_routes', function () use ($routes) {
            return $routes;
        });
    }

    private function whenRequesting(string $path): string
    {
        ob_start();
        $this->go_to($path);

        return ob_get_clean();
    }
}
