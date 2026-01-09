<?php

namespace Recca0120\ReverseProxy\Tests\Unit\WordPress\Admin;

use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Middleware\ErrorHandling;
use Recca0120\ReverseProxy\Middleware\SanitizeHeaders;
use Recca0120\ReverseProxy\Routing\MiddlewareManager;
use Recca0120\ReverseProxy\WordPress\Admin\MiddlewareRegistry;

/**
 * Tests for MiddlewareRegistry.
 * These tests verify the @UIField annotations match constructor parameters.
 */
class MiddlewareRegistryTest extends TestCase
{
    /** @var MiddlewareManager */
    private $manager;

    /** @var MiddlewareRegistry */
    private $registry;

    protected function setUp(): void
    {
        $this->manager = new MiddlewareManager();
        $this->registry = new MiddlewareRegistry($this->manager);
    }

    public function test_get_all_returns_middlewares_excluding_global()
    {
        $this->manager->registerGlobalMiddleware([
            new SanitizeHeaders(),
            new ErrorHandling(),
        ]);

        $registry = new MiddlewareRegistry($this->manager);
        $available = $registry->getAvailable();

        $this->assertArrayHasKey('SetHost', $available);
        $this->assertArrayHasKey('Timeout', $available);
        $this->assertArrayHasKey('Cors', $available);
        $this->assertArrayHasKey('ProxyHeaders', $available);

        // Global middlewares should be excluded
        $this->assertArrayNotHasKey('SanitizeHeaders', $available);
        $this->assertArrayNotHasKey('ErrorHandling', $available);
    }

    /**
     * Test descriptions match original definitions.
     */
    public function test_descriptions_match_expected()
    {
        $available = $this->registry->getAvailable();

        $expectedDescriptions = [
            'ProxyHeaders' => 'Add X-Forwarded-* headers to the proxied request',
            'SetHost' => 'Override the Host header',
            'Timeout' => 'Set request timeout',
            'AllowMethods' => 'Restrict allowed HTTP methods',
            'RewritePath' => 'Rewrite the request path using pattern',
            'Cors' => 'Add CORS headers to the response',
            'RateLimiting' => 'Limit the number of requests',
            'Caching' => 'Cache responses',
            'Retry' => 'Retry failed requests',
            'CircuitBreaker' => 'Prevent cascading failures',
            'IpFilter' => 'Filter requests by IP address',
            'RequestId' => 'Add a unique request ID header',
            'Fallback' => 'Provide fallback response on failure',
            'RewriteBody' => 'Rewrite response body content',
        ];

        foreach ($expectedDescriptions as $name => $expectedDescription) {
            $this->assertArrayHasKey($name, $available, "Middleware {$name} should exist");
            $this->assertEquals(
                $expectedDescription,
                $available[$name]['description'],
                "Description for {$name} should match"
            );
        }
    }

    /**
     * Test labels match expected format.
     */
    public function test_labels_match_expected()
    {
        $available = $this->registry->getAvailable();

        $expectedLabels = [
            'ProxyHeaders' => 'Proxy Headers',
            'SetHost' => 'Set Host',
            'Timeout' => 'Timeout',
            'AllowMethods' => 'Allow Methods',
            'RewritePath' => 'Rewrite Path',
            'Cors' => 'CORS',
            'RateLimiting' => 'Rate Limiting',
            'Caching' => 'Caching',
            'Retry' => 'Retry',
            'CircuitBreaker' => 'Circuit Breaker',
            'IpFilter' => 'IP Filter',
            'RequestId' => 'Request ID',
            'Fallback' => 'Fallback',
            'RewriteBody' => 'Rewrite Body',
        ];

        foreach ($expectedLabels as $name => $expectedLabel) {
            $this->assertEquals(
                $expectedLabel,
                $available[$name]['label'],
                "Label for {$name} should match"
            );
        }
    }

    /**
     * Test SetHost fields match constructor parameters.
     */
    public function test_set_host_fields()
    {
        $available = $this->registry->getAvailable();
        $setHost = $available['SetHost'];

        $this->assertCount(1, $setHost['fields']);

        $hostField = $setHost['fields'][0];
        $this->assertEquals('host', $hostField['name']);
        $this->assertEquals('text', $hostField['type']);
        $this->assertEquals('Host', $hostField['label']);
        $this->assertTrue($hostField['required']);
    }

    /**
     * Test Timeout fields match constructor: __construct(int $seconds = 30)
     */
    public function test_timeout_fields()
    {
        $available = $this->registry->getAvailable();
        $timeout = $available['Timeout'];

        $this->assertCount(1, $timeout['fields']);

        $field = $timeout['fields'][0];
        $this->assertEquals('seconds', $field['name']);
        $this->assertEquals('number', $field['type']);
        $this->assertEquals('Timeout (seconds)', $field['label']);
        $this->assertEquals(30, $field['default']);
    }

