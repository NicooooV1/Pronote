<?php
/**
 * API REST v1 — Point d'entrée centralisé
 *
 * URL : /API/v1/index.php/{resource}[/{id}]
 * Requires .htaccess rewrite: RewriteRule ^API/v1/(.*)$ API/v1/index.php/$1 [L,QSA]
 *
 * Auth : Bearer token (api_tokens table) ou session cookie
 */
require_once __DIR__ . '/../bootstrap.php';

use API\Router;

header('X-API-Version: v1');

// ─── Auth Middleware ─────────────────────────────────────────────
$router = new Router('/API/v1');

$router->use(function () {
    // Vérifier le Bearer token ou la session
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
        $tokenHash = hash('sha256', $m[1]);
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT user_id, user_type FROM api_tokens WHERE token_hash = ? AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1");
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['user_type'] = $row['user_type'];
            return true;
        }
    }

    // Fallback sur session existante
    if (!empty($_SESSION['user_id'])) {
        return true;
    }

    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized', 'message' => 'Valid Bearer token or session required']);
    return false;
});

// ─── Rate Limiting ──────────────────────────────────────────────
$router->use(function () {
    try {
        $firewall = app('firewall');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($firewall->isBlocked($ip)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Forbidden', 'message' => 'IP blocked']);
            return false;
        }
    } catch (\Throwable $e) { /* firewall optionnel */ }
    return true;
});

// ─── Routes ─────────────────────────────────────────────────────

$pdo = getPDO();

