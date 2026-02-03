<?php

declare(strict_types=1);

namespace Handlr\Api;

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
 */
interface PresenterInterface
{
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
    public function withData(array $data): static;

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
     */
    public function withSingleData(array $data): static;

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
    public function withMeta(array $meta): static;

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
    public function success(?string $message = null): array;

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
    public function validationError(?string $message = null, array $fieldErrors = []): array;

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
    public function invariantError(?string $message = null): array;

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
    public function warning(?string $message = null): array;
}
