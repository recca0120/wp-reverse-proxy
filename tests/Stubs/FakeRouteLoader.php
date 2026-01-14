<?php

namespace Recca0120\ReverseProxy\Tests\Stubs;

use Recca0120\ReverseProxy\Contracts\RouteLoaderInterface;

class FakeRouteLoader implements RouteLoaderInterface
{
    /** @var string */
    private $identifier = 'fake_identifier';

    /** @var string|null */
    private $fingerprint = null;

    /** @var array */
    private $routes = [];

    /** @var int */
    private $loadCallCount = 0;

    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function setFingerprint(?string $fingerprint): self
    {
        $this->fingerprint = $fingerprint;

        return $this;
    }

    public function setRoutes(array $routes): self
    {
        $this->routes = $routes;

        return $this;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getFingerprint(): ?string
    {
        return $this->fingerprint;
    }

    public function load(): array
    {
        $this->loadCallCount++;

        return $this->routes;
    }

    public function getLoadCallCount(): int
    {
        return $this->loadCallCount;
    }

    public function resetLoadCallCount(): self
    {
        $this->loadCallCount = 0;

        return $this;
    }
}
