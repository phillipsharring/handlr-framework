<?php

declare(strict_types=1);

namespace Handlr\Database\Migrations;

use Handlr\Database\Db;

// NOSONAR
use PDO;
use RuntimeException;

class MigrationRunner
{
    private Db $db;

    private string $migrationPath;

    public function __construct(Db $db, string $migrationPath)
    {
        $this->db = $db;
        $this->migrationPath = $migrationPath;
        $this->ensureMigrationsTableExists();
    }

    public function createDatabase(): void
    {
        $this->db->execute(
            "CREATE DATABASE IF NOT EXISTS `{$this->db->getDatabaseName()}`
            CHARACTER SET utf8mb4
            COLLATE utf8mb4_0900_ai_ci;"
        );
    }

    /**
     * Check if the migrations table exists and create it if necessary.
     */
    private function ensureMigrationsTableExists(): void
    {
        $sql = 'SHOW TABLES LIKE `migrations`';
        $result = $this->db->execute($sql)->fetchColumn();

        if (!$result) {
            $this->createMigrationsTable();
        }
    }

    /**
     * Create the migrations table.
     */
    private function createMigrationsTable(): void
    {
        $sql = <<<SQL
            CREATE TABLE migrations (
                `batch` INT UNSIGNED,
                `file` VARCHAR(255) NOT NULL,
                `ran_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            SQL;

        $this->db->execute($sql);
        $this->log('Migrations table created.');
    }

    public function migrate(bool $stepWise = false): void
    {
        $appliedMigrations = array_map(static fn(array $row) => ($row['file']), $this->getAppliedMigrations());
        $files = scandir($this->migrationPath);

        $filteredFiles = $this->getFilteredFiles($files);
        $newMigrations = array_diff($filteredFiles, $appliedMigrations);

        if (empty($newMigrations)) {
            $this->log('Nothing to migrate.');
            return;
        }

        $maxBatch = $this->getMaxBatch();
        $nextBatch = $maxBatch + 1;

        foreach ($newMigrations as $file) {
            $className = $this->loadMigration($file);
            $migration = new $className($this->db);

            $this->log("Applying migration: $className");
            $migration->up();

            $this->recordMigration($nextBatch, $file);

            if ($stepWise) {
                $nextBatch++;
            }
        }
    }

    public function rollback(int $steps = 1): void
    {
        $maxBatch = $this->getMaxBatch();
        $minBatch = max($maxBatch - $steps, 0);

        $appliedMigrations = array_reverse($this->getAppliedMigrations());
        $rollbackMigrations = array_filter(
            $appliedMigrations,
            static fn(array $row): bool => ($row['batch'] > $minBatch)
        );
        $migrationFiles = array_map(static fn(array $row) => ($row['file']), $rollbackMigrations);

        if (empty($migrationFiles)) {
            $this->log('Nothing to rollback.');
            return;
        }

        foreach ($migrationFiles as $file) {
            $className = $this->loadMigration($file);
            $migration = new $className($this->db);

            $this->log("Rolling back migration: $className");
            $migration->down();

            $this->removeMigrationRecord($file);
        }
    }

    private function getAppliedMigrations(): array
    {
        $this->ensureMigrationsTableExists(); // Ensure the migrations table exists
        return $this->db->execute('SELECT `batch`, `file` FROM `migrations` ORDER BY `file`')->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    private function getMaxBatch(): ?int
    {
        return (int)$this->db->execute("SELECT MAX(`batch`) AS `max_batch` FROM `migrations`")->fetchColumn();
    }

    public function getFilteredFiles(false|array $files): array|false
    {
        return array_filter(
            $files,
            static fn(string $file): bool => (pathinfo($file, PATHINFO_EXTENSION) === 'php' && $file !== 'migrate.php')
        );
    }

    private function classNameFromFile(string $file): string
    {
        // "20251227121500_create_users_table.php" -> "M20251227121500_CreateUsersTable"
        $base = basename($file, '.php'); // 20251227121500_create_users_table
        [$stamp, $rest] = [substr($base, 0, 14), substr($base, 15)];
        $studly = str_replace(' ', '', ucwords(str_replace('_', ' ', $rest)));
        return "M{$stamp}_{$studly}";
    }

    private function loadMigration(string $file): string
    {
        require_once rtrim($this->migrationPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;

        // Build class name
        $class = $this->classNameFromFile($file);

        // Basic guard
        if (!class_exists($class)) {
            throw new RuntimeException("Migration class {$class} not found in {$file}");
        }

        // Return the class name (runner will instantiate it with $this->db)
        return $class;
    }

    private function recordMigration(int $nextBatch, string $file): void
    {
        $this->db->execute('INSERT INTO `migrations` (`batch`, `file`) VALUES (?, ?)', [$nextBatch, $file]);
    }

    private function removeMigrationRecord(string $file): void
    {
        $this->db->execute('DELETE FROM `migrations` WHERE `file` = ?', [$file]);
    }

    private function log(string $message): void
    {
        echo "[MIGRATION] $message" . PHP_EOL;
    }
}
