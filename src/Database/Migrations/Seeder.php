<?php

declare(strict_types=1);

namespace Handlr\Database\Migrations;

use Handlr\Database\DbInterface;
use Handlr\Database\Record;
use Handlr\Database\Table;
use InvalidArgumentException;
use RuntimeException;

/**
 * Populates the database with seed data.
 *
 * Seeds are defined as arrays mapping Table classes to record data. Supports
 * nested relations via `_relations` key for inserting related records.
 *
 * ## Basic Usage
 *
 * ```php
 * $seeder = new Seeder($db);
 *
 * $seeder->seed([
 *     UsersTable::class => [
 *         ['name' => 'Admin', 'email' => 'admin@example.com'],
 *         ['name' => 'User', 'email' => 'user@example.com'],
 *     ],
 * ]);
 * ```
 *
 * ## Nested Relations
 *
 * Use `_relations` to insert related records. The parent's ID is automatically
 * injected as a foreign key (e.g., `user_id` for a `users` table parent).
 *
 * ```php
 * $seeder->seed([
 *     UsersTable::class => [
 *         [
 *             'name' => 'John',
 *             '_relations' => [
 *                 PostsTable::class => [
 *                     ['title' => 'First Post'],  // user_id auto-injected
 *                     ['title' => 'Second Post'],
 *                 ],
 *             ],
 *         ],
 *     ],
 * ]);
 * ```
 *
 * ## Fresh Seeding (Truncate First)
 *
 * ```php
 * $tableClasses = $seeder->collectTableClasses($data);
 * $seeder->truncate(array_reverse($tableClasses));  // Children first
 * $seeder->seed($data);
 * ```
 */
class Seeder
{
    protected DbInterface $db;

