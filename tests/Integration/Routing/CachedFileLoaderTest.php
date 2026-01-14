<?php

namespace Recca0120\ReverseProxy\Tests\Integration\Routing;

use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Routing\CachedRouteLoader;
use Recca0120\ReverseProxy\Routing\FileLoader;
use Recca0120\ReverseProxy\Tests\Stubs\ArrayCache;

class CachedFileLoaderTest extends TestCase
{
    private $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/cached-file-loader-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function test_cache_key_stable_when_file_content_changes(): void
    {
        // Arrange: Create initial routes file
        $routesFile = $this->tempDir . '/routes.json';
        $this->writeRoutesFile($routesFile, [
            ['path' => '/api/*', 'target' => 'https://api.example.com'],
        ]);

        $cache = new ArrayCache();
        $fileLoader = new FileLoader([$this->tempDir]);
        $cachedLoader = new CachedRouteLoader($fileLoader, $cache);

        // Act: First load - populates cache
        $routes1 = $cachedLoader->load();
        $cacheKeysAfterFirstLoad = array_keys($cache->all());

        // Modify file content (changes fingerprint)
        sleep(1); // Ensure mtime changes
        $this->writeRoutesFile($routesFile, [
            ['path' => '/api/*', 'target' => 'https://api.example.com'],
            ['path' => '/web/*', 'target' => 'https://web.example.com'],
        ]);
        clearstatcache();

        // Act: Second load - should use same cache key but update data
        $routes2 = $cachedLoader->load();
        $cacheKeysAfterSecondLoad = array_keys($cache->all());

        // Assert
        $this->assertCount(1, $routes1);
        $this->assertCount(2, $routes2);
        $this->assertEquals($cacheKeysAfterFirstLoad, $cacheKeysAfterSecondLoad, 'Cache key should remain stable');
    }

    public function test_cache_hit_when_fingerprint_unchanged(): void
    {
        // Arrange
        $routesFile = $this->tempDir . '/routes.json';
        $this->writeRoutesFile($routesFile, [
            ['path' => '/api/*', 'target' => 'https://api.example.com'],
        ]);

        $cache = new ArrayCache();
        $fileLoader = new FileLoader([$this->tempDir]);
        $cachedLoader = new CachedRouteLoader($fileLoader, $cache);

        // Act: First load
        $cachedLoader->load();
        $initialCacheData = $cache->all();

        // Act: Second load without file changes
        $cachedLoader->load();
        $finalCacheData = $cache->all();

        // Assert: Cache data should be identical (hit, not reloaded)
        $this->assertEquals($initialCacheData, $finalCacheData);
    }

    public function test_full_flow_load_cache_invalidate_reload(): void
    {
        // Arrange
        $routesFile = $this->tempDir . '/routes.json';
        $this->writeRoutesFile($routesFile, [
            ['path' => '/v1/*', 'target' => 'https://v1.example.com'],
        ]);

        $cache = new ArrayCache();
        $fileLoader = new FileLoader([$this->tempDir]);
        $cachedLoader = new CachedRouteLoader($fileLoader, $cache);

        // Act 1: Initial load
        $routes = $cachedLoader->load();
        $this->assertCount(1, $routes);
        $this->assertEquals('https://v1.example.com', $routes[0]['target']);

        // Act 2: Modify file
        sleep(1);
        $this->writeRoutesFile($routesFile, [
            ['path' => '/v2/*', 'target' => 'https://v2.example.com'],
        ]);
        clearstatcache();

        // Act 3: Reload - should get new data
        $routes = $cachedLoader->load();
        $this->assertCount(1, $routes);
        $this->assertEquals('https://v2.example.com', $routes[0]['target']);

        // Act 4: Clear cache and verify it's empty for this loader
        $cachedLoader->clearCache();
        $cacheKey = 'route_loader_' . $fileLoader->getIdentifier();
        $this->assertFalse($cache->has($cacheKey));
    }

    public function test_identifier_based_on_paths_not_content(): void
    {
        // Arrange: Create two different files with same paths config
        $routesFile = $this->tempDir . '/routes.json';

        $this->writeRoutesFile($routesFile, [
            ['path' => '/api/*', 'target' => 'https://api1.example.com'],
        ]);

        $fileLoader1 = new FileLoader([$this->tempDir]);
        $identifier1 = $fileLoader1->getIdentifier();

        // Modify content
        sleep(1);
        $this->writeRoutesFile($routesFile, [
            ['path' => '/api/*', 'target' => 'https://api2.example.com'],
        ]);

        $fileLoader2 = new FileLoader([$this->tempDir]);
        $identifier2 = $fileLoader2->getIdentifier();

        // Assert: Same paths should produce same identifier
        $this->assertEquals($identifier1, $identifier2);
    }

    private function writeRoutesFile(string $path, array $routes): void
    {
        file_put_contents($path, json_encode(['routes' => $routes], JSON_PRETTY_PRINT));
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
