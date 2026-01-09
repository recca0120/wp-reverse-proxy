<?php

namespace Recca0120\ReverseProxy\Routing\Loaders;

class JsonLoader extends AbstractLoader
{
    public function supports(string $file): bool
    {
        return pathinfo($file, PATHINFO_EXTENSION) === 'json';
    }

    /**
     * @return array<string, mixed>
     */
    protected function doLoad(string $file): array
    {
        $content = file_get_contents($file);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);

        return is_array($data) ? $data : [];
    }
}
