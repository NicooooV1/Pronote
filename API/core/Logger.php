<?php
/**
 * Système de journalisation centralisé
 */

class Logger {
    
    public static function log($message, $level = 'info', $context = []) {
        if (!defined('LOG_ENABLED') || !LOG_ENABLED) {
            return;
        }

        $logDir = defined('LOGS_PATH') ? LOGS_PATH : __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $user = Auth::user();
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => strtoupper($level),
            'message' => $message,
            'user_id' => $user['id'] ?? null,
            'context' => $context
        ];

        $file = $logDir . '/app_' . date('Y-m-d') . '.log';
        @file_put_contents($file, json_encode($entry) . "\n", FILE_APPEND);
    }

    public static function debug($message, $context = []) {
        self::log($message, 'debug', $context);
    }

    public static function info($message, $context = []) {
        self::log($message, 'info', $context);
    }

    public static function warning($message, $context = []) {
        self::log($message, 'warning', $context);
    }

    public static function error($message, $context = []) {
        self::log($message, 'error', $context);
    }
}
