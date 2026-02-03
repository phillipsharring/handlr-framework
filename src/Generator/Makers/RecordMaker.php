<?php

declare(strict_types=1);

namespace Handlr\Generator\Makers;

use Handlr\Generator\GeneratedFile;
use Handlr\Generator\MakerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Generates a Record class for database row representation.
 *
 * Creates a typed Record class extending Handlr\Database\Record with
 * properties for each column and optional configuration for casts,
 * UUID columns, and computed columns.
 *
 * ## Usage
 *
 * ```bash
 * # Create a basic record
 * php make.php make:record User
 *
 * # Create in a subdirectory
 * php make.php make:record Auth/User
 *
 * # Use auto-increment instead of UUID
 * php make.php make:record LegacyUser --no-uuid
 * ```
 *
 * ## Output
 *
 * Creates `app/{path}/{Name}Record.php` with a starter Record class.
 */
class RecordMaker implements MakerInterface
{
    public function name(): string
    {
        return 'record';
    }

    public function description(): string
    {
        return 'Generate a new Record class for database row representation';
    }

    public function arguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'Record name (e.g., "User" or "Auth/User")'],
        ];
    }

    public function options(): array
    {
        return [
            ['no-uuid', null, InputOption::VALUE_NONE, 'Use auto-increment integer IDs instead of UUIDs'],
        ];
    }

    public function generate(InputInterface $input, string $stubsPath): array
    {
        $name = $input->getArgument('name');
        $noUuid = $input->getOption('no-uuid');

        // Parse path and class name
        $name = str_replace('\\', '/', $name);
        $parts = explode('/', $name);
        $className = array_pop($parts);

        // Ensure className ends with Record for consistency, but don't double it
        if (!str_ends_with($className, 'Record')) {
            $className .= 'Record';
        }

        // Build namespace and path
        $subPath = implode('/', $parts);
        $namespace = 'App' . ($subPath ? '\\' . str_replace('/', '\\', $subPath) : '');
        $baseDir = getcwd() . '/app' . ($subPath ? '/' . $subPath : '');

        // Build replacements
        $replacements = [
            '{{namespace}}' => $namespace,
            '{{className}}' => $className,
            '{{useUuid}}' => $noUuid ? 'false' : 'true',
        ];

        $content = file_get_contents($stubsPath . '/Record.stub');
        $content = str_replace(array_keys($replacements), array_values($replacements), $content);

        return [
            new GeneratedFile("{$baseDir}/{$className}.php", $content),
        ];
    }
}
