<?php

/**
 * Unified code generator script.
 *
 * Usage:
 *   php make.php make:migration CreateUsersTable
 *   php make.php make:seeder Users
 *   php make.php make:scaffold GamePlay/CreateSeries
 *   php make.php make:scaffold GamePlay/CreateSeries --no-pipe
 *   php make.php make:scaffold Events/UserCreated --event-only
 *
 * Run without arguments to see available commands:
 *   php make.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/Support/path-helpers.php';

$autoloadPath = findFileInParents(__DIR__, 'vendor/autoload.php');
if ($autoloadPath === null) {
    fwrite(STDERR, "Could not find vendor/autoload.php\n");
    exit(1);
}
require_once $autoloadPath;

use Handlr\Generator\GeneratorRunner;
use Handlr\Generator\Makers\MakerMaker;
use Handlr\Generator\Makers\MigrationMaker;
use Handlr\Generator\Makers\ScaffoldMaker;
use Handlr\Generator\Makers\SeederMaker;

$stubsPath = dirname(__DIR__) . '/stubs';

$runner = new GeneratorRunner($stubsPath);
$runner
    ->register(new MakerMaker())
    ->register(new MigrationMaker())
    ->register(new ScaffoldMaker())
    ->register(new SeederMaker())
    ->run();
