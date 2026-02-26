<?php

declare(strict_types=1);

namespace Handlr\Database;

use PDOStatement;
use RuntimeException;

/**
 * No-op database implementation for simulation/test modes.
 *
 * Throws on any query attempt — catches accidental DB access when running
 * in modes that should be fully DB-free (e.g. simulation with fixtures).
 * Transaction methods are safe no-ops since nothing is actually persisted.
 */
class NullDb implements DbInterface
{
    public function __construct(private readonly string $mode = 'simulation') {}

    private function fail(string $method): never
    {
        throw new RuntimeException(
            "No database connection configured (APP_ENV={$this->mode}). "
            . "Called: {$method}()"
        );
    }

    public function getDatabaseName(): string
    {
        $this->fail(__FUNCTION__);
    }

    public function execute(string $sql, array $params = []): false|PDOStatement
    {
        $this->fail(__FUNCTION__);
    }

    public function insertId(): int
    {
        $this->fail(__FUNCTION__);
    }

    public function affectedRows(?PDOStatement $stmt = null): int
    {
        $this->fail(__FUNCTION__);
    }

    public function uuidToBin(string $uuid): string
    {
        $this->fail(__FUNCTION__);
    }

    public function binToUuid(string $bin): string
    {
        $this->fail(__FUNCTION__);
    }

    public function beginTransaction(): bool
    {
        return true;
    }

    public function commit(): bool
    {
        return true;
    }

    public function rollBack(): bool
    {
        return true;
    }

    public function inTransaction(): bool
    {
        return false;
    }
}
