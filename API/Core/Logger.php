<?php
declare(strict_types=1);

namespace API\Core;

/**
 * Logger structuré avec rotation de fichiers.
 * Écrit des logs JSON dans /logs/ avec rotation quotidienne.
 * Compatible PSR-3 (interface simplifiée).
 */
class Logger
{
    private string $logDir;
    private string $channel;
    private int $maxFiles;

    public function __construct(string $logDir = '', string $channel = 'app', int $maxFiles = 30)
    {
        $this->logDir = $logDir ?: (defined('BASE_PATH') ? BASE_PATH . '/logs' : sys_get_temp_dir());
        $this->channel = $channel;
        $this->maxFiles = $maxFiles;

        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0750, true);
        }
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }

    private function log(string $level, string $message, array $context): void
    {
        $entry = [
            'timestamp' => date('c'),
            'level' => $level,
            'channel' => $this->channel,
            'message' => $message,
            'request_id' => $_SERVER['X_REQUEST_ID'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'cli',
        ];

        if (!empty($context)) {
            $entry['context'] = $context;
        }

        // Ajouter user_id si disponible
        if (isset($_SESSION['user_id'])) {
            $entry['user_id'] = $_SESSION['user_id'];
            $entry['user_type'] = $_SESSION['user_type'] ?? '';
        }

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        $file = $this->logDir . '/' . $this->channel . '-' . date('Y-m-d') . '.log';
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);

        // Aussi envoyer à error_log pour les niveaux >= WARNING
        if (in_array($level, ['WARNING', 'ERROR', 'CRITICAL'], true)) {
            error_log("[{$level}] {$this->channel}: {$message}" . ($context ? ' ' . json_encode($context) : ''));
        }

        // Rotation : supprimer les fichiers trop anciens (1x par jour)
        $this->maybeRotate();
    }

    private function maybeRotate(): void
    {
        // Rotation vérifiée 1x par requête max
        static $checked = false;
        if ($checked) return;
        $checked = true;

        $pattern = $this->logDir . '/' . $this->channel . '-*.log';
        $files = glob($pattern);
        if ($files === false || count($files) <= $this->maxFiles) return;

        sort($files);
        $toDelete = array_slice($files, 0, count($files) - $this->maxFiles);
        foreach ($toDelete as $old) {
            @unlink($old);
        }
    }
}
