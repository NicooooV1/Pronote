<?php
// Inclure l'API centralisée
require_once __DIR__ . '/../API/core.php';

// Vérifier l'authentification
requireAuth();

// Redirection vers l'interface principale des notes
header("Location: interface_notes.php");
exit;
?>