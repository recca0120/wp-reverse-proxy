<?php

namespace Recca0120\ReverseProxy\WordPress\Admin;

use Recca0120\ReverseProxy\Routing\MiddlewareManager;
use Recca0120\ReverseProxy\Support\Arr;
use Recca0120\ReverseProxy\Support\Str;

class MiddlewareRegistry
{
    /** @var MiddlewareManager */
    private $manager;

    /** @var MiddlewareReflector */
    private $reflector;

    public function __construct(MiddlewareManager $manager, ?MiddlewareReflector $reflector = null)
    {
        $this->manager = $manager;
        $this->reflector = $reflector ?? new MiddlewareReflector();
    }

    /**
     * Get the middleware manager.
     */
    public function getManager(): MiddlewareManager
    {
        return $this->manager;
    }

    /**
     * Get available middlewares with UI field definitions.
     * Excludes global middlewares registered in the manager.
     */
    public function getAvailable(): array
    {
        $aliases = $this->manager->getAliases();
        $globalNames = $this->getGlobalMiddlewareNames();

        $middlewares = [];
        foreach ($aliases as $name => $class) {
            if (Arr::contains($globalNames, $name)) {
                continue;
            }

            $reflected = $this->reflector->reflect($class);

            $middlewares[$name] = $reflected ?? [
                'label' => $name,
                'description' => '',
                'fields' => [],
            ];
        }

        return $middlewares;
    }

    /**
     * Get names of global middlewares from the manager.
     *
     * @return array<string>
     */
    private function getGlobalMiddlewareNames(): array
    {
        return array_map(
            function ($middleware) {
                return Str::classBasename(get_class($middleware));
            },
            $this->manager->getGlobalMiddlewares()
        );
    }
}
