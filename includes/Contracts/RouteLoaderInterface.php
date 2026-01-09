<?php

namespace Recca0120\ReverseProxy\Contracts;

interface RouteLoaderInterface
{
    /**
     * Load route configurations from the source.
     *
     * @return array<array<string, mixed>> Array of route config arrays
     */
    public function load(): array;
}
