<?php

declare(strict_types=1);

namespace Handlr\Generator;

use Symfony\Component\Console\Input\InputInterface;

/**
 * Contract for code generators (makers).
 *
 * Implement this interface to create new types of generators that can be
 * registered with the GeneratorRunner.
 *
 * ## Example implementation
 *
 * ```php
 * class MyMaker implements MakerInterface
 * {
 *     public function name(): string
 *     {
 *         return 'widget';
 *     }
 *
 *     public function description(): string
 *     {
 *         return 'Create a new widget class';
 *     }
 *
 *     public function arguments(): array
 *     {
 *         return [
 *             ['name', InputArgument::REQUIRED, 'The widget name'],
 *         ];
 *     }
 *
 *     public function options(): array
 *     {
 *         return [
 *             ['fancy', 'f', InputOption::VALUE_NONE, 'Make it fancy'],
 *         ];
 *     }
 *
 *     public function generate(InputInterface $input, string $stubsPath): array
 *     {
 *         $name = $input->getArgument('name');
 *         $content = file_get_contents($stubsPath . '/widget.stub');
 *         $content = str_replace('{{name}}', $name, $content);
 *
 *         return [
 *             new GeneratedFile(getcwd() . "/widgets/{$name}.php", $content),
 *         ];
 *     }
 * }
 * ```
 */
interface MakerInterface
{
    /**
     * The maker's command name (used as subcommand).
     *
     * Example: "migration" results in `php make.php migration MyName`
     */
    public function name(): string;

    /**
     * Human-readable description for help output.
     */
    public function description(): string;

    /**
     * Argument definitions for the command.
     *
     * Each element is an array: `[name, mode, description, default?]`
     *
     * **Argument modes** (from Symfony\Component\Console\Input\InputArgument):
     * - `InputArgument::REQUIRED` - argument must be provided
     * - `InputArgument::OPTIONAL` - argument is optional
     * - `InputArgument::IS_ARRAY` - argument accepts multiple values
     *
     * ```php
     * public function arguments(): array
     * {
     *     return [
     *         ['name', InputArgument::REQUIRED, 'The widget name'],
     *         ['extra', InputArgument::OPTIONAL, 'Optional extra info', 'default'],
     *     ];
     * }
     * ```
     *
     * Access in generate(): `$input->getArgument('name')`
     *
     * @return array<int, array{0: string, 1: int, 2: string, 3?: mixed}>
     */
    public function arguments(): array;

    /**
     * Option definitions for the command.
     *
     * Each element is an array: `[name, shortcut, mode, description, default?]`
     *
     * **Option modes** (from Symfony\Component\Console\Input\InputOption):
     * - `InputOption::VALUE_NONE` - flag with no value (e.g., `--verbose`)
     * - `InputOption::VALUE_REQUIRED` - must have a value (e.g., `--format=json`)
     * - `InputOption::VALUE_OPTIONAL` - value is optional (e.g., `--config` or `--config=custom.php`)
     * - `InputOption::VALUE_IS_ARRAY` - can be repeated (e.g., `--exclude=foo --exclude=bar`)
     *
     * Modes can be combined: `InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY`
     *
     * ```php
     * public function options(): array
     * {
     *     return [
     *         // Flag (boolean) - no value
     *         ['verbose', 'v', InputOption::VALUE_NONE, 'Enable verbose output'],
     *
     *         // Required value
     *         ['format', 'f', InputOption::VALUE_REQUIRED, 'Output format', 'json'],
     *
     *         // Optional value
     *         ['config', 'c', InputOption::VALUE_OPTIONAL, 'Config file path'],
     *
     *         // Repeatable
     *         ['exclude', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Paths to exclude'],
     *     ];
     * }
     * ```
     *
     * Access in generate():
     * - Flag: `$input->getOption('verbose')` returns `true` or `false`
     * - Value: `$input->getOption('format')` returns the value or default
     * - Array: `$input->getOption('exclude')` returns `string[]`
     *
     * @return array<int, array{0: string, 1: string|null, 2: int, 3: string, 4?: mixed}>
     */
    public function options(): array;

    /**
     * Generate the files for this maker.
     *
     * @param InputInterface $input     Console input with arguments and options
     * @param string         $stubsPath Absolute path to the stubs directory
     *
     * @return GeneratedFile[] Files to be written
     */
    public function generate(InputInterface $input, string $stubsPath): array;
}
