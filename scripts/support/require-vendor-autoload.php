<?php

function requireVendorAutoload(): void
{
    $dir = __DIR__;
    while ($dir !== dirname($dir)) {
        $autoloadPath = $dir . '/vendor/autoload.php';
        if (file_exists($autoloadPath)) {
            require_once $autoloadPath;
            return;
        }
        $dir = dirname($dir);
    }

    fwrite(STDERR, "Could not find vendor/autoload.php\n");
    exit(1);
}
