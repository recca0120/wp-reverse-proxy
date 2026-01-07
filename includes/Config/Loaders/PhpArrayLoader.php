<?php

namespace Recca0120\ReverseProxy\Config\Loaders;

use Recca0120\ReverseProxy\Config\Contracts\LoaderInterface;

class PhpArrayLoader implements LoaderInterface
{
    public function supports(string $file): bool
    {
        return pathinfo($file, PATHINFO_EXTENSION) === 'php';
    }

    public function load(string $file): array
    {
        if (! file_exists($file)) {
            return [];
        }

        $data = require $file;

        return is_array($data) ? $data : [];
    }
}
