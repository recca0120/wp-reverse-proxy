<?php

namespace Recca0120\ReverseProxy\WordPress\Admin;

class JsonFileStorage implements RouteStorageInterface
{
    /** @var string */
    private $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    public function getAll(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $content = file_get_contents($this->filePath);
        $routes = json_decode($content, true);

        return is_array($routes) ? $routes : [];
    }

    public function save(array $routes): bool
    {
        $directory = dirname($this->filePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $content = json_encode($routes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return file_put_contents($this->filePath, $content) !== false;
    }

    public function getVersion(): int
    {
        if (!file_exists($this->filePath)) {
            return 0;
        }

        return (int) filemtime($this->filePath);
    }
}
