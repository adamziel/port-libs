<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteCreateIndex.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLiteMultiColumnRangePlan.php';
require_once __DIR__ . '/../src/SQLiteStat4RangeOrderCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteStat4RangeOrderCurrentSourceNextPlan;

$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$prepared = [
    'name' => 'prepared-wp-options',
    'schemaCookie' => 101,
    'stat4Generation' => 3,
    'indexes' => [[
        'name' => 'idx_wp_options_name_autoload_old',
        'rootPage' => 301,
        'estimatedRows' => 80,
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['active_plugins', 'yes']],
            ['neq' => '3 3', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['home', 'yes']],
            ['neq' => '8 8', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['transient_feed', 'no']],
            ['neq' => '2 2', 'nlt' => '12 12', 'ndlt' => '3 3', 'sample' => ['widget_recent', 'yes']],
        ],
        'sql' => 'CREATE INDEX idx_wp_options_name_autoload_old ON wp_options(option_name, autoload, option_value)',
    ]],
];

$current = $prepared;
$current['name'] = 'current-wp-options';
$current['schemaCookie'] = 102;
$current['stat4Generation'] = 4;
$current['indexes'][0]['name'] = 'idx_wp_options_name_autoload_current';
$current['indexes'][0]['rootPage'] = 302;
$current['indexes'][0]['stat4Samples'] = [
    ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['active_plugins', 'yes']],
    ['neq' => '2 2', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['home', 'yes']],
    ['neq' => '24 24', 'nlt' => '3 3', 'ndlt' => '2 2', 'sample' => ['transient_feed', 'no']],
    ['neq' => '5 5', 'nlt' => '27 27', 'ndlt' => '3 3', 'sample' => ['widget_recent', 'yes']],
];

$plan = SQLiteStat4RangeOrderCurrentSourceNextPlan::materializeRangeOrderCursorTape(
    $prepared,
    $current,
    $and($range('option_name', '>=', 'home'), $range('option_name', '<', 'transient_timeout')),
    [['column' => 'option_name']],
    ['option_name', 'autoload', 'option_value'],
);

echo json_encode([
    'scenario' => 'wordpress planner range order stat4 current-source next102',
    'selectedSource' => $plan['selectedSource'],
    'reprepareRequired' => $plan['reprepareRequired'],
    'indexName' => $plan['cursorTape']['indexName'],
    'rootPage' => $plan['cursorTape']['rootPage'],
    'seekOpcode' => $plan['cursorTape']['seekOpcode'],
    'stopOpcode' => $plan['cursorTape']['stopOpcode'],
    'stat4LowerCurrent' => $plan['cursorTape']['stat4LowerCurrent'],
    'stat4UpperCurrent' => $plan['cursorTape']['stat4UpperCurrent'],
    'programOpcodes' => array_column($plan['cursorTape']['program'], 'opcode'),
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT) . PHP_EOL;
