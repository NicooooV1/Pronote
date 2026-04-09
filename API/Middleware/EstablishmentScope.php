<?php
declare(strict_types=1);

namespace API\Middleware;

use API\Core\EstablishmentContext;

/**
 * Middleware that sets the EstablishmentContext from the session.
 * Must run after authentication.
 */
class EstablishmentScope
{
    /**
     * Resolve the current establishment and set the context.
     * Priority: session > cookie > default (1)
     */
    public static function handle(): void
    {
        // Already set (e.g., by API token auth)
        if (EstablishmentContext::isSet()) {
            return;
        }

        // From session (set during login)
        $etabId = $_SESSION['etablissement_id'] ?? null;

        if ($etabId !== null) {
            EstablishmentContext::set((int) $etabId);
            return;
        }

        // Default: establishment 1
        EstablishmentContext::set(1);
        $_SESSION['etablissement_id'] = 1;
    }

    /**
     * Switch the current establishment (for super-admin).
     * Updates session and context.
     */
    public static function switchTo(int $etabId): void
    {
        EstablishmentContext::set($etabId);
        $_SESSION['etablissement_id'] = $etabId;

        // Flush client cache to force reload of scoped data
        try {
            $cc = app('client_cache');
            $cc->flush();
        } catch (\Throwable $e) {
            // Non-critical
        }
    }
}
