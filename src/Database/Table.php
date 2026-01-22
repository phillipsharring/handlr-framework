<?php

declare(strict_types=1);

namespace Handlr\Database;

use InvalidArgumentException;
use PDO;
use RuntimeException;

abstract class Table
{
    protected DbInterface $db;
    protected string $tableName;
    protected string $recordClass;

    /**
     * Cache UUID column lists per record class.
     *
     * @var array<class-string, string[]>
     */
    private array $uuidColumnsCache = [];

    /**
     * @throws DatabaseException
     */
    public function __construct(DbInterface $db)
    {
        $this->db = $db;

        if (!isset($this->tableName, $this->recordClass)) {
            throw new DatabaseException(
                'Table name and record class must be defined in child classes.'
            );
        }
    }

    public function findById(int|string $id): ?Record
    {
        $recordInstance = $this->getRecordInstance();
        if ($recordInstance->usesUuid()) {
            $id = $this->db->uuidToBin((string)$id);
        }

        $sql = "SELECT * FROM `$this->tableName` WHERE id = ?";
        $stmt = $this->db->execute($sql, [$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data && $recordInstance->usesUuid()) {
            $data['id'] = $this->db->binToUuid($data['id']);
        }

        return $data ? new $this->recordClass($data) : null;
    }

    public function findFirst(array $columns = [], array $conditions = [], array $orderBy = []): ?Record
    {
        return $this->findWhere($columns, $conditions, $orderBy, 1)[0] ?? null;
    }

    public function findWhere(array $columns = [], array $conditions = [], array $orderBy = [], ?int $limit = null): array
    {
        $recordInstance = $this->getRecordInstance();

        $columnsSql = $this->buildSelectColumns($columns);
        [$whereSql, $params] = $this->buildWhere($conditions, $recordInstance);
        $orderSql = $this->buildOrderBy($orderBy);
        $sql = "SELECT {$columnsSql} FROM `$this->tableName`"
            . ($whereSql !== '' ? " WHERE {$whereSql}" : '');
        if ($orderSql !== '') {
            $sql .= " ORDER BY {$orderSql}";
        }
        if ($limit !== null && $limit > 0) {
            $sql .= " LIMIT {$limit} OFFSET 0";
        }

        $stmt = $this->db->execute($sql, $params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->hydrateRows($rows, $recordInstance);
    }

    /**
     * Returns paginated results in the form:
     *  - data: Record[]
     *  - meta: pagination metadata (counts/pages/range)
     */
    public function paginate(array $columns = [], array $conditions = [], int $page = 1, int $perPage = 25, array $orderBy = []): array
    {
        $recordInstance = $this->getRecordInstance();

        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $columnsSql = $this->buildSelectColumns($columns);
        [$whereSql, $params] = $this->buildWhere($conditions, $recordInstance);
        $wherePart = ($whereSql !== '' ? " WHERE {$whereSql}" : '');
        $orderSql = $this->buildOrderBy($orderBy);
        $orderPart = ($orderSql !== '' ? " ORDER BY {$orderSql}" : '');

        // 1) total count query
        $total = $this->countByWhere($whereSql, $params);
        if ($total === 0) {
            return [
                'data' => [],
                'meta' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 0,
                    'from' => 0,
                    'to' => 0,
                    'count' => 0,
                    'has_more_pages' => false,
                    'next_page' => null,
                    'prev_page' => null,
                ],
            ];
        }

        // 2) page data query
        // NOTE: Many PDO drivers do not allow binding LIMIT/OFFSET placeholders reliably.
        // Since these are integers derived from arguments, embed them after clamping/casting.
        $dataSql = "SELECT {$columnsSql} FROM `$this->tableName`{$wherePart}{$orderPart} LIMIT {$perPage} OFFSET {$offset}";
        $rows = $this->db->execute($dataSql, $params)->fetchAll(PDO::FETCH_ASSOC);
        $data = $this->hydrateRows($rows, $recordInstance);

        $lastPage = $total > 0 ? (int)ceil($total / $perPage) : 0;
        // If the requested page is out of range (or otherwise returns no rows),
        // avoid impossible ranges like from > to.
        if ($total === 0 || count($data) === 0) {
            $from = 0;
            $to = 0;
        } else {
            $from = $offset + 1;
            $to = min($offset + count($data), $total);
        }

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $from,
                'to' => $to,
                'count' => count($data),
                'has_more_pages' => ($lastPage > 0) && ($page < $lastPage),
                'next_page' => ($lastPage > 0 && $page < $lastPage) ? ($page + 1) : null,
                'prev_page' => ($lastPage === 0)
                    ? null
                    : (($page > $lastPage)
                        ? $lastPage
                        : (($page > 1) ? ($page - 1) : null)),
            ],
        ];
    }

    /**
     * Count records matching conditions (same condition syntax as findWhere/paginate).
     */
    public function count(array $conditions = []): int
    {
        $recordInstance = $this->getRecordInstance();
        [$whereSql, $params] = $this->buildWhere($conditions, $recordInstance);
        return $this->countByWhere($whereSql, $params);
    }

    /**
     * Insert a new record into the database.
     *
     * @return int|string Inserted record ID (int for auto-increment, string for UUID)
     */
    public function insert($record)
    {
        if (!is_object($record)) {
            throw new InvalidArgumentException('record must be an object');
        }

        $prepared = $this->prepareInsertData($record);

        // Determine columns for single-row insert
        $columns = $this->buildColumnsUnion([$prepared]);
        $firstUsesUuid = method_exists($record, 'usesUuid') ? $record->usesUuid() : false;
        $anyNonEmptyId = $this->detectAnyNonEmptyId([$prepared]);

        $columns = $this->maybeIncludeIdColumn($columns, $firstUsesUuid, $anyNonEmptyId);

        $normalized = $this->normalizeRowsToColumns([$prepared], $columns);
        $quotedCols = $this->quoteIdentifiers($columns);
        $sql = $this->buildInsertSql($this->quotedTableName(), $quotedCols, $normalized['placeholders']);

        $this->db->execute($sql, $normalized['values']);

        // For non-UUID records, preserve existing behavior: set insertId when appropriate
        if (!$firstUsesUuid && !$anyNonEmptyId) {
            $insertId = $this->db->insertId();
            if ($insertId) {
                $record->id = $insertId;
            }
        }

        // UUID records already have string id on $record from prepareInsertData
        return $record;
    }

    /**
     * Insert multiple records in a single query.
     *
     * @param array $records
     * @return array
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function insertMany(array $records): array
    {
        $expectedClass = $this->validateRecordsArray($records);

        // Prepare rows
        $preparedRows = [];
        foreach ($records as $record) {
            $preparedRows[] = $this->prepareInsertData($record);
        }

        // Build columns union and determine id inclusion rules
        $columns = $this->buildColumnsUnion($preparedRows);
        $firstUsesUuid = method_exists($records[0], 'usesUuid') ? $records[0]->usesUuid() : false;
        $anyNonEmptyId = $this->detectAnyNonEmptyId($preparedRows);

        $columns = $this->maybeIncludeIdColumn($columns, $firstUsesUuid, $anyNonEmptyId);

        // Normalize and build SQL
        $normalized = $this->normalizeRowsToColumns($preparedRows, $columns);
        $quotedCols = $this->quoteIdentifiers($columns);
        $sql = $this->buildInsertSql($this->quotedTableName(), $quotedCols, $normalized['placeholders']);

        // Execute (per user request: no transaction, no auto-increment id collection)
        $this->db->execute($sql, $normalized['values']);

        // Ensure UUID records keep their string ids (prepareInsertData did that)
        return $records;
    }

    /**
     * @param string $whereSql SQL without the leading "WHERE"
     * @param array $params Prepared statement params
     */
    private function countByWhere(string $whereSql, array $params): int
    {
        $wherePart = ($whereSql !== '' ? " WHERE {$whereSql}" : '');
        $countSql = "SELECT COUNT(*) AS `total` FROM `$this->tableName`{$wherePart}";
        return (int)$this->db->execute($countSql, $params)->fetchColumn();
    }

    /**
     * @param string[] $columns E.g. ['id', 'name', 'email']
     * @throws DatabaseException
     */
    private function buildSelectColumns(array $columns): string
    {
        if ($columns === []) {
            return '*';
        }

        $parts = [];
        foreach ($columns as $idx => $column) {
            if (!is_string($column)) {
                throw new DatabaseException("Invalid column at index {$idx}. Expected a string.");
            }

            $normalized = $this->normalizeSelectColumn($column);
            $parts[] = $normalized;
        }

        return implode(', ', $parts);
    }

    /**
     * Normalizes:
     * - some_column => `some_column`
     * - some_table.some_column => `some_table`.`some_column`
     * - `some_table`.some_column => `some_table`.`some_column`
     * - some_table.`some_column` => `some_table`.`some_column`
     *
     * @throws DatabaseException
     */
    private function normalizeSelectColumn(string $raw): string
    {
        $s = trim($raw);
        if ($s === '') {
            throw new DatabaseException('SELECT column cannot be empty.');
        }

        // Strip any existing backticks; we re-apply consistently.
        $s = str_replace('`', '', $s);

        $segments = explode('.', $s);
        if (count($segments) === 1) {
            $col = $segments[0];
            $this->assertSqlIdentifier($col, "SELECT column {$raw}");
            return "`{$col}`";
        }

        if (count($segments) === 2) {
            [$a, $b] = $segments;
            $this->assertSqlIdentifier($a, "SELECT column {$raw}");
            $this->assertSqlIdentifier($b, "SELECT column {$raw}");
            return "`{$a}`.`{$b}`";
        }

        throw new DatabaseException("Invalid SELECT column format: {$raw}");
    }

    /**
     * @param array<int,array{0:string,1?:string}> $orderBy E.g. [['name','ASC'], ['users.created_at','desc']]
     * @throws DatabaseException
     */
    private function buildOrderBy(array $orderBy): string
    {
        if ($orderBy === []) {
            return '';
        }

        $parts = [];
        foreach ($orderBy as $idx => $item) {
            if (!is_array($item) || !isset($item[0]) || !is_string($item[0])) {
                throw new DatabaseException("Invalid orderBy item at index {$idx}. Expected: [column, direction?].");
            }

            $column = $this->normalizeOrderByColumn($item[0]);
            $direction = isset($item[1]) ? strtoupper(trim((string)$item[1])) : 'ASC';
            if ($direction === '') {
                $direction = 'ASC';
            }
            if (!in_array($direction, ['ASC', 'DESC'], true)) {
                throw new DatabaseException("Invalid ORDER BY direction for {$item[0]}: {$direction}");
            }

            $parts[] = "{$column} {$direction}";
        }

        return implode(', ', $parts);
    }

    /**
     * Normalizes:
     * - some_column => `some_column`
     * - some_table.some_column => `some_table`.`some_column`
     * - `some_table`.some_column => `some_table`.`some_column`
     * - some_table.`some_column` => `some_table`.`some_column`
     *
     * @throws DatabaseException
     */
    private function normalizeOrderByColumn(string $raw): string
    {
        $s = trim($raw);
        if ($s === '') {
            throw new DatabaseException('ORDER BY column cannot be empty.');
        }

        // Strip any existing backticks; we re-apply consistently.
        $s = str_replace('`', '', $s);

        $segments = explode('.', $s);
        if (count($segments) === 1) {
            $col = $segments[0];
            $this->assertSqlIdentifier($col, "ORDER BY column {$raw}");
            return "`{$col}`";
        }

        if (count($segments) === 2) {
            [$a, $b] = $segments;
            $this->assertSqlIdentifier($a, "ORDER BY column {$raw}");
            $this->assertSqlIdentifier($b, "ORDER BY column {$raw}");
            return "`{$a}`.`{$b}`";
        }

        throw new DatabaseException("Invalid ORDER BY column format: {$raw}");
    }

    private function assertSqlIdentifier(string $id, string $context): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $id)) {
            throw new DatabaseException("Invalid SQL identifier in {$context}: {$id}");
        }
    }

    /**
     * @return array{0:string,1:array} [whereSql, params]
     * @throws DatabaseException
     */
    private function buildWhere(array $conditions, Record $recordInstance): array
    {
        $whereClauses = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            // Basic safety: $column becomes part of SQL (identifiers can't be parameterized).
            // We only allow simple column names here.
            if (!is_string($column) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
                throw new DatabaseException("Invalid column name in conditions: {$column}");
            }

            [$clause, $clauseParams] = $this->buildWhereClause($column, $value, $recordInstance);
            $whereClauses[] = $clause;
            array_push($params, ...$clauseParams);
        }

        return [implode(' AND ', $whereClauses), $params];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return Record[]
     */
    private function hydrateRows(array $rows, Record $recordInstance): array
    {
        return array_map(fn(array $row) => $this->hydrateRow($row, $recordInstance), $rows);
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrateRow(array $row, Record $recordInstance): Record
    {
        if (isset($row['id'])) {
            if ($recordInstance->usesUuid()) {
                $row['id'] = $this->db->binToUuid($row['id']);
            } else {
                $row['id'] = (int)$row['id'];
            }
        } else {
            $row['id'] = 0;
        }

        // Convert additional UUID columns (e.g. user_id) from BINARY(16) -> UUID string.
        foreach ($this->uuidColumnsForRecord($recordInstance) as $col) {
            if ($col === 'id') continue;
            if (!array_key_exists($col, $row)) continue;
            if ($row[$col] === null || $row[$col] === '') continue;
            if (is_string($row[$col])) {
                $row[$col] = $this->db->binToUuid($row[$col]);
            }
        }

        return new $this->recordClass($row);
    }

    /**
     * Supports:
     * - Primitive shorthand: ['name' => 'Phil'] => `name` = ?
     * - Operator form (indexed): ['name' => ['<>', 'Phil']] or ['name' => ['Phil', '<>']]
     * - Operator form (assoc): ['name' => ['operator' => '<>', 'value' => 'Phil']] (key order doesn't matter)
     * - BETWEEN: ['date' => ['BETWEEN', '2025-01-01', '2025-12-31']] or ['operator'=>'between','value'=>['2025-01-01','2025-12-31']]
     *
     * @return array{0:string,1:array} [sqlClause, params]
     * @throws DatabaseException
     */
    private function buildWhereClause(string $column, mixed $condition, Record $recordInstance): array
    {
        // 1) Primitive shorthand
        if (!is_array($condition)) {
            if ($condition === null) {
                return ["`{$column}` IS NULL", []];
            }
            $op = '=';
            $value = $this->normalizeIdConditionValue($column, $op, $condition, $recordInstance);
            return ["`{$column}` {$op} ?", [$value]];
        }

        // 2) Operator forms
        $operator = null;
        $value = null;

        // Assoc form: ['operator' => '>=', 'value' => 123]
        if (array_key_exists('operator', $condition) || array_key_exists('value', $condition)) {
            $operator = $condition['operator'] ?? null;
            $value = $condition['value'] ?? null;
        } else {
            // Indexed array form
            if (count($condition) < 2) {
                throw new DatabaseException("Invalid condition for {$column}: expected [operator, value] or [value, operator].");
            }

            // BETWEEN: ['BETWEEN', a, b]
            if (count($condition) >= 3) {
                $operator = $condition[0] ?? null;
                $value = [$condition[1] ?? null, $condition[2] ?? null];
            } else {
                $a = $condition[0] ?? null;
                $b = $condition[1] ?? null;

                // Prefer operator-first, but allow swapped if we can identify an operator safely.
                if (is_string($a) && $this->isAllowedOperator($a)) {
                    $operator = $a;
                    $value = $b;
                } elseif (is_string($b) && $this->isAllowedOperator($b)) {
                    $operator = $b;
                    $value = $a;
                } else {
                    throw new DatabaseException(
                        "Invalid operator for {$column}. Expected one of: " . implode(', ', $this->allowedOperators())
                    );
                }
            }
        }

        if (!is_string($operator) || trim($operator) === '') {
            throw new DatabaseException("Invalid operator for {$column}.");
        }

        // Case-insensitive operator input; always generate uppercase SQL keywords.
        $op = strtoupper(trim($operator));
        if (!$this->isAllowedOperator($op)) {
            throw new DatabaseException("Disallowed operator for {$column}: {$operator}");
        }

        if ($op === 'BETWEEN') {
            if (!is_array($value) || count($value) !== 2) {
                throw new DatabaseException(
                    "BETWEEN condition for {$column} must be ['BETWEEN', from, to] or ['operator'=>'BETWEEN','value'=>[from,to]]."
                );
            }

            [$from, $to] = array_values($value);
            $from = $this->normalizeIdConditionValue($column, $op, $from, $recordInstance);
            $to = $this->normalizeIdConditionValue($column, $op, $to, $recordInstance);
            return ["`{$column}` BETWEEN ? AND ?", [$from, $to]];
        }

        if ($op === 'IN' || $op === 'NOT IN') {
            if (!is_array($value)) {
                throw new DatabaseException("{$op} condition for {$column} must provide an array of values.");
            }

            // Normalize values (especially important for id UUID->BIN).
            $values = array_values($value);
            $values = array_map(
                fn($v) => $this->normalizeIdConditionValue($column, $op, $v, $recordInstance),
                $values
            );

            // SQL edge cases:
            // - `IN ()` is invalid; treat empty IN as always false.
            // - `NOT IN ()` is always true (no exclusions).
            if ($values === []) {
                return [$op === 'IN' ? '0 = 1' : '1 = 1', []];
            }

            $placeholders = implode(',', array_fill(0, count($values), '?'));
            return ["`{$column}` {$op} ({$placeholders})", $values];
        }

        // LIKE / NOT LIKE: % is not eaten by parameterization; it works as expected.
        $value = $this->normalizeIdConditionValue($column, $op, $value, $recordInstance);
        return ["`{$column}` {$op} ?", [$value]];
    }

    /**
     * @return string[]
     */
    private function allowedOperators(): array
    {
        return [
            '=',
            '!=',
            '<>',
            '>',
            '<',
            '>=',
            '<=',
            'LIKE',
            'NOT LIKE',
            'BETWEEN',
            'IN',
            'NOT IN',
        ];
    }

    private function isAllowedOperator(string $op): bool
    {
        return in_array(strtoupper(trim($op)), $this->allowedOperators(), true);
    }

    /**
     * Special-case ID values so the DB boundary stays consistent.
     * - UUID records store id in code as string UUID, and in DB as binary => uuidToBin() for conditions.
     * - int records cast id conditions to int.
     */
    private function normalizeIdConditionValue(string $column, string $op, mixed $value, Record $recordInstance): mixed
    {
        if ($column !== 'id') {
            $uuidCols = $this->uuidColumnsForRecord($recordInstance);
            if (in_array($column, $uuidCols, true)) {
                return $this->db->uuidToBin($value);
            }
            return $value;
        }

        if ($recordInstance->usesUuid()) {
            if ($value === null || $value === '') {
                return $value;
            }
            return $this->db->uuidToBin((string)$value);
        }

        return (int)$value;
    }

    /**
     * Convert configured UUID columns (other than `id`) from UUID string -> BINARY(16) for DB storage.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function convertUuidColumnsForDb(Record $record, array $data): array
    {
        foreach ($this->uuidColumnsForRecord($record) as $col) {
            if ($col === 'id') continue;
            if (!array_key_exists($col, $data)) continue;
            $v = $data[$col];
            if ($v === null || $v === '') continue;
            if (
                is_string($v)
                && preg_match(
                    '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/',
                    $v
                )
            ) {
                $data[$col] = $this->db->uuidToBin($v);
            }
        }
        return $data;
    }

    private function prepareInsertData($record): array
    {
        // Convert to array and remove created_at (preserve behavior from insert)
        $data = $record->toArray();
        if (array_key_exists('created_at', $data)) {
            unset($data['created_at']);
        }

        // Let existing method convert UUID columns to DB representation
        $data = $this->convertUuidColumnsForDb($record, $data);

        // If this record uses UUIDs, ensure the record has a string id and store binary for DB
        if (method_exists($record, 'usesUuid') && $record->usesUuid()) {
            $stringId = (string)$record->id;
            $data['id'] = $this->db->uuidToBin($stringId);
            // keep the record id as string for caller
            $record->id = $stringId;
        }

        return $data;
    }

    private function shouldOmitAutoIncrementId($record, array $data): bool
    {
        if (method_exists($record, 'usesUuid') && $record->usesUuid()) {
            return false;
        }

        // Match previous semantics: treat empty id (except 0) as "omit"
        if (!array_key_exists('id', $data)) {
            return true;
        }

        $val = $data['id'];
        return empty($val) && $val !== 0 && $val !== '0';
    }

    private function buildColumnsUnion(array $rows): array
    {
        $columns = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $col) {
                if (!in_array($col, $columns, true)) {
                    $columns[] = $col;
                }
            }
        }
        return $columns;
    }

    private function detectAnyNonEmptyId(array $rows): bool
    {
        foreach ($rows as $row) {
            if (array_key_exists('id', $row) && $row['id'] !== null && $row['id'] !== '') {
                return true;
            }
        }
        return false;
    }

    private function maybeIncludeIdColumn(array $columns, bool $firstUsesUuid, bool $anyNonEmptyId): array
    {
        if (!$firstUsesUuid && !$anyNonEmptyId) {
            // Omit id to allow auto-increment
            $columns = array_values(array_filter($columns, function ($c) {
                return $c !== 'id';
            }));
            return $columns;
        }

        // Ensure id is present (for UUIDs or when explicit ids were provided)
        if (!in_array('id', $columns, true)) {
            array_unshift($columns, 'id');
        }
        return $columns;
    }

    private function normalizeRowsToColumns(array $rows, array $columns): array
    {
        $values = [];
        $placeholders = [];

        foreach ($rows as $row) {
            $rowPlaceholders = [];
            foreach ($columns as $col) {
                if (array_key_exists($col, $row)) {
                    $values[] = $row[$col];
                } else {
                    $values[] = null;
                }
                $rowPlaceholders[] = '?';
            }
            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
        }

        return ['values' => $values, 'placeholders' => $placeholders];
    }

    private function quoteIdentifiers(array $columns): array
    {
        $out = [];
        foreach ($columns as $c) {
            $escaped = str_replace('`', '``', $c);
            $out[] = "`{$escaped}`";
        }
        return $out;
    }

    private function quotedTableName(): string
    {
        $name = $this->tableName ?? $this->table ?? '';
        $escaped = str_replace('`', '``', $name);
        return "`{$escaped}`";
    }

    private function buildInsertSql(string $quotedTable, array $quotedColumns, array $placeholders): string
    {
        if (empty($quotedColumns)) {
            throw new RuntimeException('No columns specified for INSERT');
        }
        $cols = implode(', ', $quotedColumns);
        $vals = implode(', ', $placeholders);
        return "INSERT INTO {$quotedTable} ({$cols}) VALUES {$vals}";
    }

    private function validateRecordsArray(array $records): string
    {
        if ($records === []) {
            throw new InvalidArgumentException('insertMany requires at least one record.');
        }

        $expectedClass = $this->recordClass ?? get_class($records[0]);

        foreach ($records as $i => $r) {
            if (!is_object($r) || !($r instanceof $expectedClass)) {
                throw new InvalidArgumentException("All elements passed to insertMany must be instances of {$expectedClass}. Found index {$i}.");
            }
        }

        return $expectedClass;
    }

    /**
     * @throws DatabaseException
     */
    public function update(Record $record): int
    {
        $id = $record->id;
        if ($id === null || $id === '') {
            throw new DatabaseException('Cannot update a record without an ID.');
        }

        // id is not updated
        $data = $record->toArray();
        unset($data['id']);

        // Remove updated_at from update data to let the database default handle it
        unset($data['updated_at']);

        $data = $this->convertUuidColumnsForDb($record, $data);

        if ($record->usesUuid()) {
            $id = $this->db->uuidToBin((string)$id);
        }

        $setClauses = [];
        $values = array_values($data);

        foreach (array_keys($data) as $column) {
            $setClauses[] = "`$column` = ?";
        }

        $setSql = implode(',', $setClauses);
        $values[] = $id;

        $sql = "UPDATE `$this->tableName` SET $setSql WHERE id = ?";
        $this->db->execute($sql, $values);

        return $this->db->affectedRows();
    }

    /**
     * @throws DatabaseException
     */
    public function delete(Record $record): int
    {
        if (!isset($record->id)) {
            throw new DatabaseException('Cannot delete a record without an ID.');
        }

        $id = $record->id;

        if ($record->usesUuid()) {
            $id = $this->db->uuidToBin((string)$id);
        }

        $sql = "DELETE FROM `$this->tableName` WHERE id = ?";
        $this->db->execute($sql, [$id]);

        return $this->db->affectedRows();
    }

    /**
     * Create an instance of the record class
     */
    private function getRecordInstance(): Record
    {
        return new $this->recordClass();
    }

    /**
     * Get UUID columns (excluding id) for a record instance.
     *
     * @return string[]
     */
    private function uuidColumnsForRecord(Record $record): array
    {
        $cls = $record::class;
        if (isset($this->uuidColumnsCache[$cls])) {
            return $this->uuidColumnsCache[$cls];
        }

        $cols = $record->uuidColumns();

        // Normalize: strings only, unique, preserve order.
        $out = [];
        foreach ($cols as $c) {
            if (!is_string($c)) continue;
            $c = trim($c);
            if ($c === '') continue;
            if (in_array($c, $out, true)) continue;
            $out[] = $c;
        }

        return $this->uuidColumnsCache[$cls] = $out;
    }
}
