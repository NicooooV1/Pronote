<?php
declare(strict_types=1);

namespace API\Controllers;

/**
 * Dashboard Controller
 * GET  /api/v1/dashboard/widgets  — widgets de l'utilisateur
 * PUT  /api/v1/dashboard/layout   — sauvegarder le layout
 */
class DashboardController extends BaseController
{
	public function widgets(): void
	{
		$user = $this->authenticate();
		$userId = (int) $user['id'];
		$userType = $user['type'] ?? $user['profil'] ?? $user['role'] ?? '';

		$stmt = $this->pdo->prepare("
			SELECT udc.id, udc.widget_key, udc.position_x, udc.position_y,
			       udc.width, udc.height, udc.config,
			       dw.nom AS widget_name, dw.description, dw.icone, dw.type_widget
			FROM user_dashboard_config udc
			LEFT JOIN dashboard_widgets dw ON dw.widget_key = udc.widget_key
			WHERE udc.user_id = ? AND udc.user_type = ? AND udc.is_visible = 1
			ORDER BY udc.position_y, udc.position_x
		");
		$stmt->execute([$userId, $userType]);
		$widgets = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		foreach ($widgets as &$w) {
			$w['config'] = json_decode($w['config'] ?? 'null', true);
		}

		$this->json($widgets);
	}

	public function saveLayout(): void
	{
		$user = $this->authenticate();
		$userId = (int) $user['id'];
		$userType = $user['type'] ?? $user['profil'] ?? $user['role'] ?? '';
		$body = $this->jsonBody();

		$layout = $body['layout'] ?? [];
		if (!is_array($layout)) {
			$this->error('Invalid layout data', 400);
		}

		$this->pdo->beginTransaction();
		try {
			$stmt = $this->pdo->prepare("
				UPDATE user_dashboard_config
				SET position_x = ?, position_y = ?, width = ?, height = ?, is_visible = ?
				WHERE id = ? AND user_id = ? AND user_type = ?
			");

			foreach ($layout as $item) {
				$stmt->execute([
					(int) ($item['position_x'] ?? 0),
					(int) ($item['position_y'] ?? 0),
					(int) ($item['width'] ?? 2),
					(int) ($item['height'] ?? 1),
					(int) ($item['is_visible'] ?? 1),
					(int) ($item['id'] ?? 0),
					$userId,
					$userType,
				]);
			}

			$this->pdo->commit();
			$this->json(['message' => 'Layout saved']);
		} catch (\Throwable $e) {
			$this->pdo->rollBack();
			$this->error('Failed to save layout', 500);
		}
	}
}
