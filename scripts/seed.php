<?php

/**
 * Database Seeder CLI Script
 *
 * Populates the database with seed data from files in `{app_root}/seeds/`.
 * Each seed file returns an array mapping Table classes to record data.
 *
 * ## Usage
 *
 * ```bash
 * # Run all seeders in the seeds/ directory
 * php scripts/seed.php
 *
 * # Run a specific seeder file
 * php scripts/seed.php users
 * php scripts/seed.php users.php
 *
 * # Truncate tables before seeding (fresh start)
 * php scripts/seed.php --fresh
 * php scripts/seed.php -f
 * ```
 *
 * ## Seed File Format
 *
 * Seed files return an array of [TableClass => [records...]]:
 *
 * ```php
 * // seeds/users.php
 * return [
 *     UsersTable::class => [
 *         ['name' => 'Admin', 'email' => 'admin@example.com'],
 *         ['name' => 'User', 'email' => 'user@example.com'],
 *     ],
 * ];
 * ```
 *
 * ## Creating Seed Files
 *
 * Use the make script to generate seeder files:
 * ```bash
 * php scripts/make.php seeder users
 * ```
 *
 * @see \Handlr\Database\Migrations\Seeder
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Handlr\Config\Loader;
use Handlr\Core\Container\Container;
use Handlr\Database\Db;
use Handlr\Database\Migrations\Seeder;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Symfony Console command for running database seeders.
 */
class SeedCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('db:seed')
            ->setDescription('Run database seeders.')
            ->addArgument('file', InputArgument::OPTIONAL, 'Specific seeder file to run (e.g., "series" or "series.php")')
            ->addOption('fresh', 'f', InputOption::VALUE_NONE, 'Truncate tables before seeding');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fresh = $input->getOption('fresh');

        // Prefer the app's bootstrap (loads .env + config consistently for web + CLI).
        if (function_exists('handlr_app')) {
            $app = handlr_app();
            /** @var Container $container */
            $container = $app['container'];
            /** @var array $config */
            $config = $app['config'];
        } else {
            $container = new Container();

            $appPath = defined('HANDLR_APP_APP_PATH')
                ? (string)constant('HANDLR_APP_APP_PATH')
                : null;
            $appRoot = defined('HANDLR_APP_ROOT')
                ? (string)constant('HANDLR_APP_ROOT')
                : (string)getcwd();
            $configPath = $appPath
                ? ($appPath . '/config.php')
                : ($appRoot . '/app/config.php');

            $config = Loader::load($configPath, $container);
        }

        $db = new Db($config);

        $appRoot = defined('HANDLR_APP_ROOT')
            ? (string)constant('HANDLR_APP_ROOT')
            : (string)getcwd();
        $seedsPath = $appRoot . '/seeds';

        if (!is_dir($seedsPath)) {
            $output->writeln("<comment>No seeds directory found at: {$seedsPath}</comment>");
            return Command::SUCCESS;
        }

        // Check if a specific file was requested
        $specificFile = $input->getArgument('file');

        if ($specificFile !== null) {
            // Normalize: add .php if not present
            if (!str_ends_with($specificFile, '.php')) {
                $specificFile .= '.php';
            }

            $fullPath = $seedsPath . '/' . $specificFile;

            // If the exact file doesn't exist, look for a numerically-prefixed match
            // e.g. "users.php" finds "001_users.php"
            if (!file_exists($fullPath)) {
                $pattern = $seedsPath . '/*_' . $specificFile;
                $matches = glob($pattern);
                if ($matches !== false && count($matches) === 1) {
                    $fullPath = $matches[0];
                    $specificFile = basename($fullPath);
                } elseif ($matches !== false && count($matches) > 1) {
                    $output->writeln("<error>Ambiguous seeder name \"{$specificFile}\" — multiple matches found.</error>");
                    return Command::FAILURE;
                } else {
                    $output->writeln("<error>Seeder file not found: {$fullPath}</error>");
                    return Command::FAILURE;
                }
            }

            $seedFiles = [$fullPath];
            $output->writeln("<info>Running specific seeder: {$specificFile}</info>");
        } else {
            // Find all seed files
            $seedFiles = glob($seedsPath . '/*.php');
            if ($seedFiles === false || count($seedFiles) === 0) {
                $output->writeln("<comment>No seed files found in: {$seedsPath}</comment>");
                return Command::SUCCESS;
            }
        }

        $seeder = new Seeder($db);

        // Collect all seed data first
        $allSeedData = [];
        foreach ($seedFiles as $seedFile) {
            $data = require $seedFile;
            if (!is_array($data)) {
                $output->writeln("<error>Seed file must return an array: {$seedFile}</error>");
                return Command::FAILURE;
            }
            $allSeedData = array_merge($allSeedData, $data);
        }

        // If --fresh, truncate all tables first
        if ($fresh) {
            $output->writeln("<info>Truncating tables...</info>");
            $tableClasses = $seeder->collectTableClasses($allSeedData);

            // Reverse order for truncation (children before parents)
            $seeder->truncate(array_reverse($tableClasses));
        }

        // Run seeders
        $output->writeln("<info>Running seeders...</info>");

        $totalInserted = 0;
        foreach ($seedFiles as $seedFile) {
            $filename = basename($seedFile);
            $data = require $seedFile;

            $counts = $seeder->seed($data);

            foreach ($counts as $tableClass => $count) {
                $shortName = (new ReflectionClass($tableClass))->getShortName();
                $output->writeln("  <comment>{$shortName}</comment>: {$count} records");
                $totalInserted += $count;
            }
        }

        $output->writeln("<info>Seeding complete. {$totalInserted} records inserted.</info>");

        return Command::SUCCESS;
    }
}

$app = new Application();
$app->addCommand(new SeedCommand());
$app->setDefaultCommand('db:seed', true);

try {
    $app->run();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(Command::FAILURE);
}
