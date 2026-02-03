<?php

declare(strict_types=1);

namespace Handlr\Generator\Makers;

use Handlr\Generator\GeneratedFile;
use Handlr\Generator\MakerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Generates a Pipe class for HTTP request handling.
 *
 * Creates a Pipe class implementing Handlr\Pipes\Pipe. The class name
 * is prefixed with the HTTP method (Get, Post, Patch, Delete).
 *
 * ## Usage
 *
 * ```bash
 * # Create a GET pipe (default)
 * php make.php make:pipe ListUsers
 *
 * # Create a POST pipe
 * php make.php make:pipe CreateUser --post
 *
 * # Create a PATCH pipe
 * php make.php make:pipe UpdateUser --patch
 *
 * # Create a DELETE pipe
 * php make.php make:pipe DeleteUser --delete
 *
 * # Create in a subdirectory
 * php make.php make:pipe Auth/CreateUser --post
 * ```
 *
 * ## Output
 *
 * Creates `app/{path}/{Method}{Name}.php` (e.g., `GetListUsers.php`, `PostCreateUser.php`)
 */
class PipeMaker implements MakerInterface
{
    public function name(): string
    {
        return 'pipe';
    }

    public function description(): string
    {
        return 'Generate a new Pipe class for HTTP request handling';
    }

    public function arguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'Pipe name (e.g., "ListUsers" or "Auth/CreateUser")'],
        ];
    }

    public function options(): array
    {
        return [
            ['post', null, InputOption::VALUE_NONE, 'Create a POST pipe'],
            ['patch', null, InputOption::VALUE_NONE, 'Create a PATCH pipe'],
            ['delete', null, InputOption::VALUE_NONE, 'Create a DELETE pipe'],
        ];
    }

    public function generate(InputInterface $input, string $stubsPath): array
    {
        $name = $input->getArgument('name');

        // Determine HTTP method
        $method = 'Get'; // default
        if ($input->getOption('post')) {
            $method = 'Post';
        } elseif ($input->getOption('patch')) {
            $method = 'Patch';
        } elseif ($input->getOption('delete')) {
            $method = 'Delete';
        }

        // Parse path and class name
        $name = str_replace('\\', '/', $name);
        $parts = explode('/', $name);
        $baseName = array_pop($parts);

        // Build class name with method prefix
        $className = $method . $baseName;

        // Build namespace and path
        $subPath = implode('/', $parts);
        $namespace = 'App' . ($subPath ? '\\' . str_replace('/', '\\', $subPath) : '');
        $baseDir = getcwd() . '/app' . ($subPath ? '/' . $subPath : '');

        // Build replacements
        $replacements = [
            '{{namespace}}' => $namespace,
            '{{className}}' => $className,
            '{{method}}' => $method,
        ];

        $content = file_get_contents($stubsPath . '/Pipe.stub');
        $content = str_replace(array_keys($replacements), array_values($replacements), $content);

        return [
            new GeneratedFile("{$baseDir}/{$className}.php", $content),
        ];
    }
}
