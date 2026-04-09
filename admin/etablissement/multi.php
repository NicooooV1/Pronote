<?php
/**
 * Super-admin: manage multiple establishments.
 */
require_once __DIR__ . '/../includes/header.php';

use API\Services\SuperAdminService;

// Only super-admin or admin can access
if (!SuperAdminService::isSuperAdmin() && getUserRole() !== 'administrateur') {
    redirect('accueil/accueil.php');
}

$pdo = getPDO();
$superService = new SuperAdminService($pdo);

// Handle actions
$action = $_POST['action'] ?? $_GET['action'] ?? null;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if ($action === 'create') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO etablissements (nom, code, type, adresse, code_postal, ville, telephone, email, academie)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['nom'] ?? '',
                $_POST['code'] ?? '',
                $_POST['type'] ?? 'college',
                $_POST['adresse'] ?? '',
                $_POST['code_postal'] ?? '',
                $_POST['ville'] ?? '',
                $_POST['telephone'] ?? '',
                $_POST['email'] ?? '',
                $_POST['academie'] ?? '',
            ]);
            $message = 'success:' . __('admin.establishment_created');
        } catch (\PDOException $e) {
            $message = 'error:' . ($e->getCode() === '23000' ? __('admin.code_already_exists') : $e->getMessage());
        }
    } elseif ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $actif = (int) ($_POST['actif'] ?? 0);
        $pdo->prepare("UPDATE etablissements SET actif = ? WHERE id = ?")->execute([$actif, $id]);
        $message = 'success:' . __('admin.establishment_updated');
    }
}

// Load establishments
$establishments = $superService->getEstablishments();
$pageTitle = __('admin.establishments');
?>

<div class="admin-container">
    <div class="page-header">
        <h1><i class="fas fa-building"></i> <?= htmlspecialchars($pageTitle) ?></h1>
        <button class="btn btn-primary" onclick="document.getElementById('create-modal').classList.add('active')">
            <i class="fas fa-plus"></i> <?= __('admin.add_establishment') ?>
        </button>
    </div>

    <?php if ($message):
        [$type, $text] = explode(':', $message, 2);
    ?>
    <div class="alert alert-<?= $type === 'success' ? 'success' : 'danger' ?>"><?= htmlspecialchars($text) ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th><?= __('common.name') ?></th>
                    <th>Code</th>
                    <th>Type</th>
                    <th><?= __('common.city') ?></th>
                    <th><?= __('admin.students') ?></th>
                    <th><?= __('admin.teachers') ?></th>
                    <th><?= __('common.status') ?></th>
                    <th><?= __('common.actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($establishments as $etab): ?>
                <tr>
                    <td><?= $etab['id'] ?></td>
                    <td><strong><?= htmlspecialchars($etab['nom']) ?></strong></td>
                    <td><code><?= htmlspecialchars($etab['code']) ?></code></td>
                    <td><span class="badge badge-info"><?= htmlspecialchars($etab['type']) ?></span></td>
                    <td><?= htmlspecialchars($etab['ville'] ?? '') ?></td>
                    <td><?= (int) ($etab['student_count'] ?? 0) ?></td>
                    <td><?= (int) ($etab['teacher_count'] ?? 0) ?></td>
                    <td>
                        <?php if ($etab['actif']): ?>
                            <span class="badge badge-success"><?= __('common.active') ?></span>
                        <?php else: ?>
                            <span class="badge badge-secondary"><?= __('common.inactive') ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="info.php?etab_id=<?= $etab['id'] ?>" class="btn btn-sm btn-outline" title="<?= __('common.edit') ?>">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="switch.php?id=<?= $etab['id'] ?>" class="btn btn-sm btn-outline" title="<?= __('admin.switch_to') ?>">
                            <i class="fas fa-exchange-alt"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create modal -->
<div id="create-modal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?= __('admin.add_establishment') ?></h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('active')">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="form-grid">
                <div class="form-group">
                    <label><?= __('common.name') ?> *</label>
                    <input type="text" name="nom" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Code *</label>
                    <input type="text" name="code" required class="form-control" placeholder="lycee-hugo">
                </div>
                <div class="form-group">
                    <label>Type *</label>
                    <select name="type" class="form-control">
                        <option value="college">College</option>
                        <option value="lycee">Lycee</option>
                        <option value="superieur">Superieur</option>
                        <option value="primaire">Primaire</option>
                        <option value="polyvalent">Polyvalent</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= __('common.address') ?></label>
                    <input type="text" name="adresse" class="form-control">
                </div>
                <div class="form-group">
                    <label><?= __('common.postal_code') ?></label>
                    <input type="text" name="code_postal" class="form-control">
                </div>
                <div class="form-group">
                    <label><?= __('common.city') ?></label>
                    <input type="text" name="ville" class="form-control">
                </div>
                <div class="form-group">
                    <label><?= __('common.phone') ?></label>
                    <input type="text" name="telephone" class="form-control">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
                <div class="form-group">
                    <label><?= __('common.academy') ?></label>
                    <input type="text" name="academie" class="form-control">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= __('common.create') ?></button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
