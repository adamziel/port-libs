<?php

declare(strict_types=1);

require dirname(__DIR__) . '/../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaRuntimeCatalog;

$catalog = new SQLitePragmaRuntimeCatalog();
$catalog->addCollation('wp_slug');
$catalog->addModule('wp_option_tokens');
$catalog->addFunction('wp_option_checksum', 1, 0x800, 's', 'utf8', 0);

echo json_encode([
    'scenario' => 'copied wp_options runtime pragma capability preflight',
    'collations' => array_column($catalog->execute('PRAGMA collation_list')['rows'], 'name'),
    'jsonModules' => array_values(array_intersect(
        array_column($catalog->execute('PRAGMA module_list')['rows'], 'name'),
        ['json_tree', 'json_each']
    )),
    'applicationModuleAvailable' => in_array('wp_option_tokens', array_column($catalog->execute('PRAGMA module_list')['rows'], 'name'), true),
    'applicationFunctionAvailable' => in_array('wp_option_checksum', array_column($catalog->execute('PRAGMA function_list')['rows'], 'name'), true),
    'dependencies' => [
        'sqlite-pragma-collation-list',
        'sqlite-pragma-module-list',
        'sqlite-pragma-function-list',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
