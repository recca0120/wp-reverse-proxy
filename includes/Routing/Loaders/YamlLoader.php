<?php

namespace Recca0120\ReverseProxy\Routing\Loaders;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class YamlLoader extends AbstractFileLoader
{
    /**
     * @return array<string>
     */
    public function getExtensions(): array
    {
        return ['yaml', 'yml'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function doLoad(string $file): array
    {
        $content = file_get_contents($file);
        if ($content === false || $content === '') {
            return [];
        }

        try {
            $data = Yaml::parse($content);

            return is_array($data) ? $data : [];
        } catch (ParseException $e) {
            return [];
        }
    }
}
