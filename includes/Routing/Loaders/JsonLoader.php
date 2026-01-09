<?php

namespace Recca0120\ReverseProxy\Routing\Loaders;

class JsonLoader extends AbstractFileLoader
{
    /**
     * @return array<string>
     */
    public function getExtensions(): array
    {
        return ['json'];
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
