<?php

declare(strict_types=1);

namespace Handlr\Database;

use PDO;

abstract class Table
{
    protected Db $db;
    protected string $tableName;
    protected string $recordClass;

    /**
     * @throws DatabaseException
     */
    public function __construct(Db $db)
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

        if ($recordInstance->useUuid) {
            $id = $this->db->uuidToBin((string)$id);
        }

        $sql = "SELECT * FROM `$this->tableName` WHERE id = ?";
        $stmt = $this->db->execute($sql, [$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data && $recordInstance->useUuid) {
            $data['id'] = $this->db->binToUuid($data['id']);
        }

        return $data ? new $this->recordClass($data) : null;
    }

    public function findFirst(array $conditions): ?Record
    {
        return $this->findWhere($conditions)[0] ?? null;
    }

    public function findWhere(array $conditions = []): array
    {
        $recordInstance = $this->getRecordInstance();

        [$whereSql, $params] = $this->buildWhere($conditions, $recordInstance);
        $sql = "SELECT * FROM `$this->tableName`"
            . ($whereSql !== '' ? " WHERE {$whereSql}" : '');

        $stmt = $this->db->execute($sql, $params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->hydrateRows($rows, $recordInstance);
    }

    /**
     * Returns paginated results in the form:
     *  - data: Record[]
     *  - meta: pagination metadata (counts/pages/range)
     */
    public function paginate(array $conditions = [], int $page = 1, int $perPage = 25): array
    {
        $recordInstance = $this->getRecordInstance();

        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        [$whereSql, $params] = $this->buildWhere($conditions, $recordInstance);
        $wherePart = ($whereSql !== '' ? " WHERE {$whereSql}" : '');

        // 1) total count query
        $countSql = "SELECT COUNT(*) AS `total` FROM `$this->tableName`{$wherePart}";
        $total = (int)$this->db->execute($countSql, $params)->fetchColumn();

        // 2) page data query
        // NOTE: Many PDO drivers do not allow binding LIMIT/OFFSET placeholders reliably.
        // Since these are integers derived from arguments, embed them after clamping/casting.
        $dataSql = "SELECT * FROM `$this->tableName`{$wherePart} LIMIT {$perPage} OFFSET {$offset}";
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
                'prev_page' => ($lastPage > 0 && $page > $lastPage)
                    ? $lastPage
                    : (($page > 1) ? ($page - 1) : null),
            ],
        ];
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
            if ($recordInstance->useUuid) {
                $row['id'] = $this->db->binToUuid($row['id']);
            } else {
                $row['id'] = (int)$row['id'];
            }
        } else {
            $row['id'] = 0;
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
            return $value;
        }

        if ($recordInstance->useUuid) {
            if ($value === null || $value === '') {
                return $value;
            }
            return $this->db->uuidToBin((string)$value);
        }

        return (int)$value;
    }

    public function insert(Record $record): int|string
    {
        unset($record->created_at);

        $data = $record->toArray();

        // If using auto-increment IDs, don't insert a null/empty id.
        if (!$record->useUuid && (empty($data['id']) && $data['id'] !== 0)) {
            unset($data['id']);
        }

        if ($record->useUuid && isset($data['id']) && $data['id'] !== '') {
            $data['id'] = $this->db->uuidToBin((string)$record->id);
        }

        $columns = implode(
            ',',
            array_map(static fn($column) => "`$column`", array_keys($data))
        );
        $placeholders = implode(',', array_fill(0, count($data), '?'));
        $values = array_values($data);

        $sql = "INSERT INTO `$this->tableName` ($columns) VALUES ($placeholders)";
        $this->db->execute($sql, $values);

        if ($record->useUuid) {
            $insertId = $record->id; // Use the provided/generated UUID as the ID
        } else {
            $insertId = $this->db->insertId();
            $record->id = $insertId; // Set the new ID on the Record object
        }

        return $insertId;
    }

    /**
     * @throws DatabaseException
     */
    public function update(Record $record): int
    {
        unset($record->updated_at);

        $id = $record->id;
        if ($id === null || $id === '') {
            throw new DatabaseException('Cannot update a record without an ID.');
        }

        // id is not updated
        $data = $record->toArray();
        unset($data['id']);

        if ($record->useUuid) {
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

        if ($record->useUuid) {
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
}
