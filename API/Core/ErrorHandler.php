<?php
declare(strict_types=1);

namespace API\Core;

/**
 * Gestionnaire d'erreurs global.
 * En production : pages d'erreur amicales. En dev : traces detaillees.
 */
class ErrorHandler
{
    private string $basePath;
    private bool $debug;

    public function __construct(string $basePath, bool $debug = false)
    {
        $this->basePath = $basePath;
        $this->debug = $debug;
    }

    public function register(): void
    {
        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function handleException(\Throwable $e): void
    {
        $this->log($e);

        if ($this->isAjax()) {
            $this->jsonError($e);
            return;
        }

        $this->renderErrorPage(500, $e);
    }

    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $this->log(new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));

            if (!$this->isAjax()) {
                $this->renderErrorPage(500);
            }
        }
    }

    public function renderErrorPage(int $code, ?\Throwable $e = null): void
    {
        if (headers_sent()) return;

        $errorFile = $this->basePath . "/templates/errors/{$code}.php";
        if (!file_exists($errorFile)) {
            $errorFile = $this->basePath . '/templates/errors/500.php';
        }

        $debugTrace = '';
        if ($this->debug && $e) {
            $debugTrace = get_class($e) . ': ' . $e->getMessage() . "\n"
                . $e->getFile() . ':' . $e->getLine() . "\n\n"
                . $e->getTraceAsString();
        }

        http_response_code($code);
        require $errorFile;
    }

    public static function render404(string $basePath): void
    {
        $errorFile = $basePath . '/templates/errors/404.php';
        if (file_exists($errorFile)) {
            require $errorFile;
        } else {
            http_response_code(404);
            echo 'Not Found';
        }
        exit;
    }

    public static function render403(string $basePath): void
    {
        $errorFile = $basePath . '/templates/errors/403.php';
        if (file_exists($errorFile)) {
            require $errorFile;
        } else {
            http_response_code(403);
            echo 'Forbidden';
        }
        exit;
    }

    private function jsonError(\Throwable $e): void
    {
        http_response_code(500);
        header('Content-Type: application/json');
        $payload = ['error' => 'internal_server_error'];
        if ($this->debug) {
            $payload['message'] = $e->getMessage();
            $payload['file'] = $e->getFile() . ':' . $e->getLine();
            $payload['trace'] = explode("\n", $e->getTraceAsString());
        }
        echo json_encode($payload);
    }

    private function isAjax(): bool
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return strpos($accept, 'application/json') !== false && strpos($accept, 'text/html') === false;
    }

    private function log(\Throwable $e): void
    {
        try {
            $logger = \API\Core\Application::getInstance()->make('log');
            $logger->error($e->getMessage(), [
                'exception' => get_class($e),
                'file'      => $e->getFile() . ':' . $e->getLine(),
                'trace'     => $e->getTraceAsString(),
            ]);
        } catch (\Throwable $_) {
            error_log('[Fronote] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }
}
