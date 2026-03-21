<?php

declare(strict_types=1);

namespace Handlr\Support;

use Handlr\Core\Request;

trait SortableList
{
    /**
     * Columns that may be sorted on.
     *
     * @return string[]
     */
    abstract protected function sortableColumns(): array;

    /**
     * Default sort when no valid sort param is provided.
     *
     * @return array{0: string, 1: string}  e.g. ['name', 'asc']
     */
    abstract protected function defaultSort(): array;

    /**
     * Extract a validated [column, direction] pair from query params.
     *
     * @return array{0: string, 1: string}
     */
    protected function extractSort(Request $request): array
    {
        $params = $request->getQueryParams();
        $col = $params['sort'] ?? null;
        $dir = $params['dir'] ?? null;

        if ($col && in_array($col, $this->sortableColumns(), true)) {
            $dir = ($dir === 'desc') ? 'desc' : 'asc';
            return [$col, $dir];
        }

        return $this->defaultSort();
    }

    /**
     * Build meta payload describing sort state for the frontend.
     *
     * @param array{0: string, 1: string} $currentSort
     */
    protected function tableSortsMeta(array $currentSort): array
    {
        $columns = [];
        foreach ($this->sortableColumns() as $col) {
            $columns[] = ['column' => $col];
        }

        return [
            'table_sorts' => [
                'columns' => $columns,
                'current' => ['column' => $currentSort[0], 'direction' => $currentSort[1]],
            ],
        ];
    }
}
