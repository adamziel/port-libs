<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeIndexCursor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$cursor = new SQLiteVdbeIndexCursor([
    ['key' => ['autoload', '10', 'Plugin_Z'], 'rowid' => 10, 'payload' => ['option_name' => 'Plugin_Z']],
    ['key' => ['autoload', 2, 'plugin_a'], 'rowid' => 2, 'payload' => ['option_name' => 'plugin_a']],
    ['key' => ['autoload', '02', 'Plugin_A'], 'rowid' => 1, 'payload' => ['option_name' => 'Plugin_A']],
    ['key' => ['autoload', new SQLiteBlobValue('4'), 'plugin_blob'], 'rowid' => 4, 'payload' => ['option_name' => 'plugin_blob']],
    ['key' => ['cache', null, 'cache_a'], 'rowid' => 8, 'payload' => ['option_name' => 'cache_a']],
    ['key' => ['cache', '1', 'cache_b'], 'rowid' => 7, 'payload' => ['option_name' => 'cache_b']],
], 'GCG', ['BINARY', 'BINARY', 'NOCASE']);

$autoload = $cursor->yieldEqual(['autoload']);

echo json_encode([
    'scenario' => 'application-vdbe-index-current-next',
    'autoloadRowids' => array_column($autoload, 'rowid'),
    'autoloadNames' => array_map(static fn (array $entry): string => $entry['payload']['option_name'], $autoload),
    'nextRowids' => array_column($cursor->remaining(), 'rowid'),
    'applicationUse' => 'Preview copied wp_options option_name index scans through VDBE-like current-key reads and Next advancement, applying SQLite affinity/collation before yielding rowids without ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
