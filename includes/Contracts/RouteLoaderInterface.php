<?php

namespace Recca0120\ReverseProxy\Contracts;

use Recca0120\ReverseProxy\Routing\Route;

interface RouteLoaderInterface
{
    /**
     * Load routes from the source.
     *
     * @return array<Route>
     */
    public function load(): array;
}
