<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonAggregateState;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

$state = new SQLiteJsonAggregateState();
$state->stepArrayWindowFrame('siteurl', 10, 1);
$state->stepArrayWindowFrame(new SQLiteJsonSubtypeValue('{"plugin":"seo","autoload":true}'), 20, true);
$state->stepArrayWindowFrame('home', 20, 0);
$state->stepArrayWindowFrame(new SQLiteBlobValue(SQLiteJsonB::encode(['cache' => 'primed'])), 30, '1');

$state->stepObjectWindowFrame('siteurl', 'https://example.test', 10, 1);
$state->stepObjectWindowFrame('rules', new SQLiteJsonSubtypeValue('{"plugin":"seo","autoload":true}'), 20, true);
$state->stepObjectWindowFrame('home', 'https://example.test/home', 20, 0);
$state->stepObjectWindowFrame('cache', new SQLiteBlobValue(SQLiteJsonB::encode(['cache' => 'primed'])), 30, '1');

$jsonbFrames = $state->finalizeWindowFrameObject(0, 1, 'CURRENT ROW', 'jsonb_group_object');

$summary = [
    'array_current_next' => $state->finalizeWindowFrameArray(0, 1),
    'array_exclude_ties' => $state->finalizeWindowFrameArray(0, 1, 'TIES'),
    'array_groups_current_next' => $state->finalizeWindowFrameArrayByUnit('GROUPS', 0, 1),
    'array_range_current_next' => $state->finalizeWindowFrameArrayByUnit('RANGE', 0, 10),
    'object_exclude_current_row' => $state->finalizeWindowFrameObject(0, 1, 'CURRENT ROW'),
    'object_groups_exclude_group' => $state->finalizeWindowFrameObjectByUnit('GROUPS', 0, 1, 'GROUP'),
    'jsonb_object_exclude_current_row' => array_map(
        static fn (SQLiteBlobValue $frame): mixed => SQLiteJsonB::decode($frame->bytes),
        $jsonbFrames,
    ),
    'state_rows' => [
        'array' => $state->summary()['windowArrayFrameRows'],
        'object' => $state->summary()['windowObjectFrameRows'],
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['array_current_next'][0] !== '["siteurl",{"plugin":"seo","autoload":true}]') {
        fwrite(STDERR, "unexpected first array frame\n");
        exit(1);
    }
    if ($summary['object_exclude_current_row'][0] !== '{"rules":{"plugin":"seo","autoload":true}}') {
        fwrite(STDERR, "unexpected object exclusion frame\n");
        exit(1);
    }
    if ($summary['array_groups_current_next'][1] !== '[{"plugin":"seo","autoload":true},{"cache":"primed"}]') {
        fwrite(STDERR, "unexpected GROUPS current-next array frame\n");
        exit(1);
    }
    if ($summary['object_groups_exclude_group'][1] !== '{"cache":{"cache":"primed"}}') {
        fwrite(STDERR, "unexpected GROUPS exclude-group object frame\n");
        exit(1);
    }
    if ($summary['jsonb_object_exclude_current_row'][2] !== ['cache' => ['cache' => 'primed']]) {
        fwrite(STDERR, "unexpected JSONB decoded frame\n");
        exit(1);
    }
    echo "application-json-aggregate-window-current-next self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
