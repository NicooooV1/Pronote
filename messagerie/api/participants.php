<?php
/**
 * API REST pour la gestion des participants
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../models/participant.php';

// Vérifier l'authentification
$user = checkAuth();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

// Définir le type de contenu JSON
header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$convId = isset($_GET['conv_id']) ? (int)$_GET['conv_id'] : 0;
$action = $_GET['action'] ?? '';

try {
    if ($action === 'get_list') {
        // Récupérer la liste HTML des participants
        $participants = getParticipants($convId);
        
        ob_start();
        include __DIR__ . '/../templates/components/participant-list.php';
        $html = ob_get_clean();
        
        echo json_encode([
            'success' => true,
            'html' => $html
        ]);
        exit;
    }
    
    if (empty($type) || !$convId) {
        throw new Exception('Paramètres manquants');
    }
    
    // Récupérer les participants disponibles
    $participants = getAvailableParticipants($convId, $type);
    
    echo json_encode($participants);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}