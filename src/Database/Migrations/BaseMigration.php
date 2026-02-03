<?php

declare(strict_types=1);

namespace Handlr\Database\Migrations;

use Handlr\Database\Db;

/**
 * Base class for database migrations with helper methods.
 *
 * Extend this class when creating migrations. Use `$this->exec()` for SQL
 * statements and the helper methods to check schema state.
 *
 * ## Example Migration
 *
 * ```php
 * class Migration_20250101120000_CreateUsersTable extends BaseMigration
 * {
 *     public function up(): void
 *     {
 *         $this->exec("
 *             CREATE TABLE users (
 *                 id CHAR(36) PRIMARY KEY,
 *                 email VARCHAR(255) NOT NULL UNIQUE,
 *                 name VARCHAR(100) NOT NULL,
 *                 created_at DATETIME DEFAULT CURRENT_TIMESTAMP
 *             )
 *         ");
 *     }
 *
 *     public function down(): void
 *     {
 *         $this->exec("DROP TABLE IF EXISTS users");
 *     }
 * }
 * ```
 *
 * ## Helper Methods
 *
 * - `tableExists($table)` - Check if a table exists
 * - `columnExists($table, $column)` - Check if a column exists
 * - `indexExists($table, $index)` - Check if an index exists
 */
abstract class BaseMigration implements MigrationInterface
{
    protected Db $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    abstract public function up(): void;

    abstract public function down(): void;

    /** Execute a raw SQL statement */
    protected function exec(string $sql): void
    {
        $this->db->execute($sql);
    }

    /** Check if a table exists in the current database */
    protected function tableExists(string $table): bool
    {
        $stmt = $this->db->execute("SHOW TABLES LIKE :t", [':t' => $table]);
        return (bool) $stmt->fetchColumn();
    }

    /** Check if a column exists in a table */
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

    /** Check if an index exists on a table */
    protected function indexExists(string $table, string $index): bool
    {
        $stmt = $this->db->execute("SHOW INDEX FROM `:t` WHERE Key_name = :k", [':t' => $table, ':k' => $index]);
        return (bool) $stmt->fetchColumn();
    }
}
