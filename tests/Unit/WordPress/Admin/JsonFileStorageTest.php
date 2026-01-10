<?php

namespace Recca0120\ReverseProxy\Tests\Unit\WordPress\Admin;

use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Routing\JsonFileStorage;

class JsonFileStorageTest extends TestCase
{
    /** @var string */
    private $testFile;

    /** @var JsonFileStorage */
    private $storage;

    protected function setUp(): void
    {
        $this->testFile = sys_get_temp_dir() . '/test-routes-' . uniqid() . '.json';
        $this->storage = new JsonFileStorage($this->testFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }
    }

    public function test_get_all_returns_empty_array_when_file_not_exists()
    {
        $routes = $this->storage->getAll();

        $this->assertIsArray($routes);
        $this->assertEmpty($routes);
    }

    public function test_save_creates_file_with_routes()
    {
        $routes = [
            [
                'id' => 'route_1',
                'path' => '/api/*',
                'target' => 'https://api.example.com',
                'methods' => ['GET', 'POST'],
                'middlewares' => [],
                'enabled' => true,
            ],
        ];

        $result = $this->storage->save($routes);

        $this->assertTrue($result);
        $this->assertFileExists($this->testFile);

        $content = json_decode(file_get_contents($this->testFile), true);
        $this->assertEquals($routes, $content);
    }

    public function test_get_all_returns_saved_routes()
    {
        $routes = [
            [
                'id' => 'route_1',
                'path' => '/api/*',
                'target' => 'https://api.example.com',
                'methods' => ['GET'],
                'middlewares' => [],
                'enabled' => true,
            ],
            [
                'id' => 'route_2',
                'path' => '/static/*',
                'target' => 'https://static.example.com',
                'methods' => ['GET'],
                'middlewares' => [],
                'enabled' => false,
            ],
        ];

        $this->storage->save($routes);
        $retrieved = $this->storage->getAll();

        $this->assertEquals($routes, $retrieved);
    }

    public function test_get_version_returns_zero_when_file_not_exists()
    {
        $version = $this->storage->getVersion();

        $this->assertEquals(0, $version);
    }

    public function test_get_version_returns_file_mtime()
    {
        $routes = [['id' => 'route_1', 'path' => '/test/*', 'target' => 'https://test.com', 'methods' => [], 'middlewares' => [], 'enabled' => true]];
        $this->storage->save($routes);

        $version = $this->storage->getVersion();

        $this->assertEquals(filemtime($this->testFile), $version);
    }

    public function test_save_updates_version()
    {
        $this->storage->save([]);
        $version1 = $this->storage->getVersion();

        sleep(1); // Ensure mtime changes

        $this->storage->save([['id' => 'route_1', 'path' => '/a/*', 'target' => 'https://a.com', 'methods' => [], 'middlewares' => [], 'enabled' => true]]);
        $version2 = $this->storage->getVersion();

        $this->assertGreaterThan($version1, $version2);
    }

    public function test_save_overwrites_existing_routes()
    {
        $routes1 = [['id' => 'route_1', 'path' => '/old/*', 'target' => 'https://old.com', 'methods' => [], 'middlewares' => [], 'enabled' => true]];
        $routes2 = [['id' => 'route_2', 'path' => '/new/*', 'target' => 'https://new.com', 'methods' => [], 'middlewares' => [], 'enabled' => true]];

        $this->storage->save($routes1);
        $this->storage->save($routes2);

        $retrieved = $this->storage->getAll();

        $this->assertEquals($routes2, $retrieved);
        $this->assertCount(1, $retrieved);
    }

    public function test_save_creates_directory_if_not_exists()
    {
        $nestedFile = sys_get_temp_dir() . '/nested-' . uniqid() . '/routes.json';
        $storage = new JsonFileStorage($nestedFile);

        $routes = [['id' => 'route_1', 'path' => '/test/*', 'target' => 'https://test.com', 'methods' => [], 'middlewares' => [], 'enabled' => true]];
        $result = $storage->save($routes);

        $this->assertTrue($result);
        $this->assertFileExists($nestedFile);

        // Cleanup
        unlink($nestedFile);
        rmdir(dirname($nestedFile));
    }

    public function test_save_formats_json_with_pretty_print()
    {
        $routes = [['id' => 'route_1', 'path' => '/test/*', 'target' => 'https://test.com', 'methods' => [], 'middlewares' => [], 'enabled' => true]];

        $this->storage->save($routes);

        $content = file_get_contents($this->testFile);

        // Pretty print should have newlines and indentation
        $this->assertStringContainsString("\n", $content);
        $this->assertStringContainsString('    ', $content);
    }

    public function test_get_all_returns_empty_array_when_file_contains_invalid_json()
    {
        file_put_contents($this->testFile, 'invalid json content');

        $routes = $this->storage->getAll();

        $this->assertIsArray($routes);
        $this->assertEmpty($routes);
    }
}
