<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTextAggregate;
use PortLibs\LibSqlite\SQLiteTextAggregateState;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['siteurl', 'https://example.test', 'yes', 20],
    ['home', 'https://example.test', 'yes', 10],
    ['blogname', 'Demo Site', 'yes', 30],
    ['_transient_feed', 'cached', 'no', 40],
    ['siteurl', 'https://example.test', 'yes', 50],
    ['unused', null, 'no', 60],
];

$state = new SQLiteTextAggregateState();
foreach ($rows as [$name]) {
    $state->step($name);
    $state->stepDistinct($name);
}

$ordered = [];
$filtered = [];
foreach ($rows as [$name, $value, $autoload, $sort]) {
    $ordered[] = [$name, $sort];
    $filtered[] = [$name, $autoload === 'yes' ? 1 : 0];
}

echo json_encode([
    'applicationUse' => 'Summarize copied wp_options names with SQLite group_concat()/string_agg() aggregate semantics, including DISTINCT, ORDER BY, FILTER-style autoload selection, NULL skipping, and bounded window previews without requiring the SQLite extension.',
    'groupConcat' => $state->finalize('|'),
    'distinctOptionNames' => $state->finalizeDistinct('|'),
    'orderedOptionNames' => SQLiteTextAggregate::groupConcatOrderBy($ordered, '|'),
    'autoloadOptionNames' => SQLiteTextAggregate::groupConcatFilter($filtered, '|'),
    'emptyGroup' => SQLiteTextAggregate::groupConcat([null, null]),
    'rollingPairs' => SQLiteTextAggregate::groupConcatWindow(array_column($rows, 0), 1, 0, '|'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
