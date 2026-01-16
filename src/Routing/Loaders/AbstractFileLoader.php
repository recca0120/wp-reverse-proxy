<?php

namespace Recca0120\ReverseProxy\Routing\Loaders;

use Recca0120\ReverseProxy\Contracts\FileLoaderInterface;
use Recca0120\ReverseProxy\Support\Arr;

abstract class AbstractFileLoader implements FileLoaderInterface
{
    /**
     * Check if this loader supports the given file.
     */
    public function supports(string $file): bool
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        return Arr::contains($this->getExtensions(), $extension);
    }

    /**
     * Load configuration from file.
     *
     * @return array<string, mixed>
     */
    public function load(string $file): array
    {
        if (! file_exists($file)) {
            return [];
        }

        return $this->doLoad($file);
    }

    /**
     * Perform the actual loading of the file.
     *
     * @return array<string, mixed>
     */
    abstract protected function doLoad(string $file): array;
}
