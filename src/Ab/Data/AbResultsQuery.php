<?php

declare(strict_types=1);

namespace Handlr\Ab\Data;

use Handlr\Database\Query;

class AbResultsQuery extends Query
{
    /**
     * Get results for a single test: event counts per variant.
     */
    public function getResultsForTest(int $testId): array
    {
        $sql = <<<'SQL'
            SELECT
                `variant`,
                `event`,
                SUM(`count`) AS `count`,
                COUNT(DISTINCT `session_id`) AS `unique_sessions`
            FROM `ab_events`
            WHERE `ab_test_id` = ?
            GROUP BY `variant`, `event`
            ORDER BY `variant`, `event`
        SQL;

        return $this->rows($sql, [$testId]);
    }

    /**
     * Get summary for all tests: test info + total events per variant.
     */
    public function getAllTestSummaries(): array
    {
        $sql = <<<'SQL'
            SELECT
                `t`.`id`,
                `t`.`name`,
                `t`.`variants`,
                `t`.`status`,
                `t`.`created_at`,
                COALESCE(SUM(`e`.`count`), 0) AS `total_events`,
                COUNT(DISTINCT `e`.`session_id`) AS `unique_visitors`
            FROM `ab_tests` AS `t`
            LEFT JOIN `ab_events` AS `e`
              ON `e`.`ab_test_id` = `t`.`id`
            GROUP BY `t`.`id`
            ORDER BY `t`.`created_at` DESC
        SQL;

        return array_map(function ($row) {
            $row['total_events'] = (int) $row['total_events'];
            $row['unique_visitors'] = (int) $row['unique_visitors'];
            return $row;
        }, $this->rows($sql));
    }
}
