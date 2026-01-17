# Handlr Framework CLI Skills

This document describes the CLI scripts available in the Handlr framework. These scripts are located in `vendor/phillipsharring/handlr-framework/scripts/` and are run from the app root.

## a. Making a Migration

Create a new database migration file.

```bash
php vendor/phillipsharring/handlr-framework/scripts/make-migration.php <name>
```

**Example:**
```bash
php vendor/phillipsharring/handlr-framework/scripts/make-migration.php create_users_table
```

Creates: `migrations/20260116123456_create_users_table.php`

The generated file extends `Handlr\Database\Migrations\BaseMigration` with `up()` and `down()` methods.

---

## b. Running Migrations

Run or rollback database migrations.

```bash
# Run all pending migrations
php vendor/phillipsharring/handlr-framework/scripts/migrate.php up

# Run migrations one at a time (step mode)
php vendor/phillipsharring/handlr-framework/scripts/migrate.php up step

# Rollback the last batch
php vendor/phillipsharring/handlr-framework/scripts/migrate.php down

# Rollback multiple batches
php vendor/phillipsharring/handlr-framework/scripts/migrate.php rollback 3
```

---

## c. Making a Scaffold

Generate a feature scaffold with Input, Handler, Pipe, and Test files.

```bash
php vendor/phillipsharring/handlr-framework/scripts/make-scaffold.php <Name>
```

**Example:**
```bash
php vendor/phillipsharring/handlr-framework/scripts/make-scaffold.php GamePlay/CreateSeries
```

Creates in `app/GamePlay/CreateSeries/`:
- `CreateSeriesInput.php` - Input DTO implementing `HandlerInput`
- `CreateSeriesHandler.php` - Business logic implementing `Handler`
- `CreateSeriesPipe.php` - HTTP pipe connecting request to handler
- `CreateSeriesTest.php` - PHPUnit test

**Options:**
- `--no-pipe` - Skip Pipe generation
- `--event-only` - Only generate Input and Handler (for domain events)

---

## d. Running Seeders

Seed the database with test/sample data.

```bash
# Run all seeders
php vendor/phillipsharring/handlr-framework/scripts/seed.php

# Run a specific seeder file
php vendor/phillipsharring/handlr-framework/scripts/seed.php series
php vendor/phillipsharring/handlr-framework/scripts/seed.php pack_templates.php

# Truncate tables first, then seed (fresh start)
php vendor/phillipsharring/handlr-framework/scripts/seed.php --fresh

# Run a specific seeder with fresh (truncates only tables in that seeder)
php vendor/phillipsharring/handlr-framework/scripts/seed.php series --fresh
```

**Arguments:**
- `[file]` - Optional. Specific seeder file to run (e.g., `series` or `series.php`). The `.php` extension is added automatically if omitted.

**Options:**
- `--fresh`, `-f` - Truncate tables before seeding

Seed files live in the `seeds/` directory and return arrays keyed by Table class names.

---

## e. Making a Seeder

Create a new seeder stub file.

```bash
php vendor/phillipsharring/handlr-framework/scripts/make-seeder.php <name>
```

**Example:**
```bash
php vendor/phillipsharring/handlr-framework/scripts/make-seeder.php Series
php vendor/phillipsharring/handlr-framework/scripts/make-seeder.php user_packs
```

Creates: `seeds/series.php` or `seeds/user_packs.php`

The generated stub is a skeleton that needs to be filled in with:
1. The correct `use` statements for your Table classes
2. Actual seed data

---

# Creating Seeders with Real Data (Claude Workflow)

When asked to create a seeder for a specific Table class, follow this workflow:

## Step 1: Create the stub
```bash
php vendor/phillipsharring/handlr-framework/scripts/make-seeder.php <Name>
```

## Step 2: Find the Table class
Look in `app/` for the Table class (e.g., `SeriesTable`). The Table class has:
- `protected string $tableName` - the database table name
- `protected string $recordClass` - the Record class to use

## Step 3: Find the Record class
From the Table's `$recordClass` property, locate the Record class. Look for:
- Public properties (these are the fields)
- The `$uuidColumns` array (FK fields that are UUIDs)
- The `$casts` array (type hints for fields)

## Step 4: Generate fake data
Fill in the seeder with realistic fake data based on the Record's properties.

## Seed File Format

```php
<?php

declare(strict_types=1);

use App\Content\SeriesTable;
use App\Content\CollectionsTable;

return [
    SeriesTable::class => [
        [
            'name' => 'Epic Fantasy Series',
            'description' => 'A grand adventure spanning multiple volumes',
            'status' => 'active',
            '_relations' => [
                CollectionsTable::class => [
                    ['name' => 'Book One: The Beginning', 'order' => 1],
                    ['name' => 'Book Two: The Journey', 'order' => 2],
                ],
            ],
        ],
        [
            'name' => 'Sci-Fi Chronicles',
            'description' => 'Exploring the cosmos',
            'status' => 'active',
            '_relations' => [
                CollectionsTable::class => [
                    ['name' => 'Volume 1: First Contact', 'order' => 1],
                ],
            ],
        ],
    ],
];
```

## Key Points

- **Table class as key**: Use the fully-qualified Table class name as the array key
- **`_relations` array**: Nested records that belong to the parent
- **FK injection**: Parent IDs are automatically injected into child records (e.g., `series_id`)
- **FK naming**: Derived from parent's `$tableName` singularized + `_id` (e.g., `series` -> `series_id`, `users` -> `user_id`)

## Example: Creating a Seeder for SeriesTable

Given a request like "create a seeder for SeriesTable", do:

1. Run `make-seeder.php Series`
2. Read `app/Content/SeriesTable.php` to find `$recordClass`
3. Read the Record class (e.g., `app/Content/SeriesRecord.php`)
4. Extract properties like `name`, `description`, `status`, etc.
5. Edit `seeds/series.php` with:
   - Correct `use` statement for `SeriesTable`
   - 2-5 realistic sample records
   - Include `_relations` if there are related tables (check for FK properties like `series_id` in other Records)