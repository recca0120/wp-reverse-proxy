<?php

namespace Recca0120\ReverseProxy\WordPress;

use DateInterval;
use Psr\SimpleCache\CacheInterface;
use Recca0120\ReverseProxy\Support\Arr;

class TransientCache implements CacheInterface
{
    /** @var string */
    private $prefix;

    /** @var array */
    private $keys = [];

    /**
     * @param  string  $prefix
     */
    public function __construct($prefix = 'rp_')
    {
        $this->prefix = $prefix;
        $this->loadKeys();
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        $value = get_transient($this->prefixKey($key));

        return $value !== false ? $value : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        $seconds = $this->ttlToSeconds($ttl);
        $prefixedKey = $this->prefixKey($key);

        $result = set_transient($prefixedKey, $value, $seconds);

        if ($result) {
            $this->trackKey($key);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        $this->untrackKey($key);

        return delete_transient($this->prefixKey($key));
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        foreach ($this->keys as $key) {
            delete_transient($this->prefixKey($key));
        }

        $this->keys = [];
        $this->saveKeys();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys, $default = null)
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null)
    {
        $success = true;

        foreach ($values as $key => $value) {
            if (! $this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple($keys)
    {
        $success = true;

        foreach ($keys as $key) {
            if (! $this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        return get_transient($this->prefixKey($key)) !== false;
    }

    /**
     * @param  string  $key
     * @return string
     */
    private function prefixKey($key)
    {
        return $this->prefix.$key;
    }

    /**
     * @param  null|int|DateInterval  $ttl
     * @return int
     */
    private function ttlToSeconds($ttl)
    {
        if ($ttl === null) {
            return 0;
        }

        if ($ttl instanceof DateInterval) {
            return $ttl->days * 86400 + $ttl->h * 3600 + $ttl->i * 60 + $ttl->s;
        }

        return (int) $ttl;
    }

    private function loadKeys()
    {
        $keys = get_option($this->prefix.'_keys', []);
        $this->keys = is_array($keys) ? $keys : [];
    }

    private function saveKeys()
    {
        update_option($this->prefix.'_keys', $this->keys);
    }

    /**
     * @param  string  $key
     */
    private function trackKey($key)
    {
        if (! Arr::contains($this->keys, $key)) {
            $this->keys[] = $key;
            $this->saveKeys();
        }
    }

    /**
     * @param  string  $key
     */
    private function untrackKey($key)
    {
        $index = array_search($key, $this->keys, true);
        if ($index !== false) {
            unset($this->keys[$index]);
            $this->keys = array_values($this->keys);
            $this->saveKeys();
        }
    }
}
