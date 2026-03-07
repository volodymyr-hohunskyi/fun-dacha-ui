<?php
/**
 * Fun Dacha - Compile SCSS to CSS using PHP (ScssPhp)
 * No Node.js required. Uses OpenCart's existing ScssPhp vendor.
 */
chdir(__DIR__);

// Load config for DIR_* constants
if (!is_file('config.php')) {
    fwrite(STDERR, "ERROR: config.php not found. Run from OpenCart root.\n");
    exit(1);
}
require_once 'config.php';

if (!defined('DIR_STORAGE') || !defined('DIR_SYSTEM')) {
    fwrite(STDERR, "ERROR: config.php must define DIR_STORAGE and DIR_SYSTEM.\n");
    exit(1);
}

// Bootstrap autoloader and vendor (ScssPhp)
require_once DIR_SYSTEM . 'engine/autoloader.php';
$autoloader = new \Opencart\System\Engine\Autoloader();
$autoloader->register('Opencart\System', DIR_SYSTEM);
if (defined('DIR_CATALOG')) {
    $autoloader->register('Opencart\Catalog', DIR_CATALOG);
}
require_once DIR_SYSTEM . 'vendor.php';

// Compile
$stylesheetDir = (defined('DIR_CATALOG') ? DIR_CATALOG : __DIR__ . '/catalog/') . 'view/stylesheet/';
$files = ['bootstrap', 'stylesheet'];

foreach ($files as $name) {
    $scssFile = $stylesheetDir . $name . '.scss';
    $cssFile  = $stylesheetDir . $name . '.css';
    if (!is_file($scssFile)) {
        fwrite(STDERR, "Skip: $scssFile not found\n");
        continue;
    }
    try {
        $scss = new \ScssPhp\ScssPhp\Compiler();
        $scss->setImportPaths($stylesheetDir);
        $output = $scss->compileString('@import "' . $name . '.scss"')->getCss();
        file_put_contents($cssFile, $output);
        echo "Compiled: $name.scss -> $name.css\n";
    } catch (\Throwable $e) {
        fwrite(STDERR, "ERROR compiling $name: " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "Done.\n";
