<?php
/**
 * Switch active establishment (super-admin or multi-etab admin).
 * GET /admin/etablissement/switch.php?id=2
 */
require_once __DIR__ . '/../../API/bootstrap.php';
require_once __DIR__ . '/../../API/Legacy/Bridge.php';

use API\Middleware\EstablishmentScope;
use API\Services\SuperAdminService;

if (!SuperAdminService::isSuperAdmin() && getUserRole() !== 'administrateur') {
    redirect('accueil/accueil.php');
}

$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    redirect('admin/etablissement/multi.php');
}

// Verify the establishment exists and is active
$pdo = getPDO();
$stmt = $pdo->prepare("SELECT id, nom FROM etablissements WHERE id = ? AND actif = 1");
$stmt->execute([$id]);
$etab = $stmt->fetch(\PDO::FETCH_ASSOC);

if (!$etab) {
    $_SESSION['error_message'] = __('admin.establishment_not_found');
    redirect('admin/etablissement/multi.php');
}

// Switch context
EstablishmentScope::switchTo($id);
$_SESSION['success_message'] = __('admin.switched_to', ['name' => $etab['nom']]);

redirect('admin/dashboard.php');
