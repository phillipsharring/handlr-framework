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
     * Each element should be an array: [name, mode, description, default?]
     * Mode should be InputArgument::REQUIRED, OPTIONAL, or IS_ARRAY.
     *
     * @return array<int, array{0: string, 1: int, 2: string, 3?: mixed}>
     */
    public function arguments(): array;

    /**
     * Option definitions for the command.
     *
     * Each element should be an array: [name, shortcut, mode, description, default?]
     * Mode should be InputOption::VALUE_NONE, VALUE_REQUIRED, VALUE_OPTIONAL, etc.
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