    public function __construct(DbInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Seed data from an array.
     *
     * Format:
     * [
     *     TableClass::class => [
     *         ['field' => 'value', '_relations' => [
     *             RelatedTableClass::class => [
     *                 ['field' => 'value'],
     *             ]
     *         ]],
     *     ],
     * ]
     *
     * @param array<class-string<Table>, array<int, array<string, mixed>>> $data
     * @return array<string, int> Count of records inserted per table class
     */
    public function seed(array $data): array
    {
        $counts = [];

        foreach ($data as $tableClass => $records) {
            if (!is_string($tableClass) || !class_exists($tableClass)) {
                throw new InvalidArgumentException("Invalid table class: {$tableClass}");
            }

            if (!is_subclass_of($tableClass, Table::class)) {
                throw new InvalidArgumentException("{$tableClass} must extend " . Table::class);
            }

            $counts[$tableClass] = $this->seedTable($tableClass, $records);
        }

        return $counts;
    }

    /**
     * Truncate tables before seeding.
     *
     * @param array<class-string<Table>> $tableClasses
     */
    public function truncate(array $tableClasses): void
    {
        // Disable FK checks for truncation
        $this->db->execute('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($tableClasses as $tableClass) {
                if (!is_string($tableClass) || !class_exists($tableClass)) {
                    continue;
                }

                /** @var Table $table */
                $table = new $tableClass($this->db);
                $tableName = $this->getTableName($table);

                $this->db->execute("TRUNCATE TABLE `{$tableName}`");
            }
        } finally {
            $this->db->execute('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    /**
     * Seed records for a single table class.
     *
     * @param class-string<Table> $tableClass
     * @param array<int, array<string, mixed>> $records
     * @param string|null $parentFkColumn FK column name to inject (e.g., 'series_id')
     * @param string|int|null $parentId Parent record ID to inject
     * @return int Number of records inserted (including nested relations)
     */
    protected function seedTable(
        string $tableClass,
        array $records,
        ?string $parentFkColumn = null,
        string|int|null $parentId = null
    ): int {
        /** @var Table $table */
        $table = new $tableClass($this->db);
        $recordClass = $this->getRecordClass($table);
        $tableName = $this->getTableName($table);

        $count = 0;

        foreach ($records as $data) {
            if (!is_array($data)) {
                throw new InvalidArgumentException("Record data must be an array");
            }

            // Extract relations before creating the record
            $relations = $data['_relations'] ?? [];
            unset($data['_relations']);

            // Inject parent FK if provided
            if ($parentFkColumn !== null && $parentId !== null) {
                $data[$parentFkColumn] = $parentId;
            }

            // Create and insert the record
            /** @var Record $record */
            $record = new $recordClass($data);
            $insertedRecord = $table->insert($record);
            $count++;

            // Process nested relations
            if (!empty($relations)) {
                $fkColumn = $this->singularize($tableName) . '_id';

                foreach ($relations as $relatedTableClass => $relatedRecords) {
                    if (!is_string($relatedTableClass) || !class_exists($relatedTableClass)) {
                        throw new InvalidArgumentException("Invalid related table class: {$relatedTableClass}");
                    }

                    if (!is_subclass_of($relatedTableClass, Table::class)) {
                        throw new InvalidArgumentException("{$relatedTableClass} must extend " . Table::class);
                    }

                    $count += $this->seedTable(
                        $relatedTableClass,
                        $relatedRecords,
                        $fkColumn,
                        $insertedRecord->id
                    );
                }
            }
        }

        return $count;
    }

    /**
     * Get the record class from a table instance.
     *
     * @return class-string<Record>
     */
    protected function getRecordClass(Table $table): string
    {
        $reflection = new \ReflectionClass($table);
        $property = $reflection->getProperty('recordClass');

        return $property->getValue($table);
    }

    /**
     * Get the table name from a table instance.
     */
    protected function getTableName(Table $table): string
    {
        $reflection = new \ReflectionClass($table);
        $property = $reflection->getProperty('tableName');

        return $property->getValue($table);
    }

    /**
     * Get UUID columns from a record instance, including 'id' if the record uses UUIDs.
     *
     * @return string[]
     */
    protected function getUuidColumns(Record $record): array
    {
        $columns = [];

        // Check if the record uses UUID for its primary key
        if (method_exists($record, 'usesUuid') && $record->usesUuid()) {
            $pk = method_exists($record, 'primaryKey') ? $record->primaryKey() : 'id';
            $columns[] = $pk;
        }

        // Get explicitly declared UUID columns
        $reflection = new \ReflectionClass($record);
        if ($reflection->hasProperty('uuidColumns')) {
            $property = $reflection->getProperty('uuidColumns');
            $columns = array_merge($columns, $property->getValue($record));
        }

        return array_unique($columns);
    }

    /**
     * Simple singularization for FK column names.
     * Handles common plural patterns.
     */
    protected function singularize(string $word): string
    {
        // Common irregular plurals
        $irregulars = [
            'series' => 'series',
            'species' => 'species',
            'news' => 'news',
            'data' => 'data',
            'children' => 'child',
            'people' => 'person',
            'men' => 'man',
            'women' => 'woman',
        ];

        $lower = strtolower($word);
        if (isset($irregulars[$lower])) {
            return $irregulars[$lower];
        }

        // Words ending in 'ies' -> 'y' (e.g., 'categories' -> 'category')
        if (strlen($word) > 3 && str_ends_with($lower, 'ies')) {
            return substr($word, 0, -3) . 'y';
        }

        // Words ending in 'ses', 'xes', 'zes', 'ches', 'shes' -> remove 'es'
        if (strlen($word) > 2 && preg_match('/(s|x|z|ch|sh)es$/i', $word)) {
            return substr($word, 0, -2);
        }

        // Words ending in 's' (but not 'ss') -> remove 's'
        if (strlen($word) > 1 && str_ends_with($lower, 's') && !str_ends_with($lower, 'ss')) {
            return substr($word, 0, -1);
        }

        return $word;
    }

    /**
     * Upsert seed data — inserts new rows, updates existing ones.
     *
     * Uses INSERT ... ON DUPLICATE KEY UPDATE for each record.
     * Works with raw row data from dump files (no Record instantiation).
     *
     * @param array<class-string<Table>, array<int, array<string, mixed>>> $data
     * @return array<string, int> Count of records upserted per table class
     */
    public function upsert(array $data): array
    {
        $counts = [];

        foreach ($data as $tableClass => $records) {
            if (!is_string($tableClass) || !class_exists($tableClass)) {
                throw new InvalidArgumentException("Invalid table class: {$tableClass}");
            }

            if (!is_subclass_of($tableClass, Table::class)) {
                throw new InvalidArgumentException("{$tableClass} must extend " . Table::class);
            }

            /** @var Table $table */
            $table = new $tableClass($this->db);
            $tableName = $this->getTableName($table);

            // Detect UUID columns from the record class
            $recordClass = $this->getRecordClass($table);
            $recordInstance = new $recordClass([]);
            $uuidColumns = $this->getUuidColumns($recordInstance);

            $count = 0;

            foreach ($records as $row) {
                if (!is_array($row)) {
                    throw new InvalidArgumentException("Record data must be an array");
                }

                unset($row['_relations']);

                // Convert UUID columns to binary
                foreach ($uuidColumns as $col) {
                    if (isset($row[$col]) && is_string($row[$col]) && $row[$col] !== '') {
                        $row[$col] = $this->db->uuidToBin($row[$col]);
                    }
                }

                // Convert booleans to int for MySQL
                foreach ($row as $key => $value) {
                    if (is_bool($value)) {
                        $row[$key] = $value ? 1 : 0;
                    }
                }

                $columns = array_keys($row);
                $placeholders = array_fill(0, count($columns), '?');
                $updates = array_map(fn($col) => "`{$col}` = VALUES(`{$col}`)", $columns);

                $sql = sprintf(
                    'INSERT INTO `%s` (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
                    $tableName,
                    implode(', ', array_map(fn($c) => "`{$c}`", $columns)),
                    implode(', ', $placeholders),
                    implode(', ', $updates)
                );

                $this->db->execute($sql, array_values($row));
                $count++;
            }

            $counts[$tableClass] = $count;
        }

        return $counts;
    }

    /**
     * Collect all table classes from seed data (including nested relations).
     * Useful for truncation order.
     *
     * @param array<class-string<Table>, array<int, array<string, mixed>>> $data
     * @return array<class-string<Table>>
     */
    public function collectTableClasses(array $data): array
    {
        $classes = [];

        foreach ($data as $tableClass => $records) {
            if (!in_array($tableClass, $classes, true)) {
                $classes[] = $tableClass;
            }

            foreach ($records as $record) {
                if (isset($record['_relations']) && is_array($record['_relations'])) {
                    $nested = $this->collectTableClasses($record['_relations']);
                    foreach ($nested as $nestedClass) {
                        if (!in_array($nestedClass, $classes, true)) {
                            $classes[] = $nestedClass;
                        }
                    }
                }
            }
        }

        return $classes;
    }
}

