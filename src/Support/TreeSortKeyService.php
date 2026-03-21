<?php

declare(strict_types=1);

namespace Handlr\Support;

use Handlr\Database\DbInterface;
use RuntimeException;

class TreeSortKeyService
{
    public function __construct(
        private DbInterface $db,
        private MaxSortKeyQuery $query,
    ) {}

    public function rootKey(): string
    {
        return '00';
    }

    /**
     * Compute the next child sort_key under a given parent within a scoped table.
     *
     * Must be called inside a transaction (caller's responsibility).
     * Uses SELECT ... FOR UPDATE to prevent concurrent sibling collisions.
     *
     * @param string $tableName   e.g. 'dialog_nodes'
     * @param string $scopeColumn e.g. 'encounter_id' (BINARY(16) UUID column)
     * @param string $scopeId     UUID string
     * @param string $parentSortKey e.g. '00' or '00.01'
     * @return string e.g. '00.00' or '00.02'
     * @throws RuntimeException if not in transaction or sibling overflow (>255)
     */
    public function nextChildKey(
        string $tableName,
        string $scopeColumn,
        string $scopeId,
        string $parentSortKey,
    ): string {
        if (!$this->db->inTransaction()) {
            throw new RuntimeException('TreeSortKeyService::nextChildKey() must be called inside a transaction.');
        }

        $parentDepth = $parentSortKey === '' ? 0 : substr_count($parentSortKey, '.') + 1;
        $childDepth = $parentDepth + 1;

        $row = $this->query->forParent($tableName, $scopeColumn, $scopeId, $parentSortKey, $childDepth);

        if ($row === null || $row['max_key'] === null) {
            return $parentSortKey . '.00';
        }

        $maxKey = $row['max_key'];
        $lastSegment = substr($maxKey, strrpos($maxKey, '.') + 1);
        $nextVal = hexdec($lastSegment) + 1;

        if ($nextVal > 255) {
            throw new RuntimeException("Sort key sibling overflow: parent '{$parentSortKey}' already has 256 children.");
        }

        return $parentSortKey . '.' . str_pad(dechex($nextVal), 2, '0', STR_PAD_LEFT);
    }
}
