<?php

namespace Recca0120\ReverseProxy\WordPress\Admin;

use Recca0120\ReverseProxy\Contracts\StorageInterface;
use Recca0120\ReverseProxy\Support\Arr;

class OptionsStorage implements StorageInterface
{
    public const OPTION_NAME = 'reverse_proxy_admin_routes';

    public const VERSION_OPTION_NAME = 'reverse_proxy_admin_routes_version';

    public function all(): array
    {
        return get_option(self::OPTION_NAME, []);
    }

    public function find(string $id): ?array
    {
        return Arr::firstWhere($this->all(), 'id', $id);
    }

    public function save(array $routes): bool
    {
        update_option(self::OPTION_NAME, $routes);

        $saved = get_option(self::OPTION_NAME, []);
        $success = $saved === $routes;

        if ($success) {
            $this->incrementVersion();
        }

        return $success;
    }

    public function getVersion(): int
    {
        return (int) get_option(self::VERSION_OPTION_NAME, 0);
    }

    public function update(string $id, array $route): bool
    {
        $routes = $this->all();
        $index = Arr::search($routes, 'id', $id);

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
        $index = Arr::search($routes, 'id', $id);

        if ($index !== null) {
            unset($routes[$index]);
            $routes = array_values($routes);
        }

        return $this->save($routes);
    }

    private function incrementVersion(): void
    {
        update_option(self::VERSION_OPTION_NAME, $this->getVersion() + 1);
    }
}
