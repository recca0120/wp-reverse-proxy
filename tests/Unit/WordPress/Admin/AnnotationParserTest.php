<?php

namespace Recca0120\ReverseProxy\Tests\Unit\WordPress\Admin;

use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\WordPress\Admin\AnnotationParser;

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
        $result = $this->parser->parse('Host name');

        $this->assertEquals('Host name', $result['label']);
        $this->assertNull($result['options']);
        $this->assertNull($result['default']);
        $this->assertNull($result['labels']);
        $this->assertFalse($result['skip']);
    }

    public function test_parse_skip_annotation()
    {
        $result = $this->parser->parse('Some config (skip)');

        $this->assertEquals('Some config', $result['label']);
        $this->assertTrue($result['skip']);
    }

    public function test_parse_skip_annotation_case_insensitive()
    {
        $result = $this->parser->parse('Config (SKIP)');

        $this->assertTrue($result['skip']);
    }

    public function test_parse_default_annotation()
    {
        $result = $this->parser->parse('Timeout (default: 30)');

        $this->assertEquals('Timeout', $result['label']);
        $this->assertEquals('30', $result['default']);
    }

    public function test_parse_options_annotation()
    {
        $result = $this->parser->parse('Mode (options: allow|deny)');

        $this->assertEquals('Mode', $result['label']);
        $this->assertEquals('allow|deny', $result['options']);
    }

    public function test_parse_labels_annotation()
    {
        $result = $this->parser->parse('Replacements (labels: Pattern|Replacement)');

        $this->assertEquals('Replacements', $result['label']);
        $this->assertEquals('Pattern|Replacement', $result['labels']);
    }

    public function test_parse_labels_with_escaped_parentheses()
    {
        $result = $this->parser->parse('Replacements (labels: Pattern \(regex\)|Replacement)');

        $this->assertEquals('Replacements', $result['label']);
        $this->assertEquals('Pattern (regex)|Replacement', $result['labels']);
    }

    public function test_parse_multiple_annotations()
    {
        $result = $this->parser->parse('Status Codes (default: 404) (options: 404|500|502)');

        $this->assertEquals('Status Codes', $result['label']);
        $this->assertEquals('404', $result['default']);
        $this->assertEquals('404|500|502', $result['options']);
    }

    public function test_parse_empty_description()
    {
        $result = $this->parser->parse('');

        $this->assertEquals('', $result['label']);
        $this->assertNull($result['options']);
        $this->assertNull($result['default']);
        $this->assertNull($result['labels']);
        $this->assertFalse($result['skip']);
    }

    public function test_parse_only_skip()
    {
        $result = $this->parser->parse('(skip)');

        $this->assertEquals('', $result['label']);
        $this->assertTrue($result['skip']);
    }

    public function test_parse_description_single_line()
    {
        $docComment = '/** Filter requests by IP address. */';

        $result = $this->parser->parseDescription($docComment);

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

        $result = $this->parser->parseDescription($docComment);

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

        $result = $this->parser->parseDescription($docComment);

        $this->assertEquals('Filter requests by IP address.', $result);
    }

    public function test_parse_description_empty()
    {
        $docComment = <<<'DOC'
/**
 * @param string $mode
 */
DOC;

        $result = $this->parser->parseDescription($docComment);

        $this->assertEquals('', $result);
    }

    public function test_parse_description_no_docblock()
    {
        $result = $this->parser->parseDescription('');

        $this->assertEquals('', $result);
    }
}
