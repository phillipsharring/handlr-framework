<?php

declare(strict_types=1);

namespace Handlr\Database;

use DateTime;
use JsonSerializable;
use Ramsey\Uuid\Uuid;

abstract class Record implements JsonSerializable
{
    // int or UUID (string)
    public int|string|null $id = null;
    protected bool $useUuid = true;

    protected array $data = [];
    protected array $casts = [];

    public function usesUuid(): bool
    {
        return $this->useUuid;
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
            $this->data[$key] = $value;
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
                'date' => is_string($value) ? new DateTime($value) : null,
                'int' => (int)$value,
                'float' => (float)$value,
                'bool' => (bool)$value,
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
        return Uuid::uuid4()->toString();
    }
}
