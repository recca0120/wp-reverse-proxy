<?php

namespace ReverseProxy\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface PluginInterface
{
    public function filterRequest(RequestInterface $request): RequestInterface;

    public function filterResponse(ResponseInterface $response): ResponseInterface;
}
