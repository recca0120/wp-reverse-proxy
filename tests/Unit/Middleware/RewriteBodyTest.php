<?php

namespace Recca0120\ReverseProxy\Tests\Unit\Middleware;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Middleware\RewriteBody;

class RewriteBodyTest extends TestCase
{
    /** @var Psr17Factory */
    private $streamFactory;

    protected function setUp(): void
    {
        $this->streamFactory = new Psr17Factory();
    }

    public function test_it_rewrites_urls_in_html_response(): void
    {
        $middleware = new RewriteBody(
            ['#https://example\.com#' => 'https://my-site.com'],
            $this->streamFactory
        );

        $request = new ServerRequest('GET', 'https://proxy.com/page');
        $originalBody = '<html><link href="https://example.com/style.css"></html>';
        $response = (new Response(200))
            ->withHeader('Content-Type', 'text/html')
            ->withBody($this->streamFactory->createStream($originalBody));

        $result = $middleware->process($request, function () use ($response) {
            return $response;
        });

        $this->assertEquals(
            '<html><link href="https://my-site.com/style.css"></html>',
            (string) $result->getBody()
        );
    }

    public function test_it_rewrites_urls_in_css_response(): void
    {
        $middleware = new RewriteBody(
            ['#https://example\.com#' => 'https://my-site.com'],
            $this->streamFactory
        );

        $request = new ServerRequest('GET', 'https://proxy.com/style.css');
        $originalBody = 'body { background: url(https://example.com/bg.png); }';
        $response = (new Response(200))
            ->withHeader('Content-Type', 'text/css')
            ->withBody($this->streamFactory->createStream($originalBody));

        $result = $middleware->process($request, function () use ($response) {
            return $response;
        });

        $this->assertEquals(
            'body { background: url(https://my-site.com/bg.png); }',
            (string) $result->getBody()
        );
    }

    public function test_it_rewrites_urls_in_javascript_response(): void
    {
        $middleware = new RewriteBody(
            ['#https://api\.example\.com#' => 'https://my-site.com/api'],
            $this->streamFactory
        );

        $request = new ServerRequest('GET', 'https://proxy.com/app.js');
        $originalBody = 'const API_URL = "https://api.example.com/v1";';
        $response = (new Response(200))
            ->withHeader('Content-Type', 'application/javascript')
            ->withBody($this->streamFactory->createStream($originalBody));

        $result = $middleware->process($request, function () use ($response) {
            return $response;
        });

        $this->assertEquals(
            'const API_URL = "https://my-site.com/api/v1";',
            (string) $result->getBody()
        );
    }

    public function test_it_rewrites_urls_in_json_response(): void
    {
        $middleware = new RewriteBody(
            ['#https://example\.com#' => 'https://my-site.com'],
            $this->streamFactory
        );

        $request = new ServerRequest('GET', 'https://proxy.com/api/data');
        $originalBody = '{"url": "https://example.com/resource"}';
        $response = (new Response(200))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream($originalBody));

        $result = $middleware->process($request, function () use ($response) {
            return $response;
        });

