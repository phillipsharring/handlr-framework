<?php

declare(strict_types=1);

namespace Handlr\Api;

use Handlr\Database\Record;

class Presenter implements PresenterInterface
{
    /** @var array<int, mixed> Collection of items for list responses */
    protected array $data = [];

    /** @var array<string, mixed>|null Single record data for single-item responses */
    protected ?array $singleRecord = null;

    /** @var array<string, mixed> Metadata to include in the response (pagination, totals, etc.) */
    protected array $meta = [];

    /** @var string[] Whitelist of columns to include in output (excludes all others) */
    protected array $onlyColumns = [];

    /** @var string[] Blacklist of columns to exclude from output */
    protected array $withoutColumns = [];

    /** {@inheritDoc} */
    public function withData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    /** {@inheritDoc} */
    public function withMeta(array $meta): static
    {
        $this->meta = $meta;
        return $this;
    }

    /**
     * Set single record data from a Record instance.
     *
     * Converts the Record to an array using toArray() if available.
     * Use this when returning a single database record.
     *
     * @param Record $record The record instance to present
     * @return static Fluent interface
     *
     * @example
     *     $presenter->fromRecord($user)->without(['password'])->success();
     *
     * @see withSingleData() For presenting a plain array as a single record
     */
    public function fromRecord(Record $record): static
    {
        $this->singleRecord = method_exists($record, 'toArray') ? $record->toArray() : (array)$record;
        return $this;
    }

    /** {@inheritDoc} */
    public function withSingleData(array $data): static
    {
        $this->singleRecord = $data;
        return $this;
    }


    /**
     * Whitelist specific columns to include in output.
     *
     * Only the specified columns will appear in the response data.
     * Mutually exclusive with without() - only() takes precedence.
     *
     * @param string[] $cols Column names to include
     * @return static Fluent interface
     *
     * @example
     *     $presenter->fromRecord($user)->only(['id', 'name', 'email'])->success();
     */
    public function only(array $cols): static
    {
        $this->onlyColumns = $cols;
        return $this;
    }

    /**
     * Blacklist specific columns to exclude from output.
     *
     * All columns except the specified ones will appear in the response data.
     * Ignored if only() is also set.
     *
     * @param string[] $cols Column names to exclude
     * @return static Fluent interface
     *
     * @example
     *     $presenter->fromRecord($user)->without(['password', 'api_token'])->success();
     */
    public function without(array $cols): static
    {
        $this->withoutColumns = $cols;
        return $this;
    }

    /** {@inheritDoc} */
    public function success(?string $message = null): array
    {
        return $this->buildResponse('success', $message);
    }

    /** {@inheritDoc} */
    public function validationError(?string $message = null, array $fieldErrors = []): array
    {
        $response = ['status' => 'error'];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if (!empty($fieldErrors)) {
            $response['errors'] = $fieldErrors;
        }

        return $response;
    }

    /** {@inheritDoc} */
    public function invariantError(?string $message = null): array
    {
        $response = ['status' => 'error'];

        if ($message !== null) {
            $response['message'] = $message;
        }

        return $response;
    }

    /** {@inheritDoc} */
    public function warning(?string $message = null): array
    {
        return $this->buildResponse('warning', $message);
    }

    /**
     * Build the response array with status, message, data, and meta.
     *
     * @param string $status Response status ('success', 'warning', 'error')
     * @param string|null $message Optional message
     * @return array<string, mixed>
     */
    protected function buildResponse(string $status, ?string $message): array
    {
        $response = ['status' => $status];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($this->singleRecord !== null) {
            $response['data'] = $this->prepareSingleRecord();
        } elseif (!empty($this->data)) {
            $response['data'] = $this->prepareData();
        }

        if (!empty($this->meta)) {
            $response['meta'] = $this->meta;
        }

        return $response;
    }

    /**
     * Prepare collection data by processing each item through toOutput().
     *
     * @return array<int, array<string, mixed>>
     */
    protected function prepareData(): array
    {
        return array_map(fn($item) => $this->toOutput($item), $this->data);
    }

    /**
     * Prepare single record data through toOutput().
     *
     * @return array<string, mixed>
     */
    protected function prepareSingleRecord(): array
    {
        return $this->toOutput($this->singleRecord);
    }

    /**
     * Convert an item to output array, applying column filters.
     *
     * Handles arrays, Records, and objects. Applies only() or without()
     * column filtering if configured.
     *
     * @param mixed $item The item to convert
     * @return array<string, mixed>
     */
    protected function toOutput(mixed $item): array
    {
        if (!empty($this->onlyColumns)) {
            $out = [];
            foreach ($this->onlyColumns as $col) {
                $out[$col] = is_array($item) ? ($item[$col] ?? null) : ($item->$col ?? null);
            }
            return $out;
        }

        if (!empty($this->withoutColumns)) {
            $arr = is_array($item)
                ? $item
                : (method_exists($item, 'toArray') ? $item->toArray() : (array) $item);

            return array_diff_key($arr, array_flip($this->withoutColumns));
        }

        if (is_array($item)) {
            return $item;
        }

        return method_exists($item, 'toArray') ? $item->toArray() : (array) $item;
    }
}
