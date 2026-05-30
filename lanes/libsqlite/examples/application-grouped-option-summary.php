<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteGroupedAggregate;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['autoload' => 'yes', 'option_name' => 'siteurl', 'bytes' => 20],
    ['autoload' => 'yes', 'option_name' => 'home', 'bytes' => 20],
    ['autoload' => 'yes', 'option_name' => 'blogname', 'bytes' => 9],
    ['autoload' => 'no', 'option_name' => '_transient_feed', 'bytes' => 12],
    ['autoload' => 'no', 'option_name' => 'empty_cache_key', 'bytes' => 0],
    ['autoload' => 'no', 'option_name' => 'legacy_null', 'bytes' => null],
    ['autoload' => null, 'option_name' => 'orphaned', 'bytes' => 3],
    ['autoload' => null, 'option_name' => 'orphaned-again', 'bytes' => 7],
];

$summary = SQLiteGroupedAggregate::summarize($rows, 'autoload', 'bytes');

echo json_encode([
    'applicationUse' => 'Preview copied wp_options GROUP BY autoload summaries with count(), sum(), avg(), min(), max(), group_concat(), HAVING, and ORDER BY result semantics without requiring the SQLite extension.',
    'orderedByGroup' => SQLiteGroupedAggregate::orderBy($summary, 'group'),
    'havingAtLeastThreeRows' => SQLiteGroupedAggregate::havingCountAtLeast($summary, 3),
    'havingLargeByteGroups' => SQLiteGroupedAggregate::havingSumGreaterThan($summary, 12),
    'orderedByTotalBytesDescending' => SQLiteGroupedAggregate::orderBy($summary, 'sum', 'DESC'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
