<?php

namespace Recca0120\ReverseProxy\Contracts;

use Psr\SimpleCache\CacheInterface;

interface CacheAwareInterface
{
    public function setCache(CacheInterface $cache): void;
}
