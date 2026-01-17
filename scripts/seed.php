<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Handlr\Config\Loader;
use Handlr\Core\Container\Container;
use Handlr\Database\Db;
use Handlr\Database\Seeder;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SeedCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('db:seed')
            ->setDescription('Run database seeders.')
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

        // Find all seed files
        $seedFiles = glob($seedsPath . '/*.php');
        if ($seedFiles === false || count($seedFiles) === 0) {
            $output->writeln("<comment>No seed files found in: {$seedsPath}</comment>");
            return Command::SUCCESS;
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