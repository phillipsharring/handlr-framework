<?php

namespace Handlr\Database;

use PDOStatement;

interface DbInterface
{
    public function getDatabaseName(): string;

    public function execute(string $sql, array $params = []): false|PDOStatement;

    public function insertId(): int;

    public function affectedRows(?PDOStatement $stmt = null): int;

    public function uuidToBin(string $uuid): string;

    public function binToUuid(string $bin): string;
}
