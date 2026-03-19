<?php
/**
 * Annonces admin — diffusion de messages à tous les utilisateurs ou par profil/classe
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

$pdo = getPDO();
$admin = getCurrentUser();
$message = '';
$error = '';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$classes = $pdo->query("SELECT id, nom FROM classes WHERE actif = 1 ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

// POST : Envoyer une annonce
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf_token'] ?? '') === $csrf_token) {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_announcement') {
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $target = $_POST['target'] ?? 'all';
        $targetClasse = $_POST['target_classe'] ?? '';

        if (empty($subject) || empty($body)) {
            $error = "Le sujet et le message sont obligatoires.";
        } else {
            try {
                $pdo->beginTransaction();
                // Créer une conversation broadcast
                $pdo->prepare("INSERT INTO conversations (subject, type) VALUES (?, 'broadcast')")->execute([$subject]);
                $convId = $pdo->lastInsertId();

                // Ajouter le message
                $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, sender_type, body, status) VALUES (?, ?, 'administrateur', ?, 'annonce')")
                    ->execute([$convId, $admin['id'], $body]);

                // Ajouter l'admin comme participant
                $pdo->prepare("INSERT INTO conversation_participants (conversation_id, user_id, user_type, is_admin) VALUES (?, ?, 'administrateur', 1)")
                    ->execute([$convId, $admin['id']]);

                // Déterminer les destinataires
                $recipients = [];
                $tables = ['eleve' => 'eleves', 'professeur' => 'professeurs', 'parent' => 'parents', 'vie_scolaire' => 'vie_scolaire'];

                $allowedTables = ['eleve' => 'eleves', 'professeur' => 'professeurs', 'parent' => 'parents', 'vie_scolaire' => 'vie_scolaire'];
                if ($target === 'all') {
                    foreach ($allowedTables as $type => $tbl) {
                        $stmt = $pdo->query("SELECT id FROM `{$tbl}` WHERE actif = 1");
                        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $recipients[] = ['id' => $r['id'], 'type' => $type];
                        }
                    }
                } elseif ($target === 'eleves') {
                    if (!empty($targetClasse)) {
                        $stmt = $pdo->prepare("SELECT id FROM eleves WHERE actif = 1 AND classe = ?");
                        $stmt->execute([$targetClasse]);
                    } else {
                        $stmt = $pdo->query("SELECT id FROM eleves WHERE actif = 1");
                    }
                    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $recipients[] = ['id' => $r['id'], 'type' => 'eleve'];
                    }
                } elseif ($target === 'professeurs') {
                    $stmt = $pdo->query("SELECT id FROM professeurs WHERE actif = 1");
                    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $recipients[] = ['id' => $r['id'], 'type' => 'professeur'];
                    }
                } elseif ($target === 'parents') {
                    $stmt = $pdo->query("SELECT id FROM parents WHERE actif = 1");
                    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $recipients[] = ['id' => $r['id'], 'type' => 'parent'];
                    }
                }

                // Ajouter comme participants
                $insertPart = $pdo->prepare("INSERT INTO conversation_participants (conversation_id, user_id, user_type) VALUES (?, ?, ?)");
                foreach ($recipients as $rec) {
                    $insertPart->execute([$convId, $rec['id'], $rec['type']]);
                }

                $pdo->commit();
                logAudit('announcement_sent', 'conversations', $convId, [], ['target' => $target, 'recipients' => count($recipients)]);
                $message = "Annonce envoyée à " . count($recipients) . " destinataire(s).";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Erreur : " . $e->getMessage();
            }
        }
    }
}

// Historique des annonces
$annonces = $pdo->query("
    SELECT c.id, c.subject, c.created_at, m.body,
        (SELECT COUNT(*) FROM conversation_participants cp WHERE cp.conversation_id = c.id AND cp.user_type != 'administrateur') AS dest_count
    FROM conversations c
    JOIN messages m ON m.conversation_id = c.id AND m.status = 'annonce'
    WHERE c.type = 'broadcast'
    ORDER BY c.created_at DESC
    LIMIT 30
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Annonces';
$currentPage = 'msg_annonces';
$extraCss = ['../../assets/css/admin.css'];

ob_start();
?>
<style>
    .ann-container { max-width: 900px; margin: 0 auto; }
    .form-card { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 25px; }
    .form-card h3 { margin: 0 0 15px; font-size: 18px; }
    .form-group textarea { min-height: 120px; resize: vertical; }
    .target-selector { display: flex; gap: 10px; flex-wrap: wrap; }
    .target-option { padding: 8px 16px; border: 2px solid #e2e8f0; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 500; transition: all 0.2s; }
    .target-option.active { border-color: #0f4c81; background: #eff6ff; color: #0f4c81; }
    .ann-item { background: white; border-radius: 8px; padding: 14px 18px; margin-bottom: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.05); }
    .ann-item h4 { margin: 0 0 4px; font-size: 15px; }
    .ann-meta { font-size: 12px; color: #888; }
    .ann-body { font-size: 13px; color: #555; margin-top: 6px; max-height: 50px; overflow: hidden; }
</style>
<?php
$extraHeadHtml = ob_get_clean();
include __DIR__ . '/../includes/sub_header.php';
?>

<div class="ann-container">
    <?php if (!empty($message)): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="form-card">
        <h3><i class="fas fa-bullhorn"></i> Nouvelle annonce</h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="action" value="send_announcement">
            <div class="form-group">
                <label>Sujet</label>
                <input type="text" name="subject" placeholder="Objet de l'annonce…" required>
            </div>
            <div class="form-group">
                <label>Destinataires</label>
                <div class="target-selector">
                    <label class="target-option active" onclick="selectTarget(this, 'all')"><input type="radio" name="target" value="all" checked style="display:none"> <i class="fas fa-globe"></i> Tous</label>
                    <label class="target-option" onclick="selectTarget(this, 'eleves')"><input type="radio" name="target" value="eleves" style="display:none"> <i class="fas fa-user-graduate"></i> Élèves</label>
                    <label class="target-option" onclick="selectTarget(this, 'professeurs')"><input type="radio" name="target" value="professeurs" style="display:none"> <i class="fas fa-chalkboard-teacher"></i> Professeurs</label>
                    <label class="target-option" onclick="selectTarget(this, 'parents')"><input type="radio" name="target" value="parents" style="display:none"> <i class="fas fa-user-friends"></i> Parents</label>
                </div>
            </div>
            <div class="form-group" id="classeFilter" style="display:none">
                <label>Filtrer par classe (optionnel)</label>
                <select name="target_classe"><option value="">Toutes les classes</option>
                    <?php foreach ($classes as $c): ?><option value="<?= htmlspecialchars($c['nom']) ?>"><?= htmlspecialchars($c['nom']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Message</label>
                <textarea name="body" placeholder="Contenu de l'annonce…" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Envoyer l'annonce</button>
        </form>
    </div>

    <h3 style="margin-bottom:15px"><i class="fas fa-history"></i> Historique</h3>
    <div class="ann-history">
        <?php if (empty($annonces)): ?>
            <div style="text-align:center;padding:30px;color:#999"><p>Aucune annonce envoyée.</p></div>
        <?php else: ?>
            <?php foreach ($annonces as $a): ?>
            <div class="ann-item">
                <h4><?= htmlspecialchars($a['subject']) ?></h4>
                <div class="ann-meta">
                    <i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($a['created_at'])) ?>
                    <span style="margin-left:12px"><i class="fas fa-users"></i> <?= $a['dest_count'] ?> destinataire(s)</span>
                </div>
                <div class="ann-body"><?= nl2br(htmlspecialchars(mb_substr($a['body'], 0, 150))) ?></div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function selectTarget(el, val) {
    document.querySelectorAll('.target-option').forEach(o => o.classList.remove('active'));
    el.classList.add('active');
    el.querySelector('input').checked = true;
    document.getElementById('classeFilter').style.display = (val === 'eleves') ? '' : 'none';
}
</script>

<?php include __DIR__ . '/../includes/sub_footer.php'; ?>
