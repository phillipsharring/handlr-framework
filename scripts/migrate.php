<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Handlr\Config\Loader;
use Handlr\Core\Container\Container;
use Handlr\Database\Db;
use Handlr\Database\Migrations\MigrationRunner;

array_shift($argv);

$action = $argv[0] ?? 'up';
$batches = $argv[1] ?? 1;
$stepWise = $action === 'up' && $batches === 'step';

if ($action === 'help') {
    help(false);
}

$batches = $batches === 'step' ? $batches : (int)$batches;

if (
    (!is_int($batches) && $batches !== 'step')
    || ($action === 'down' && $batches === 'step')
) {
    help();
}

// if ($action === 'db-create') {
//     echo "DB Host: ";
//     $host = trim(readline());
//
//     echo "DB User: ";
//     $user = trim(readline());
//
//     echo "DB Password: ";
//     shell_exec('stty -echo');  // hide input
//     $password = trim(readline());
//     shell_exec('stty echo');
//     echo "\n";
// }

$container = new Container();

// Prefer app-defined constants (set by the app's bootstrap.php). Fall back to cwd for framework dev usage.
$configPath = defined('HANDLR_APP_APP_PATH')
    ? HANDLR_APP_APP_PATH . '/config.php'
    : (getcwd() . '/app/config.php');

$config = Loader::load($configPath, $container);
$db = new Db($config);

$migrationPath = (defined('HANDLR_APP_ROOT') ? HANDLR_APP_ROOT : getcwd()) . '/migrations';
$runner = new MigrationRunner($db, $migrationPath);

switch ($action) {
    case 'up':
        $runner->migrate($stepWise);
        break;

    case 'down':
    case 'rollback':
        $runner->rollback($batches);
        break;

    case 'db-create':
        $runner->createDatabase();
        break;

    default:
        help();
}

function help(bool $invalid = true): void
{
    if ($invalid) {
        echo "Invalid options" . PHP_EOL
            . "===============" . PHP_EOL
            . PHP_EOL;
    }

    echo <<<HELP
Migration script
----------------
    
php migrate.php action [batches] [step]

Options:
action = string 'up', 'down', 'rollback' or 'help'. Required. 'up' to migrate. 'down' or 'rollback' to rollback. 'help' shows this message.
batches = integer >= 1. Defaults to 1. How many batches to rollback when action = 'down' or 'rollback'.
step = string 'step'. `true` if provided, defaults to `false`. When `true`, will run each migration in a separate batch.

Usage:

Migrate
In 1 batch
php migrate.php up

Migrate
In separate batches
php migrate.php up step

Rollback 1 step
php migrate.php down

Rollback N steps
php migrate.php down 2

HELP;
    exit();
}
