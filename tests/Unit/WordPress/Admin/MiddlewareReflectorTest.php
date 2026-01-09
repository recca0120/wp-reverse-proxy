<?php

namespace Recca0120\ReverseProxy\Tests\Unit\WordPress\Admin;

use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Middleware\Cors;
use Recca0120\ReverseProxy\Middleware\SetHost;
use Recca0120\ReverseProxy\Middleware\Timeout;
use Recca0120\ReverseProxy\WordPress\Admin\MiddlewareReflector;

class MiddlewareReflectorTest extends TestCase
{
    /** @var MiddlewareReflector */
    private $reflector;

    protected function setUp(): void
    {
        $this->reflector = new MiddlewareReflector();
    }

    public function test_it_extracts_class_description_from_phpdoc()
    {
        $info = $this->reflector->reflect(Timeout::class);

        $this->assertArrayHasKey('description', $info);
    }

    public function test_it_extracts_constructor_parameters()
    {
        $info = $this->reflector->reflect(Timeout::class);

        $this->assertArrayHasKey('fields', $info);
        $this->assertNotEmpty($info['fields']);
    }

    public function test_it_extracts_parameter_name()
    {
        $info = $this->reflector->reflect(SetHost::class);

        $this->assertCount(1, $info['fields']);
        $this->assertEquals('host', $info['fields'][0]['name']);
    }

    public function test_it_extracts_parameter_type()
    {
        $info = $this->reflector->reflect(Timeout::class);

        $field = $info['fields'][0];
        $this->assertEquals('number', $field['type']);
    }

    public function test_it_extracts_default_value()
    {
        $info = $this->reflector->reflect(Timeout::class);

        $field = $info['fields'][0];
        $this->assertEquals(30, $field['default']);
    }

    public function test_it_marks_required_when_no_default()
    {
        $info = $this->reflector->reflect(SetHost::class);

        $field = $info['fields'][0];
        $this->assertTrue($field['required']);
    }

    public function test_it_generates_label_from_parameter_name()
    {
        $info = $this->reflector->reflect(SetHost::class);

        $field = $info['fields'][0];
        $this->assertEquals('Host', $field['label']);
    }

    public function test_it_generates_label_from_camel_case()
    {
        // Use test fixture to test auto-reflection from parameters
        $info = $this->reflector->reflect(AutoReflectMiddlewareFixture::class);

        $allowedOriginsField = $this->findField($info['fields'], 'allowedOrigins');
        $this->assertEquals('Allowed Origins', $allowedOriginsField['label']);
    }

    public function test_it_maps_string_type_to_text()
    {
        $info = $this->reflector->reflect(SetHost::class);

        $field = $info['fields'][0];
        $this->assertEquals('text', $field['type']);
    }

    public function test_it_maps_int_type_to_number()
    {
        $info = $this->reflector->reflect(Timeout::class);

        $field = $info['fields'][0];
        $this->assertEquals('number', $field['type']);
    }

    public function test_it_maps_bool_type_to_checkbox()
    {
        // Use test fixture to test auto-reflection from parameters
        $info = $this->reflector->reflect(AutoReflectMiddlewareFixture::class);

        $allowCredentialsField = $this->findField($info['fields'], 'allowCredentials');
        $this->assertEquals('checkbox', $allowCredentialsField['type']);
    }

    public function test_it_maps_array_type_to_textarea()
    {
        // Use test fixture to test auto-reflection from parameters
        $info = $this->reflector->reflect(AutoReflectMiddlewareFixture::class);

        $allowedOriginsField = $this->findField($info['fields'], 'allowedOrigins');
        $this->assertEquals('textarea', $allowedOriginsField['type']);
    }

    public function test_it_extracts_param_description_from_phpdoc()
    {
        // Use test fixture to test auto-reflection with @param description
        $info = $this->reflector->reflect(ParamDescriptionMiddlewareFixture::class);

        $field = $info['fields'][0];
        $this->assertNotEmpty($field['description']);
        $this->assertEquals('Timeout in seconds', $field['description']);
    }

    public function test_it_generates_class_label_from_class_name()
    {
        $info = $this->reflector->reflect(SetHost::class);

        $this->assertEquals('Set Host', $info['label']);
    }

    public function test_it_handles_class_with_optional_array_parameter()
    {
        // Use test fixture to test auto-reflection with optional array parameter
        $info = $this->reflector->reflect(OptionalArrayMiddlewareFixture::class);

        $this->assertCount(1, $info['fields']);
        $this->assertEquals('options', $info['fields'][0]['name']);
        $this->assertEquals('textarea', $info['fields'][0]['type']);
    }

