<?php

declare(strict_types=1);

namespace Handlr\Database;

use ArrayAccess;
use DateTime;
use JsonSerializable;
use Ramsey\Uuid\Uuid;

/**
 * Abstract base class for database record objects (Active Record pattern).
 *
 * Extend this class to create record objects that map to database rows.
 * Records support automatic UUID generation, type casting, computed columns,
 * and seamless JSON serialization.
 *
 * ## Basic usage
 *
 * Use `@property` docblocks to define columns for IDE autocomplete:
 *
 * ```php
 * /**
 *  * @property string $name
 *  * @property string $email
 *  * @property string|null $bio
 *  * /
 * class UserRecord extends Record
 * {
 *     protected array $casts = [
 *         'created_at' => 'date',
 *     ];
 * }
 *
 * // Create a new record (UUID auto-generated)
 * $user = new UserRecord([
 *     'name' => 'John Doe',
 *     'email' => 'john@example.com',
 * ]);
 * echo $user->id;    // e.g., "0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6b"
 * echo $user->name;  // "John Doe"
 *
 * // Hydrate from database row
 * $user = new UserRecord($databaseRow);
 * ```
 *
 * ## UUID vs auto-increment IDs
 *
 * By default, records use UUIDv7 (time-ordered) for IDs. To use auto-increment
 * integer IDs instead, set `$useUuid = false`:
 *
 * ```php
 * class LegacyRecord extends Record
 * {
 *     protected bool $useUuid = false;  // Use auto-increment integers
 * }
 * ```
 *
 * ## Type casting
 *
 * Define `$casts` to automatically convert values when accessed via `__get`:
 *
 * ```php
 * /**
 *  * @property string $title
 *  * @property bool $is_published
 *  * @property int $view_count
 *  * /
 * class PostRecord extends Record
 * {
 *     protected array $casts = [
 *         'is_published' => 'bool',
 *         'view_count' => 'int',
 *         'rating' => 'float',
 *         'published_at' => 'date',
 *     ];
 * }
 *
 * $post = new PostRecord(['is_published' => 1, 'view_count' => '42']);
 * var_dump($post->is_published);  // bool(true)
 * var_dump($post->view_count);    // int(42)
 * ```
 *
 * Supported cast types: `bool`, `int`, `float`, `string`, `date` (DateTime)
 *
 * ## UUID columns (foreign keys)
 *
 * When a column stores a UUID foreign key as BINARY(16) in the database,
 * declare it in `$uuidColumns` for automatic conversion:
 *
 * ```php
 * /**
 *  * @property string $user_id
 *  * @property string $post_id
 *  * @property string $content
 *  * /
 * class CommentRecord extends Record
 * {
 *     protected array $uuidColumns = ['user_id', 'post_id'];
 * }
 *
 * // In code, work with UUID strings:
 * $comment->user_id = '550e8400-e29b-41d4-a716-446655440000';
 *
 * // Table class automatically converts to/from BINARY(16) for storage
 * ```
 *
 * ## Computed columns
 *
 * Computed columns are included in `toArray()` output but excluded from
 * database inserts/updates. Use for derived or joined data:
 *
 * ```php
 * /**
 *  * @property string $first_name
 *  * @property string $last_name
 *  * @property string|null $full_name
 *  * /
 * class UserRecord extends Record
 * {
 *     protected array $computed = ['full_name'];
 * }
 * ```
 *
 * ## Array and property access
 *
 * Records support both property and array access:
 *
 * ```php
 * $user->name = 'John';      // Property access (via __set)
 * $user['name'] = 'John';    // Array access (equivalent)
 *
 * echo $user->name;          // Property access (via __get)
 * echo $user['name'];        // Array access (equivalent)
 * ```
 *
 * ## JSON serialization
 *
 * Records implement `JsonSerializable` for easy API responses:
 *
 * ```php
 * $user = new UserRecord(['name' => 'John', 'email' => 'john@example.com']);
 * echo json_encode($user);
 * // {"id":"...","name":"John","email":"john@example.com"}
 * ```
 */
abstract class Record implements JsonSerializable, ArrayAccess
{
    /**
     * Primary key value - integer for auto-increment tables, string UUID for UUID tables.
     */
    public int|string|null $id = null;

    /**
     * The column name of the primary key.
     * Override in child class if the primary key column is not `id`.
     */
    protected string $primaryKey = 'id';

    /**
     * Whether this record uses UUIDs for the primary key.
     * Set to `false` in child class for auto-increment integer IDs.
     */
    protected bool $useUuid = true;

    /**
     * Columns (other than `id`) that store UUIDs as BINARY(16) in the database.
     *
     * @var string[]
     */
    protected array $uuidColumns = [];

    /**
     * Internal data storage for all columns.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Type casting definitions for automatic value conversion on read.
     *
     * Supported types: `bool`, `int`, `float`, `string`, `date` (DateTime)
     *
     * @var array<string, string>
     */
    protected array $casts = [];

