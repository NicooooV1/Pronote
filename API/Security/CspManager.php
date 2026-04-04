<?php
declare(strict_types=1);

namespace API\Security;

/**
 * CspManager — Content Security Policy dynamique et configurable.
 *
 * Génère le header CSP en fonction de la configuration admin
 * et du contexte de la page (nonce, CDN, WebSocket).
 */
class CspManager
{
    private array $directives = [];
    private string $nonce;
    private bool $reportOnly = false;

    public function __construct(string $nonce)
    {
        $this->nonce = $nonce;
        $this->setDefaults();
    }

    /**
     * Définit les directives CSP par défaut.
     */
    private function setDefaults(): void
    {
        $this->directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$this->nonce}'", 'cdnjs.cloudflare.com', 'cdn.socket.io', 'code.jquery.com'],
            'style-src' => ["'self'", "'nonce-{$this->nonce}'", 'cdnjs.cloudflare.com'],
            'font-src' => ['cdnjs.cloudflare.com', 'data:'],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'connect-src' => ["'self'", 'ws:', 'wss:'],
            'frame-ancestors' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'object-src' => ["'none'"],
        ];

        // HTTPS : upgrade insecure requests
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $this->directives['upgrade-insecure-requests'] = [];
        }
    }

    /**
     * Ajoute une source à une directive.
     */
    public function addSource(string $directive, string $source): self
    {
        if (!isset($this->directives[$directive])) {
            $this->directives[$directive] = [];
        }
        if (!in_array($source, $this->directives[$directive], true)) {
            $this->directives[$directive][] = $source;
        }
        return $this;
    }

    /**
     * Ajoute un domaine pour la visioconférence Jitsi.
     */
    public function allowJitsi(string $domain = 'meet.jit.si'): self
    {
        $this->addSource('frame-src', "https://{$domain}");
        $this->addSource('connect-src', "https://{$domain}");
        return $this;
    }

    /**
     * Active le mode report-only (pour tester sans bloquer).
     */
    public function reportOnly(bool $enabled = true): self
    {
        $this->reportOnly = $enabled;
        return $this;
    }

    /**
     * Ajoute une URL de reporting CSP.
     */
    public function setReportUri(string $uri): self
    {
        $this->directives['report-uri'] = [$uri];
        return $this;
    }

    /**
     * Génère la chaîne CSP complète.
     */
    public function build(): string
    {
        $parts = [];
        foreach ($this->directives as $directive => $sources) {
            if (empty($sources)) {
                $parts[] = $directive;
            } else {
                $parts[] = $directive . ' ' . implode(' ', $sources);
            }
        }
        return implode('; ', $parts) . ';';
    }

    /**
     * Envoie le header CSP.
     */
    public function send(): void
    {
        if (headers_sent()) return;

        $headerName = $this->reportOnly
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        header("{$headerName}: " . $this->build());
    }

    /**
     * Retourne le nonce pour les scripts/styles inline.
     */
    public function getNonce(): string
    {
        return $this->nonce;
    }
}
