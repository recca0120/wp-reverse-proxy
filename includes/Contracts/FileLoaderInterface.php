<?php

namespace Recca0120\ReverseProxy\Contracts;

interface FileLoaderInterface
{
    /**
     * Get supported file extensions.
     *
     * @return array<string>
     */
    public function getExtensions(): array;

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
