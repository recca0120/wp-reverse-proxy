<?php

namespace Recca0120\ReverseProxy\Config\Loaders;

use Recca0120\ReverseProxy\Config\Contracts\LoaderInterface;

class JsonLoader implements LoaderInterface
{
    public function supports(string $file): bool
    {
        return pathinfo($file, PATHINFO_EXTENSION) === 'json';
    }

    public function load(string $file): array
    {
        if (! file_exists($file)) {
            return [];
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);

        return is_array($data) ? $data : [];
    }
}
