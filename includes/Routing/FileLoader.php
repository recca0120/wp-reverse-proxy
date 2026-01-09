<?php

namespace Recca0120\ReverseProxy\Routing;

use Recca0120\ReverseProxy\Contracts\FileLoaderInterface;
use Recca0120\ReverseProxy\Contracts\RouteLoaderInterface;
use Recca0120\ReverseProxy\Routing\Loaders\JsonLoader;
use Recca0120\ReverseProxy\Routing\Loaders\PhpArrayLoader;
use Recca0120\ReverseProxy\Routing\Loaders\YamlLoader;
use Recca0120\ReverseProxy\Support\Arr;

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
     * Get the cache key for this loader.
     */
    public function getCacheKey(): ?string
    {
        if (empty($this->paths)) {
            return null;
        }

        $paths = $this->paths;
        sort($paths);

        return 'file_loader_' . md5(implode('|', $paths));
    }

    /**
     * Get metadata for cache validation (max mtime of all files).
     *
     * @return int
     */
    public function getCacheMetadata()
    {
        return $this->getMaxMtime();
    }

    /**
     * Check if cached data is still valid by comparing mtime.
     *
     * @param mixed $metadata The mtime stored with cached data
     */
    public function isCacheValid($metadata): bool
    {
        return $metadata === $this->getMaxMtime();
    }

    /**
     * Get the maximum modification time of all files.
     */
    private function getMaxMtime(): int
    {
        $maxMtime = 0;

        foreach ($this->getAllFiles() as $file) {
            $mtime = filemtime($file);
            if ($mtime > $maxMtime) {
                $maxMtime = $mtime;
            }
        }

        return $maxMtime;
    }

    /**
     * Get all files from configured paths.
     *
     * @return array<string>
     */
    private function getAllFiles(): array
    {
        $files = [];
        $pattern = $this->getPattern();

        foreach ($this->paths as $path) {
            if (!file_exists($path)) {
                continue;
            }

            if (is_dir($path)) {
                foreach ($this->globFiles($path, $pattern) as $file) {
                    $files[] = $file;
                }
            } else {
                $files[] = $path;
            }
        }

        return $files;
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

        $this->pattern = count($extensions) === 1
            ? '*.' . $extensions[0]
            : '*.{' . implode(',', $extensions) . '}';

        return $this->pattern;
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function loadFromFile(string $file): array
    {
        $config = $this->parseFile($file);

        if (empty($config) || !isset($config['routes'])) {
            return [];
        }

        return $config['routes'];
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

        if (defined('GLOB_BRACE') && strpos($pattern, '{') !== false) {
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
