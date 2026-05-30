<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAnalyzeStatPlanner.php';
require_once __DIR__ . '/../src/SQLiteJoinOrderPlan.php';

use PortLibs\LibSqlite\SQLiteJoinOrderPlan;

$statRows = [
    ['tbl' => 'wp_options', 'idx' => null, 'stat' => '12000'],
    ['tbl' => 'wp_options', 'idx' => 'wp_options_name', 'stat' => '12000 1'],
    ['tbl' => 'wp_postmeta', 'idx' => null, 'stat' => '240000'],
    ['tbl' => 'wp_postmeta', 'idx' => 'wp_postmeta_key', 'stat' => '240000 800'],
    ['tbl' => 'wp_postmeta', 'idx' => 'wp_postmeta_post_id', 'stat' => '240000 40'],
    ['tbl' => 'wp_posts', 'idx' => null, 'stat' => '30000'],
    ['tbl' => 'wp_posts', 'idx' => 'wp_posts_id', 'stat' => '30000 1'],
];

$indexes = [
    ['name' => 'wp_options_name', 'table' => 'wp_options', 'columns' => ['option_name'], 'unique' => true],
    ['name' => 'wp_postmeta_key', 'table' => 'wp_postmeta', 'columns' => ['meta_key']],
    ['name' => 'wp_postmeta_post_id', 'table' => 'wp_postmeta', 'columns' => ['post_id']],
    ['name' => 'wp_posts_id', 'table' => 'wp_posts', 'columns' => ['ID'], 'unique' => true],
];

$leftJoinPlan = SQLiteJoinOrderPlan::choose(
    $statRows,
    $indexes,
    ['wp_posts', 'wp_postmeta'],
    ['wp_postmeta' => [['column' => 'meta_key', 'operator' => '=', 'value' => '_thumbnail_id']]],
    [[
        'leftTable' => 'wp_posts',
        'leftColumn' => 'ID',
        'rightTable' => 'wp_postmeta',
        'rightColumn' => 'post_id',
        'joinType' => 'LEFT',
    ]],
);

$crossJoinPlan = SQLiteJoinOrderPlan::choose(
    $statRows,
    $indexes,
    ['wp_options', 'wp_postmeta'],
    ['wp_postmeta' => [['column' => 'meta_key', 'operator' => '=', 'value' => '_thumbnail_id']]],
    [[
        'leftTable' => 'wp_options',
        'rightTable' => 'wp_postmeta',
        'joinType' => 'CROSS',
    ]],
);

if (($leftJoinPlan['tables'] ?? []) !== ['wp_posts', 'wp_postmeta'] || ($crossJoinPlan['tables'] ?? []) !== ['wp_options', 'wp_postmeta']) {
    fwrite(STDERR, "application-select-planner-join-fences self-test failed\n");
    exit(1);
}

echo json_encode([
    'scenario' => 'application-select-planner-join-fences',
    'applicationUse' => 'Copied Application import diagnostics can preview SQLite planner join fences for LEFT and CROSS joins without incorrectly moving a selective nullable/source table ahead of the preserved side.',
    'leftJoinTables' => $leftJoinPlan['tables'],
    'leftJoinFence' => $leftJoinPlan['loops'][1]['joinFence'],
    'crossJoinTables' => $crossJoinPlan['tables'],
    'crossJoinFence' => $crossJoinPlan['loops'][1]['joinFence'],
    'dependencyClosure' => 'no new support component needed; reuses native sqlite_stat1 planner metadata and join-order planner primitives',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
