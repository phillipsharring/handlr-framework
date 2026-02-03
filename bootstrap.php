<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Support/path-helpers.php';

// Framework package root (this directory). When installed via Composer, this is typically:
// <app>/vendor/phillipsharring/handlr-framework
const HANDLR_ROOT = __DIR__;

// The framework's own bootstrap.php (this file) - used to distinguish from app bootstrap
$frameworkBootstrap = realpath(__FILE__);

// Find the app root by walking up from cwd looking for a bootstrap.php that isn't this file
$appRoot = findInParentDirectories(getcwd() ?: __DIR__, function ($dir) use ($frameworkBootstrap) {
    $bootstrapPath = realpath("$dir/bootstrap.php");
    return $bootstrapPath && $bootstrapPath !== $frameworkBootstrap;
});

if ($appRoot === null) {
    throw new RuntimeException(
        'Unable to locate app bootstrap.php. '
        . 'Run this from your app root (where bootstrap.php exists), or install the framework via Composer.'
    );
}

// Define once; apps may also define this constant.
if (!defined('HANDLR_APP_ROOT')) {
    define('HANDLR_APP_ROOT', $appRoot);
}

require_once HANDLR_APP_ROOT . '/bootstrap.php';
