<?php

declare(strict_types=1);

namespace Handlr\Generator\Makers;

use Handlr\Generator\GeneratedFile;
use Handlr\Generator\MakerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Generates database seeder files.
 *
 * Creates seeder files in the `seeds/` directory with a template
 * for defining seed data per table.
 *
 * ## Usage
 *
 * ```bash
 * php make.php make:seeder Users
 * php make.php make:seeder user_packs
 * php make.php make:seeder GameSeries
 * ```
 *
 * ## Output
 *
 * Creates: `seeds/users.php`, `seeds/user_packs.php`, `seeds/game_series.php`
 */
class SeederMaker implements MakerInterface
{
    public function name(): string
    {
        return 'seeder';
    }

    public function description(): string
    {
        return 'Create a new database seeder file';
    }

    public function arguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The seeder name (e.g., Users, user_packs, GameSeries)'],
        ];
    }

    public function options(): array
    {
        return [];
    }

    public function generate(InputInterface $input, string $stubsPath): array
    {
        $name = trim($input->getArgument('name'));

        // Convert to snake_case for filename (preserving original case boundaries)
        $snakeName = $this->toSnakeCase($name);
        $filename = $snakeName . '.php';
        $path = getcwd() . '/seeds/' . $filename;

        // Convert to StudlyCase for class references in comments
        $studly = $this->toStudlyCase($snakeName);

        $stub = file_get_contents($stubsPath . '/seeder.stub');
        $content = str_replace('{{StudlyName}}', $studly, $stub);

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
        // First convert StudlyCase boundaries to underscores
        $snake = preg_replace('/([a-z])([A-Z])/', '$1_$2', $value);
        // Then normalize any existing separators (hyphens, spaces) and lowercase
        return strtolower(preg_replace('/[\s-]+/', '_', $snake));
    }
}
