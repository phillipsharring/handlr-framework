<?php

declare(strict_types=1);

namespace Handlr\Api;

use Handlr\Database\Record;

class Presenter implements PresenterInterface
{
    protected array $data = [];
    protected ?array $singleRecord = null;
    protected array $meta = [];
    protected array $onlyColumns = [];
    protected array $withoutColumns = [];

    public function withData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function withMeta(array $meta): static
    {
        $this->meta = $meta;
        return $this;
    }

    public function fromRecord(Record $record): static
    {
        $this->singleRecord = method_exists($record, 'toArray') ? $record->toArray() : (array)$record;
        return $this;
    }

    public function withSingleData(array $data): static
    {
        $this->singleRecord = $data;
        return $this;
    }


    public function only(array $cols): static
    {
        $this->onlyColumns = $cols;
        return $this;
    }

    public function without(array $cols): static
    {
        $this->withoutColumns = $cols;
        return $this;
    }

    public function success(?string $message = null): array
    {
        return $this->buildResponse('success', $message);
    }

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

    public function invariantError(?string $message = null): array
    {
        $response = ['status' => 'error'];

        if ($message !== null) {
            $response['message'] = $message;
        }

        return $response;
    }

    public function warning(?string $message = null): array
    {
        return $this->buildResponse('warning', $message);
    }

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

    protected function prepareData(): array
    {
        return array_map(fn($item) => $this->toOutput($item), $this->data);
    }

    protected function prepareSingleRecord(): array
    {
        return $this->toOutput($this->singleRecord);
    }

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
