<?php

declare(strict_types=1);

namespace Handlr\Api;

use Handlr\Database\Record;

/**
 * API response presenter for building consistent JSON responses.
 *
 * Provides a fluent interface for constructing API responses with data, metadata,
 * and status information. Supports both single records and collections.
 *
 * @example Single record response:
 *     $presenter->withSingleData(['level' => 12, 'xp' => 1240])->success();
 *     // Returns: ['status' => 'success', 'data' => ['level' => 12, 'xp' => 1240]]
 *
 * @example Collection response:
 *     $presenter->withData($records)->withMeta(['total' => 100])->success();
 *     // Returns: ['status' => 'success', 'data' => [...], 'meta' => ['total' => 100]]
 *
 * @example From a Record instance:
 *     $presenter->fromRecord($user)->only(['id', 'name'])->success();
 *     // Returns: ['status' => 'success', 'data' => ['id' => '...', 'name' => '...']]
 */
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

    /**
     * Set collection data for list responses.
     *
     * Use this for returning multiple items (e.g., a list of records).
     * Each item will be processed through toOutput() for column filtering.
     *
     * @param array<int, mixed> $data Array of items (records, arrays, or objects)
     * @return static Fluent interface
     *
     * @example
     *     $presenter->withData($userRecords)->success();
     *     $presenter->withData([['id' => 1], ['id' => 2]])->success();
     *
     * @see withSingleData() For single record responses
     */
    public function withData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Set metadata to include in the response.
     *
     * Commonly used for pagination info, totals, flags, or other contextual data.
     * Metadata appears in the 'meta' key of the response.
     *
     * @param array<string, mixed> $meta Key-value pairs of metadata
     * @return static Fluent interface
     *
     * @example
     *     $presenter->withData($items)->withMeta([
     *         'total' => 100,
     *         'page' => 1,
     *         'per_page' => 20,
     *         'has_more' => true,
     *     ])->success();
     */
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

    /**
     * Set single record data from an array.
     *
     * Use this for returning a single item (not a list) when you have
     * an array rather than a Record instance.
     *
     * @param array<string, mixed> $data Single record as associative array
     * @return static Fluent interface
     *
     * @example
     *     $presenter->withSingleData(['level' => 12, 'xp' => 1240])->success();
     *     // Returns: ['status' => 'success', 'data' => ['level' => 12, 'xp' => 1240]]
     *
     * @see withData() For collection/list responses
     * @see fromRecord() For Record instances
     */
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

    /**
     * Build and return a success response.
     *
     * @param string|null $message Optional success message
     * @return array{status: 'success', message?: string, data?: mixed, meta?: array<string, mixed>}
     *
     * @example
     *     $presenter->success(); // ['status' => 'success']
     *     $presenter->success('Created successfully'); // ['status' => 'success', 'message' => '...']
     */
    public function success(?string $message = null): array
    {
        return $this->buildResponse('success', $message);
    }

    /**
     * Build and return a validation error response.
     *
     * Use for form validation failures with field-specific error messages.
     *
     * @param string|null $message General error message
     * @param array<string, string> $fieldErrors Map of field names to error messages
     * @return array{status: 'error', message?: string, errors?: array<string, string>}
     *
     * @example
     *     $presenter->validationError('Validation failed', [
     *         'email' => 'Invalid email format',
     *         'password' => 'Password must be at least 8 characters',
     *     ]);
     */
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

    /**
     * Build and return an invariant/business logic error response.
     *
     * Use for errors that aren't field-specific validation issues,
     * such as "Record not found" or "Insufficient permissions".
     *
     * @param string|null $message Error message
     * @return array{status: 'error', message?: string}
     *
     * @example
     *     $presenter->invariantError('Series must have at least one collection');
     */
    public function invariantError(?string $message = null): array
    {
        $response = ['status' => 'error'];

        if ($message !== null) {
            $response['message'] = $message;
        }

        return $response;
    }

    /**
     * Build and return a warning response.
     *
     * Use for non-fatal issues that the client should be aware of.
     *
     * @param string|null $message Warning message
     * @return array{status: 'warning', message?: string, data?: mixed, meta?: array<string, mixed>}
     *
     * @example
     *     $presenter->withData($results)->warning('Some items could not be processed');
     */
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
