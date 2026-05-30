<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeAggregateDistinctCursor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['rowid' => 1, 'site' => 'main', 'option_name' => 'SiteUrl', 'bytes' => '24', 'enabled' => 1],
    ['rowid' => 2, 'site' => 'main', 'option_name' => 'siteurl', 'bytes' => 24, 'enabled' => 1],
    ['rowid' => 3, 'site' => 'main', 'option_name' => 'plugin_cache ', 'bytes' => '12', 'enabled' => 1],
    ['rowid' => 4, 'site' => 'main', 'option_name' => 'plugin_cache', 'bytes' => 12.0, 'enabled' => 1],
    ['rowid' => 5, 'site' => 'network', 'option_name' => 'Plugin_Cache', 'bytes' => '12.00', 'enabled' => 1],
    ['rowid' => 6, 'site' => 'network', 'option_name' => '_transient_feed', 'bytes' => 30, 'enabled' => 0],
    ['rowid' => 7, 'site' => 'network', 'option_name' => '_transient_feed_timeout', 'bytes' => 30, 'enabled' => '1'],
    ['rowid' => 8, 'site' => 'main', 'option_name' => null, 'bytes' => null, 'enabled' => 1],
];

$nameCursor = new SQLiteVdbeAggregateDistinctCursor($rows, 'option_name', 'option_name', 'enabled', 'G', ['NOCASE']);
$byteCursor = new SQLiteVdbeAggregateDistinctCursor($rows, 'bytes', 'bytes', 'enabled', 'C');
$compositeCursor = new SQLiteVdbeAggregateDistinctCursor($rows, ['site', 'option_name'], 'rowid', 'enabled', 'GG', ['BINARY', 'NOCASE']);

$nameCurrent = $nameCursor->current();
$nameCursor->next();
$nameNext = $nameCursor->current();

echo json_encode([
    'scenario' => 'application-vdbe-sorter-distinct-collation',
    'nameDistinctValues' => $nameCursor->values(),
    'nameCurrentKey' => $nameCurrent['key'] ?? null,
    'nameNextKey' => $nameNext['key'] ?? null,
    'numericByteDistinctValues' => $byteCursor->values(),
    'numericByteSum' => $byteCursor->sum(),
    'compositeDistinctRowids' => $compositeCursor->values(),
    'summary' => $nameCursor->summary(count($rows)),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
