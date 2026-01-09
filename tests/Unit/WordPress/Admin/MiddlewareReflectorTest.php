<?php

namespace Recca0120\ReverseProxy\Tests\Unit\WordPress\Admin;

use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Middleware\Cors;
use Recca0120\ReverseProxy\Middleware\IpFilter;
use Recca0120\ReverseProxy\Middleware\RequestId;
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
        $this->assertEquals('Target host name', $field['label']);
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

    public function test_it_maps_array_type_without_phpdoc_to_json()
    {
        // Use test fixture to test auto-reflection from parameters
        // array without PHPDoc type hint should be json (allows any structure)
        $info = $this->reflector->reflect(AutoReflectMiddlewareFixture::class);

        $allowedOriginsField = $this->findField($info['fields'], 'allowedOrigins');
        $this->assertEquals('json', $allowedOriginsField['type']);
    }

    public function test_it_extracts_param_description_as_label()
    {
        // Use test fixture to test auto-reflection with @param description
        $info = $this->reflector->reflect(ParamDescriptionMiddlewareFixture::class);

        $field = $info['fields'][0];
        $this->assertEquals('Timeout in seconds', $field['label']);
    }

    public function test_it_generates_class_label_from_class_name()
    {
        $info = $this->reflector->reflect(SetHost::class);

        $this->assertEquals('Set Host', $info['label']);
    }

    public function test_it_handles_class_with_optional_array_parameter()
    {
        // Use test fixture to test auto-reflection with optional array parameter
        // array without PHPDoc type hint should be json
        $info = $this->reflector->reflect(OptionalArrayMiddlewareFixture::class);

        $this->assertCount(1, $info['fields']);
        $this->assertEquals('options', $info['fields'][0]['name']);
        $this->assertEquals('json', $info['fields'][0]['type']);
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

    public function test_it_parses_param_with_options_as_select()
    {
        $info = $this->reflector->reflect(ParamWithOptionsTestMiddleware::class);

        $this->assertCount(1, $info['fields']);
        $this->assertEquals('mode', $info['fields'][0]['name']);
        $this->assertEquals('select', $info['fields'][0]['type']);
        $this->assertEquals('Mode', $info['fields'][0]['label']);
        $this->assertEquals('allow:Allow|deny:Deny', $info['fields'][0]['options']);
    }

    public function test_it_parses_array_with_options_as_checkboxes()
    {
        $info = $this->reflector->reflect(ArrayWithOptionsTestMiddleware::class);

        $this->assertCount(1, $info['fields']);
        $this->assertEquals('methods', $info['fields'][0]['name']);
        $this->assertEquals('checkboxes', $info['fields'][0]['type']);
        $this->assertEquals('Allowed Methods', $info['fields'][0]['label']);
        $this->assertEquals('GET|POST|PUT|DELETE', $info['fields'][0]['options']);
    }

    public function test_it_parses_keyvalue_type()
    {
        $info = $this->reflector->reflect(KeyValueTestMiddleware::class);

        $this->assertCount(1, $info['fields']);
        $this->assertEquals('replacements', $info['fields'][0]['name']);
        $this->assertEquals('keyvalue', $info['fields'][0]['type']);
        $this->assertEquals('Pattern replacements', $info['fields'][0]['label']);
        $this->assertEquals('Pattern (regex)', $info['fields'][0]['keyLabel']);
        $this->assertEquals('Replacement', $info['fields'][0]['valueLabel']);
    }

    public function test_it_generates_label_with_acronyms()
    {
        $info = $this->reflector->reflect(Cors::class);
        $this->assertEquals('CORS', $info['label']);

        $info = $this->reflector->reflect(RequestId::class);
        $this->assertEquals('Request ID', $info['label']);

        $info = $this->reflector->reflect(IpFilter::class);
        $this->assertEquals('IP Filter', $info['label']);
    }

    public function test_it_parses_phpdoc_description()
    {
        $info = $this->reflector->reflect(PHPDocDescriptionTestMiddleware::class);

        $this->assertEquals('Add CORS headers to the response.', $info['description']);
    }

    public function test_it_falls_back_to_parameters_when_no_ui_field()
    {
        // Use test fixture that has no @UIField, should use parameter reflection
        $info = $this->reflector->reflect(NoUIFieldMiddlewareFixture::class);

        $this->assertCount(1, $info['fields']);
        $this->assertEquals('host', $info['fields'][0]['name']);
    }

    /**
     * Test class without PHPDoc has empty description.
     */
    public function test_no_phpdoc_has_empty_description()
    {
        $info = $this->reflector->reflect(NoPHPDocMiddleware::class);

        $this->assertEquals('', $info['description']);
        $this->assertEquals('No PHPDoc', $info['label']);
    }

    /**
     * Test array without PHPDoc should be json (allows any structure).
     */
    public function test_array_without_phpdoc_is_json()
    {
        $info = $this->reflector->reflect(ArrayWithoutPHPDocMiddleware::class);

        $this->assertCount(2, $info['fields']);

        $itemsField = $this->findField($info['fields'], 'items');
        $this->assertEquals('json', $itemsField['type']);
        $this->assertEquals('Items', $itemsField['label']);
    }

    /**
     * Test variadic string parameter should be repeater, not required.
     */
    public function test_variadic_string_is_repeater_not_required()
    {
        $info = $this->reflector->reflect(VariadicStringMiddleware::class);

        $valuesField = $this->findField($info['fields'], 'values');
        $this->assertEquals('repeater', $valuesField['type']);
        $this->assertArrayNotHasKey('required', $valuesField);
    }

    /**
     * Test variadic int parameter should be repeater with inputType number.
     */
    public function test_variadic_int_is_repeater_with_input_type_number()
    {
        $info = $this->reflector->reflect(VariadicIntMiddleware::class);

        $numbersField = $this->findField($info['fields'], 'numbers');
        $this->assertEquals('repeater', $numbersField['type']);
        $this->assertEquals('number', $numbersField['inputType']);
        $this->assertArrayNotHasKey('required', $numbersField);
    }

    /**
     * Test variadic without type hint should be repeater.
     */
    public function test_variadic_without_type_is_repeater()
    {
        $info = $this->reflector->reflect(VariadicNoTypeMiddleware::class);

        $argsField = $this->findField($info['fields'], 'args');
        $this->assertEquals('repeater', $argsField['type']);
        $this->assertArrayNotHasKey('required', $argsField);
    }

    /**
     * Test (skip) in @param description hides the field.
     */
    public function test_skip_annotation_hides_field()
    {
        $info = $this->reflector->reflect(SkipAnnotationMiddleware::class);

        $this->assertCount(1, $info['fields']);
        $this->assertEquals('name', $info['fields'][0]['name']);
        $this->assertNull($this->findField($info['fields'], 'options'));
    }

    /**
     * Test (default: value) in @param description sets default.
     */
    public function test_default_annotation_sets_default()
    {
        $info = $this->reflector->reflect(DefaultAnnotationMiddleware::class);

        $codesField = $this->findField($info['fields'], 'codes');
        $this->assertEquals('404', $codesField['default']);
        $this->assertArrayNotHasKey('required', $codesField);
    }

    /**
     * Test (options: a|b|c) in @param description sets options for select.
     */
    public function test_options_annotation_for_select()
    {
        $info = $this->reflector->reflect(OptionsAnnotationSelectMiddleware::class);

        $modeField = $this->findField($info['fields'], 'mode');
        $this->assertEquals('select', $modeField['type']);
        $this->assertEquals('allow|deny', $modeField['options']);
    }

    /**
     * Test (options: a|b|c) in @param description sets options for checkboxes.
     */
    public function test_options_annotation_for_checkboxes()
    {
        $info = $this->reflector->reflect(OptionsAnnotationCheckboxesMiddleware::class);

        $methodsField = $this->findField($info['fields'], 'methods');
        $this->assertEquals('checkboxes', $methodsField['type']);
        $this->assertEquals('GET|POST|PUT|DELETE', $methodsField['options']);
    }

    /**
     * Test multiple annotations combined.
     */
    public function test_multiple_annotations_combined()
    {
        $info = $this->reflector->reflect(MultipleAnnotationsMiddleware::class);

        $modeField = $this->findField($info['fields'], 'mode');
        $this->assertEquals('select', $modeField['type']);
        $this->assertEquals('allow|deny', $modeField['options']);
        $this->assertEquals('allow', $modeField['default']);
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
 * Test middleware with @param options (select).
 */
class ParamWithOptionsTestMiddleware
{
    /**
     * @param string $mode Mode (options: allow:Allow|deny:Deny)
     */
    public function __construct(string $mode = 'allow')
    {
    }
}

/**
 * Test middleware with array @param options (checkboxes).
 */
class ArrayWithOptionsTestMiddleware
{
    /**
     * @param string[] $methods Allowed Methods (options: GET|POST|PUT|DELETE)
     */
    public function __construct(array $methods = ['GET'])
    {
    }
}

/**
 * Test middleware with keyvalue type.
 */
class KeyValueTestMiddleware
{
    /**
     * @param array<string,string> $replacements Pattern replacements (labels: Pattern \(regex\)|Replacement)
     */
    public function __construct(array $replacements = [])
    {
    }
}


/**
 * Add CORS headers to the response.
 */
class PHPDocDescriptionTestMiddleware
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

// No PHPDoc at all
class NoPHPDocMiddleware
{
    public function __construct(string $name, int $timeout = 30)
    {
    }
}

/**
 * Test fixture for array without PHPDoc.
 */
class ArrayWithoutPHPDocMiddleware
{
    public function __construct(string $name, array $items = [])
    {
    }
}

/**
 * Test fixture for variadic string parameter.
 */
class VariadicStringMiddleware
{
    public function __construct(string $name, string ...$values)
    {
    }
}

/**
 * Test fixture for variadic int parameter.
 */
class VariadicIntMiddleware
{
    public function __construct(string $name, int ...$numbers)
    {
    }
}

/**
 * Test fixture for variadic without type hint.
 */
class VariadicNoTypeMiddleware
{
    public function __construct(string $name, ...$args)
    {
    }
}

/**
 * Test fixture for (skip) annotation.
 */
class SkipAnnotationMiddleware
{
    /**
     * @param string $name Name
     * @param array $options (skip)
     */
    public function __construct(string $name, array $options = [])
    {
    }
}

/**
 * Test fixture for (default: value) annotation.
 */
class DefaultAnnotationMiddleware
{
    /**
     * @param int|int[] $codes Status Codes (default: 404)
     */
    public function __construct(...$codes)
    {
    }
}

/**
 * Test fixture for (options:) annotation with select.
 */
class OptionsAnnotationSelectMiddleware
{
    /**
     * @param string $mode Mode (options: allow|deny)
     */
    public function __construct(string $mode = 'allow')
    {
    }
}

/**
 * Test fixture for (options:) annotation with checkboxes.
 */
class OptionsAnnotationCheckboxesMiddleware
{
    /**
     * @param string[] $methods Methods (options: GET|POST|PUT|DELETE)
     */
    public function __construct(array $methods = ['GET'])
    {
    }
}

/**
 * Test fixture for multiple annotations.
 */
class MultipleAnnotationsMiddleware
{
    /**
     * @param string $mode Mode (options: allow|deny) (default: allow)
     */
    public function __construct(string $mode = 'allow')
    {
    }
}
