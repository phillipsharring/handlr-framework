<?php

declare(strict_types=1);

namespace Handlr\Database;

use ArrayAccess;
use DateTime;
use JsonSerializable;
use Ramsey\Uuid\Uuid;

abstract class Record implements JsonSerializable, ArrayAccess
{
    // int or UUID (string)
    public int|string|null $id = null;
    protected bool $useUuid = true;

    /**
     * Columns (other than `id`) that should be treated as UUIDs in code (string)
     * and stored in the DB as BINARY(16).
     *
     * Example in a child record:
     *   protected array $uuidColumns = ['user_id', 'series_id'];
     *
     * @var string[]
     */
    protected array $uuidColumns = [];

    protected array $data = [];
    protected array $casts = [];

    public function usesUuid(): bool
    {
        return $this->useUuid;
    }

    /**
     * @return string[]
     */
    public function uuidColumns(): array
    {
        return $this->uuidColumns;
    }

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
            if (in_array($key, $this->uuidColumns(), true)) {
                $value = $this->binToUuid($value);
            }

            $this->data[$key] = $value;

            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

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

    public function __set(string $key, $value)
    {
        if ($key === 'id') {
            $this->id = $value;
            return;
        }
        if (property_exists($this, $key)) {
            $this->$key = $value;
            return;
        }
        $this->data[$key] = $value;
    }

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

    public function offsetExists(mixed $offset): bool {
        return $this->__isset($offset);
    }

    public function offsetGet(mixed $offset): mixed {
        return $this->__get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void {
        $this->__set($offset, $value);
    }

    public function offsetUnset(mixed $offset): void {
        $this->__unset($offset);
    }

    public function toArray(): array
    {
        return ['id' => $this->id] + $this->data;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function generateUuid(): string
    {
        // UUIDv7 is time-ordered (better index locality than v4) while remaining globally unique.
        return Uuid::uuid7()->toString();
    }

    private function binToUuid(string $value): string
    {
        if (Uuid::isValid($value)) {
            // it is probably already a uuid
            return $value;
        }

        // Accept raw 16-byte binary string from BINARY(16) columns.
        return Uuid::fromBytes($value)->toString();
    }
}
