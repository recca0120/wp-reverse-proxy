<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
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

    public function test_parse_description_single_line()
    {
        $docComment = '/** Filter requests by IP address. */';

        $result = $this->parser->extractDescription($docComment);

        $this->assertEquals('Filter requests by IP address.', $result);
    }

    public function test_parse_description_multi_line()
    {
        $docComment = <<<'DOC'
/**
 * Filter requests by IP address.
 * Supports CIDR notation.
 */
DOC;

        $result = $this->parser->extractDescription($docComment);

        $this->assertEquals('Filter requests by IP address. Supports CIDR notation.', $result);
    }

    public function test_parse_description_stops_at_first_tag()
    {
        $docComment = <<<'DOC'
/**
 * Filter requests by IP address.
 *
 * @param string $mode Mode
 * @param string $ip IP address
 */
DOC;

        $result = $this->parser->extractDescription($docComment);

        $this->assertEquals('Filter requests by IP address.', $result);
    }

    public function test_parse_description_empty()
    {
        $docComment = <<<'DOC'
/**
 * @param string $mode
 */
DOC;

        $result = $this->parser->extractDescription($docComment);

        $this->assertEquals('', $result);
    }

    public function test_parse_description_no_docblock()
    {
        $result = $this->parser->extractDescription('');

        $this->assertEquals('', $result);
    }

    public function test_parse_params_extracts_param_annotations()
    {
        $docComment = <<<'DOC'
/**
 * @param string $host Target host name
 * @param int $timeout Request timeout (default: 30)
 */
DOC;

        $result = $this->parser->parseParams($docComment);

        $this->assertArrayHasKey('host', $result);
        $this->assertEquals('string', $result['host']['type']);
        $this->assertEquals('Target host name', $result['host']['label']);

        $this->assertArrayHasKey('timeout', $result);
        $this->assertEquals('int', $result['timeout']['type']);
        $this->assertEquals('Request timeout', $result['timeout']['label']);
        $this->assertEquals('30', $result['timeout']['default']);
    }

    public function test_parse_params_handles_complex_types()
    {
        $docComment = <<<'DOC'
/**
 * @param array<string, mixed> $options Configuration options
 * @param string[] $items List of items
 */
DOC;

        $result = $this->parser->parseParams($docComment);

        $this->assertEquals('array<string, mixed>', $result['options']['type']);
        $this->assertEquals('string[]', $result['items']['type']);
    }

    public function test_parse_params_handles_options_annotation()
    {
        $docComment = <<<'DOC'
/**
 * @param string $mode Filter mode (options: allow|deny)
 */
DOC;

        $result = $this->parser->parseParams($docComment);

        $this->assertEquals('Filter mode', $result['mode']['label']);
        $this->assertEquals('allow|deny', $result['mode']['options']);
    }

    public function test_parse_params_handles_skip_annotation()
    {
        $docComment = <<<'DOC'
/**
 * @param callable $callback Internal callback (skip)
 */
DOC;

        $result = $this->parser->parseParams($docComment);

        $this->assertTrue($result['callback']['skip']);
    }

    public function test_parse_params_handles_param_without_description()
    {
        $docComment = <<<'DOC'
/**
 * Some class description.
 *
 * @param string $name This has description
 * @param int $count
 */
DOC;

        $result = $this->parser->parseParams($docComment);

        $this->assertArrayHasKey('name', $result);
        $this->assertEquals('string', $result['name']['type']);
        $this->assertEquals('This has description', $result['name']['label']);

        // Second param without description should still be captured
        $this->assertCount(2, $result);
    }

    public function test_parse_params_returns_empty_for_empty_docblock()
    {
        $result = $this->parser->parseParams('');

        $this->assertEquals([], $result);
    }

    public function test_parse_params_handles_labels_annotation()
    {
        $docComment = <<<'DOC'
/**
 * @param array<string, string> $replacements Replacements (labels: Pattern \(regex\)|Replacement)
 */
DOC;

        $result = $this->parser->parseParams($docComment);

        $this->assertEquals('Replacements', $result['replacements']['label']);
        $this->assertEquals('Pattern (regex)|Replacement', $result['replacements']['labels']);
    }
}
