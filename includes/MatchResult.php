<?php

namespace ReverseProxy;

class MatchResult
{
    /** @var Rule */
    private $rule;

    /** @var string */
    private $targetUrl;

    public function __construct(Rule $rule, string $targetUrl)
    {
        $this->rule = $rule;
        $this->targetUrl = $targetUrl;
    }

    public function getRule(): Rule
    {
        return $this->rule;
    }

    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }
}
