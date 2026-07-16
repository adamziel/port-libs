<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeIndexCursor;

$cursor = new SQLiteVdbeIndexCursor([
    ['key' => ['autoload', 'Plugin_A', '02'], 'rowid' => 20, 'payload' => ['option_name' => 'Plugin_A']],
    ['key' => ['autoload', 'plugin_a', 2], 'rowid' => 10, 'payload' => ['option_name' => 'plugin_a']],
    ['key' => ['autoload', 'plugin_b', '10'], 'rowid' => 30, 'payload' => ['option_name' => 'plugin_b']],
    ['key' => ['cache', 'Cache ', null], 'rowid' => 40, 'payload' => ['option_name' => 'Cache ']],
    ['key' => ['cache', 'cache', '1'], 'rowid' => 50, 'payload' => ['option_name' => 'cache']],
    ['key' => ['network', 'SiteURL', new SQLiteBlobValue('4')], 'rowid' => 60, 'payload' => ['option_name' => 'SiteURL']],
], 'GGC', ['BINARY', 'NOCASE', 'RTRIM']);

$autoload = $cursor->yieldEqual(['autoload', 'plugin_a', 2]);
$cursor->seekGreaterOrEqual(['cache']);
$cacheBoundary = $cursor->compareCurrentToNext([1], 'G', ['RTRIM']);
$cacheRecords = [$cursor->currentRecord([0, 1, 2]), $cursor->nextRecord([0, 1, 2])];
$cursor->seekGreaterOrEqual(['network', 'siteurl']);

echo json_encode([
    'scenario' => 'application-vdbe-index-affinity-collation-current-next35',
    'autoloadPluginRowids' => array_column($autoload, 'rowid'),
    'cacheNameBoundary' => $cacheBoundary,
    'cacheCurrentNextRecords' => $cacheRecords,
    'networkCurrentRowid' => $cursor->currentRowid(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
