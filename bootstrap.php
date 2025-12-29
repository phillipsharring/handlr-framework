<?php

declare(strict_types=1);

// Framework package root (this directory). When installed via Composer, this is typically:
// <app>/vendor/phillipsharring/handlr-framework
const HANDLR_ROOT = __DIR__;

// When installed via Composer, the app root is 3 levels up from HANDLR_ROOT.
// In this monorepo, that assumption is not always true, so fall back to getcwd().
$computedAppRoot = realpath(HANDLR_ROOT . '/../../..') ?: (HANDLR_ROOT . '/../../..');
$cwdAppRoot = getcwd() ?: '';

$appRoot = (is_string($computedAppRoot) && is_file($computedAppRoot . '/bootstrap.php'))
    ? $computedAppRoot
    : ((is_string($cwdAppRoot) && is_file($cwdAppRoot . '/bootstrap.php')) ? $cwdAppRoot : null);

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
