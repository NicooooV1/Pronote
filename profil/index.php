<?php
/**
 * Profil utilisateur — Alias vers le module Paramètres
 *
 * Ce fichier est un point d'entrée alternatif pour accéder aux réglages
 * personnels (apparence, notifications, tableau de bord, compte).
 * Toute la logique est gérée dans parametres/parametres.php.
 *
 * Paramètres GET transmis :
 *   ?section=profil|preferences|notifications|securite|accueil|confidentialite
 */

$section = $_GET['section'] ?? 'profil';
$allowed = ['profil', 'preferences', 'notifications', 'securite', 'accueil', 'confidentialite'];
if (!in_array($section, $allowed, true)) {
    $section = 'profil';
}

header('Location: ../parametres/parametres.php?section=' . urlencode($section));
exit;
