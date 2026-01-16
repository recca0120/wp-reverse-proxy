<?php

namespace Recca0120\ReverseProxy\Routing\Loaders;

class PhpArrayLoader extends AbstractFileLoader
{
    /**
     * @return array<string>
     */
    public function getExtensions(): array
    {
        return ['php'];
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
