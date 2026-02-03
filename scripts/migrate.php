<?php

/**
 * Database Migration CLI Script
 *
 * Runs or rolls back database migrations using MigrationRunner. Migrations
 * live in `{app_root}/migrations/` and are tracked in a `migrations` table.
 *
 * ## Usage
 *
 * ```bash
 * # Run all pending migrations
 * php scripts/migrate.php up
 *
 * # Run migrations one at a time (step-wise)
 * php scripts/migrate.php up step
 *
 * # Rollback the last batch
 * php scripts/migrate.php down
 * php scripts/migrate.php rollback
 *
 * # Rollback multiple batches
 * php scripts/migrate.php down 3
 * ```
 *
 * ## Creating Migrations
 *
 * Use the make script to generate migration files:
 * ```bash
 * php scripts/make.php migration create_users_table
 * ```
 *
 * @see \Handlr\Database\Migrations\MigrationRunner
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Handlr\Database\DatabaseException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Handlr\Config\Loader;
use Handlr\Core\Container\Container;
use Handlr\Database\Db;
use Handlr\Database\Migrations\MigrationRunner;

/**
 * Symfony Console command for running database migrations.
 */
class MigrateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('migrate')
            ->setDescription('Run or rollback database migrations.')
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform: up, down, rollback, help')
            ->addArgument('batches', InputArgument::OPTIONAL, 'Number of batches or "step" for step-wise migration');
    }

    /**
     * @throws DatabaseException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = $input->getArgument('action');
        $batches = $input->getArgument('batches') ?? 1;
        $stepWise = $action === 'up' && $batches === 'step';
        $batches = $stepWise ? $batches : (int) $batches;

        if (
            !in_array($action, ['up', 'down', 'rollback', 'help'], true)
            || ($action === 'down' && $batches === 'step')
        ) {
            return $this->displayHelp($output, true);
        }

        if ($action === 'help') {
            return $this->displayHelp($output, false);
        }

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
        $migrationPath = $appRoot . '/migrations';
        $runner = new MigrationRunner($db, $migrationPath);

        match ($action) {
            'up'       => $runner->migrate($stepWise),
            'down',
            'rollback' => $runner->rollback($batches),
        };

        return Command::SUCCESS;
    }

    private function displayHelp(OutputInterface $output, bool $invalid): int
    {
        if ($invalid) {
            $output->writeln("<error>Invalid options provided.</error>");
        }

        $output->writeln(<<<HELP

Migration script
----------------

Usage:
php scripts/migrate.php [action] [batches]

Arguments:
  action   Action to perform: up, down, rollback, help
  batches  Integer >= 1 (default: 1), or "step" for step-wise migration

Examples:
  php scripts/migrate.php up
  php scripts/migrate.php up step
  php scripts/migrate.php down
  php scripts/migrate.php down 2

HELP);
        return Command::INVALID;
    }
}

$app = new Application();
$app->addCommand(new MigrateCommand());
$app->setDefaultCommand('migrate', true);

try {
    $app->run();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(Command::FAILURE);
}
