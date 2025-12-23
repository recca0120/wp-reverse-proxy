<?php

namespace ReverseProxy\Tests\Unit;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use ReverseProxy\MatchResult;
use ReverseProxy\Rule;

class RuleTest extends TestCase
{
    public function test_it_matches_exact_path()
    {
        $rule = new Rule('/api/users', 'https://backend.example.com');
        $request = new ServerRequest('GET', '/api/users');

        $result = $rule->matches($request);

        $this->assertInstanceOf(MatchResult::class, $result);
        $this->assertEquals('https://backend.example.com/api/users', $result->getTargetUrl());
    }

    public function test_it_returns_null_when_path_does_not_match()
    {
        $rule = new Rule('/api/users', 'https://backend.example.com');
        $request = new ServerRequest('GET', '/about');

        $result = $rule->matches($request);

        $this->assertNull($result);
    }

    public function test_it_matches_wildcard_pattern()
    {
        $rule = new Rule('/api/*', 'https://backend.example.com');
        $request = new ServerRequest('GET', '/api/users/123');

        $result = $rule->matches($request);

        $this->assertInstanceOf(MatchResult::class, $result);
        $this->assertEquals('https://backend.example.com/api/users/123', $result->getTargetUrl());
    }

    public function test_it_rewrites_path_with_captures()
    {
        $rule = new Rule('/api/v1/*', 'https://backend.example.com', '/v1/$1');
        $request = new ServerRequest('GET', '/api/v1/users/123');

        $result = $rule->matches($request);

        $this->assertInstanceOf(MatchResult::class, $result);
        $this->assertEquals('https://backend.example.com/v1/users/123', $result->getTargetUrl());
    }

    public function test_it_preserves_query_string()
    {
        $rule = new Rule('/api/*', 'https://backend.example.com');
        $request = new ServerRequest('GET', '/api/users?page=2&limit=10');

        $result = $rule->matches($request);

        $this->assertEquals('https://backend.example.com/api/users?page=2&limit=10', $result->getTargetUrl());
    }

    public function test_it_preserves_query_string_with_rewrite()
    {
        $rule = new Rule('/api/v1/*', 'https://backend.example.com', '/v1/$1');
        $request = new ServerRequest('GET', '/api/v1/users?page=2');

        $result = $rule->matches($request);

        $this->assertEquals('https://backend.example.com/v1/users?page=2', $result->getTargetUrl());
    }

    public function test_it_returns_preserve_host_setting()
    {
        $rule = new Rule('/api/*', 'https://backend.example.com', null, true);

        $this->assertTrue($rule->shouldPreserveHost());
    }

    public function test_it_returns_false_for_preserve_host_by_default()
    {
        $rule = new Rule('/api/*', 'https://backend.example.com');

        $this->assertFalse($rule->shouldPreserveHost());
    }

    public function test_it_returns_target_host()
    {
        $rule = new Rule('/api/*', 'https://backend.example.com');

        $this->assertEquals('backend.example.com', $rule->getTargetHost());
    }

    public function test_match_result_contains_rule_reference()
    {
        $rule = new Rule('/api/*', 'https://backend.example.com');
        $request = new ServerRequest('GET', '/api/users');

        $result = $rule->matches($request);

        $this->assertSame($rule, $result->getRule());
    }
}
