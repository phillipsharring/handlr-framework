<?php

declare(strict_types=1);

namespace Handlr\Database;

use Handlr\Config\Config;
use PDO;
use PDOStatement;
use PDOException;

class Db
{
    private PDO $connection;
    private ?PDOStatement $lastStatement = null;
    private string $dsn;

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

        // For MySQL, PDO requires `dbname=` to select a default database.
        // If the DSN omits it, queries like "SHOW TABLES" will fail with "No database selected".
        if ($this->isMysqlDsn($dsn) && !$this->dsnHasDatabaseName($dsn)) {
            throw new DatabaseException(
                'MySQL DSN must include a database name using `dbname=`. '
                    . 'Example: mysql:host=127.0.0.1;port=3306;dbname=your_db;charset=utf8mb4. '
                    . "Received: {$dsn}"
            );
        }

        try {
            $this->connection = new PDO($dsn, $user, $password, $options);
        } catch (PDOException $e) {
            throw new DatabaseException(
                'Failed to connect to database. '
                    . "DSN: {$dsn}. "
                    . "PDO error: {$e->getMessage()}",
                (int)$e->getCode(),
                $e
            );
        }
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
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        $this->lastStatement = $stmt;
        return $stmt;
    }

    public function insertId(): int
    {
        return (int)$this->connection->lastInsertId();
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
        return $this->execute("SELECT UUID_TO_BIN(:uuid) AS `bin`", [':uuid' => $uuid])
            ->fetch(PDO::FETCH_COLUMN);
    }

    public function binToUuid(string $bin): string
    {
        return $this->execute("SELECT BIN_TO_UUID(:bin) AS `uuid`", [':bin' => $bin])
            ->fetch(PDO::FETCH_COLUMN);
    }
}
