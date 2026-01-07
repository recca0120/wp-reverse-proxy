<?php

namespace Recca0120\ReverseProxy\Config\Loaders;

class PhpArrayLoader extends AbstractLoader
{
    public function supports(string $file): bool
    {
        return pathinfo($file, PATHINFO_EXTENSION) === 'php';
    }

    /**
     * @return array<string, mixed>
     */
    protected function doLoad(string $file): array
    {
        $data = require $file;

        return is_array($data) ? $data : [];
    }
}
