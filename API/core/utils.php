<?php
/**
 * Fonctions utilitaires pour l'application Pronote
 */
namespace Pronote\Utils;

/**
 * Formate une date pour l'affichage
 * @param string $date Date au format Y-m-d
 * @param string $format Format de sortie
 * @return string Date formatée
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) {
        return '';
    }
    
    try {
        $dateObj = new \DateTime($date);
        return $dateObj->format($format);
    } catch (\Exception $e) {
        return $date;
    }
}

/**
 * Formate un nombre pour l'affichage (par exemple une note)
 * @param float $number Nombre à formater
 * @param int $decimals Nombre de décimales
 * @return string Nombre formaté
 */
function formatNumber($number, $decimals = 2) {
    return number_format($number, $decimals, ',', ' ');
}

/**
 * Abrège un texte à la longueur spécifiée
 * @param string $text Texte à abréger
 * @param int $length Longueur maximale
 * @param string $suffix Suffixe à ajouter si le texte est abrégé
 * @return string Texte abrégé
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    
    return mb_substr($text, 0, $length) . $suffix;
}

/**
 * Renvoie l'URL actuelle
 * @return string URL actuelle
 */
function getCurrentUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    
    return $protocol . '://' . $host . $uri;
}

/**
 * Génère une URL à partir d'un chemin relatif
 * @param string $path Chemin relatif
 * @return string URL complète
 */
function url($path = '') {
    $baseUrl = defined('BASE_URL') ? BASE_URL : '';
    return $baseUrl . '/' . ltrim($path, '/');
}

/**
 * Formate un numéro de téléphone français
 * @param string $phone Numéro de téléphone
 * @return string Numéro formaté
 */
function formatPhone($phone) {
    // Supprimer tous les caractères non numériques
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Vérifier si le numéro a 10 chiffres (format français)
    if (strlen($phone) === 10) {
        return substr($phone, 0, 2) . ' ' . substr($phone, 2, 2) . ' ' . 
               substr($phone, 4, 2) . ' ' . substr($phone, 6, 2) . ' ' . 
               substr($phone, 8, 2);
    }
    
    // Sinon retourner tel quel
    return $phone;
}

/**
 * Convertit le chemin d'un fichier en URL accessible
 * @param string $filePath Chemin du fichier
 * @return string URL du fichier
 */
function fileToUrl($filePath) {
    if (empty($filePath)) {
        return '';
    }
    
    // Remplacer le chemin physique par l'URL de base
    $rootPath = defined('APP_ROOT') ? APP_ROOT : realpath(__DIR__ . '/../../');
    $baseUrl = defined('BASE_URL') ? BASE_URL : '';
    
    // Normaliser les chemins
    $rootPath = str_replace('\\', '/', $rootPath);
    $filePath = str_replace('\\', '/', $filePath);
    
    // Remplacer le chemin physique par l'URL de base
    if (strpos($filePath, $rootPath) === 0) {
        return $baseUrl . substr($filePath, strlen($rootPath));
    }
    
    return $filePath;
}

/**
 * Obtient l'extension d'un fichier
 * @param string $filename Nom du fichier
 * @return string Extension du fichier
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Vérifie si l'extension d'un fichier est une image
 * @param string $filename Nom du fichier
 * @return bool True si c'est une image
 */
function isImageFile($filename) {
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
    return in_array(getFileExtension($filename), $imageExtensions);
}

/**
 * Génère un identifiant unique basé sur le nom et le prénom
 * @param string $nom Nom
 * @param string $prenom Prénom
 * @param string $suffix Suffixe à ajouter si l'identifiant existe déjà
 * @return string Identifiant
 */
function generateIdentifiant($nom, $prenom, $suffix = '') {
    // Nettoyer et normaliser les chaînes
    $nom = mb_strtolower(trim($nom));
    $prenom = mb_strtolower(trim($prenom));
    
    // Retirer les accents
    $nom = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nom);
    $prenom = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $prenom);
    
    // Retirer les caractères spéciaux
    $nom = preg_replace('/[^a-z0-9]/', '', $nom);
    $prenom = preg_replace('/[^a-z0-9]/', '', $prenom);
    
    // Construire l'identifiant
    $identifiant = $nom . '.' . $prenom;
    
    // Ajouter un suffixe si nécessaire
    if (!empty($suffix)) {
        $identifiant .= $suffix;
    }
    
    return $identifiant;
}

/**
 * Calcul du trimestre actuel en fonction de la date
 * @param \DateTime|null $date Date (null pour la date actuelle)
 * @return int Numéro du trimestre (1, 2 ou 3)
 */
function getCurrentTrimester(\DateTime $date = null) {
    if ($date === null) {
        $date = new \DateTime();
    }
    
    $month = (int)$date->format('n');
    
    if ($month >= 9 && $month <= 12) {
        return 1; // Premier trimestre: septembre à décembre
    } elseif ($month >= 1 && $month <= 3) {
        return 2; // Deuxième trimestre: janvier à mars
    } else {
        return 3; // Troisième trimestre: avril à août
    }
}

/**
 * Redirection sécurisée
 */
function redirect($path, $message = null, $type = 'info') {
    if ($message) {
        $_SESSION['flash'][$type][] = $message;
    }
    
    // Validation de l'URL
    if (!filter_var($path, FILTER_VALIDATE_URL)) {
        $path = BASE_URL . '/' . ltrim($path, '/');
    }
    
    header('Location: ' . $path);
    exit;
}

/**
 * Messages flash
 */
function setFlash($type, $message) {
    $_SESSION['flash'][$type][] = $message;
}

function getFlash($type = null) {
    if ($type === null) {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }
    
    $messages = $_SESSION['flash'][$type] ?? [];
    unset($_SESSION['flash'][$type]);
    return $messages;
}

/**
 * Nettoyage des entrées
 */
function cleanInput($data) {
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validation d'email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
