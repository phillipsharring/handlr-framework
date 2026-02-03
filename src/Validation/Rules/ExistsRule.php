<?php

declare(strict_types=1);

namespace Handlr\Validation\Rules;

use Handlr\Core\Kernel;
use Handlr\Database\Db;

/**
 * Validates that a value exists in a database table.
 *
 * Rule args (positional):
 * - First arg: table name
 * - Second arg: column name
 *
 * Usage: `'exists|users,id'`, `'exists|categories,slug'`
 */
class ExistsRule extends BaseRule
{
    private Db $db;

    public function __construct(public string $field)
    {
        parent::__construct($field);

        $this->db = Kernel::getContainer()->singleton(Db::class);
    }

    public function validate($value, array $ruleArgs = [], array $data = []): bool
    {
        [$table, $column] = $ruleArgs;
        $query = "SELECT COUNT(*) FROM $table WHERE $column = ?";
        $result = $this->db->execute($query, [$value])->fetchColumn();

        if ($result <= 0) {
            $this->errorMessage = "The %s '$value' does not exist in the system.";
            return false;
        }

        return true;
    }
}
