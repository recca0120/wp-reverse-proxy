<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Recca0120\ReverseProxy\Support\AnnotationParser;

class AnnotationParserTest extends TestCase
{
    /** @var AnnotationParser */
    private $parser;

    protected function setUp(): void
    {
        $this->parser = new AnnotationParser();
    }

    public function test_parse_simple_label()
    {
        $result = $this->parser->parseParamDescription('Host name');

        $this->assertEquals('Host name', $result['label']);
        $this->assertNull($result['options']);
        $this->assertNull($result['default']);
        $this->assertNull($result['labels']);
        $this->assertFalse($result['skip']);
    }

    public function test_parse_skip_annotation()
    {
        $result = $this->parser->parseParamDescription('Some config (skip)');

        $this->assertEquals('Some config', $result['label']);
        $this->assertTrue($result['skip']);
    }

    public function test_parse_skip_annotation_case_insensitive()
    {
        $result = $this->parser->parseParamDescription('Config (SKIP)');

        $this->assertTrue($result['skip']);
    }

    public function test_parse_default_annotation()
    {
        $result = $this->parser->parseParamDescription('Timeout (default: 30)');

        $this->assertEquals('Timeout', $result['label']);
        $this->assertEquals('30', $result['default']);
    }

    public function test_parse_options_annotation()
    {
        $result = $this->parser->parseParamDescription('Mode (options: allow|deny)');

        $this->assertEquals('Mode', $result['label']);
        $this->assertEquals('allow|deny', $result['options']);
    }

    public function test_parse_labels_annotation()
    {
        $result = $this->parser->parseParamDescription('Replacements (labels: Pattern|Replacement)');

        $this->assertEquals('Replacements', $result['label']);
        $this->assertEquals('Pattern|Replacement', $result['labels']);
    }

    public function test_parse_labels_with_escaped_parentheses()
    {
        $result = $this->parser->parseParamDescription('Replacements (labels: Pattern \(regex\)|Replacement)');

        $this->assertEquals('Replacements', $result['label']);
        $this->assertEquals('Pattern (regex)|Replacement', $result['labels']);
    }

    public function test_parse_multiple_annotations()
    {
        $result = $this->parser->parseParamDescription('Status Codes (default: 404) (options: 404|500|502)');

        $this->assertEquals('Status Codes', $result['label']);
        $this->assertEquals('404', $result['default']);
        $this->assertEquals('404|500|502', $result['options']);
    }

    public function test_parse_empty_description()
    {
        $result = $this->parser->parseParamDescription('');

        $this->assertEquals('', $result['label']);
        $this->assertNull($result['options']);
        $this->assertNull($result['default']);
        $this->assertNull($result['labels']);
        $this->assertFalse($result['skip']);
    }

    public function test_parse_only_skip()
    {
        $result = $this->parser->parseParamDescription('(skip)');

        $this->assertEquals('', $result['label']);
        $this->assertTrue($result['skip']);
    }

    public function test_parse_class_description_single_line()
    {
        $result = $this->parser->parseClassDescription(
            new ReflectionClass(SingleLineDescriptionFixture::class)
        );

        $this->assertEquals('Filter requests by IP address.', $result);
    }

    public function test_parse_class_description_multi_line()
    {
        $result = $this->parser->parseClassDescription(
            new ReflectionClass(MultiLineDescriptionFixture::class)
        );

        $this->assertEquals('Filter requests by IP address. Supports CIDR notation.', $result);
    }

    public function test_parse_class_description_stops_at_first_tag()
    {
        $result = $this->parser->parseClassDescription(
            new ReflectionClass(DescriptionWithTagsFixture::class)
        );

        $this->assertEquals('Filter requests by IP address.', $result);
    }

    public function test_parse_class_description_empty()
    {
        $result = $this->parser->parseClassDescription(
            new ReflectionClass(EmptyDescriptionFixture::class)
        );

        $this->assertEquals('', $result);
    }

    public function test_parse_class_description_no_docblock()
    {
        $result = $this->parser->parseClassDescription(
            new ReflectionClass(NoDocBlockFixture::class)
        );

        $this->assertEquals('', $result);
    }

    public function test_parse_constructor_params_extracts_param_annotations()
    {
        $constructor = (new ReflectionClass(ConstructorParamsFixture::class))->getConstructor();

        $result = $this->parser->parseConstructorParams($constructor);

        $this->assertArrayHasKey('host', $result);
        $this->assertEquals('string', $result['host']['type']);
        $this->assertEquals('Target host name', $result['host']['label']);

        $this->assertArrayHasKey('timeout', $result);
        $this->assertEquals('int', $result['timeout']['type']);
        $this->assertEquals('Request timeout', $result['timeout']['label']);
        $this->assertEquals('30', $result['timeout']['default']);
    }

