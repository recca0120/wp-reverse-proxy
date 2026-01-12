<?php

namespace Recca0120\ReverseProxy\Tests\Unit\WordPress\Admin;

use PHPUnit\Framework\TestCase;
use Recca0120\ReverseProxy\Routing\JsonFileStorage;
use Recca0120\ReverseProxy\WordPress\Admin\RoutesPage;

class RoutesPageExportImportTest extends TestCase
{
    /** @var string */
    private $testFile;

    /** @var JsonFileStorage */
    private $storage;

    /** @var RoutesPage */
    private $routesPage;

    protected function setUp(): void
    {
        $this->testFile = sys_get_temp_dir() . '/test-routes-' . uniqid() . '.json';
        $this->storage = new JsonFileStorage($this->testFile);
        $this->routesPage = new RoutesPage(null, $this->storage);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }
    }

    public function test_export_routes_returns_correct_structure()
    {
        $routes = [
            [
                'id' => 'route_1',
                'path' => '/api/*',
                'target' => 'https://api.example.com',
                'methods' => ['GET', 'POST'],
                'middlewares' => ['ProxyHeaders'],
                'enabled' => true,
            ],
        ];
        $this->storage->save($routes);

        $exported = $this->routesPage->exportRoutes();

        $this->assertArrayHasKey('version', $exported);
        $this->assertArrayHasKey('exported_at', $exported);
        $this->assertArrayHasKey('routes', $exported);
        $this->assertEquals('1.0', $exported['version']);
    }

    public function test_export_routes_contains_all_routes()
    {
        $routes = [
            ['id' => 'route_1', 'path' => '/api/v1/*', 'target' => 'https://v1.example.com', 'methods' => [], 'middlewares' => [], 'enabled' => true],
            ['id' => 'route_2', 'path' => '/api/v2/*', 'target' => 'https://v2.example.com', 'methods' => [], 'middlewares' => [], 'enabled' => false],
        ];
        $this->storage->save($routes);

        $exported = $this->routesPage->exportRoutes();

        $this->assertCount(2, $exported['routes']);
        $this->assertEquals($routes, $exported['routes']);
    }

    public function test_export_routes_returns_empty_routes_when_no_routes()
    {
        $exported = $this->routesPage->exportRoutes();

        $this->assertArrayHasKey('routes', $exported);
        $this->assertEmpty($exported['routes']);
    }

    public function test_export_routes_includes_valid_timestamp()
    {
        $exported = $this->routesPage->exportRoutes();

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/',
            $exported['exported_at']
        );
    }

    public function test_import_routes_rejects_missing_routes_key()
    {
        $result = $this->routesPage->importRoutes(['invalid' => 'data'], 'replace');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_import_routes_rejects_non_array_routes()
    {
        $result = $this->routesPage->importRoutes(['routes' => 'not-an-array'], 'replace');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_import_routes_replace_mode_clears_existing_routes()
    {
        $existingRoutes = [
            ['id' => 'old_1', 'path' => '/old/*', 'target' => 'https://old.example.com', 'methods' => [], 'middlewares' => [], 'enabled' => true],
        ];
        $this->storage->save($existingRoutes);

        $importData = [
            'version' => '1.0',
            'routes' => [
                ['id' => 'new_1', 'path' => '/new/*', 'target' => 'https://new.example.com', 'methods' => [], 'middlewares' => [], 'enabled' => true],
            ],
        ];

        $result = $this->routesPage->importRoutes($importData, 'replace');

        $this->assertTrue($result['success']);
        $routes = $this->storage->all();
        $this->assertCount(1, $routes);
        $this->assertEquals('new_1', $routes[0]['id']);
    }

    public function test_import_routes_merge_mode_keeps_existing_routes()
    {
        $existingRoutes = [
            ['id' => 'existing_1', 'path' => '/existing/*', 'target' => 'https://existing.example.com', 'methods' => [], 'middlewares' => [], 'enabled' => true],
        ];
        $this->storage->save($existingRoutes);

        $importData = [
            'version' => '1.0',
            'routes' => [
                ['id' => 'new_1', 'path' => '/new/*', 'target' => 'https://new.example.com', 'methods' => [], 'middlewares' => [], 'enabled' => true],
            ],
        ];

        $result = $this->routesPage->importRoutes($importData, 'merge');

        $this->assertTrue($result['success']);
        $routes = $this->storage->all();
        $this->assertCount(2, $routes);
    }

    public function test_import_routes_merge_mode_updates_existing_by_id()
    {
        $existingRoutes = [
            ['id' => 'route_1', 'path' => '/old-path/*', 'target' => 'https://old.example.com', 'methods' => [], 'middlewares' => [], 'enabled' => true],
        ];
        $this->storage->save($existingRoutes);

        $importData = [
            'version' => '1.0',
            'routes' => [
                ['id' => 'route_1', 'path' => '/updated-path/*', 'target' => 'https://updated.example.com', 'methods' => [], 'middlewares' => [], 'enabled' => false],
            ],
        ];

        $result = $this->routesPage->importRoutes($importData, 'merge');

        $this->assertTrue($result['success']);
        $routes = $this->storage->all();
        $this->assertCount(1, $routes);
        $this->assertEquals('/updated-path/*', $routes[0]['path']);
        $this->assertEquals('https://updated.example.com', $routes[0]['target']);
    }

    public function test_import_routes_returns_imported_count()
    {
        $importData = [
            'version' => '1.0',
            'routes' => [
                ['id' => 'route_1', 'path' => '/a/*', 'target' => 'https://a.example.com', 'methods' => [], 'middlewares' => [], 'enabled' => true],
                ['id' => 'route_2', 'path' => '/b/*', 'target' => 'https://b.example.com', 'methods' => [], 'middlewares' => [], 'enabled' => true],
                ['id' => 'route_3', 'path' => '/c/*', 'target' => 'https://c.example.com', 'methods' => [], 'middlewares' => [], 'enabled' => true],
            ],
        ];

        $result = $this->routesPage->importRoutes($importData, 'replace');

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['imported']);
        $this->assertEquals(0, $result['skipped']);
    }

    public function test_import_routes_skips_non_array_routes()
    {
        $importData = [
            'version' => '1.0',
            'routes' => [
                ['id' => 'route_1', 'path' => '/valid/*', 'target' => 'https://valid.example.com', 'methods' => [], 'middlewares' => [], 'enabled' => true],
                'invalid-route-string',
                123,
                null,
            ],
        ];

        $result = $this->routesPage->importRoutes($importData, 'replace');

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['imported']);
        $this->assertEquals(3, $result['skipped']);
    }

    public function test_import_routes_empty_routes_array_succeeds()
    {
        $importData = [
            'version' => '1.0',
            'routes' => [],
        ];

        $result = $this->routesPage->importRoutes($importData, 'replace');

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['imported']);
        $this->assertEquals(0, $result['skipped']);
    }

    public function test_export_then_import_roundtrip()
    {
        $routes = [
            ['id' => 'route_1', 'path' => '/api/*', 'target' => 'https://api.example.com', 'methods' => ['GET'], 'middlewares' => ['ProxyHeaders'], 'enabled' => true],
            ['id' => 'route_2', 'path' => '/web/*', 'target' => 'https://web.example.com', 'methods' => ['POST'], 'middlewares' => [], 'enabled' => false],
        ];
        $this->storage->save($routes);

        // Export
        $exported = $this->routesPage->exportRoutes();

        // Clear storage
        $this->storage->save([]);
        $this->assertEmpty($this->storage->all());

        // Import
        $result = $this->routesPage->importRoutes($exported, 'replace');

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['imported']);

        $importedRoutes = $this->storage->all();
        $this->assertEquals($routes, $importedRoutes);
    }
}
