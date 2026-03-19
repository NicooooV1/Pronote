<?php
declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;

class TranslationServiceTest extends TestCase
{
	public function testTranslationFilesExist(): void
	{
		$basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);

		$this->assertDirectoryExists($basePath . '/lang/fr');
		$this->assertDirectoryExists($basePath . '/lang/en');
	}

	public function testFrenchCommonFileHasKeys(): void
	{
		$basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
		$file = $basePath . '/lang/fr/common.json';

		$this->assertFileExists($file);

		$data = json_decode(file_get_contents($file), true);
		$this->assertIsArray($data);
		$this->assertNotEmpty($data);

		// Verify essential keys exist
		$essentialKeys = ['btn.save', 'btn.cancel', 'btn.delete'];
		foreach ($essentialKeys as $key) {
			$this->assertArrayHasKey($key, $data, "Missing key: {$key}");
		}
	}

	public function testEnglishCommonFileHasKeys(): void
	{
		$basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
		$file = $basePath . '/lang/en/common.json';

		$this->assertFileExists($file);

		$data = json_decode(file_get_contents($file), true);
		$this->assertIsArray($data);
		$this->assertNotEmpty($data);
	}

	public function testFrenchAndEnglishHaveSameKeys(): void
	{
		$basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);

		$fr = json_decode(file_get_contents($basePath . '/lang/fr/common.json'), true);
		$en = json_decode(file_get_contents($basePath . '/lang/en/common.json'), true);

		$frKeys = array_keys($fr);
		$enKeys = array_keys($en);

		$missingInEn = array_diff($frKeys, $enKeys);
		$missingInFr = array_diff($enKeys, $frKeys);

		$this->assertEmpty(
			$missingInEn,
			'Keys in fr/common.json missing from en/common.json: ' . implode(', ', $missingInEn)
		);
		$this->assertEmpty(
			$missingInFr,
			'Keys in en/common.json missing from fr/common.json: ' . implode(', ', $missingInFr)
		);
	}

	public function testTranslationServiceClassExists(): void
	{
		$this->assertTrue(class_exists(\API\Services\TranslationService::class));
	}
}
