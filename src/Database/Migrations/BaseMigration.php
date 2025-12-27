<?php

declare(strict_types=1);

namespace Handlr\Database\Migrations;

use Handlr\Database\Db;

abstract class BaseMigration implements MigrationInterface
{
    protected Db $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    abstract public function up(): void;

    abstract public function down(): void;

    // Execute a statement
    protected function exec(string $sql): void
    {
        $this->db->execute($sql);
    }

    // Helpers are handy in raw-SQL land
    protected function tableExists(string $table): bool
    {
        $stmt = $this->db->execute("SHOW TABLES LIKE :t", [':t' => $table]);
        return (bool) $stmt->fetchColumn();
    }

    protected function columnExists(string $table, string $column): bool
    {
        $stmt = $this->db->execute(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :t
               AND COLUMN_NAME = :c",
            [':t' => $table, ':c' => $column]
        );
        return (bool) $stmt->fetchColumn();
    }

    protected function indexExists(string $table, string $index): bool
    {
        $stmt = $this->db->execute("SHOW INDEX FROM `:t` WHERE Key_name = :k", [':t' => $table, ':k' => $index]);
        return (bool) $stmt->fetchColumn();
    }
}
