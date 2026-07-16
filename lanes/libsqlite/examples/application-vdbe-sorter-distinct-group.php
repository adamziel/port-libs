<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeSorterDistinctGroupCursor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['autoload' => 'yes', 'kind' => 'core', 'option_name' => 'siteurl', 'bytes' => '24', 'enabled' => 1],
    ['autoload' => 'yes', 'kind' => 'core', 'option_name' => 'home', 'bytes' => 24, 'enabled' => 1],
    ['autoload' => 'yes', 'kind' => 'plugin', 'option_name' => 'Plugin_Cache', 'bytes' => '12', 'enabled' => 1],
    ['autoload' => 'yes', 'kind' => 'plugin', 'option_name' => 'plugin_cache', 'bytes' => 12.0, 'enabled' => 1],
    ['autoload' => 'yes', 'kind' => 'plugin', 'option_name' => 'plugin_cache_debug', 'bytes' => 12, 'enabled' => 0],
    ['autoload' => 'no', 'kind' => 'transient', 'option_name' => 'transient_feed', 'bytes' => 30, 'enabled' => 1],
];

$cursor = new SQLiteVdbeSorterDistinctGroupCursor(
    $rows,
    ['autoload', 'kind'],
    'bytes',
    'bytes',
    'enabled',
    'GG',
    ['NOCASE', 'BINARY'],
    [],
    [],
    'C'
);

echo json_encode($cursor->drainSummaries(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
