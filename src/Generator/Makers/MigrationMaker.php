<?php

declare(strict_types=1);

namespace Handlr\Generator\Makers;

use Handlr\Generator\GeneratedFile;
use Handlr\Generator\MakerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Generates database migration files.
 *
 * Creates timestamped migration files in the `migrations/` directory
 * with up() and down() methods for schema changes.
 *
 * ## Usage
 *
 * ```bash
 * php make.php make:migration CreateUsersTable
 * php make.php make:migration add_email_to_users
 * ```
 *
 * ## Output
 *
 * Creates: `migrations/20250203123456_create_users_table.php`
 */
class MigrationMaker implements MakerInterface
{
    public function name(): string
    {
        return 'migration';
    }

    public function description(): string
    {
        return 'Create a new database migration file';
    }

    public function arguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The migration name (e.g., CreateUsersTable, add_index_to_posts)'],
        ];
    }

    public function options(): array
    {
        return [];
    }

    public function generate(InputInterface $input, string $stubsPath): array
    {
        $name = trim($input->getArgument('name'));
        $timestamp = date('YmdHis');

        // Convert to StudlyCase for class name
        $studly = $this->toStudlyCase($name);
        $className = "Migration_{$timestamp}_{$studly}";

        // Convert to snake_case for filename
        $snakeName = $this->toSnakeCase($name);
        $filename = "{$timestamp}_{$snakeName}.php";
        $path = getcwd() . '/migrations/' . $filename;

        $stub = file_get_contents($stubsPath . '/migration.stub');
        $content = str_replace('{{className}}', $className, $stub);

        return [
            new GeneratedFile($path, $content),
        ];
    }

    private function toStudlyCase(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', strtolower($value))));
    }

    private function toSnakeCase(string $value): string
    {
        // First convert StudlyCase to snake_case
        $snake = preg_replace('/([a-z])([A-Z])/', '$1_$2', $value);
        // Then normalize any existing separators and lowercase
        return strtolower(preg_replace('/[\s-]+/', '_', $snake));
    }
}
