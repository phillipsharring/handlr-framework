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
 * Extend this class to create typed record objects that map to database rows.
 * Records support automatic UUID generation, type casting, computed columns,
 * and seamless JSON serialization.
 *
 * ## Basic usage
 *
 * ```php
 * class UserRecord extends Record
 * {
 *     public string $name;
 *     public string $email;
 *     public ?string $bio = null;
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
 * Define `$casts` to automatically convert values when accessed:
 *
 * ```php
 * class PostRecord extends Record
 * {
 *     public string $title;
 *     public bool $is_published;
 *     public int $view_count;
 *
 *     protected array $casts = [
 *         'is_published' => 'bool',     // Cast to boolean
 *         'view_count' => 'int',        // Cast to integer
 *         'rating' => 'float',          // Cast to float
 *         'published_at' => 'date',     // Cast to DateTime object
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
 * class CommentRecord extends Record
 * {
 *     public string $user_id;     // UUID string in code
 *     public string $post_id;     // UUID string in code
 *     public string $content;
 *
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
 * class UserRecord extends Record
 * {
 *     public string $first_name;
 *     public string $last_name;
 *     public ?string $full_name = null;  // Computed in SQL or PHP
 *
 *     protected array $computed = ['full_name'];
 *
 *     public function toArray(): array
 *     {
 *         $data = parent::toArray();
 *         $data['full_name'] = "{$this->first_name} {$this->last_name}";
 *         return $data;
 *     }
 * }
 * ```
 *
 * ## Array and property access
 *
 * Records support both property and array access:
 *
 * ```php
 * $user->name = 'John';      // Property access
 * $user['name'] = 'John';    // Array access (equivalent)
 *
 * echo $user->name;          // Property access
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
     * Primary key - integer for auto-increment tables, string UUID for UUID tables.
     */
    public int|string|null $id = null;

    /**
     * Whether this record uses UUIDs for the primary key.
     * Set to `false` in child class for auto-increment integer IDs.
     */
    protected bool $useUuid = true;

    /**
     * Columns (other than `id`) that store UUIDs as BINARY(16) in the database.
     *
     * These columns are automatically converted between UUID strings (in code)
     * and binary (in database). Typically used for foreign key references to
     * other UUID-based tables.
     *
     * ```php
     * protected array $uuidColumns = ['user_id', 'organization_id'];
     * ```
     *
     * @var string[]
     */
    protected array $uuidColumns = [];

    /**
     * Internal data storage for non-property columns.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Type casting definitions for automatic value conversion.
     *
     * Supported types: `bool`, `int`, `float`, `string`, `date` (DateTime)
     *
     * ```php
     * protected array $casts = [
     *     'is_active' => 'bool',
     *     'view_count' => 'int',
     *     'price' => 'float',
     *     'created_at' => 'date',
     * ];
     * ```
     *
     * @var array<string, string>
     */
    protected array $casts = [];

    /**
     * Columns that are computed/virtual and should NOT be persisted to the database.
     *
     * These columns are included in `toArray()` for JSON responses and presentation,
     * but are automatically excluded by `toPersistableArray()` during insert/update.
     * Use for derived values, aggregates from JOINs, or calculated fields.
     *
     * ```php
     * protected array $computed = ['full_name', 'comment_count', 'is_overdue'];
     * ```
     *
     * @var string[]
     */
    protected array $computed = [];

    /**
     * Check if this record uses UUIDs for the primary key.
     *
     * @return bool True if using UUIDs, false for auto-increment integers
     */
    public function usesUuid(): bool
    {
        return $this->useUuid;
    }

    /**
     * Get the list of UUID columns (excluding `id`).
     *
     * Used by the Table class to convert between UUID strings and BINARY(16).
     *
     * @return string[] Column names that store UUIDs
     */
    public function uuidColumns(): array
    {
        return $this->uuidColumns;
    }

    /**
     * Get the list of computed columns.
     *
     * Used by the Table class to exclude these from database persistence.
     *
     * @return string[] Column names that are computed/virtual
     */
    public function computedColumns(): array
    {
        return $this->computed;
    }

    /**
     * Create a new record instance.
     *
     * When creating a new record with UUID enabled and no ID provided,
     * a UUIDv7 is automatically generated. When hydrating from a database
     * row, pass the row data directly.
     *
     * ```php
     * // New record (UUID auto-generated)
     * $user = new UserRecord(['name' => 'John', 'email' => 'john@example.com']);
     *
     * // Hydrate from database row
     * $user = new UserRecord($row);
     *
     * // With explicit ID (for updates or non-UUID records)
     * $user = new UserRecord(['id' => 123, 'name' => 'John']);
     * ```
     *
     * @param array<string, mixed> $data Column values to populate
     */
    public function __construct(array $data = [])
    {
        // ID is stored on the public property, never inside $this->data.
        if (array_key_exists('id', $data) && $data['id'] !== null && $data['id'] !== '') {
            $this->id = $data['id'];
            unset($data['id']);
        } elseif ($this->usesUuid()) {
            // If UUIDs are enabled and no id is provided, assume this is a new record.
            $this->id = $this->generateUuid();
        }

        foreach ($data as $key => $value) {
            if (in_array($key, $this->uuidColumns(), true) && $value !== null) {
                $value = $this->binToUuid($value);
            }

            // Apply bool cast before assigning to typed properties
            if (isset($this->casts[$key]) && $this->casts[$key] === 'bool') {
                $value = (bool)$value;
            }

            $this->data[$key] = $value;

            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Magic getter for property access.
     *
     * Retrieves values with automatic type casting if defined in `$casts`.
     * Checks typed properties first, then falls back to internal `$data` array.
     *
     * @param string $key Column/property name
     *
     * @return mixed The value (cast if applicable), or null if not set
     */
    public function __get(string $key)
    {
        if ($key === 'id') {
            return $this->id;
        }

        $value = null;
        if (property_exists($this, $key)) {
            $value = $this->$key;
        } elseif (isset($this->data[$key])) {
            $value = $this->data[$key];
        }

        // Cast on read if a cast is declared. (Don't gate on truthiness; values like 0/false should still cast.)
        if ($value !== null && isset($this->casts[$key])) {
            $castType = $this->casts[$key];
            $value = match ($castType) {
                'date'   => is_string($value) ? new DateTime($value) : null,
                'int'    => (int)$value,
                'float'  => (float)$value,
                'bool'   => (bool)$value,
                'string' => (string)$value,
            };
        }

        return $value ?? null;
    }

    /**
     * Magic setter for property access.
     *
     * Sets values on both typed properties (if they exist) and the internal
     * `$data` array. The `id` property is handled specially on the public property.
     *
     * @param string $key   Column/property name
     * @param mixed  $value Value to set
     */
    public function __set(string $key, $value)
    {
        if ($key === 'id') {
            $this->id = $value;
            return;
        }
        if (property_exists($this, $key)) {
            $this->$key = $value;
        }
        $this->data[$key] = $value;
    }

    /**
     * Magic isset check for property access.
     *
     * @param string $key Column/property name
     *
     * @return bool True if the property is set and not null
     */
    public function __isset(string $key): bool
    {
        if ($key === 'id') {
            return isset($this->id);
        }
        if (property_exists($this, $key)) {
            return isset($this->$key);
        }
        return isset($this->data[$key]);
    }

    /**
     * Magic unset for property access.
     *
     * @param string $key Column/property name to unset
     */
    public function __unset(string $key)
    {
        if ($key === 'id') {
            $this->id = null;
            return;
        }
        if (property_exists($this, $key)) {
            unset($this->$key);
            return;
        }
        unset($this->data[$key]);
    }

    /**
     * ArrayAccess: Check if offset exists.
     *
     * @param mixed $offset Column/property name
     */
    public function offsetExists(mixed $offset): bool {
        return $this->__isset($offset);
    }

    /**
     * ArrayAccess: Get value at offset.
     *
     * @param mixed $offset Column/property name
     */
    public function offsetGet(mixed $offset): mixed {
        return $this->__get($offset);
    }

    /**
     * ArrayAccess: Set value at offset.
     *
     * @param mixed $offset Column/property name
     * @param mixed $value  Value to set
     */
    public function offsetSet(mixed $offset, mixed $value): void {
        $this->__set($offset, $value);
    }

    /**
     * ArrayAccess: Unset value at offset.
     *
     * @param mixed $offset Column/property name
     */
    public function offsetUnset(mixed $offset): void {
        $this->__unset($offset);
    }

    /**
     * Convert the record to an associative array.
     *
     * Includes all columns (including computed) for presentation/API responses.
     * Override in child classes to add computed values or transform data.
     *
     * ```php
     * $user = new UserRecord(['name' => 'John', 'email' => 'john@example.com']);
     * $array = $user->toArray();
     * // ['id' => '...', 'name' => 'John', 'email' => 'john@example.com']
     * ```
     *
     * @return array<string, mixed> All record data including ID
     */
    public function toArray(): array
    {
        return ['id' => $this->id] + $this->data;
    }

    /**
     * Convert the record to an array for database persistence.
     *
     * Excludes computed columns that should not be saved to the database.
     * Used internally by the Table class during insert/update operations.
     *
     * If you override `toArray()` to add computed fields, those will be
     * automatically excluded here if listed in `$computed`. Override this
     * method only if you need custom persistence logic.
     *
     * ```php
     * // Given a record with computed columns:
     * protected array $computed = ['full_name', 'comment_count'];
     *
     * $data = $record->toPersistableArray();
     * // 'full_name' and 'comment_count' are excluded
     * ```
     *
     * @return array<string, mixed> Data suitable for database insert/update
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
     * Allows records to be passed directly to `json_encode()`:
     *
     * ```php
     * $user = new UserRecord(['name' => 'John']);
     * echo json_encode($user);
     * // {"id":"...","name":"John"}
     *
     * // In API responses:
     * return new JsonResponse($user);
     * ```
     *
     * @return array<string, mixed> Data to be JSON encoded
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Generate a new UUIDv7.
     *
     * UUIDv7 is time-ordered for better database index locality compared to
     * random UUIDv4, while remaining globally unique.
     *
     * @return string UUID string (e.g., "0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6b")
     */
    private function generateUuid(): string
    {
        return Uuid::uuid7()->toString();
    }

    /**
     * Convert binary UUID to string format.
     *
     * Handles both raw 16-byte binary from BINARY(16) columns and already-valid
     * UUID strings (returns as-is).
     *
     * @param string $value Binary UUID or UUID string
     *
     * @return string UUID string format
     */
    private function binToUuid(string $value): string
    {
        if (Uuid::isValid($value)) {
            return $value;
        }

        return Uuid::fromBytes($value)->toString();
    }
}
