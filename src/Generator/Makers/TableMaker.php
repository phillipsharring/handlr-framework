<?php

declare(strict_types=1);

namespace Handlr\Generator\Makers;

use Handlr\Generator\GeneratedFile;
use Handlr\Generator\MakerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Generates a Table class for database table access.
 *
 * Creates a Table gateway class extending Handlr\Database\Table with
 * the required $tableName and $recordClass properties.
 *
 * ## Usage
 *
 * ```bash
 * # Create a table (assumes UsersRecord exists)
 * php make.php make:table Users
 *
 * # Create in a subdirectory
 * php make.php make:table Auth/Users
 *
 * # Specify a custom record class
 * php make.php make:table Users --record=UserRecord
 * ```
 *
 * ## Output
 *
 * Creates `app/{path}/{Name}Table.php` with a starter Table class.
 */
class TableMaker implements MakerInterface
{
    public function name(): string
    {
        return 'table';
    }

    public function description(): string
    {
        return 'Generate a new Table class for database table access';
    }

    public function arguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'Table name (e.g., "Users" or "Auth/Users")'],
        ];
    }

    public function options(): array
    {
        return [
            ['record', 'r', InputOption::VALUE_REQUIRED, 'Custom record class name (defaults to {Name}Record)'],
        ];
    }

    public function generate(InputInterface $input, string $stubsPath): array
    {
        $name = $input->getArgument('name');
        $customRecord = $input->getOption('record');

        // Parse path and class name
        $name = str_replace('\\', '/', $name);
        $parts = explode('/', $name);
        $className = array_pop($parts);

        // Ensure className ends with Table for consistency, but don't double it
        if (!str_ends_with($className, 'Table')) {
            $baseClassName = $className;
            $className .= 'Table';
        } else {
            $baseClassName = substr($className, 0, -5); // Remove "Table" suffix
        }

        // Build namespace and path
        $subPath = implode('/', $parts);
        $namespace = 'App' . ($subPath ? '\\' . str_replace('/', '\\', $subPath) : '');
        $baseDir = getcwd() . '/app' . ($subPath ? '/' . $subPath : '');

        // Determine record class
        $recordClass = $customRecord ?: $baseClassName . 'Record';
        if (!str_ends_with($recordClass, 'Record')) {
            $recordClass .= 'Record';
        }

        // Convert class name to snake_case table name
        $tableName = $this->toSnakeCase($baseClassName);

        // Build replacements
        $replacements = [
            '{{namespace}}' => $namespace,
            '{{className}}' => $className,
            '{{tableName}}' => $tableName,
            '{{recordClass}}' => $recordClass,
        ];

        $content = file_get_contents($stubsPath . '/Table.stub');
        $content = str_replace(array_keys($replacements), array_values($replacements), $content);

        return [
            new GeneratedFile("{$baseDir}/{$className}.php", $content),
        ];
    }

    private function toSnakeCase(string $input): string
    {
        // Insert underscore before uppercase letters, then lowercase
        $result = preg_replace('/([a-z])([A-Z])/', '$1_$2', $input);
        return strtolower($result);
    }
}
