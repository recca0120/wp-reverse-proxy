<?php

namespace Recca0120\ReverseProxy\Config\Contracts;

interface LoaderInterface
{
    /**
     * Check if this loader supports the given file.
     */
    public function supports(string $file): bool;

    /**
     * Load configuration from file.
     *
     * @return array<string, mixed>
     */
    public function load(string $file): array;
}
