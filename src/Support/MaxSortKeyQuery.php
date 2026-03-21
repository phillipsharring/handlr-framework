<?php

declare(strict_types=1);

namespace Handlr\Support;

use Handlr\Database\Query;

class MaxSortKeyQuery extends Query
{
    /**
     * Find the maximum sort_key among children of a parent at a specific depth.
     *
     * Uses FOR UPDATE to prevent concurrent sibling collisions.
     */
    public function forParent(
        string $tableName,
        string $scopeColumn,
        string $scopeId,
        string $parentSortKey,
        int $childDepth,
    ): ?array {
        $sql = <<<SQL
            SELECT MAX(`sort_key`) AS `max_key`
            FROM `{$tableName}`
            WHERE `{$scopeColumn}` = ?
              AND `sort_key` LIKE ?
              AND (LENGTH(`sort_key`) - LENGTH(REPLACE(`sort_key`, '.', ''))) = ?
            FOR UPDATE
        SQL;

        $likePattern = $parentSortKey . '.%';

        return $this->row($sql, [$this->uuidToBin($scopeId), $likePattern, $childDepth]);
    }
}
