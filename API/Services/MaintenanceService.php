<?php
declare(strict_types=1);

namespace API\Services;

/**
 * Mode maintenance base sur fichier JSON (independant de la BDD).
 * Fichier : storage/maintenance.json
 */
class MaintenanceService
{
    private string $filePath;

    public function __construct(string $basePath)
    {
        $this->filePath = $basePath . '/storage/maintenance.json';
    }

    public function isActive(): bool
    {
        $data = $this->read();
        return ($data['active'] ?? false) === true;
    }

    public function activate(string $message = '', array $allowedIps = [], ?int $etaMinutes = null): void
    {
        $this->write([
            'active'      => true,
            'message'     => $message ?: 'Maintenance en cours. Merci de votre patience.',
            'allowed_ips' => $allowedIps,
            'eta_minutes' => $etaMinutes,
            'started_at'  => date('c'),
        ]);
    }

    public function deactivate(): void
    {
        if (file_exists($this->filePath)) {
            unlink($this->filePath);
        }
    }

    public function getMessage(): string
    {
        return $this->read()['message'] ?? 'Maintenance en cours.';
    }

    public function getEta(): ?string
    {
        $data = $this->read();
        if (!isset($data['started_at']) || !isset($data['eta_minutes'])) {
            return null;
        }
        $start = strtotime($data['started_at']);
        $end = $start + ($data['eta_minutes'] * 60);
        $remaining = $end - time();
        if ($remaining <= 0) return 'bientot';
        if ($remaining < 60) return $remaining . 's';
        return ceil($remaining / 60) . 'min';
    }

    public function isIpAllowed(string $ip): bool
    {
        $data = $this->read();
        $allowed = $data['allowed_ips'] ?? [];

        if (empty($allowed)) {
            return false;
        }

        foreach ($allowed as $rule) {
            if ($rule === $ip) return true;
            if (strpos($rule, '/') !== false && $this->ipInCidr($ip, $rule)) return true;
        }

        return false;
    }

    public function getStatus(): array
    {
        return $this->read();
    }

    public function updateConfig(array $data): void
    {
        $current = $this->read();
        $this->write(array_merge($current, $data));
    }

    private function read(): array
    {
        if (!file_exists($this->filePath)) {
            return ['active' => false];
        }

        $content = file_get_contents($this->filePath);
        $data = json_decode($content, true);

        return is_array($data) ? $data : ['active' => false];
    }

    private function write(array $data): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($this->filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        if ($ip === false || $subnet === false) return false;
        $mask = -1 << (32 - (int)$bits);
        return ($ip & $mask) === ($subnet & $mask);
    }
}