    public function test_it_handles_class_without_phpdoc()
    {
        // Create anonymous class without PHPDoc
        $class = new class ('test') {
            private $value;

            public function __construct(string $value)
            {
                $this->value = $value;
            }
        };

        $info = $this->reflector->reflect(get_class($class));

        $this->assertEmpty($info['description']);
        $this->assertCount(1, $info['fields']);
        $this->assertEquals('value', $info['fields'][0]['name']);
        $this->assertEquals('text', $info['fields'][0]['type']);
        $this->assertTrue($info['fields'][0]['required']);
    }

    public function test_it_handles_class_without_constructor()
    {
        $class = new class () {
            public function process()
            {
                return 'test';
            }
        };

        $info = $this->reflector->reflect(get_class($class));

        $this->assertEmpty($info['fields']);
    }

    public function test_it_handles_closure_middleware()
    {
        // Closure cannot be reflected as class, should return null or empty
        $result = $this->reflector->reflectCallable(function ($request, $next) {
            return $next($request);
        });

        $this->assertNull($result);
    }

    public function test_it_returns_null_for_non_existent_class()
    {
        $result = $this->reflector->reflect('NonExistentClass');

        $this->assertNull($result);
    }

    public function test_it_handles_parameter_without_type_hint()
    {
        $class = new class ('test') {
            private $value;

            public function __construct($value)
            {
                $this->value = $value;
            }
        };

        $info = $this->reflector->reflect(get_class($class));

        // Without type hint, defaults to text
        $this->assertEquals('text', $info['fields'][0]['type']);
    }

    public function test_it_parses_ui_field_annotations()
    {
        $info = $this->reflector->reflect(UIFieldTestMiddleware::class);

        $this->assertCount(2, $info['fields']);

        $this->assertEquals('timeout', $info['fields'][0]['name']);
        $this->assertEquals('number', $info['fields'][0]['type']);
        $this->assertEquals('Seconds', $info['fields'][0]['label']);
        $this->assertEquals(30, $info['fields'][0]['default']);

        $this->assertEquals('delay', $info['fields'][1]['name']);
        $this->assertEquals('number', $info['fields'][1]['type']);
        $this->assertEquals('Delay (ms)', $info['fields'][1]['label']);
        $this->assertEquals(100, $info['fields'][1]['default']);
    }

    public function test_it_parses_ui_field_with_required()
    {
        $info = $this->reflector->reflect(UIFieldRequiredTestMiddleware::class);

        $this->assertCount(1, $info['fields']);
        $this->assertEquals('host', $info['fields'][0]['name']);
        $this->assertTrue($info['fields'][0]['required']);
    }

    public function test_it_parses_ui_label_annotation()
    {
        $info = $this->reflector->reflect(UILabelTestMiddleware::class);

        $this->assertEquals('CORS', $info['label']);
    }

    public function test_it_parses_ui_description_annotation()
    {
        $info = $this->reflector->reflect(UIDescriptionTestMiddleware::class);

        $this->assertEquals('Add CORS headers to the response', $info['description']);
    }

    public function test_it_falls_back_to_parameters_when_no_ui_field()
    {
        // Use test fixture that has no @UIField, should use parameter reflection
        $info = $this->reflector->reflect(NoUIFieldMiddlewareFixture::class);

        $this->assertCount(1, $info['fields']);
        $this->assertEquals('host', $info['fields'][0]['name']);
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

/**
 * Test middleware with @UIField annotations.
 */
class UIFieldTestMiddleware
{
    /**
     * @UIField(name="timeout", type="number", label="Seconds", default=30)
     * @UIField(name="delay", type="number", label="Delay (ms)", default=100)
     */
    public function __construct(int $seconds = 30)
    {
    }
}

/**
 * Test middleware with required @UIField.
 */
class UIFieldRequiredTestMiddleware
{
    /**
     * @UIField(name="host", type="text", label="Host", required=true)
     */
    public function __construct(string $host)
    {
    }
}

/**
 * @UILabel("CORS")
 */
class UILabelTestMiddleware
{
    public function __construct()
    {
    }
}

/**
 * @UIDescription("Add CORS headers to the response")
 */
class UIDescriptionTestMiddleware
{
    public function __construct()
    {
    }
}

/**
 * Test fixture for auto-reflection from constructor parameters.
 * No @UIField annotations, so reflector should auto-generate from params.
 */
class AutoReflectMiddlewareFixture
{
    public function __construct(
        array $allowedOrigins = ['*'],
        bool $allowCredentials = false
    ) {
    }
}

/**
 * Test fixture for @param description extraction.
 */
class ParamDescriptionMiddlewareFixture
{
    /**
     * @param int $seconds Timeout in seconds
     */
    public function __construct(int $seconds = 30)
    {
    }
}

/**
 * Test fixture for optional array parameter.
 */
class OptionalArrayMiddlewareFixture
{
    public function __construct(array $options = [])
    {
    }
}

/**
 * Test fixture for fallback to parameter reflection.
 */
class NoUIFieldMiddlewareFixture
{
    public function __construct(string $host)
    {
    }
}
