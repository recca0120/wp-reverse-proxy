<?php

namespace Recca0120\ReverseProxy\WordPress\Admin;

class OptionsStorage implements RouteStorageInterface
{
    public const OPTION_NAME = 'reverse_proxy_admin_routes';

    public const VERSION_OPTION_NAME = 'reverse_proxy_admin_routes_version';

    public function getAll(): array
    {
        return get_option(self::OPTION_NAME, []);
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

    private function incrementVersion(): void
    {
        update_option(self::VERSION_OPTION_NAME, $this->getVersion() + 1);
    }
}