// --- Students ---
$router->get('/students', function () use ($pdo) {
    $p = Router::paginationParams();
    $search = $_GET['q'] ?? '';
    $classeId = $_GET['classe_id'] ?? null;

    $where = "WHERE 1=1";
    $params = [];
    if ($search) {
        $where .= " AND (e.nom LIKE ? OR e.prenom LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    if ($classeId) {
        $where .= " AND e.classe_id = ?";
        $params[] = (int) $classeId;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM eleves e {$where}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $params[] = $p['per_page'];
    $params[] = $p['offset'];
    $stmt = $pdo->prepare("SELECT e.id, e.nom, e.prenom, e.date_naissance, e.mail, e.telephone, e.actif, c.nom AS classe_nom FROM eleves e LEFT JOIN classes c ON c.id = e.classe_id {$where} ORDER BY e.nom, e.prenom LIMIT ? OFFSET ?");
    $stmt->execute($params);

    return Router::paginate($stmt->fetchAll(PDO::FETCH_ASSOC), $total, $p['page'], $p['per_page']);
});

$router->get('/students/{id}', function ($params) use ($pdo) {
    $stmt = $pdo->prepare("SELECT e.*, c.nom AS classe_nom FROM eleves e LEFT JOIN classes c ON c.id = e.classe_id WHERE e.id = ?");
    $stmt->execute([(int) $params['id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        http_response_code(404);
        return ['error' => 'Student not found'];
    }
    unset($student['mot_de_passe']);
    return ['data' => $student];
});

// --- Teachers ---
$router->get('/teachers', function () use ($pdo) {
    $p = Router::paginationParams();
    $countStmt = $pdo->query("SELECT COUNT(*) FROM professeurs");
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT id, nom, prenom, mail, telephone, matiere, actif FROM professeurs ORDER BY nom, prenom LIMIT ? OFFSET ?");
    $stmt->execute([$p['per_page'], $p['offset']]);

    return Router::paginate($stmt->fetchAll(PDO::FETCH_ASSOC), $total, $p['page'], $p['per_page']);
});

// --- Classes ---
$router->get('/classes', function () use ($pdo) {
    $stmt = $pdo->query("SELECT c.id, c.nom, c.niveau, c.annee_scolaire, p.nom AS pp_nom, p.prenom AS pp_prenom FROM classes c LEFT JOIN professeurs p ON p.id = c.professeur_principal_id ORDER BY c.nom");
    return ['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
});

// --- Grades ---
$router->get('/grades', function () use ($pdo) {
    $p = Router::paginationParams();
    $eleveId = $_GET['eleve_id'] ?? null;
    $matiereId = $_GET['matiere_id'] ?? null;

    $where = "WHERE 1=1";
    $params = [];
    if ($eleveId) { $where .= " AND n.eleve_id = ?"; $params[] = (int) $eleveId; }
    if ($matiereId) { $where .= " AND n.matiere_id = ?"; $params[] = (int) $matiereId; }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notes n {$where}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $params[] = $p['per_page'];
    $params[] = $p['offset'];
    $stmt = $pdo->prepare("SELECT n.id, n.eleve_id, n.matiere_id, n.note, n.bareme, n.coefficient, n.commentaire, n.date_evaluation, m.nom AS matiere_nom FROM notes n LEFT JOIN matieres m ON m.id = n.matiere_id {$where} ORDER BY n.date_evaluation DESC LIMIT ? OFFSET ?");
    $stmt->execute($params);

    return Router::paginate($stmt->fetchAll(PDO::FETCH_ASSOC), $total, $p['page'], $p['per_page']);
});

// --- Absences ---
$router->get('/absences', function () use ($pdo) {
    $p = Router::paginationParams();
    $eleveId = $_GET['eleve_id'] ?? null;

    $where = "WHERE 1=1";
    $params = [];
    if ($eleveId) { $where .= " AND a.eleve_id = ?"; $params[] = (int) $eleveId; }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM absences a {$where}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $params[] = $p['per_page'];
    $params[] = $p['offset'];
    $stmt = $pdo->prepare("SELECT a.id, a.eleve_id, a.date_absence, a.heure_debut, a.heure_fin, a.motif, a.justifie, a.type FROM absences a {$where} ORDER BY a.date_absence DESC LIMIT ? OFFSET ?");
    $stmt->execute($params);

    return Router::paginate($stmt->fetchAll(PDO::FETCH_ASSOC), $total, $p['page'], $p['per_page']);
});

// --- Schedule ---
$router->get('/schedule', function () use ($pdo) {
    $classeId = $_GET['classe_id'] ?? null;
    $day = $_GET['day'] ?? null;

    $where = "WHERE 1=1";
    $params = [];
    if ($classeId) { $where .= " AND edt.classe_id = ?"; $params[] = (int) $classeId; }
    if ($day) { $where .= " AND edt.jour = ?"; $params[] = $day; }

    $stmt = $pdo->prepare("SELECT edt.*, m.nom AS matiere_nom, p.nom AS prof_nom, p.prenom AS prof_prenom, s.nom AS salle_nom FROM emploi_du_temps edt LEFT JOIN matieres m ON m.id = edt.matiere_id LEFT JOIN professeurs p ON p.id = edt.professeur_id LEFT JOIN salles s ON s.id = edt.salle_id {$where} ORDER BY edt.jour, edt.heure_debut");
    $stmt->execute($params);

    return ['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
});

// --- Notifications ---
$router->get('/notifications', function () use ($pdo) {
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $userType = $_SESSION['user_type'] ?? '';
    $p = Router::paginationParams();

    $stmt = $pdo->prepare("SELECT id, type, titre, message, importance, lu, date_creation FROM notifications_globales WHERE user_id = ? AND user_type = ? ORDER BY date_creation DESC LIMIT ? OFFSET ?");
    $stmt->execute([$userId, $userType, $p['per_page'], $p['offset']]);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications_globales WHERE user_id = ? AND user_type = ?");
    $countStmt->execute([$userId, $userType]);

    return Router::paginate($stmt->fetchAll(PDO::FETCH_ASSOC), (int) $countStmt->fetchColumn(), $p['page'], $p['per_page']);
});

// --- Modules ---
$router->get('/modules', function () {
    $modules = app('modules');
    return ['data' => $modules->getAll()];
});

// --- System info ---
$router->get('/system/info', function () {
    $versionFile = defined('BASE_PATH') ? BASE_PATH . '/version.json' : '';
    $version = file_exists($versionFile) ? json_decode(file_get_contents($versionFile), true) : [];
    return [
        'data' => [
            'version' => $version['version'] ?? '2.0.0',
            'codename' => $version['codename'] ?? '',
            'instance_id' => defined('INSTANCE_ID') ? INSTANCE_ID : null,
            'php_version' => PHP_VERSION,
        ],
    ];
});

// ─── Dispatch ───────────────────────────────────────────────────
$router->dispatch();
