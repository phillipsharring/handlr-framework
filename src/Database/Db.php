<?php

declare(strict_types=1);

namespace Handlr\Database;

use Handlr\Config\Config;
use PDO;
use PDOStatement;
use PDOException;
use Ramsey\Uuid\Uuid;

class Db implements DbInterface
{
    private ?PDO $connection = null;
    private ?PDOStatement $lastStatement = null;
    private string $dsn;
    private string $user;
    private string $password;
    private array $options;

    /**
     * Validates DB config eagerly but defers the PDO connection to first use.
     *
     * @throws DatabaseException If DSN is missing or invalid
     */
    public function __construct(private readonly Config $config)
    {
        $dbConfig = $this->config->get('database', []);
        [
            'dsn' => $dsn,
            'user' => $user,
            'password' => $password,
            'options' => $options,
        ] = $dbConfig + ['options' => []]; // default options if missing

        if (!is_string($dsn) || trim($dsn) === '') {
            throw new DatabaseException('Missing database DSN (expected config key: database.dsn).');
        }

        $this->dsn = $dsn;
        $this->user = $user;
        $this->password = $password;
        $this->options = $options;

        // For MySQL, PDO requires `dbname=` to select a default database.
        // If the DSN omits it, queries like "SHOW TABLES" will fail with "No database selected".
        if ($this->isMysqlDsn($dsn) && !$this->dsnHasDatabaseName($dsn)) {
            throw new DatabaseException(
                'MySQL DSN must include a database name using `dbname=`. '
                    . 'Example: mysql:host=127.0.0.1;port=3306;dbname=your_db;charset=utf8mb4. '
                    . "Received: {$dsn}"
            );
        }
    }

    /**
     * @throws DatabaseException
     */
    private function connect(): PDO
    {
        if ($this->connection === null) {
            try {
                $this->connection = new PDO($this->dsn, $this->user, $this->password, $this->options);
            } catch (PDOException $e) {
                throw new DatabaseException(
                    'Failed to connect to database. '
                        . "DSN: {$this->dsn}. "
                        . "PDO error: {$e->getMessage()}",
                    (int)$e->getCode(),
                    $e
                );
            }
        }

        return $this->connection;
    }

    private function isMysqlDsn(string $dsn): bool
    {
        return str_starts_with(strtolower($dsn), 'mysql:');
    }

    private function dsnHasDatabaseName(string $dsn): bool
    {
        return (bool)preg_match('/(^|;)dbname=([^;]+)/i', $dsn);
    }

    public function getDatabaseName(): string
    {
        preg_match('/dbname=([^;]+)/i', $this->dsn, $matches);
        return $matches[1] ?? '';
    }

    public function execute(string $sql, array $params = []): false|PDOStatement
    {
        $stmt = $this->connect()->prepare($sql);
        // PDO execute() binds all array values as PARAM_STR.
        // PHP (string)false === "" which MySQL rejects for integer columns.
        $params = array_map(static fn($v) => is_bool($v) ? (int)$v : $v, $params);
        $stmt->execute($params);
        $this->lastStatement = $stmt;
        return $stmt;
    }

    public function insertId(): int
    {
        return (int)$this->connect()->lastInsertId();
    }

    /**
     * @throws DatabaseException
     */
    public function affectedRows(?PDOStatement $stmt = null): int
    {
        $stmt = $stmt ?? $this->lastStatement;
        if (!$stmt) {
            throw new DatabaseException('No statement available to get affected rows.');
        }
        return $stmt->rowCount();
    }

    public function uuidToBin(string $uuid): string
    {
        if (!Uuid::isValid($uuid)) {
            // it is probably already a binary string
            return $uuid;
        }

        // Return raw 16-byte binary string for storage in BINARY(16) columns.
        return Uuid::fromString($uuid)->getBytes();
    }

    public function binToUuid(string $bin): string
    {
        if (Uuid::isValid($bin)) {
            // it is probably already a uuid
            return $bin;
        }

        // Accept raw 16-byte binary string from BINARY(16) columns.
        return Uuid::fromBytes($bin)->toString();
    }

    public function beginTransaction(): bool
    {
        return $this->connect()->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->connect()->commit();
    }

    public function rollBack(): bool
    {
        return $this->connect()->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->connection !== null && $this->connection->inTransaction();
    }
}
