<?php

declare(strict_types=1);

namespace Handlr\Ab\Domain;

use Handlr\Database\Record;

/**
 * @property int $id
 * @property int $ab_test_id
 * @property string $variant
 * @property string $session_id
 * @property string $event
 * @property string $event_date
 * @property int $count
 * @property string|null $created_at
 */
class AbEventRecord extends Record
{
    protected bool $useUuid = false;

    protected array $casts = [
        'ab_test_id' => 'int',
        'count' => 'int',
        'created_at' => 'date',
    ];
}
