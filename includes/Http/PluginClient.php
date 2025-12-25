<?php

namespace ReverseProxy\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class PluginClient implements ClientInterface
{
    /** @var ClientInterface */
    private $client;

    /** @var PluginInterface[] */
    private $plugins;

    /**
     * @param  PluginInterface[]  $plugins
     */
    public function __construct(ClientInterface $client, array $plugins = [])
    {
        $this->client = $client;
        $this->plugins = $plugins;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        foreach ($this->plugins as $plugin) {
            $request = $plugin->filterRequest($request);
        }

        $response = $this->client->sendRequest($request);

        foreach (array_reverse($this->plugins) as $plugin) {
            $response = $plugin->filterResponse($response);
        }

        return $response;
    }
}
