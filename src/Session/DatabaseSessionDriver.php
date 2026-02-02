<?php

declare(strict_types=1);

namespace Handlr\Session;

use Handlr\Database\DatabaseException;
use Handlr\Database\Db;
use PDO;
use SessionHandlerInterface;

class DatabaseSessionDriver implements SessionHandlerInterface
{
    public function __construct(private Db $db, private string $table = 'sessions') {}

    public function open($path, $name): bool
    {
        // No explicit action needed; connection is handled by Db
        return true;
    }

    public function close(): bool
    {
        // No explicit action needed for closing
        return true;
    }

    public function read($id): string
    {
        $sql = "SELECT `data` FROM `$this->table` WHERE `id` = :id";
        $params = [':id' => $id];
        $result = $this->db->execute($sql, $params)->fetch(PDO::FETCH_ASSOC);

        return $result['data'] ?? '';
    }

    public function write($id, $data): bool
    {
        $access = time();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userId = $_SESSION['user_id'] ?? null;

        $sql = "
            REPLACE INTO `$this->table` (`id`, `access`, `data`)
            VALUES (:id, :access, :data)
        ";

        $params = [
            ':id' => $id,
            ':access' => $access,
            ':data' => $data,
        ];

        return (bool)$this->db->execute($sql, $params);
    }

    public function destroy($id): bool
    {
        $sql = "DELETE FROM `$this->table` WHERE `id` = :id";
        $params = [':id' => $id];

        return (bool)$this->db->execute($sql, $params);
    }

    /**
     * @throws DatabaseException
     */
    public function gc($max_lifetime): false|int
    {
        $threshold = time() - $max_lifetime;
        $sql = "DELETE FROM `$this->table` WHERE `access` < :threshold";
        $params = [':threshold' => $threshold];

        if (!$this->db->execute($sql, $params)) {
            return false;
        }

        return (bool)$this->db->affectedRows();
    }
}
