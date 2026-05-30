<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJsonExtract.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonQuote.php';
require_once __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require_once __DIR__ . '/../src/SQLiteJsonConstructor.php';
require_once __DIR__ . '/../src/SQLiteJsonAggregate.php';
require_once __DIR__ . '/../src/SQLiteJsonAggregateState.php';

use PortLibs\LibSqlite\SQLiteJsonAggregateState;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

$rows = [
    ['seo', 'enabled', 10, 1],
    ['cache', new SQLiteJsonSubtypeValue('{"ttl":60}'), 20, 1],
    ['seo', 'enabled', 20, 1],
    ['theme', 'twentytwenty', 30, 1],
    ['cache', new SQLiteJsonSubtypeValue('{"ttl":60}'), 30, 0],
    ['seo', 'disabled', 40, 1],
];

$state = new SQLiteJsonAggregateState();
foreach ($rows as $row) {
    $state->stepObjectWindowFrame($row[0], $row[1], $row[2], $row[3]);
}

$frames = $state->finalizeDistinctOrderedWindowFrameObjectByUnit('GROUPS', 0, 1);
$jsonbFrames = $state->finalizeDistinctOrderedWindowFrameObjectByUnit('ROWS', 0, 2, 'NO OTHERS', 'jsonb_group_object');

$summary = [
    'scenario' => 'application-json-aggregate-distinct-object-window-current-next81',
    'description' => 'Copied wp_options plugin setting rows summarized with json_group_object(DISTINCT label,value ORDER BY rank) over current/next window frames.',
    'groupsCurrentNext' => $frames,
    'firstJsonbRowsFrame' => SQLiteJsonB::decode($jsonbFrames[0]->bytes),
    'dependencies' => [
        'sqlite-json-group-object-distinct-window-current-next81',
        'sqlite-json-window-frame-rows-groups-range',
        'sqlite-jsonb-aggregate-dispatch',
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['groupsCurrentNext'][0] === '{"seo":"enabled","cache":{"ttl":60}}');
    assert($summary['groupsCurrentNext'][1] === '{"cache":{"ttl":60},"seo":"enabled","theme":"twentytwenty"}');
    assert($summary['firstJsonbRowsFrame'] === ['seo' => 'enabled', 'cache' => ['ttl' => 60]]);
    echo "application-json-aggregate-distinct-object-window-current-next81 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
