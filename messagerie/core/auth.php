<?php
/**
 * Module d'authentification pour la messagerie
 * Charge l'API centralisée (Bridge fournit : requireAuth, getCurrentUser, checkAuth, etc.)
 */

require_once __DIR__ . '/../../API/core.php';

// checkAuth() est fourni par l'API (Bridge) — alias de getCurrentUser()
// countUnreadNotifications() est défini dans messagerie/models/notification.php