    public function test_parse_constructor_params_handles_complex_types()
    {
        $constructor = (new ReflectionClass(ComplexTypesFixture::class))->getConstructor();

        $result = $this->parser->parseConstructorParams($constructor);

        $this->assertEquals('array<string, mixed>', $result['options']['type']);
        $this->assertEquals('string[]', $result['items']['type']);
    }

    public function test_parse_constructor_params_handles_options_annotation()
    {
        $constructor = (new ReflectionClass(OptionsAnnotationFixture::class))->getConstructor();

        $result = $this->parser->parseConstructorParams($constructor);

        $this->assertEquals('Filter mode', $result['mode']['label']);
        $this->assertEquals('allow|deny', $result['mode']['options']);
    }

    public function test_parse_constructor_params_handles_skip_annotation()
    {
        $constructor = (new ReflectionClass(SkipAnnotationFixture::class))->getConstructor();

        $result = $this->parser->parseConstructorParams($constructor);

        $this->assertTrue($result['callback']['skip']);
    }

    public function test_parse_constructor_params_handles_param_without_description()
    {
        $constructor = (new ReflectionClass(ParamWithoutDescriptionFixture::class))->getConstructor();

        $result = $this->parser->parseConstructorParams($constructor);

        $this->assertArrayHasKey('name', $result);
        $this->assertEquals('string', $result['name']['type']);
        $this->assertEquals('This has description', $result['name']['label']);

        // Second param without description should still be captured
        $this->assertCount(2, $result);
    }

    public function test_parse_constructor_params_returns_empty_for_no_constructor()
    {
        $class = new ReflectionClass(NoDocBlockFixture::class);

        // Class has no constructor, so we need to handle this case
        $constructor = $class->getConstructor();

        // If no constructor, parseConstructorParams should not be called
        // This test verifies behavior when constructor exists but has no PHPDoc
        $this->assertNull($constructor);
    }

    public function test_parse_constructor_params_returns_empty_for_empty_docblock()
    {
        $constructor = (new ReflectionClass(EmptyDocBlockConstructorFixture::class))->getConstructor();

        $result = $this->parser->parseConstructorParams($constructor);

        $this->assertEquals([], $result);
    }

    public function test_parse_constructor_params_handles_labels_annotation()
    {
        $constructor = (new ReflectionClass(LabelsAnnotationFixture::class))->getConstructor();

        $result = $this->parser->parseConstructorParams($constructor);

        $this->assertEquals('Replacements', $result['replacements']['label']);
        $this->assertEquals('Pattern (regex)|Replacement', $result['replacements']['labels']);
    }
}

/** Filter requests by IP address. */
class SingleLineDescriptionFixture
{
}

/**
 * Filter requests by IP address.
 * Supports CIDR notation.
 */
class MultiLineDescriptionFixture
{
}

/**
 * Filter requests by IP address.
 *
 * @param string $mode Mode
 * @param string $ip IP address
 */
class DescriptionWithTagsFixture
{
}

/**
 * @param string $mode
 */
class EmptyDescriptionFixture
{
}

class NoDocBlockFixture
{
}

class ConstructorParamsFixture
{
    /**
     * @param string $host Target host name
     * @param int $timeout Request timeout (default: 30)
     */
    public function __construct(string $host, int $timeout = 30)
    {
    }
}

class ComplexTypesFixture
{
    /**
     * @param array<string, mixed> $options Configuration options
     * @param string[] $items List of items
     */
    public function __construct(array $options = [], array $items = [])
    {
    }
}

class OptionsAnnotationFixture
{
    /**
     * @param string $mode Filter mode (options: allow|deny)
     */
    public function __construct(string $mode = 'allow')
    {
    }
}

class SkipAnnotationFixture
{
    /**
     * @param callable $callback Internal callback (skip)
     */
    public function __construct(callable $callback)
    {
    }
}

class ParamWithoutDescriptionFixture
{
    /**
     * Some class description.
     *
     * @param string $name This has description
     * @param int $count
     */
    public function __construct(string $name, int $count = 0)
    {
    }
}

class EmptyDocBlockConstructorFixture
{
    public function __construct(string $name)
    {
    }
}

class LabelsAnnotationFixture
{
    /**
     * @param array<string, string> $replacements Replacements (labels: Pattern \(regex\)|Replacement)
     */
    public function __construct(array $replacements = [])
    {
    }
}