    /**
     * Columns that are computed/virtual and should NOT be persisted to the database.
     *
     * @var string[]
     */
    protected array $computed = [];

    /**
     * Check if this record uses UUIDs for the primary key.
     */
    public function usesUuid(): bool
    {
        return $this->useUuid;
    }

    /**
     * Get the primary key column name.
     */
    public function primaryKey(): string
    {
        return $this->primaryKey;
    }

    /**
     * Check if a key refers to the primary key.
     */
    protected function isPrimaryKey(string $key): bool
    {
        return $key === 'id' || $key === $this->primaryKey;
    }

    /**
     * Get the list of UUID columns (excluding `id`).
     *
     * @return string[]
     */
    public function uuidColumns(): array
    {
        return $this->uuidColumns;
    }

    /**
     * Get the list of computed columns.
     *
     * @return string[]
     */
    public function computedColumns(): array
    {
        return $this->computed;
    }

    /**
     * Create a new record instance.
     *
     * @param array<string, mixed> $data Column values to populate
     */
    public function __construct(array $data = [])
    {
        $pk = $this->primaryKey();

        // Extract or generate the primary key
        if (array_key_exists($pk, $data) && $data[$pk] !== null && $data[$pk] !== '') {
            $this->id = $data[$pk];
            unset($data[$pk]);
        } elseif ($this->usesUuid()) {
            $this->id = $this->generateUuid();
        }

        // Populate $data, converting UUID columns from binary
        foreach ($data as $key => $value) {
            if (in_array($key, $this->uuidColumns(), true) && $value !== null) {
                $value = $this->binToUuid($value);
            }
            $this->data[$key] = $value;
        }
    }

    /**
     * Magic getter for property access.
     *
     * Retrieves values from $data with automatic type casting.
     *
     * @param string $key Column name
     * @return mixed The value (cast if applicable), or null if not set
     */
    public function __get(string $key)
    {
        if ($this->isPrimaryKey($key)) {
            return $this->id;
        }

        if (!array_key_exists($key, $this->data)) {
            return null;
        }

        $value = $this->data[$key];

        // Apply cast on read
        if ($value !== null && isset($this->casts[$key])) {
            $value = match ($this->casts[$key]) {
                'date'   => is_string($value) ? new DateTime($value) : $value,
                'int'    => (int)$value,
                'float'  => (float)$value,
                'bool'   => (bool)$value,
                'string' => (string)$value,
                default  => $value,
            };
        }

        return $value;
    }

    /**
     * Magic setter for property access.
     *
     * Stores values in $data.
     *
     * @param string $key   Column name
     * @param mixed  $value Value to set
     */
    public function __set(string $key, $value)
    {
        if ($this->isPrimaryKey($key)) {
            $this->id = $value;
            return;
        }
        $this->data[$key] = $value;
    }

    /**
     * Magic isset check for property access.
     *
     * @param string $key Column name
     * @return bool True if the key exists in $data (even if null)
     */
    public function __isset(string $key): bool
    {
        if ($this->isPrimaryKey($key)) {
            return $this->id !== null;
        }
        return array_key_exists($key, $this->data);
    }

    /**
     * Magic unset for property access.
     *
     * @param string $key Column name to unset
     */
    public function __unset(string $key)
    {
        if ($this->isPrimaryKey($key)) {
            $this->id = null;
            return;
        }
        unset($this->data[$key]);
    }

    /**
     * ArrayAccess: Check if offset exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset($offset);
    }

    /**
     * ArrayAccess: Get value at offset.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->__get($offset);
    }

    /**
     * ArrayAccess: Set value at offset.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->__set($offset, $value);
    }

    /**
     * ArrayAccess: Unset value at offset.
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->__unset($offset);
    }

    /**
     * Convert the record to an associative array.
     *
     * Returns all data including ID. Only includes columns that were
     * explicitly set (either from DB hydration or via setters).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [$this->primaryKey() => $this->id] + $this->data;
    }

    /**
     * Convert the record to an array for database persistence.
     *
     * Excludes computed columns that should not be saved to the database.
     *
     * @return array<string, mixed>
     */
    public function toPersistableArray(): array
    {
        $data = $this->toArray();

        foreach ($this->computedColumns() as $col) {
            unset($data[$col]);
        }

        return $data;
    }

    /**
     * JsonSerializable implementation for JSON encoding.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Generate a new UUIDv7.
     */
    private function generateUuid(): string
    {
        return Uuid::uuid7()->toString();
    }

    /**
     * Convert binary UUID to string format.
     */
    private function binToUuid(string $value): string
    {
        if (Uuid::isValid($value)) {
            return $value;
        }

        return Uuid::fromBytes($value)->toString();
    }
}
