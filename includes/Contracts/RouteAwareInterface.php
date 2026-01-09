<?php

namespace Recca0120\ReverseProxy\Contracts;

use Recca0120\ReverseProxy\Routing\Route;

interface RouteAwareInterface
{
    public function setRoute(Route $route): void;
}