    /**
     * Test Cors fields match constructor parameters.
     * Constructor: __construct(array $allowedOrigins, array $allowedMethods, array $allowedHeaders, bool $allowCredentials, int $maxAge)
     */
    public function test_cors_fields()
    {
        $available = $this->registry->getAvailable();
        $cors = $available['Cors'];

        $this->assertCount(5, $cors['fields']);

        $fieldNames = array_column($cors['fields'], 'name');
        $this->assertContains('allowedOrigins', $fieldNames);
        $this->assertContains('allowedMethods', $fieldNames);
        $this->assertContains('allowedHeaders', $fieldNames);
        $this->assertContains('allowCredentials', $fieldNames);
        $this->assertContains('maxAge', $fieldNames);

        // Check types
        $allowedOriginsField = $this->findField($cors['fields'], 'allowedOrigins');
        $this->assertEquals('repeater', $allowedOriginsField['type']);
        $this->assertEquals('*', $allowedOriginsField['default']);

        $allowedHeadersField = $this->findField($cors['fields'], 'allowedHeaders');
        $this->assertEquals('repeater', $allowedHeadersField['type']);

        $allowedMethodsField = $this->findField($cors['fields'], 'allowedMethods');
        $this->assertEquals('checkboxes', $allowedMethodsField['type']);

        $allowCredentialsField = $this->findField($cors['fields'], 'allowCredentials');
        $this->assertEquals('checkbox', $allowCredentialsField['type']);
        $this->assertFalse($allowCredentialsField['default']);

        $maxAgeField = $this->findField($cors['fields'], 'maxAge');
        $this->assertEquals('number', $maxAgeField['type']);
        $this->assertEquals(0, $maxAgeField['default']);
    }

    /**
     * Test CircuitBreaker fields match constructor parameters.
     * Constructor: __construct(string $serviceName, int $failureThreshold, int $resetTimeout, array $failureStatusCodes)
     */
    public function test_circuit_breaker_fields()
    {
        $available = $this->registry->getAvailable();
        $cb = $available['CircuitBreaker'];

        $this->assertCount(4, $cb['fields']);

        $fieldNames = array_column($cb['fields'], 'name');
        $this->assertContains('serviceName', $fieldNames);
        $this->assertContains('failureThreshold', $fieldNames);
        $this->assertContains('resetTimeout', $fieldNames);
        $this->assertContains('failureStatusCodes', $fieldNames);

        // serviceName should be required
        $serviceNameField = $this->findField($cb['fields'], 'serviceName');
        $this->assertTrue($serviceNameField['required']);

        // failureThreshold should have default 5
        $failureField = $this->findField($cb['fields'], 'failureThreshold');
        $this->assertEquals(5, $failureField['default']);

        // resetTimeout should have default 60
        $resetField = $this->findField($cb['fields'], 'resetTimeout');
        $this->assertEquals(60, $resetField['default']);

        // failureStatusCodes should be repeater
        $statusCodesField = $this->findField($cb['fields'], 'failureStatusCodes');
        $this->assertEquals('repeater', $statusCodesField['type']);
        $this->assertEquals('number', $statusCodesField['inputType']);
    }

    /**
     * Test RateLimiting fields match constructor parameters.
     * Constructor: __construct(int $maxRequests, int $windowSeconds, ?callable $keyGenerator = null)
     */
    public function test_rate_limiting_fields()
    {
        $available = $this->registry->getAvailable();
        $rl = $available['RateLimiting'];

        $this->assertCount(2, $rl['fields']);

        $maxRequestsField = $this->findField($rl['fields'], 'maxRequests');
        $this->assertEquals('number', $maxRequestsField['type']);
        $this->assertEquals('Max Requests', $maxRequestsField['label']);
        $this->assertEquals(100, $maxRequestsField['default']);

        $windowField = $this->findField($rl['fields'], 'windowSeconds');
        $this->assertEquals('number', $windowField['type']);
        $this->assertEquals('Window (seconds)', $windowField['label']);
        $this->assertEquals(60, $windowField['default']);
    }

    /**
     * Test Caching fields match constructor: __construct(int $ttl = 300)
     */
    public function test_caching_fields()
    {
        $available = $this->registry->getAvailable();
        $caching = $available['Caching'];

        $this->assertCount(1, $caching['fields']);

        $ttlField = $caching['fields'][0];
        $this->assertEquals('ttl', $ttlField['name']);
        $this->assertEquals('number', $ttlField['type']);
        $this->assertEquals('TTL (seconds)', $ttlField['label']);
        $this->assertEquals(300, $ttlField['default']);
    }

