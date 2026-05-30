<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteNumericAggregate;
use PortLibs\LibSqlite\SQLiteNumericAggregateState;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['siteurl', 'https://example.test', 'yes'],
    ['home', 'https://example.test', 'yes'],
    ['blogname', 'Demo Site', 'yes'],
    ['_transient_feed', str_repeat('x', 12), 'no'],
    ['empty_cache_key', '', 'no'],
    ['legacy_null', null, 'no'],
];

$lengthState = new SQLiteNumericAggregateState();
$autoloadLengths = [];
foreach ($rows as [$name, $value, $autoload]) {
    $length = $value === null ? null : strlen($value);
    $lengthState->step($length);
    $lengthState->stepDistinct($length);
    $lengthState->stepFilter($length, $autoload === 'yes' ? 1 : 0);
    $lengthState->stepWindow($length);
    $autoloadLengths[] = [$length, $autoload === 'yes' ? 1 : 0];
}

echo json_encode([
    'applicationUse' => 'Summarize copied wp_options value sizes with SQLite count/sum/total/avg/min/max aggregate semantics, including NULL skipping, DISTINCT counting, FILTER-style autoload selection, and bounded rolling totals without requiring the SQLite extension.',
    'rowCount' => SQLiteNumericAggregate::countAll($rows),
    'nonNullValueCount' => $lengthState->countValue(),
    'distinctValueLengths' => $lengthState->countDistinct(),
    'totalValueBytes' => $lengthState->sum(),
    'distinctValueBytes' => $lengthState->sumDistinct(),
    'averageValueBytes' => $lengthState->avg(),
    'averageDistinctValueBytes' => $lengthState->avgDistinct(),
    'totalDistinctValueBytes' => $lengthState->totalDistinct(),
    'autoloadValueBytes' => SQLiteNumericAggregate::sumFilter($autoloadLengths),
    'minimumValueBytes' => $lengthState->min(),
    'maximumValueBytes' => $lengthState->max(),
    'rollingValueBytes' => $lengthState->totalWindowed(1),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
