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

        $whereClauses = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            // Basic safety: $column becomes part of SQL (identifiers can't be parameterized).
            // We only allow simple column names here.
            if (!is_string($column) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
                throw new DatabaseException("Invalid column name in conditions: {$column}");
            }

            if ($column === 'id') {
                if ($recordInstance->useUuid) {
                    $value = $this->db->uuidToBin((string)$value);
                } else {
                    $value = (int)$value;
                }
            }

            $whereClauses[] = "`$column` = ?";
            $params[] = $value;
        }

        $whereSql = implode(' AND ', $whereClauses);
        $sql = "SELECT * FROM `$this->tableName`"
            . ($whereSql !== '' ? " WHERE {$whereSql}" : '');

        $stmt = $this->db->execute($sql, $params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($row) use ($recordInstance) {
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
        }, $rows);
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
