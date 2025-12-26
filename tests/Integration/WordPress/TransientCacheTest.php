<?php

namespace ReverseProxy\Tests\Integration\WordPress;

use Psr\SimpleCache\CacheInterface;
use ReverseProxy\WordPress\TransientCache;
use WP_UnitTestCase;

class TransientCacheTest extends WP_UnitTestCase
{
    /** @var TransientCache */
    private $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new TransientCache('test_');
    }

    protected function tearDown(): void
    {
        $this->cache->clear();
        parent::tearDown();
    }

    public function test_it_implements_cache_interface()
    {
        $this->assertInstanceOf(CacheInterface::class, $this->cache);
    }

    public function test_it_can_set_and_get_value()
    {
        $this->cache->set('key1', 'value1');

        $this->assertEquals('value1', $this->cache->get('key1'));
    }

    public function test_it_returns_default_when_key_not_exists()
    {
        $this->assertEquals('default', $this->cache->get('nonexistent', 'default'));
        $this->assertNull($this->cache->get('nonexistent'));
    }

    public function test_it_can_store_array_values()
    {
        $data = ['foo' => 'bar', 'count' => 42];
        $this->cache->set('array_key', $data);

        $this->assertEquals($data, $this->cache->get('array_key'));
    }

    public function test_it_can_delete_value()
    {
        $this->cache->set('to_delete', 'value');
        $this->assertTrue($this->cache->has('to_delete'));

        $this->cache->delete('to_delete');

        $this->assertFalse($this->cache->has('to_delete'));
    }

    public function test_it_can_check_if_key_exists()
    {
        $this->assertFalse($this->cache->has('new_key'));

        $this->cache->set('new_key', 'value');

        $this->assertTrue($this->cache->has('new_key'));
    }

    public function test_it_can_clear_all_values()
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $this->cache->clear();

        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }

    public function test_it_can_get_multiple_values()
    {
        $this->cache->set('multi1', 'value1');
        $this->cache->set('multi2', 'value2');

        $result = $this->cache->getMultiple(['multi1', 'multi2', 'multi3'], 'default');

        $this->assertEquals([
            'multi1' => 'value1',
            'multi2' => 'value2',
            'multi3' => 'default',
        ], $result);
    }

    public function test_it_can_set_multiple_values()
    {
        $this->cache->setMultiple([
            'batch1' => 'value1',
            'batch2' => 'value2',
        ]);

        $this->assertEquals('value1', $this->cache->get('batch1'));
        $this->assertEquals('value2', $this->cache->get('batch2'));
    }

    public function test_it_can_delete_multiple_values()
    {
        $this->cache->set('del1', 'value1');
        $this->cache->set('del2', 'value2');
        $this->cache->set('keep', 'value3');

        $this->cache->deleteMultiple(['del1', 'del2']);

        $this->assertFalse($this->cache->has('del1'));
        $this->assertFalse($this->cache->has('del2'));
        $this->assertTrue($this->cache->has('keep'));
    }

    public function test_it_respects_ttl()
    {
        $this->cache->set('ttl_key', 'value', 1);

        $this->assertEquals('value', $this->cache->get('ttl_key'));
    }

    public function test_different_prefixes_are_isolated()
    {
        $cache1 = new TransientCache('prefix1_');
        $cache2 = new TransientCache('prefix2_');

        $cache1->set('shared_key', 'value1');
        $cache2->set('shared_key', 'value2');

        $this->assertEquals('value1', $cache1->get('shared_key'));
        $this->assertEquals('value2', $cache2->get('shared_key'));

        $cache1->clear();
        $cache2->clear();
    }
}
