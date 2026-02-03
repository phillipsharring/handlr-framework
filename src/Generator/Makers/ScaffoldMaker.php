<?php

declare(strict_types=1);

namespace Handlr\Generator\Makers;

use Handlr\Generator\GeneratedFile;
use Handlr\Generator\MakerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Generates a complete feature scaffold.
 *
 * Creates Input, Handler, Pipe, and Test files for a new feature
 * in the `app/` directory structure.
 *
 * ## Usage
 *
 * ```bash
 * # Full scaffold (Input, Handler, Pipe, Test)
 * php make.php make:scaffold GamePlay/CreateSeries
 *
 * # Skip Pipe generation
 * php make.php make:scaffold GamePlay/CreateSeries --no-pipe
 *
 * # Event-only (Input and Handler only, no Pipe or Test)
 * php make.php make:scaffold Events/UserCreated --event-only
 * ```
 *
 * ## Output
 *
 * Creates in `app/GamePlay/CreateSeries/`:
 * - `CreateSeriesInput.php`
 * - `CreateSeriesHandler.php`
 * - `CreateSeriesPipe.php` (unless --no-pipe or --event-only)
 * - `CreateSeriesTest.php` (unless --event-only)
 */
class ScaffoldMaker implements MakerInterface
{
    public function name(): string
    {
        return 'scaffold';
    }

    public function description(): string
    {
        return 'Scaffold a new feature with Input, Handler, Pipe, and Test';
    }

    public function arguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'Feature name/path (e.g., GamePlay/CreateSeries)'],
        ];
    }

    public function options(): array
    {
        return [
            ['no-pipe', null, InputOption::VALUE_NONE, 'Skip Pipe generation'],
            ['event-only', null, InputOption::VALUE_NONE, 'Only generate Input and Handler (for domain events)'],
        ];
    }

    public function generate(InputInterface $input, string $stubsPath): array
    {
        $name = $input->getArgument('name');
        $noPipe = $input->getOption('no-pipe');
        $eventOnly = $input->getOption('event-only');

        $baseDir = getcwd() . '/app/' . $name;
        $className = basename(str_replace('\\', '/', $name));
        $namespace = 'App\\' . str_replace('/', '\\', $name);

        // Build replacement map
        $replacements = [
            '{{namespace}}' => $namespace,
            '{{className}}' => $className,
            '{{name}}' => $name,
        ];

        $files = [];

        // Always generate Input and Handler
        $files[] = $this->generateFile(
            "{$baseDir}/{$className}Input.php",
            $stubsPath . '/scaffold/Input.stub',
            $replacements
        );

        $files[] = $this->generateFile(
            "{$baseDir}/{$className}Handler.php",
            $stubsPath . '/scaffold/Handler.stub',
            $replacements
        );

        // Generate Pipe unless --no-pipe or --event-only
        if (!$noPipe && !$eventOnly) {
            $files[] = $this->generateFile(
                "{$baseDir}/{$className}Pipe.php",
                $stubsPath . '/scaffold/Pipe.stub',
                $replacements
            );
        }

        // Generate Test unless --event-only
        if (!$eventOnly) {
            $files[] = $this->generateFile(
                "{$baseDir}/{$className}Test.php",
                $stubsPath . '/scaffold/Test.stub',
                $replacements
            );
        }

        return $files;
    }

    private function generateFile(string $path, string $stubPath, array $replacements): GeneratedFile
    {
        $content = file_get_contents($stubPath);
        $content = str_replace(array_keys($replacements), array_values($replacements), $content);

        return new GeneratedFile($path, $content);
    }
}
