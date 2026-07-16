<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteIncrementalBlobIoPlan;

$rows = [
    ['rowid' => 1, 'option_name' => 'siteurl', 'option_value' => new SQLiteBlobValue('https://example.test')],
    ['rowid' => 2, 'option_name' => '_site_transient_update_plugins', 'option_value' => new SQLiteBlobValue(str_repeat("\0", 20))],
];

$handle = SQLiteIncrementalBlobIoPlan::open($rows, [
    'table' => 'wp_options',
    'column' => 'option_value',
    'rowid' => 2,
]);
$first = SQLiteIncrementalBlobIoPlan::write($rows, $handle, 0, 'plugin-cache');
$secondHandle = array_replace($handle, ['payload' => $first['payload']]);
$second = SQLiteIncrementalBlobIoPlan::write($first['rows'], $secondHandle, 12, '-v1');
$readback = SQLiteIncrementalBlobIoPlan::read(array_replace($secondHandle, ['payload' => $second['payload']]), 0, 15);

echo json_encode([
    'scenario' => 'application-incremental-blob-io',
    'applicationUse' => 'Patch a fixed-size copied wp_options BLOB value in chunks using SQLite incremental BLOB semantics without requiring ext/sqlite.',
    'openedBytes' => $handle['bytes'],
    'writtenBytes' => $first['written'] + $second['written'],
    'previewHex' => bin2hex($readback['bytes']->bytes),
    'finalHex' => bin2hex($second['payload']->bytes),
    'dependencies' => array_values(array_unique(array_merge(
        $handle['dependencies'],
        $first['dependencies'],
        $second['dependencies'],
        $readback['dependencies'],
    ))),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
