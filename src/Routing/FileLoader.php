<?php

namespace Recca0120\ReverseProxy\Routing;

use Recca0120\ReverseProxy\Contracts\FileLoaderInterface;
use Recca0120\ReverseProxy\Contracts\RouteLoaderInterface;
use Recca0120\ReverseProxy\Routing\Loaders\JsonLoader;
use Recca0120\ReverseProxy\Routing\Loaders\PhpArrayLoader;
use Recca0120\ReverseProxy\Routing\Loaders\YamlLoader;
use Recca0120\ReverseProxy\Support\Arr;
use Recca0120\ReverseProxy\Support\Str;

class FileLoader implements RouteLoaderInterface
{
    /** @var array<string> */
    private $paths;

    /** @var array<FileLoaderInterface> */
    private $parsers;

    /** @var string|null */
    private $pattern;

    /**
     * @param array<string> $paths Directories or files to load routes from
     * @param array<FileLoaderInterface>|null $parsers Custom parsers (default: JSON, YAML, PHP)
     */
    public function __construct(array $paths, ?array $parsers = null)
    {
        $this->paths = $paths;
        $this->parsers = $parsers ?? $this->defaultParsers();
    }

    /**
     * Get a stable identifier based on configured paths.
     */
    public function getIdentifier(): string
    {
        $paths = $this->paths;
        sort($paths);

        return md5(implode('|', $paths));
    }

    /**
     * Load route configurations from all configured paths.
     *
     * @return array<array<string, mixed>>
     */
    public function load(): array
    {
        return Arr::flatMap($this->getAllFiles(), function ($file) {
            return $this->loadFromFile($file);
        });
    }

    /**
     * Get a fingerprint for cache identification and validation.
     *
     * Returns null if no paths configured, otherwise returns a string
     * combining file paths and their modification times.
     */
    public function getFingerprint(): ?string
    {
        $files = $this->getAllFiles();

        if (empty($files)) {
            return null;
        }

        sort($files);
        $parts = array_map(static function ($file) {
            return $file . ':' . filemtime($file);
        }, $files);

        return implode('|', $parts);
    }

    /**
     * Get all files from configured paths.
     *
     * @return array<string>
     */
    private function getAllFiles(): array
    {
        $pattern = $this->getPattern();

        return Arr::flatMap($this->paths, function ($path) use ($pattern) {
            if (!file_exists($path)) {
                return [];
            }

            return is_dir($path) ? $this->globFiles($path, $pattern) : [$path];
        });
    }

    /**
     * Get glob pattern (cached).
     */
    private function getPattern(): string
    {
        if ($this->pattern !== null) {
            return $this->pattern;
        }

        $extensions = array_unique(Arr::flatMap($this->parsers, function ($parser) {
            return $parser->getExtensions();
        }));

        $this->pattern = '*.{' . implode(',', $extensions) . '}';

        return $this->pattern;
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function loadFromFile(string $file): array
    {
        $config = $this->parseFile($file);

        if (empty($config) || !Arr::has($config, 'routes')) {
            return [];
        }

        return array_filter($config['routes'], function ($route) {
            // Skip routes explicitly disabled (enabled: false)
            // Routes without 'enabled' field or with enabled: true are included
            return !isset($route['enabled']) || $route['enabled'] !== false;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFile(string $file): array
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($file)) {
                return $parser->load($file);
            }
        }

        return [];
    }

    /**
     * @return array<string>
     */
    private function globFiles(string $directory, string $pattern): array
    {
        $fullPattern = $directory . '/' . $pattern;

        if (defined('GLOB_BRACE') && Str::contains($pattern, '{')) {
            return $this->safeGlob($fullPattern, GLOB_BRACE);
        }

        if (preg_match('/\{([^}]+)\}/', $pattern, $matches)) {
            return array_unique(Arr::flatMap(explode(',', $matches[1]), function ($alt) use ($matches, $fullPattern) {
                return $this->safeGlob(str_replace($matches[0], $alt, $fullPattern));
            }));
        }

        return $this->safeGlob($fullPattern);
    }

    /**
     * @return array<string>
     */
    private function safeGlob(string $pattern, int $flags = 0): array
    {
        return glob($pattern, $flags) ?: [];
    }

    /**
     * @return array<FileLoaderInterface>
     */
    private function defaultParsers(): array
    {
        return [
            new JsonLoader(),
            new YamlLoader(),
            new PhpArrayLoader(),
        ];
    }
}
