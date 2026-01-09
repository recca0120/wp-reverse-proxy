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
            'ProxyHeaders' => 'Add X-Forwarded-* headers to the proxied request.',
            'SetHost' => 'Override the Host header.',
            'Timeout' => 'Set request timeout.',
            'AllowMethods' => 'Restrict allowed HTTP methods.',
            'RewritePath' => 'Rewrite the request path using pattern.',
            'Cors' => 'Add CORS headers to the response.',
            'RateLimiting' => 'Limit the number of requests.',
            'Caching' => 'Cache responses.',
            'Retry' => 'Retry failed requests.',
            'CircuitBreaker' => 'Prevent cascading failures.',
            'IpFilter' => 'Filter requests by IP address.',
            'RequestId' => 'Add a unique request ID header.',
            'Fallback' => 'Provide fallback response on failure.',
            'RewriteBody' => 'Rewrite response body content.',
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
     * Constructor: __construct(array $origins, array $methods, array $headers, bool $credentials, int $maxAge)
     */
    public function test_cors_fields()
    {
        $available = $this->registry->getAvailable();
        $cors = $available['Cors'];

        $this->assertCount(5, $cors['fields']);

        $fieldNames = array_column($cors['fields'], 'name');
        $this->assertContains('origins', $fieldNames);
        $this->assertContains('methods', $fieldNames);
        $this->assertContains('headers', $fieldNames);
        $this->assertContains('credentials', $fieldNames);
        $this->assertContains('maxAge', $fieldNames);

        // Check types
        $originsField = $this->findField($cors['fields'], 'origins');
        $this->assertEquals('repeater', $originsField['type']);
        $this->assertEquals('*', $originsField['default']);

        $headersField = $this->findField($cors['fields'], 'headers');
        $this->assertEquals('repeater', $headersField['type']);

        $methodsField = $this->findField($cors['fields'], 'methods');
        $this->assertEquals('checkboxes', $methodsField['type']);

        $credentialsField = $this->findField($cors['fields'], 'credentials');
        $this->assertEquals('checkbox', $credentialsField['type']);
        $this->assertFalse($credentialsField['default']);

        $maxAgeField = $this->findField($cors['fields'], 'maxAge');
        $this->assertEquals('number', $maxAgeField['type']);
        $this->assertEquals(0, $maxAgeField['default']);
    }

    /**
     * Test CircuitBreaker fields match constructor parameters.
     * Constructor: __construct(string $serviceName, int $threshold, int $timeout, array $statusCodes)
     */
    public function test_circuit_breaker_fields()
    {
        $available = $this->registry->getAvailable();
        $cb = $available['CircuitBreaker'];

        $this->assertCount(4, $cb['fields']);

        $fieldNames = array_column($cb['fields'], 'name');
        $this->assertContains('serviceName', $fieldNames);
        $this->assertContains('threshold', $fieldNames);
        $this->assertContains('timeout', $fieldNames);
        $this->assertContains('statusCodes', $fieldNames);

        // serviceName should be required
        $serviceNameField = $this->findField($cb['fields'], 'serviceName');
        $this->assertTrue($serviceNameField['required']);

        // threshold should have default 5
        $thresholdField = $this->findField($cb['fields'], 'threshold');
        $this->assertEquals(5, $thresholdField['default']);

        // timeout should have default 60
        $timeoutField = $this->findField($cb['fields'], 'timeout');
        $this->assertEquals(60, $timeoutField['default']);

        // statusCodes should be repeater
        $statusCodesField = $this->findField($cb['fields'], 'statusCodes');
        $this->assertEquals('repeater', $statusCodesField['type']);
        $this->assertEquals('number', $statusCodesField['inputType']);
    }

    /**
     * Test RateLimiting fields match constructor parameters.
     * Constructor: __construct(int $limit, int $window, ?callable $keyGenerator = null)
     */
    public function test_rate_limiting_fields()
    {
        $available = $this->registry->getAvailable();
        $rl = $available['RateLimiting'];

        $this->assertCount(2, $rl['fields']);

        $limitField = $this->findField($rl['fields'], 'limit');
        $this->assertEquals('number', $limitField['type']);
        $this->assertEquals('Max requests allowed', $limitField['label']);
        $this->assertEquals(100, $limitField['default']);

        $windowField = $this->findField($rl['fields'], 'window');
        $this->assertEquals('number', $windowField['type']);
        $this->assertEquals('Time window in seconds', $windowField['label']);
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
     * Constructor: __construct(int $retries, array $methods, array $statusCodes)
     */
    public function test_retry_fields()
    {
        $available = $this->registry->getAvailable();
        $retry = $available['Retry'];

        $this->assertCount(3, $retry['fields']);

        $fieldNames = array_column($retry['fields'], 'name');
        $this->assertContains('retries', $fieldNames);
        $this->assertContains('methods', $fieldNames);
        $this->assertContains('statusCodes', $fieldNames);

        $retriesField = $this->findField($retry['fields'], 'retries');
        $this->assertEquals('number', $retriesField['type']);
        $this->assertEquals(3, $retriesField['default']);

        $methodsField = $this->findField($retry['fields'], 'methods');
        $this->assertEquals('checkboxes', $methodsField['type']);

        $statusCodesField = $this->findField($retry['fields'], 'statusCodes');
        $this->assertEquals('repeater', $statusCodesField['type']);
        $this->assertEquals('number', $statusCodesField['inputType']);
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
     * Test RequestId fields match constructor: __construct(string $header = 'X-Request-ID')
     */
    public function test_request_id_fields()
    {
        $available = $this->registry->getAvailable();
        $requestId = $available['RequestId'];

        $this->assertCount(1, $requestId['fields']);

        $headerField = $requestId['fields'][0];
        $this->assertEquals('header', $headerField['name']);
        $this->assertEquals('text', $headerField['type']);
        $this->assertEquals('X-Request-ID', $headerField['default']);
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
     * Test AllowMethods fields match constructor: __construct(string ...$methods)
     */
    public function test_allow_methods_fields()
    {
        $available = $this->registry->getAvailable();
        $allowMethods = $available['AllowMethods'];

        $this->assertCount(1, $allowMethods['fields']);

        $methodsField = $allowMethods['fields'][0];
        $this->assertEquals('methods', $methodsField['name']);
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
     * Test middlewares without fields (ProxyHeaders has @type block).
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
