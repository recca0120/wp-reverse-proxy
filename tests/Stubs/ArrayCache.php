<?php

namespace Recca0120\ReverseProxy\Tests\Stubs;

use Psr\SimpleCache\CacheInterface;

class ArrayCache implements CacheInterface
{
    /** @var array<string, mixed> */
    private $data = [];

    /** @var array<string, int> */
    private $expiry = [];

    public function get($key, $default = null)
    {
        if (! $this->has($key)) {
            return $default;
        }

        return $this->data[$key];
    }

    public function set($key, $value, $ttl = null)
    {
        $this->data[$key] = $value;

        if ($ttl !== null) {
            $this->expiry[$key] = time() + (int) $ttl;
        }

        return true;
    }

    public function delete($key)
    {
        unset($this->data[$key], $this->expiry[$key]);

        return true;
    }

    public function clear()
    {
        $this->data = [];
        $this->expiry = [];

        return true;
    }

    public function getMultiple($keys, $default = null)
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    public function setMultiple($values, $ttl = null)
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple($keys)
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has($key)
    {
        if (! array_key_exists($key, $this->data)) {
            return false;
        }

        if (isset($this->expiry[$key]) && time() > $this->expiry[$key]) {
            $this->delete($key);

            return false;
        }

        return true;
    }

    /**
     * Get all stored data (for testing).
     */
    public function all(): array
    {
        return $this->data;
    }
}
