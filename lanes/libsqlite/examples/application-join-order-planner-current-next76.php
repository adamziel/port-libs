<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAnalyzeStatPlanner.php';
require_once __DIR__ . '/../src/SQLiteJoinOrderPlan.php';

use PortLibs\LibSqlite\SQLiteJoinOrderPlan;

$statRows = [
    ['tbl' => 'wp_posts', 'idx' => null, 'stat' => '30000'],
    ['tbl' => 'wp_posts', 'idx' => 'wp_posts_id', 'stat' => '30000 1'],
    ['tbl' => 'wp_posts', 'idx' => 'wp_posts_type_status_date', 'stat' => '30000 6000 1000 80'],
    ['tbl' => 'wp_postmeta', 'idx' => null, 'stat' => '240000'],
    ['tbl' => 'wp_postmeta', 'idx' => 'wp_postmeta_post_id', 'stat' => '240000 40'],
    ['tbl' => 'wp_postmeta', 'idx' => 'wp_postmeta_key', 'stat' => '240000 800'],
    ['tbl' => 'wp_postmeta', 'idx' => 'wp_postmeta_post_key', 'stat' => '240000 40 2'],
];

$indexes = [
    ['name' => 'wp_posts_id', 'table' => 'wp_posts', 'columns' => ['ID'], 'unique' => true],
    ['name' => 'wp_posts_type_status_date', 'table' => 'wp_posts', 'columns' => ['post_type', 'post_status', 'post_date']],
    ['name' => 'wp_postmeta_post_id', 'table' => 'wp_postmeta', 'columns' => ['post_id']],
    ['name' => 'wp_postmeta_key', 'table' => 'wp_postmeta', 'columns' => ['meta_key']],
    ['name' => 'wp_postmeta_post_key', 'table' => 'wp_postmeta', 'columns' => ['post_id', 'meta_key']],
];

$plan = SQLiteJoinOrderPlan::choose(
    $statRows,
    $indexes,
    ['wp_posts', 'wp_postmeta'],
    [
        'wp_posts' => [
            ['column' => 'post_type', 'operator' => '=', 'value' => 'post'],
            ['column' => 'post_status', 'operator' => '=', 'value' => 'publish'],
            ['column' => 'post_date', 'operator' => '>=', 'value' => '2026-01-01'],
        ],
        'wp_postmeta' => [
            ['column' => 'meta_key', 'operator' => '=', 'value' => '_thumbnail_id'],
        ],
    ],
    [
        ['leftTable' => 'wp_posts', 'leftColumn' => 'ID', 'rightTable' => 'wp_postmeta', 'rightColumn' => 'post_id'],
    ],
);

if (($plan['tables'] ?? []) !== ['wp_posts', 'wp_postmeta']) {
    fwrite(STDERR, "application-join-order-planner-current-next76 self-test failed\n");
    exit(1);
}

echo json_encode([
    'scenario' => 'application-join-order-planner-current-next76',
    'applicationUse' => 'Preview copied wp_posts/wp_postmeta nested-loop order from sqlite_stat1-style evidence before import or archive-generation queries run without ext/sqlite.',
    'tables' => $plan['tables'],
    'indexes' => array_column($plan['loops'], 'index'),
    'estimatedRows' => $plan['estimatedRows'],
    'detail' => $plan['detail'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
