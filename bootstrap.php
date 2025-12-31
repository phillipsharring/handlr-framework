<?php

declare(strict_types=1);

// Framework package root (this directory). When installed via Composer, this is typically:
// <app>/vendor/phillipsharring/handlr-framework
const HANDLR_ROOT = __DIR__;

// When installed via Composer, the app root is 3 levels up from HANDLR_ROOT.
// In this monorepo, that assumption is not always true, so fall back to getcwd().
$computedAppRoot = realpath(HANDLR_ROOT . '/../../..') ?: (HANDLR_ROOT . '/../../..');
$cwdAppRoot = getcwd() ?: '';

$frameworkBootstrap = realpath(HANDLR_ROOT . '/bootstrap.php') ?: (HANDLR_ROOT . '/bootstrap.php');

$isValidAppRoot = static function (string $root) use ($frameworkBootstrap): bool {
    $bootstrapPath = realpath($root . '/bootstrap.php') ?: ($root . '/bootstrap.php');
    return is_file($bootstrapPath) && $bootstrapPath !== $frameworkBootstrap;
};

$appRoot = (is_string($computedAppRoot) && $isValidAppRoot($computedAppRoot))
    ? $computedAppRoot
    : ((is_string($cwdAppRoot) && $isValidAppRoot($cwdAppRoot)) ? $cwdAppRoot : null);

if (!is_string($appRoot)) {
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
