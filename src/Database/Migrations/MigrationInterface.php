<?php

declare(strict_types=1);

namespace Handlr\Database\Migrations;

/**
 * Contract for database migrations.
 *
 * Migrations modify the database schema. Each migration has an `up()` method
 * to apply changes and a `down()` method to reverse them.
 *
 * @see BaseMigration For a base class with helper methods
 */
interface MigrationInterface
{
    /** Apply the migration (create tables, add columns, etc.) */
    public function up(): void;

    /** Reverse the migration (drop tables, remove columns, etc.) */
    public function down(): void;
}
