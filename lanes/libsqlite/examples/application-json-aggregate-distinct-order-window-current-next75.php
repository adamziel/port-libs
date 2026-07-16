<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonAggregateState;
use PortLibs\LibSqlite\SQLiteJsonB;

$state = new SQLiteJsonAggregateState();
foreach ([
    ['seo_rules', 10, 1],
    ['cache_rules', 20, 1],
    ['seo_rules', 20, 1],
    ['theme_mods', 30, 1],
    ['seo_rules', 30, 1],
    ['home', 40, 1],
] as $row) {
    $state->stepArrayWindowFrame($row[0], $row[1], $row[2]);
}

$jsonFrames = $state->finalizeDistinctOrderedWindowFrameArrayByUnit('GROUPS', 0, 1);
$jsonbFrames = $state->finalizeDistinctOrderedWindowFrameArrayByUnit('GROUPS', 0, 1, 'TIES', 'jsonb_group_array');

$summary = [
    'groupsCurrentNextDistinctOrder' => $jsonFrames,
    'groupsCurrentNextExcludeTiesJsonbDecoded' => array_map(
        static fn (SQLiteBlobValue $frame): array => SQLiteJsonB::decode($frame->bytes),
        $jsonbFrames,
    ),
    'applicationUse' => 'Copied wp_options/plugin setting names can be previewed with json_group_array(DISTINCT name ORDER BY priority) over current/next window frames without ext/sqlite.',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['groupsCurrentNextDistinctOrder'][1] !== '["cache_rules","seo_rules","theme_mods"]') {
        fwrite(STDERR, "unexpected DISTINCT ORDER BY current-next JSON frame\n");
        exit(1);
    }
    if ($summary['groupsCurrentNextExcludeTiesJsonbDecoded'][2] !== ['seo_rules', 'theme_mods']) {
        fwrite(STDERR, "unexpected DISTINCT ORDER BY JSONB exclude-ties frame\n");
        exit(1);
    }
    echo "application-json-aggregate-distinct-order-window-current-next75 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
