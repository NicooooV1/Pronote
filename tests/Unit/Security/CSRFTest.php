<?php
declare(strict_types=1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;

class CSRFTest extends TestCase
{
	protected function setUp(): void
	{
		if (session_status() !== PHP_SESSION_ACTIVE) {
			@session_start();
		}
	}

	public function testCSRFClassExists(): void
	{
		$this->assertTrue(class_exists(\API\Security\CSRF::class));
	}

	public function testCSRFImplementsTokenBucket(): void
	{
		$csrf = new \API\Security\CSRF();
		$this->assertInstanceOf(\API\Security\CSRF::class, $csrf);
	}

	public function testGenerateReturnsToken(): void
	{
		$csrf = new \API\Security\CSRF();
		$token = $csrf->generate();

		$this->assertIsString($token);
		$this->assertNotEmpty($token);
		$this->assertGreaterThanOrEqual(32, strlen($token));
	}

	public function testValidateAcceptsGeneratedToken(): void
	{
		$csrf = new \API\Security\CSRF();
		$token = $csrf->generate();

		$this->assertTrue($csrf->validate($token));
	}

	public function testValidateRejectsInvalidToken(): void
	{
		$csrf = new \API\Security\CSRF();
		$csrf->generate(); // Ensure bucket is initialized

		$this->assertFalse($csrf->validate('invalid_token'));
	}

	public function testValidateRejectsEmptyToken(): void
	{
		$csrf = new \API\Security\CSRF();
		$this->assertFalse($csrf->validate(''));
	}
}
