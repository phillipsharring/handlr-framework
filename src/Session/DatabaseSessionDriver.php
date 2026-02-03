<?php

declare(strict_types=1);

namespace Handlr\Session;

use Handlr\Database\DatabaseException;
use Handlr\Database\Db;
use PDO;
use SessionHandlerInterface;

/**
 * Database-backed session storage driver.
 *
 * Stores session data in a database table instead of the filesystem.
 * Useful for load-balanced environments, better security, and session analytics.
 *
 * ## Database table schema
 *
 * Create the sessions table with this migration:
 *
 * ```sql
 * CREATE TABLE `sessions` (
 *     `id` VARCHAR(128) NOT NULL PRIMARY KEY,
 *     `access` INT UNSIGNED NOT NULL,
 *     `data` TEXT NOT NULL,
 *     INDEX `idx_access` (`access`)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 * ```
 *
 * Or use a migration:
 *
 * ```php
 * class Migration_CreateSessionsTable extends BaseMigration
 * {
 *     public function up(): void
 *     {
 *         $this->exec("
 *             CREATE TABLE `sessions` (
 *                 `id` VARCHAR(128) NOT NULL PRIMARY KEY,
 *                 `access` INT UNSIGNED NOT NULL,
 *                 `data` TEXT NOT NULL,
 *                 INDEX `idx_access` (`access`)
 *             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
 *         ");
 *     }
 *
 *     public function down(): void
 *     {
 *         $this->exec("DROP TABLE IF EXISTS `sessions`");
 *     }
 * }
 * ```
 *
 * ## Setup
 *
 * ```php
 * // Create the driver
 * $handler = new DatabaseSessionDriver($db);
 *
 * // Or with custom table name
 * $handler = new DatabaseSessionDriver($db, 'user_sessions');
 *
 * // Use with Session class
 * $session = new Session($handler);
 * $session->start();
 * ```
 *
 * ## How it works
 *
 * - **read()**: Fetches session data from database by session ID
 * - **write()**: Uses REPLACE INTO to insert or update session data
 * - **destroy()**: Deletes the session row
 * - **gc()**: Removes expired sessions based on `session.gc_maxlifetime`
 *
 * ## Garbage collection
 *
 * PHP periodically calls `gc()` based on `session.gc_probability` and
 * `session.gc_divisor` settings. For high-traffic sites, consider running
 * cleanup via cron instead:
 *
 * ```php
 * // In a cron job or scheduled task
 * $maxLifetime = ini_get('session.gc_maxlifetime');
 * $threshold = time() - $maxLifetime;
 * $db->execute("DELETE FROM sessions WHERE access < ?", [$threshold]);
 * ```
 *
 * ## Benefits over file sessions
 *
 * - **Scalability**: Works across multiple servers (load balancing)
 * - **Security**: Sessions not stored in predictable filesystem locations
 * - **Analytics**: Query session data for active users, etc.
 * - **Cleanup**: Database handles cleanup more reliably than filesystem GC
 */
class DatabaseSessionDriver implements SessionHandlerInterface
{
    /**
     * @param Db     $db    Database connection
     * @param string $table Table name for session storage
     */
    public function __construct(private Db $db, private string $table = 'sessions') {}

    /**
     * Open the session storage.
     *
     * No action needed - database connection is already established.
     *
     * @param string $path Session save path (unused for database)
     * @param string $name Session name (unused for database)
     *
     * @return bool Always returns true
     */
    public function open($path, $name): bool
    {
        return true;
    }

    /**
     * Close the session storage.
     *
     * No action needed - database connection managed elsewhere.
     *
     * @return bool Always returns true
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * Read session data from the database.
     *
     * @param string $id Session ID
     *
     * @return string Serialized session data, or empty string if not found
     */
    public function read($id): string
    {
        $sql = "SELECT `data` FROM `$this->table` WHERE `id` = :id";
        $params = [':id' => $id];
        $result = $this->db->execute($sql, $params)->fetch(PDO::FETCH_ASSOC);

        return $result['data'] ?? '';
    }

    /**
     * Write session data to the database.
     *
     * Uses REPLACE INTO for atomic insert-or-update behavior.
     *
     * @param string $id   Session ID
     * @param string $data Serialized session data
     *
     * @return bool True on success
     */
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

    /**
     * Destroy a session.
     *
     * @param string $id Session ID to destroy
     *
     * @return bool True on success
     */
    public function destroy($id): bool
    {
        $sql = "DELETE FROM `$this->table` WHERE `id` = :id";
        $params = [':id' => $id];

        return (bool)$this->db->execute($sql, $params);
    }

    /**
     * Garbage collection - remove expired sessions.
     *
     * Called periodically by PHP based on gc_probability/gc_divisor settings.
     *
     * @param int $max_lifetime Maximum session lifetime in seconds
     *
     * @return int|false Number of deleted sessions, or false on failure
     *
     * @throws DatabaseException On database errors
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
