<?php

declare(strict_types=1);

namespace Handlr\Generator\Makers;

use Handlr\Generator\GeneratedFile;
use Handlr\Generator\MakerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Generates new Maker classes.
 *
 * Creates a new Maker class and its corresponding stub file,
 * ready to be registered with the GeneratorRunner.
 *
 * ## Usage
 *
 * ```bash
 * php make.php make:maker Widget
 * php make.php make:maker middleware
 * php make.php make:maker api-resource
 * ```
 *
 * ## Output
 *
 * Creates:
 * - `src/Generator/Makers/WidgetMaker.php`
 * - `stubs/widget.stub`
 *
 * Then register in `scripts/make.php`:
 * ```php
 * ->register(new WidgetMaker())
 * ```
 */
class MakerMaker implements MakerInterface
{
    public function name(): string
    {
        return 'maker';
    }

    public function description(): string
    {
        return 'Create a new Maker class (yes, really)';
    }

    public function arguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The maker name (e.g., Widget, middleware, api-resource)'],
        ];
    }

    public function options(): array
    {
        return [];
    }

    public function generate(InputInterface $input, string $stubsPath): array
    {
        $name = trim($input->getArgument('name'));

        // StudlyCase for class name
        $studly = $this->toStudlyCase($name);
        $className = "{$studly}Maker";

        // lowercase for command name and stub filename
        $commandName = $this->toKebabCase($name);

        // Paths
        $makerPath = dirname($stubsPath) . "/src/Generator/Makers/{$className}.php";
        $stubPath = $stubsPath . "/{$commandName}.stub";

        // Generate the Maker class
        $makerStub = file_get_contents($stubsPath . '/maker/Maker.stub');
        $makerContent = str_replace(
            ['{{className}}', '{{commandName}}', '{{studlyName}}'],
            [$className, $commandName, $studly],
            $makerStub
        );

        // Generate the stub file
        $stubStub = file_get_contents($stubsPath . '/maker/stub.stub');
        $stubContent = str_replace('{{studlyName}}', $studly, $stubStub);

        return [
            new GeneratedFile($makerPath, $makerContent),
            new GeneratedFile($stubPath, $stubContent),
        ];
    }

    private function toStudlyCase(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', strtolower($value))));
    }

    private function toKebabCase(string $value): string
    {
        // Convert StudlyCase to kebab-case
        $kebab = preg_replace('/([a-z])([A-Z])/', '$1-$2', $value);
        // Normalize underscores and spaces to hyphens, lowercase
        return strtolower(preg_replace('/[\s_]+/', '-', $kebab));
    }
}
