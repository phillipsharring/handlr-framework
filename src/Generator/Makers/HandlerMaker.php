<?php

declare(strict_types=1);

namespace Handlr\Generator\Makers;

use Handlr\Generator\GeneratedFile;
use Handlr\Generator\MakerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Generates a Handler class for business logic.
 *
 * Creates a Handler class implementing Handlr\Handlers\Handler with
 * constructor injection and a handle() method.
 *
 * ## Usage
 *
 * ```bash
 * # Create a basic handler
 * php make.php make:handler CreateUser
 *
 * # Create in a subdirectory
 * php make.php make:handler Auth/CreateUser
 * ```
 *
 * ## Output
 *
 * Creates `app/{path}/{Name}Handler.php` with a starter Handler class.
 */
class HandlerMaker implements MakerInterface
{
    public function name(): string
    {
        return 'handler';
    }

    public function description(): string
    {
        return 'Generate a new Handler class for business logic';
    }

    public function arguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'Handler name (e.g., "CreateUser" or "Auth/CreateUser")'],
        ];
    }

    public function options(): array
    {
        return [];
    }

    public function generate(InputInterface $input, string $stubsPath): array
    {
        $name = $input->getArgument('name');

        // Parse path and class name
        $name = str_replace('\\', '/', $name);
        $parts = explode('/', $name);
        $className = array_pop($parts);

        // Ensure className ends with Handler for consistency, but don't double it
        if (!str_ends_with($className, 'Handler')) {
            $className .= 'Handler';
        }

        // Build namespace and path
        $subPath = implode('/', $parts);
        $namespace = 'App' . ($subPath ? '\\' . str_replace('/', '\\', $subPath) : '');
        $baseDir = getcwd() . '/app' . ($subPath ? '/' . $subPath : '');

        // Build replacements
        $replacements = [
            '{{namespace}}' => $namespace,
            '{{className}}' => $className,
        ];

        $content = file_get_contents($stubsPath . '/Handler.stub');
        $content = str_replace(array_keys($replacements), array_values($replacements), $content);

        return [
            new GeneratedFile("{$baseDir}/{$className}.php", $content),
        ];
    }
}
