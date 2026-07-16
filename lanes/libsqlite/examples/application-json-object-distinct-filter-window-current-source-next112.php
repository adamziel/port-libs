<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonAggregate;
use PortLibs\LibSqlite\SQLiteJsonB;

$jsonbRules = new SQLiteBlobValue(SQLiteJsonB::encode(['source' => 'plugin', 'enabled' => true]));

$rows = [
    ['siteurl', 'https://example.test', 1, 1],
    ['blogname', 'Port Libs', 2, 1],
    ['siteurl', 'https://example.test', 3, 1],
    ['plugin_rules', $jsonbRules, 4, 1],
    ['plugin_rules', $jsonbRules, 5, 1],
    ['theme_rules', ['source' => 'theme'], 6, 0],
    ['empty_option', null, 7, 1],
];

$frames = SQLiteJsonAggregate::jsonGroupObjectDistinctWindowFrameRows($rows, 1, 2);
$jsonbFrames = SQLiteJsonAggregate::jsonGroupObjectDistinctWindowFrameRowsSqlFunction('jsonb_group_object', $rows, 1, 1);

$summary = [
    'scenario' => 'application-json-object-distinct-filter-window-current-source-next112',
    'sqlShape' => 'json_group_object(DISTINCT option_name, payload) FILTER (WHERE enabled) OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND 2 FOLLOWING)',
    'applicationUse' => 'Copied wp_options diagnostics can build per-row option maps while removing exact duplicate option/value pairs, honoring FILTER before DISTINCT, and preserving JSONB plugin settings without ext/sqlite.',
    'firstFrame' => $frames[0],
    'pluginFrame' => $frames[4],
    'pluginJsonbFrame' => SQLiteJsonB::decode($jsonbFrames[4]->bytes),
    'dependency' => 'native PHP JSON aggregate window helpers; no new support component required',
];

if ($summary['firstFrame'] !== '{"siteurl":"https://example.test","blogname":"Port Libs"}') {
    fwrite(STDERR, "unexpected first JSON object window frame\n");
    exit(1);
}
if ($summary['pluginFrame'] !== '{"plugin_rules":{"source":"plugin","enabled":true},"empty_option":null}') {
    fwrite(STDERR, "unexpected plugin JSON object window frame\n");
    exit(1);
}
if ($summary['pluginJsonbFrame'] != ['plugin_rules' => ['source' => 'plugin', 'enabled' => true]]) {
    fwrite(STDERR, "unexpected plugin JSONB object window frame\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
