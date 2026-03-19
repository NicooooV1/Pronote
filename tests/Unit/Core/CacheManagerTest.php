<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use API\Core\CacheManager;
use PHPUnit\Framework\TestCase;

class CacheManagerTest extends TestCase
{
	private CacheManager $cache;
	private string $testDir;

	protected function setUp(): void
	{
		$this->testDir = sys_get_temp_dir() . '/fronote_test_cache_' . uniqid();
		mkdir($this->testDir, 0755, true);
		$this->cache = new CacheManager('file', $this->testDir . '/..');
	}

	protected function tearDown(): void
	{
		// Cleanup
		$files = glob($this->testDir . '/cache/*.cache');
		if ($files) {
			foreach ($files as $f) unlink($f);
		}
		@rmdir($this->testDir . '/cache');
		@rmdir($this->testDir);
	}

	public function testPutAndGet(): void
	{
		$this->cache->put('test_key', 'test_value', 60);
		$this->assertSame('test_value', $this->cache->get('test_key'));
	}

	public function testGetDefaultWhenMissing(): void
	{
		$this->assertNull($this->cache->get('nonexistent'));
		$this->assertSame('default', $this->cache->get('nonexistent', 'default'));
	}

	public function testForget(): void
	{
		$this->cache->put('to_delete', 'value', 60);
		$this->assertSame('value', $this->cache->get('to_delete'));

		$this->cache->forget('to_delete');
		$this->assertNull($this->cache->get('to_delete'));
	}

	public function testRemember(): void
	{
		$callCount = 0;
		$callback = function () use (&$callCount) {
			$callCount++;
			return 'computed';
		};

		// First call — computes
		$value = $this->cache->remember('remember_key', 60, $callback);
		$this->assertSame('computed', $value);
		$this->assertSame(1, $callCount);

		// Second call — cached
		$value = $this->cache->remember('remember_key', 60, $callback);
		$this->assertSame('computed', $value);
		$this->assertSame(1, $callCount); // Not called again
	}

	public function testHas(): void
	{
		$this->assertFalse($this->cache->has('has_key'));
		$this->cache->put('has_key', 'yes', 60);
		$this->assertTrue($this->cache->has('has_key'));
	}

	public function testIncrement(): void
	{
		$this->cache->put('counter', 5, 0);
		$result = $this->cache->increment('counter', 3);
		$this->assertSame(8, $result);
	}

	public function testFlush(): void
	{
		$this->cache->put('a', 1, 60);
		$this->cache->put('b', 2, 60);

		$this->cache->flush();

		$this->assertNull($this->cache->get('a'));
		$this->assertNull($this->cache->get('b'));
	}

	public function testComplexDataTypes(): void
	{
		$data = ['key' => 'value', 'nested' => ['a' => 1, 'b' => [2, 3]]];
		$this->cache->put('complex', $data, 60);
		$this->assertSame($data, $this->cache->get('complex'));
	}

	public function testPermanentCache(): void
	{
		$this->cache->put('permanent', 'forever', 0);
		$this->assertSame('forever', $this->cache->get('permanent'));
	}
}
