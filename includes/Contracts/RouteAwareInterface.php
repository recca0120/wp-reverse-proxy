<?php

namespace ReverseProxy\Contracts;

use ReverseProxy\Route;

interface RouteAwareInterface
{
    public function setRoute(Route $route): void;
}
