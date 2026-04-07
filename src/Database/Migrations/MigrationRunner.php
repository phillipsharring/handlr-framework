<?php

declare(strict_types=1);

namespace Handlr\Database\Migrations;

use Handlr\Database\Db;
use Migrations; // NOSONAR
use PDO;
use RuntimeException;

/**
 * Runs and tracks database migrations.
 *
 * Migrations are PHP files in the migrations directory. Each file defines a class
 * extending BaseMigration with `up()` and `down()` methods. The runner tracks
 * applied migrations in a `migrations` table with batch numbers for rollback.
 *
 * ## File Naming Convention
 *
 * Migration files must be named: `{timestamp}_{description}.php`
 * Example: `20250101120000_create_users_table.php`
 *
 * The class name is derived from the filename:
 * `Migration_{timestamp}_{StudlyDescription}`
 * Example: `Migration_20250101120000_CreateUsersTable`
 *
 * ## Usage
 *
 * ```php
 * $runner = new MigrationRunner($db, '/path/to/migrations');
 *
 * // Run all pending migrations (single batch)
 * $runner->migrate();
 *
 * // Run migrations one at a time (each gets its own batch number)
 * $runner->migrate(stepWise: true);
 *
 * // Rollback the last batch
 * $runner->rollback();
 *
 * // Rollback the last 3 batches
 * $runner->rollback(3);
 * ```
 *
 * @see BaseMigration
 */
class MigrationRunner
{
    private Db $db;

    /** @var array<int, string> Ordered list of directories scanned for migration files. */
    private array $migrationPaths;

    /**
     * @param Db                    $db             Database connection
     * @param string|array<string>  $migrationPaths One path (back-compat) or an array of paths.
     *                                              When multiple paths are given, files from all
     *                                              paths are merged and sorted by filename — so
     *                                              timestamp-prefixed migration names interleave
     *                                              correctly across providers.
     */
    public function __construct(Db $db, string|array $migrationPaths)
    {
        $this->db = $db;
        $this->migrationPaths = is_array($migrationPaths) ? array_values($migrationPaths) : [$migrationPaths];
        $this->ensureMigrationsTableExists();
    }

    /** Create the database if it doesn't exist (utf8mb4) */
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
        $sql = "SHOW TABLES LIKE 'migrations'";
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

    /**
     * Run all pending migrations.
     *
     * @param bool $stepWise If true, each migration gets its own batch number
     */
    public function migrate(bool $stepWise = false): void
    {
        $appliedMigrations = array_map(static fn(array $row) => ($row['file']), $this->getAppliedMigrations());
        $filteredFiles = $this->collectMigrationFiles();
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

    /**
     * Drop all tables and re-run every migration from scratch.
     */
    public function fresh(): void
    {
        $this->log('Dropping all tables...');

        $tables = $this->db->execute('SHOW TABLES')
            ->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($tables)) {
            $this->db->execute('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($tables as $table) {
                $this->db->execute("DROP TABLE IF EXISTS `{$table}`");
                $this->log("  Dropped: {$table}");
            }
            $this->db->execute('SET FOREIGN_KEY_CHECKS = 1');
        }

        $this->ensureMigrationsTableExists();
        $this->log('Running all migrations...');
        $this->migrate();
    }

    /**
     * Rollback migrations by batch count.
     *
     * @param int $steps Number of batches to rollback (default: 1)
     */
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

    /**
     * Collect migration filenames across every configured path, sorted by basename.
     *
     * Sorting by basename means timestamp-prefixed migrations from different
     * providers interleave correctly: a 2025-08 framework migration runs before
     * a 2025-09 module migration even if the module path is listed first.
     *
     * @return array<int, string>
     */
    private function collectMigrationFiles(): array
    {
        $all = [];
        foreach ($this->migrationPaths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            $files = scandir($path);
            if ($files === false) {
                continue;
            }
            foreach ($this->getFilteredFiles($files) as $file) {
                $all[$file] = $file;
            }
        }
        ksort($all);
        return array_values($all);
    }

    private function classNameFromFile(string $file): string
    {
        // "20251227121500_create_users_table.php" -> "CreateUsersTable"
        $base = basename($file, '.php'); // 20251227121500_create_users_table
        [$stamp, $rest] = [substr($base, 0, 14), substr($base, 15)];
        $studly = str_replace(' ', '', ucwords(str_replace('_', '', $rest)));
        return "Migration_{$stamp}_{$studly}";
    }

    private function loadMigration(string $file): string
    {
        $found = null;
        foreach ($this->migrationPaths as $path) {
            $candidate = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
            if (is_file($candidate)) {
                $found = $candidate;
                break;
            }
        }

        if ($found === null) {
            throw new RuntimeException("Migration file {$file} not found in any configured migration path.");
        }

        require_once $found;

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
