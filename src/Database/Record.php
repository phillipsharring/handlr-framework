<?php

declare(strict_types=1);

namespace Handlr\Database;

use Ramsey\Uuid\Uuid;

abstract class Record
{
    // int or UUID (string)
    public int|string|null $id = null;

    protected array $data = [];
    public bool $useUuid = true;

    public function __construct(array $data = [])
    {
        // ID is stored on the public property, never inside $this->data.
        if (array_key_exists('id', $data) && $data['id'] !== null && $data['id'] !== '') {
            $this->id = $data['id'];
            unset($data['id']);
        } elseif ($this->useUuid) {
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
        return $this->data[$key] ?? null;
    }

    public function __set(string $key, $value)
    {
        if ($key === 'id') {
            $this->id = $value;
            return;
        }
        $this->data[$key] = $value;
    }

    public function __isset(string $key): bool
    {
        if ($key === 'id') {
            return isset($this->id);
        }
        return isset($this->data[$key]);
    }

    public function __unset(string $key)
    {
        if ($key === 'id') {
            $this->id = null;
            return;
        }
        unset($this->data[$key]);
    }

    public function toArray(): array
    {
        return ['id' => $this->id] + $this->data;
    }

    private function generateUuid(): string
    {
        return Uuid::uuid4()->toString();
    }
}
