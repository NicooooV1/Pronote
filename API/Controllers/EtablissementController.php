<?php
declare(strict_types=1);

namespace API\Controllers;

/**
 * Établissement Controller
 * GET /api/v1/etablissement
 */
class EtablissementController extends BaseController
{
	public function index(): void
	{
		$this->authenticate();

		try {
			$info = app('etablissement')->getInfo();

			// Ne pas exposer les données sensibles
			$public = [
				'nom' => $info['nom'] ?? '',
				'type' => $info['type'] ?? 'college',
				'adresse' => $info['adresse'] ?? '',
				'ville' => $info['ville'] ?? '',
				'code_postal' => $info['code_postal'] ?? '',
				'telephone' => $info['telephone'] ?? '',
				'email' => $info['email'] ?? '',
				'site_web' => $info['site_web'] ?? '',
				'annee_scolaire' => $info['annee_scolaire'] ?? '',
				'default_locale' => $info['default_locale'] ?? 'fr',
			];

			$this->json($public);
		} catch (\Throwable $e) {
			$this->error('Establishment info unavailable', 500);
		}
	}
}
