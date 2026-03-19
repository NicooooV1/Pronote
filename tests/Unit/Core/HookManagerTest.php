<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use API\Core\HookManager;
use PHPUnit\Framework\TestCase;

class HookManagerTest extends TestCase
{
	private HookManager $hooks;

	protected function setUp(): void
	{
		$this->hooks = new HookManager();
	}

	public function testRegisterAndFire(): void
	{
		$called = false;
		$this->hooks->register('test.event', function () use (&$called) {
			$called = true;
		});

		$this->hooks->fire('test.event');
		$this->assertTrue($called);
	}

	public function testFirePassesArguments(): void
	{
		$received = null;
		$this->hooks->register('test.args', function ($arg) use (&$received) {
			$received = $arg;
		});

		$this->hooks->fire('test.args', 'hello');
		$this->assertSame('hello', $received);
	}

	public function testPriorityOrder(): void
	{
		$order = [];

		$this->hooks->register('test.priority', function () use (&$order) {
			$order[] = 'second';
		}, 20);

		$this->hooks->register('test.priority', function () use (&$order) {
			$order[] = 'first';
		}, 10);

		$this->hooks->fire('test.priority');
		$this->assertSame(['first', 'second'], $order);
	}

	public function testFilter(): void
	{
		$this->hooks->register('test.filter', function ($value) {
			return $value . ' world';
		});

		$result = $this->hooks->filter('test.filter', 'hello');
		$this->assertSame('hello world', $result);
	}

	public function testFilterChaining(): void
	{
		$this->hooks->register('test.chain', function ($value) {
			return $value + 1;
		}, 10);

		$this->hooks->register('test.chain', function ($value) {
			return $value * 2;
		}, 20);

		// 5 → +1 = 6 → *2 = 12
		$result = $this->hooks->filter('test.chain', 5);
		$this->assertSame(12, $result);
	}

	public function testFireUnregisteredEvent(): void
	{
		// Should not throw
		$this->hooks->fire('nonexistent.event');
		$this->assertTrue(true);
	}

	public function testCallbackErrorIsolation(): void
	{
		$secondCalled = false;

		$this->hooks->register('test.error', function () {
			throw new \RuntimeException('Boom');
		}, 10);

		$this->hooks->register('test.error', function () use (&$secondCalled) {
			$secondCalled = true;
		}, 20);

		// The exception in the first callback should not prevent the second
		$this->hooks->fire('test.error');
		$this->assertTrue($secondCalled);
	}
}
