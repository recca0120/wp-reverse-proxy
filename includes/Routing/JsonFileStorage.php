<?php

namespace Recca0120\ReverseProxy\Routing;

use Recca0120\ReverseProxy\Contracts\StorageInterface;
use Recca0120\ReverseProxy\Support\Arr;

class JsonFileStorage implements StorageInterface
{
    /** @var string */
    private $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    public function all(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $content = file_get_contents($this->filePath);
        $routes = json_decode($content, true);

        return is_array($routes) ? $routes : [];
    }

    public function find(string $id): ?array
    {
        return Arr::find($this->all(), static function ($route) use ($id) {
            return $route['id'] === $id;
        });
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

    public function update(string $id, array $route): bool
    {
        $routes = $this->all();
        $index = $this->findIndex($id);

        if ($index !== null) {
            $routes[$index] = $route;
        } else {
            $routes[] = $route;
        }

        return $this->save($routes);
    }

    public function delete(string $id): bool
    {
        $routes = $this->all();
        $routes = array_values(array_filter($routes, function ($route) use ($id) {
            return $route['id'] !== $id;
        }));

        return $this->save($routes);
    }

    public function getVersion(): int
    {
        if (!file_exists($this->filePath)) {
            return 0;
        }

        return (int) filemtime($this->filePath);
    }

    private function findIndex(string $id): ?int
    {
        $routes = $this->all();

        foreach ($routes as $index => $route) {
            if ($route['id'] === $id) {
                return $index;
            }
        }

        return null;
    }
}
