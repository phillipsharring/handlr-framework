<?php

declare(strict_types=1);

namespace Handlr\Ab\Data;

use Handlr\Ab\Domain\AbEventRecord;
use Handlr\Database\Table;

class AbEventsTable extends Table
{
    protected string $tableName = 'ab_events';
    protected string $recordClass = AbEventRecord::class;

    /**
     * Insert or increment the count for an aggregated event row.
     * One row per (test, variant, session, event, date).
     */
    public function upsertEvent(int $abTestId, string $variant, string $sessionId, string $event): void
    {
        $sql = <<<'SQL'
            INSERT INTO `ab_events` (`ab_test_id`, `variant`, `session_id`, `event`, `event_date`, `count`)
            VALUES (?, ?, ?, ?, CURDATE(), 1)
            ON DUPLICATE KEY UPDATE `count` = `count` + 1
        SQL;

        $this->db->execute($sql, [$abTestId, $variant, $sessionId, $event]);
    }
}
