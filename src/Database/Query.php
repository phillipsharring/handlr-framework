<?php

declare(strict_types=1);

namespace Handlr\Database;

use PDO;

abstract class Query
{
    public function __construct(
        protected DbInterface $db,
    ) {}

    /**
     * Execute SQL and return all rows as associative arrays.
     */
    protected function rows(string $sql, array $params = []): array
    {
        $stmt = $this->db->execute($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Execute SQL and return the first row, or null if none.
     */
    protected function row(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->execute($sql, $params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Execute SQL and return a single scalar value.
     */
    protected function scalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->db->execute($sql, $params);
        return $stmt->fetchColumn();
    }

    /**
     * Execute a COUNT query and return the result as an int.
     */
    protected function count(string $sql, array $params = []): int
    {
        return (int) $this->scalar($sql, $params);
    }

    /**
     * Execute SQL and return a single column as a flat array.
     */
    protected function column(string $sql, array $params = []): array
    {
        $stmt = $this->db->execute($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    protected function uuidToBin(string $uuid): string
    {
        return $this->db->uuidToBin($uuid);
    }

    protected function binToUuid(string $bin): string
    {
        return $this->db->binToUuid($bin);
    }
}
