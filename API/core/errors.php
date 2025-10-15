<?php
/**
 * Gestionnaire d'erreurs centralisé pour Pronote
 */

namespace Pronote\Errors;

/**
 * Enregistre les gestionnaires d'erreurs personnalisés
 */
function registerErrorHandlers() {
    // Gestionnaire d'erreurs PHP
    set_error_handler('\\Pronote\\Errors\\errorHandler');
    
    // Gestionnaire d'exceptions non capturées
    set_exception_handler('\\Pronote\\Errors\\exceptionHandler');
    
    // Gestionnaire de shutdown pour les erreurs fatales
    register_shutdown_function('\\Pronote\\Errors\\shutdownHandler');
}

/**
 * Gestionnaire d'erreurs personnalisé
 */
function errorHandler($errno, $errstr, $errfile, $errline) {
    // Ne pas traiter les erreurs supprimées avec @
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $errorTypes = [
        E_ERROR => 'Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];
    
    $type = $errorTypes[$errno] ?? 'Unknown Error';
    
    $message = sprintf(
        "[%s] %s in %s on line %d",
        $type,
        $errstr,
        $errfile,
        $errline
    );
    
    error_log($message);
    
    // En développement, afficher l'erreur
    if (defined('APP_ENV') && APP_ENV === 'development') {
        echo "<div style='background:#f8d7da;color:#721c24;padding:10px;margin:10px;border:1px solid #f5c6cb;border-radius:4px;'>";
        echo "<strong>$type:</strong> $errstr<br>";
        echo "<strong>File:</strong> $errfile<br>";
        echo "<strong>Line:</strong> $errline";
        echo "</div>";
    }
    
    return true;
}

/**
 * Gestionnaire d'exceptions non capturées
 */
function exceptionHandler($exception) {
    $message = sprintf(
        "Uncaught Exception: %s in %s on line %d\nStack trace:\n%s",
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );
    
    error_log($message);
    
    // Affichage selon l'environnement
    if (defined('APP_ENV') && APP_ENV === 'development') {
        echo "<div style='background:#f8d7da;color:#721c24;padding:20px;margin:20px;border:1px solid #f5c6cb;border-radius:4px;'>";
        echo "<h2>Exception non capturée</h2>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
        echo "<p><strong>Fichier:</strong> " . htmlspecialchars($exception->getFile()) . "</p>";
        echo "<p><strong>Ligne:</strong> " . $exception->getLine() . "</p>";
        echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        echo "</div>";
    } else {
        http_response_code(500);
        echo "Une erreur s'est produite. Veuillez réessayer ultérieurement.";
    }
}

/**
 * Gestionnaire de shutdown pour les erreurs fatales
 */
function shutdownHandler() {
    $error = error_get_last();
    
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        $message = sprintf(
            "Fatal Error: %s in %s on line %d",
            $error['message'],
            $error['file'],
            $error['line']
        );
        
        error_log($message);
        
        if (defined('APP_ENV') && APP_ENV === 'development') {
            echo "<div style='background:#f8d7da;color:#721c24;padding:20px;margin:20px;'>";
            echo "<h2>Erreur Fatale</h2>";
            echo "<p>" . htmlspecialchars($error['message']) . "</p>";
            echo "<p><strong>Fichier:</strong> " . htmlspecialchars($error['file']) . "</p>";
            echo "<p><strong>Ligne:</strong> " . $error['line'] . "</p>";
            echo "</div>";
        } else {
            http_response_code(500);
            echo "Erreur critique. Veuillez contacter l'administrateur.";
        }
    }
}