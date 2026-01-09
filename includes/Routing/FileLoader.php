<?php

namespace Recca0120\ReverseProxy\Routing;

use Recca0120\ReverseProxy\Contracts\FileLoaderInterface;
use Recca0120\ReverseProxy\Contracts\RouteLoaderInterface;
use Recca0120\ReverseProxy\Routing\Loaders\JsonLoader;
use Recca0120\ReverseProxy\Routing\Loaders\PhpArrayLoader;
use Recca0120\ReverseProxy\Routing\Loaders\YamlLoader;

class FileLoader implements RouteLoaderInterface
{
    /** @var array<string> */
    private $paths;

    /** @var array<FileLoaderInterface> */
    private $parsers;

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
        $configs = [];

        foreach ($this->paths as $path) {
            foreach ($this->loadFromPath($path) as $config) {
                $configs[] = $config;
            }
        }

        return $configs;
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

        foreach ($this->paths as $path) {
            if (!file_exists($path)) {
                continue;
            }

            if (is_dir($path)) {
                foreach ($this->globFiles($path, $this->buildPattern()) as $file) {
                    $files[] = $file;
                }
            } else {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function loadFromPath(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        if (is_dir($path)) {
            return $this->loadFromDirectory($path);
        }

        return $this->loadFromFile($path);
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function loadFromDirectory(string $directory): array
    {
        $files = $this->globFiles($directory, $this->buildPattern());
        $configs = [];

        foreach ($files as $file) {
            foreach ($this->loadFromFile($file) as $config) {
                $configs[] = $config;
            }
        }

        return $configs;
    }

    /**
     * Build glob pattern from parsers' extensions.
     */
    private function buildPattern(): string
    {
        $extensions = [];
        foreach ($this->parsers as $parser) {
            foreach ($parser->getExtensions() as $ext) {
                $extensions[] = $ext;
            }
        }

        $extensions = array_unique($extensions);

        if (count($extensions) === 1) {
            return '*.' . $extensions[0];
        }

        return '*.{' . implode(',', $extensions) . '}';
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
            $files = [];
            foreach (explode(',', $matches[1]) as $alt) {
                foreach ($this->safeGlob(str_replace($matches[0], $alt, $fullPattern)) as $file) {
                    $files[] = $file;
                }
            }

            return array_unique($files);
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
