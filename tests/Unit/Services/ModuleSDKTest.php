<?php
declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;

class ModuleSDKTest extends TestCase
{
	public function testDiscoverFindsModules(): void
	{
		// Test that module.json files exist for core modules
		$basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
		$coreModules = ['accueil', 'notes', 'absences', 'messagerie', 'parametres'];

		foreach ($coreModules as $mod) {
			$path = $basePath . '/' . $mod . '/module.json';
			$this->assertFileExists($path, "module.json missing for {$mod}");
		}
	}

	public function testModuleJsonStructure(): void
	{
		$basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
		$path = $basePath . '/notes/module.json';

		$this->assertFileExists($path);
		$manifest = json_decode(file_get_contents($path), true);

		$this->assertIsArray($manifest);
		$this->assertArrayHasKey('key', $manifest);
		$this->assertArrayHasKey('version', $manifest);
		$this->assertArrayHasKey('name', $manifest);
		$this->assertArrayHasKey('permissions', $manifest);
		$this->assertArrayHasKey('routes', $manifest);

		$this->assertSame('notes', $manifest['key']);
		$this->assertArrayHasKey('fr', $manifest['name']);
		$this->assertArrayHasKey('en', $manifest['name']);
	}

	public function testAllModulesHaveRequiredFields(): void
	{
		$basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
		$requiredFields = ['key', 'version', 'name', 'permissions', 'routes'];

		$dirs = glob($basePath . '/*/module.json');
		$this->assertNotEmpty($dirs, 'No module.json files found');

		foreach ($dirs as $file) {
			$manifest = json_decode(file_get_contents($file), true);
			$moduleName = basename(dirname($file));

			$this->assertIsArray($manifest, "Invalid JSON in {$moduleName}/module.json");

			foreach ($requiredFields as $field) {
				$this->assertArrayHasKey(
					$field,
					$manifest,
					"Missing '{$field}' in {$moduleName}/module.json"
				);
			}
		}
	}

	public function testEstablishmentTypesAreValid(): void
	{
		$basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
		$validTypes = ['college', 'lycee', 'superieur'];

		$dirs = glob($basePath . '/*/module.json');

		foreach ($dirs as $file) {
			$manifest = json_decode(file_get_contents($file), true);
			$types = $manifest['establishment_types'] ?? null;

			if ($types !== null) {
				$this->assertIsArray($types);
				foreach ($types as $type) {
					$this->assertContains(
						$type,
						$validTypes,
						"Invalid establishment_type '{$type}' in " . basename(dirname($file))
					);
				}
			}
		}
	}
}