    /**
     * Test Retry fields match constructor parameters.
     * Constructor: __construct(int $maxRetries, array $retryableMethods, array $retryableStatusCodes)
     */
    public function test_retry_fields()
    {
        $available = $this->registry->getAvailable();
        $retry = $available['Retry'];

        $this->assertCount(3, $retry['fields']);

        $fieldNames = array_column($retry['fields'], 'name');
        $this->assertContains('maxRetries', $fieldNames);
        $this->assertContains('retryableMethods', $fieldNames);
        $this->assertContains('retryableStatusCodes', $fieldNames);

        $maxRetriesField = $this->findField($retry['fields'], 'maxRetries');
        $this->assertEquals('number', $maxRetriesField['type']);
        $this->assertEquals(3, $maxRetriesField['default']);

        $retryableMethodsField = $this->findField($retry['fields'], 'retryableMethods');
        $this->assertEquals('checkboxes', $retryableMethodsField['type']);

        $retryableStatusCodesField = $this->findField($retry['fields'], 'retryableStatusCodes');
        $this->assertEquals('repeater', $retryableStatusCodesField['type']);
        $this->assertEquals('number', $retryableStatusCodesField['inputType']);
    }

    /**
     * Test IpFilter fields match constructor parameters.
     * Constructor: __construct(string $modeOrIp, string ...$ips)
     */
    public function test_ip_filter_fields()
    {
        $available = $this->registry->getAvailable();
        $ipFilter = $available['IpFilter'];

        $this->assertCount(2, $ipFilter['fields']);

        $fieldNames = array_column($ipFilter['fields'], 'name');
        $this->assertContains('modeOrIp', $fieldNames);
        $this->assertContains('ips', $fieldNames);

        $modeField = $this->findField($ipFilter['fields'], 'modeOrIp');
        $this->assertEquals('select', $modeField['type']);
        $this->assertEquals('allow', $modeField['default']);

        $ipsField = $this->findField($ipFilter['fields'], 'ips');
        $this->assertEquals('repeater', $ipsField['type']);
    }

    /**
     * Test RequestId fields match constructor: __construct(string $headerName = 'X-Request-ID')
     */
    public function test_request_id_fields()
    {
        $available = $this->registry->getAvailable();
        $requestId = $available['RequestId'];

        $this->assertCount(1, $requestId['fields']);

        $headerNameField = $requestId['fields'][0];
        $this->assertEquals('headerName', $headerNameField['name']);
        $this->assertEquals('text', $headerNameField['type']);
        $this->assertEquals('X-Request-ID', $headerNameField['default']);
    }

    /**
     * Test Fallback fields match constructor: __construct(int ...$statusCodes)
     */
    public function test_fallback_fields()
    {
        $available = $this->registry->getAvailable();
        $fallback = $available['Fallback'];

        $this->assertCount(1, $fallback['fields']);

        $statusCodesField = $fallback['fields'][0];
        $this->assertEquals('statusCodes', $statusCodesField['name']);
        $this->assertEquals('repeater', $statusCodesField['type']);
        $this->assertEquals('number', $statusCodesField['inputType']);
        $this->assertEquals('404', $statusCodesField['default']);
    }

    /**
     * Test RewriteBody fields match constructor: __construct(array $replacements)
     */
    public function test_rewrite_body_fields()
    {
        $available = $this->registry->getAvailable();
        $rewriteBody = $available['RewriteBody'];

        $this->assertCount(1, $rewriteBody['fields']);

        $replacementsField = $rewriteBody['fields'][0];
        $this->assertEquals('replacements', $replacementsField['name']);
        $this->assertEquals('keyvalue', $replacementsField['type']);
        $this->assertEquals('Pattern (regex)', $replacementsField['keyLabel']);
        $this->assertEquals('Replacement', $replacementsField['valueLabel']);
    }

    /**
     * Test AllowMethods fields match constructor: __construct(string ...$allowedMethods)
     */
    public function test_allow_methods_fields()
    {
        $available = $this->registry->getAvailable();
        $allowMethods = $available['AllowMethods'];

        $this->assertCount(1, $allowMethods['fields']);

        $methodsField = $allowMethods['fields'][0];
        $this->assertEquals('allowedMethods', $methodsField['name']);
        $this->assertEquals('checkboxes', $methodsField['type']);
        $this->assertNotEmpty($methodsField['options']);
    }

    /**
     * Test RewritePath fields match constructor: __construct(string $replacement)
     */
    public function test_rewrite_path_fields()
    {
        $available = $this->registry->getAvailable();
        $rewritePath = $available['RewritePath'];

        $this->assertCount(1, $rewritePath['fields']);

        $replacementField = $rewritePath['fields'][0];
        $this->assertEquals('replacement', $replacementField['name']);
        $this->assertEquals('text', $replacementField['type']);
        $this->assertTrue($replacementField['required']);
    }

    /**
     * Test middlewares without fields (ProxyHeaders has @UINoFields).
     */
    public function test_middlewares_without_fields()
    {
        $available = $this->registry->getAvailable();

        $this->assertEmpty($available['ProxyHeaders']['fields']);
    }

    /**
     * Test get_manager returns injected manager.
     */
    public function test_get_manager_returns_injected_manager()
    {
        $this->assertSame($this->manager, $this->registry->getManager());
    }

    private function findField(array $fields, string $name): ?array
    {
        foreach ($fields as $field) {
            if ($field['name'] === $name) {
                return $field;
            }
        }

        return null;
    }
}
