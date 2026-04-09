<?php
declare(strict_types=1);

namespace API\Core;

/**
 * Singleton holding the current establishment ID for the request.
 * Set once during auth/middleware, read everywhere.
 */
class EstablishmentContext
{
    private static ?int $id = null;

    public static function set(int $id): void
    {
        self::$id = $id;
    }

    public static function id(): int
    {
        return self::$id ?? 1;
    }

    public static function isSet(): bool
    {
        return self::$id !== null;
    }

    /**
     * Scope a PDO query builder by adding WHERE etablissement_id = ?
     * Returns the value to bind.
     */
    public static function scopeValue(): int
    {
        return self::id();
    }

    /**
     * Returns SQL fragment: "AND etablissement_id = ?"
     * For use in existing WHERE clauses.
     */
    public static function sqlAnd(): string
    {
        return ' AND etablissement_id = ' . self::id();
    }

    /**
     * Returns SQL fragment: "WHERE etablissement_id = ?"
     * For use as the primary WHERE clause.
     */
    public static function sqlWhere(): string
    {
        return ' WHERE etablissement_id = ' . self::id();
    }

    public static function reset(): void
    {
        self::$id = null;
    }
}
