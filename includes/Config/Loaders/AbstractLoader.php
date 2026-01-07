<?php

namespace Recca0120\ReverseProxy\Config\Loaders;

use Recca0120\ReverseProxy\Config\Contracts\LoaderInterface;

abstract class AbstractLoader implements LoaderInterface
{
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
