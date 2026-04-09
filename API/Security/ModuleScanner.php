<?php
declare(strict_types=1);

namespace API\Security;

/**
 * Analyse statique de securite pour les modules marketplace.
 * Utilise token_get_all() pour scanner le PHP sans regex fragile.
 */
class ModuleScanner
{
    // Fonctions considerees dangereuses
    private const BLOCKED_FUNCTIONS = [
        'eval', 'exec', 'system', 'shell_exec', 'passthru',
        'proc_open', 'popen', 'pcntl_exec', 'dl',
        'putenv', 'apache_setenv',
    ];

    // Fonctions conditionnellement bloquees (sauf si module declare permission 'network')
    private const NETWORK_FUNCTIONS = [
        'curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen',
        'stream_socket_client',
    ];

    // Fonctions suspectes (warning, pas blocking)
    private const SUSPICIOUS_FUNCTIONS = [
        'file_get_contents', 'file_put_contents', 'fopen', 'fwrite',
        'unlink', 'rmdir', 'rename', 'chmod', 'chown', 'chgrp',
        'symlink', 'link', 'mkdir',
    ];

    private array $violations = [];
    private array $warnings = [];
    private array $modulePermissions = [];

    public function __construct(array $modulePermissions = [])
    {
        $this->modulePermissions = $modulePermissions;
    }

    /**
     * Scanne un repertoire et retourne les violations.
     * @return array ['violations' => [...], 'warnings' => [...], 'safe' => bool]
     */
    public function scanDirectory(string $path): array
    {
        $this->violations = [];
        $this->warnings = [];

        if (!is_dir($path)) {
            return ['violations' => [], 'warnings' => [], 'safe' => true];
        }

        $files = $this->getPhpFiles($path);

        foreach ($files as $file) {
            $this->scanFile($file, $path);
        }

        return [
            'violations' => $this->violations,
            'warnings'   => $this->warnings,
            'safe'       => empty($this->violations),
            'files_scanned' => count($files),
        ];
    }

    private function scanFile(string $filePath, string $basePath): void
    {
        $code = file_get_contents($filePath);
        if ($code === false) return;

        $relativePath = str_replace($basePath . '/', '', $filePath);
        $relativePath = str_replace($basePath . '\\', '', $relativePath);

        $tokens = @token_get_all($code);
        if ($tokens === false) return;

        $tokenCount = count($tokens);

        for ($i = 0; $i < $tokenCount; $i++) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                // Check for backtick operator
                if ($token === '`') {
                    $this->violations[] = [
                        'file'    => $relativePath,
                        'line'    => 0,
                        'type'    => 'backtick_operator',
                        'message' => 'Backtick operator (shell execution) detected',
                    ];
                }
                continue;
            }

            [$tokenType, $tokenValue, $tokenLine] = $token;

            // Check function calls
            if ($tokenType === T_STRING) {
                $funcName = strtolower($tokenValue);

                // Blocked functions
                if (in_array($funcName, self::BLOCKED_FUNCTIONS, true)) {
                    $this->violations[] = [
                        'file'    => $relativePath,
                        'line'    => $tokenLine,
                        'type'    => 'blocked_function',
                        'message' => "Blocked function '{$funcName}()' detected",
                    ];
                }

                // Network functions (conditional)
                if (in_array($funcName, self::NETWORK_FUNCTIONS, true)) {
                    if (!in_array('network', $this->modulePermissions, true)) {
                        $this->violations[] = [
                            'file'    => $relativePath,
                            'line'    => $tokenLine,
                            'type'    => 'network_function',
                            'message' => "Network function '{$funcName}()' requires 'network' permission",
                        ];
                    }
                }

                // Suspicious functions (warning)
                if (in_array($funcName, self::SUSPICIOUS_FUNCTIONS, true)) {
                    $this->warnings[] = [
                        'file'    => $relativePath,
                        'line'    => $tokenLine,
                        'type'    => 'suspicious_function',
                        'message' => "Suspicious function '{$funcName}()' — review needed",
                    ];
                }
            }

            // Check for variable variables ($$var)
            if ($tokenType === T_VARIABLE) {
                // Look ahead for another T_VARIABLE or $ sign
                if ($i > 0) {
                    $prev = $tokens[$i - 1];
                    if (is_array($prev) && $prev[0] === T_VARIABLE) {
                        $this->warnings[] = [
                            'file'    => $relativePath,
                            'line'    => $tokenLine,
                            'type'    => 'variable_variable',
                            'message' => "Variable variable detected — potential security risk",
                        ];
                    }
                }
            }

            // Check for eval in combination with base64_decode
            if ($tokenType === T_STRING && strtolower($tokenValue) === 'base64_decode') {
                // Look for eval nearby
                for ($j = max(0, $i - 5); $j < min($tokenCount, $i + 5); $j++) {
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_EVAL) {
                        $this->violations[] = [
                            'file'    => $relativePath,
                            'line'    => $tokenLine,
                            'type'    => 'obfuscated_eval',
                            'message' => 'base64_decode() used near eval() — likely obfuscated code',
                        ];
                        break;
                    }
                }
            }

            // Check for eval (T_EVAL token)
            if ($tokenType === T_EVAL) {
                $this->violations[] = [
                    'file'    => $relativePath,
                    'line'    => $tokenLine,
                    'type'    => 'eval',
                    'message' => 'eval() detected — arbitrary code execution',
                ];
            }

            // Check for preg_replace with /e modifier
            if ($tokenType === T_STRING && strtolower($tokenValue) === 'preg_replace') {
                // Look for the pattern string
                for ($j = $i + 1; $j < min($tokenCount, $i + 10); $j++) {
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_CONSTANT_ENCAPSED_STRING) {
                        if (preg_match('#/[^/]*e[^/]*$#', $tokens[$j][1])) {
                            $this->violations[] = [
                                'file'    => $relativePath,
                                'line'    => $tokenLine,
                                'type'    => 'preg_replace_e',
                                'message' => 'preg_replace() with /e modifier — code execution',
                            ];
                        }
                        break;
                    }
                }
            }

            // Check for include/require with variable paths
            if (in_array($tokenType, [T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE], true)) {
                for ($j = $i + 1; $j < min($tokenCount, $i + 5); $j++) {
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_VARIABLE) {
                        $this->warnings[] = [
                            'file'    => $relativePath,
                            'line'    => $tokenLine,
                            'type'    => 'dynamic_include',
                            'message' => "Dynamic include/require with variable — potential LFI",
                        ];
                        break;
                    }
                }
            }
        }
    }

    private function getPhpFiles(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