        $this->assertEquals(
            '{"url": "https://my-site.com/resource"}',
            (string) $result->getBody()
        );
    }

    public function test_it_handles_content_type_with_charset(): void
    {
        $middleware = new RewriteBody(
            ['#https://example\.com#' => 'https://my-site.com'],
            $this->streamFactory
        );

        $request = new ServerRequest('GET', 'https://proxy.com/page');
        $originalBody = '<a href="https://example.com/link">Link</a>';
        $response = (new Response(200))
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody($this->streamFactory->createStream($originalBody));

        $result = $middleware->process($request, function () use ($response) {
            return $response;
        });

        $this->assertEquals(
            '<a href="https://my-site.com/link">Link</a>',
            (string) $result->getBody()
        );
    }

    public function test_it_does_not_rewrite_binary_content(): void
    {
        $middleware = new RewriteBody(
            ['#https://example\.com#' => 'https://my-site.com'],
            $this->streamFactory
        );

        $request = new ServerRequest('GET', 'https://proxy.com/image.png');
        $originalBody = 'binary-content-https://example.com';
        $response = (new Response(200))
            ->withHeader('Content-Type', 'image/png')
            ->withBody($this->streamFactory->createStream($originalBody));

        $result = $middleware->process($request, function () use ($response) {
            return $response;
        });

        $this->assertEquals($originalBody, (string) $result->getBody());
    }

    public function test_it_does_not_rewrite_when_no_content_type(): void
    {
        $middleware = new RewriteBody(
            ['#https://example\.com#' => 'https://my-site.com'],
            $this->streamFactory
        );

        $request = new ServerRequest('GET', 'https://proxy.com/unknown');
        $originalBody = 'https://example.com';
        $response = (new Response(200))
            ->withBody($this->streamFactory->createStream($originalBody));

        $result = $middleware->process($request, function () use ($response) {
            return $response;
        });

        $this->assertEquals($originalBody, (string) $result->getBody());
    }

    public function test_it_does_not_rewrite_when_no_replacements(): void
    {
        $middleware = new RewriteBody([], $this->streamFactory);

        $request = new ServerRequest('GET', 'https://proxy.com/page');
        $originalBody = '<a href="https://example.com">Link</a>';
        $response = (new Response(200))
            ->withHeader('Content-Type', 'text/html')
            ->withBody($this->streamFactory->createStream($originalBody));

        $result = $middleware->process($request, function () use ($response) {
            return $response;
        });

        $this->assertEquals($originalBody, (string) $result->getBody());
    }

    public function test_it_applies_multiple_replacements(): void
    {
        $middleware = new RewriteBody(
            [
                '#https://example\.com#' => 'https://my-site.com',
                '#https://cdn\.example\.com#' => 'https://my-site.com/cdn',
            ],
            $this->streamFactory
        );

        $request = new ServerRequest('GET', 'https://proxy.com/page');
        $originalBody = '<img src="https://cdn.example.com/img.png"><a href="https://example.com">Link</a>';
        $response = (new Response(200))
            ->withHeader('Content-Type', 'text/html')
            ->withBody($this->streamFactory->createStream($originalBody));

        $result = $middleware->process($request, function () use ($response) {
            return $response;
        });

        $this->assertEquals(
            '<img src="https://my-site.com/cdn/img.png"><a href="https://my-site.com">Link</a>',
            (string) $result->getBody()
        );
    }

    public function test_it_handles_xml_content(): void
    {
        $middleware = new RewriteBody(
            ['#https://example\.com#' => 'https://my-site.com'],
            $this->streamFactory
        );

        $request = new ServerRequest('GET', 'https://proxy.com/feed.xml');
        $originalBody = '<rss><link>https://example.com/article</link></rss>';
        $response = (new Response(200))
            ->withHeader('Content-Type', 'application/xml')
            ->withBody($this->streamFactory->createStream($originalBody));

        $result = $middleware->process($request, function () use ($response) {
            return $response;
        });

        $this->assertEquals(
            '<rss><link>https://my-site.com/article</link></rss>',
            (string) $result->getBody()
        );
    }

    public function test_it_preserves_response_headers(): void
    {
        $middleware = new RewriteBody(
            ['#https://example\.com#' => 'https://my-site.com'],
            $this->streamFactory
        );

        $request = new ServerRequest('GET', 'https://proxy.com/page');
        $response = (new Response(200))
            ->withHeader('Content-Type', 'text/html')
            ->withHeader('X-Custom-Header', 'value')
            ->withBody($this->streamFactory->createStream('https://example.com'));

        $result = $middleware->process($request, function () use ($response) {
            return $response;
        });

        $this->assertEquals('value', $result->getHeaderLine('X-Custom-Header'));
        $this->assertEquals(200, $result->getStatusCode());
    }

    public function test_it_works_without_stream_factory(): void
    {
        $middleware = new RewriteBody(['#https://example\.com#' => 'https://my-site.com']);

        $request = new ServerRequest('GET', 'https://proxy.com/page');
        $response = (new Response(200))
            ->withHeader('Content-Type', 'text/html')
            ->withBody($this->streamFactory->createStream('<a href="https://example.com">Link</a>'));

        $result = $middleware->process($request, function () use ($response) {
            return $response;
        });

        $this->assertEquals(
            '<a href="https://my-site.com">Link</a>',
            (string) $result->getBody()
        );
    }

    public function test_it_applies_regex_patterns_with_slash_delimiter(): void
    {
        $middleware = new RewriteBody(
            ['/https:\/\/([a-z]+)\.example\.com/' => 'https://$1.my-site.com']
        );

        $request = new ServerRequest('GET', 'https://proxy.com/page');
        $originalBody = '<img src="https://cdn.example.com/img.png"><link href="https://static.example.com/style.css">';
        $response = (new Response(200))
            ->withHeader('Content-Type', 'text/html')
            ->withBody($this->streamFactory->createStream($originalBody));

        $result = $middleware->process($request, function () use ($response) {
            return $response;
        });

        $this->assertEquals(
            '<img src="https://cdn.my-site.com/img.png"><link href="https://static.my-site.com/style.css">',
            (string) $result->getBody()
        );
    }

    public function test_it_applies_regex_patterns_with_hash_delimiter(): void
    {
        $middleware = new RewriteBody(
            ['#https://([a-z]+)\.example\.com#' => 'https://$1.my-site.com']
        );

        $request = new ServerRequest('GET', 'https://proxy.com/page');
        $originalBody = '<img src="https://cdn.example.com/img.png"><link href="https://static.example.com/style.css">';
        $response = (new Response(200))
            ->withHeader('Content-Type', 'text/html')
            ->withBody($this->streamFactory->createStream($originalBody));

        $result = $middleware->process($request, function () use ($response) {
            return $response;
        });

        $this->assertEquals(
            '<img src="https://cdn.my-site.com/img.png"><link href="https://static.my-site.com/style.css">',
            (string) $result->getBody()
        );
    }

    public function test_it_applies_regex_with_capture_groups(): void
    {
        $middleware = new RewriteBody(
            ['#/api/v([0-9]+)/#' => '/api/v$1-legacy/']
        );

        $request = new ServerRequest('GET', 'https://proxy.com/app.js');
        $originalBody = 'const url1 = "/api/v1/users"; const url2 = "/api/v2/posts";';
        $response = (new Response(200))
            ->withHeader('Content-Type', 'application/javascript')
            ->withBody($this->streamFactory->createStream($originalBody));

        $result = $middleware->process($request, function () use ($response) {
            return $response;
        });

        $this->assertEquals(
            'const url1 = "/api/v1-legacy/users"; const url2 = "/api/v2-legacy/posts";',
            (string) $result->getBody()
        );
    }

    public function test_it_applies_multiple_regex_patterns(): void
    {
        $middleware = new RewriteBody([
            '#https://example\.com#' => 'https://my-site.com',
            '#https://cdn[0-9]+\.example\.com#' => 'https://cdn.my-site.com',
        ]);

        $request = new ServerRequest('GET', 'https://proxy.com/page');
        $originalBody = '<a href="https://example.com">Home</a><img src="https://cdn1.example.com/a.png"><img src="https://cdn2.example.com/b.png">';
        $response = (new Response(200))
            ->withHeader('Content-Type', 'text/html')
            ->withBody($this->streamFactory->createStream($originalBody));

        $result = $middleware->process($request, function () use ($response) {
            return $response;
        });

        $this->assertEquals(
            '<a href="https://my-site.com">Home</a><img src="https://cdn.my-site.com/a.png"><img src="https://cdn.my-site.com/b.png">',
            (string) $result->getBody()
        );
    }

    public function test_it_skips_rewrite_when_replacements_empty(): void
    {
        $middleware = new RewriteBody([]);

        $request = new ServerRequest('GET', 'https://proxy.com/page');
        $originalBody = '<a href="https://example.com">Link</a>';
        $response = (new Response(200))
            ->withHeader('Content-Type', 'text/html')
            ->withBody($this->streamFactory->createStream($originalBody));

        $result = $middleware->process($request, function () use ($response) {
            return $response;
        });

        $this->assertEquals($originalBody, (string) $result->getBody());
    }
}
