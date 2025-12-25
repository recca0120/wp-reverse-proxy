<?php

namespace ReverseProxy;

interface RouteAwareInterface
{
    public function setRoute(Route $route): void;
}
