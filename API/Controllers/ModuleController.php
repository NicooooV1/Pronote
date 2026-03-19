<?php
declare(strict_types=1);

namespace API\Controllers;

/**
 * Module Controller — CRUD sur les modules
 * GET /api/v1/modules
 * GET /api/v1/modules/:key
 */
class ModuleController extends BaseController
{
	public function index(): void
	{
		$this->authenticate();

		$modules = [];
		try {
			$sdk = app('module_sdk');
			$all = $sdk->discover();

			foreach ($all as $manifest) {
				$modules[] = [
					'key' => $manifest['key'],
					'name' => $manifest['name'],
					'description' => $manifest['description'] ?? null,
					'version' => $manifest['version'] ?? '1.0.0',
					'icon' => $manifest['icon'] ?? 'fas fa-puzzle-piece',
					'category' => $manifest['category'] ?? 'outils',
					'core' => $manifest['core'] ?? false,
					'establishment_types' => $manifest['establishment_types'] ?? null,
				];
			}
		} catch (\Throwable $e) {
			// Fallback: empty list
		}

		$this->json($modules);
	}

	public function show(array $params): void
	{
		$this->authenticate();
		$key = $params['key'] ?? '';

		$manifestPath = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/' . $key . '/module.json';
		if (!file_exists($manifestPath)) {
			$this->error('Module not found', 404);
		}

		$manifest = json_decode(file_get_contents($manifestPath), true);
		if (!$manifest) {
			$this->error('Invalid module manifest', 500);
		}

		$this->json($manifest);
	}
}
