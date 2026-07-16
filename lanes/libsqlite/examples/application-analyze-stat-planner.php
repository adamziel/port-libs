<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAnalyzeStatPlanner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$statRows = [
    ['tbl' => 'wp_options', 'idx' => null, 'stat' => '10000'],
    ['tbl' => 'wp_options', 'idx' => 'wp_options_autoload', 'stat' => '10000 5000'],
    ['tbl' => 'wp_options', 'idx' => 'wp_options_name', 'stat' => '10000 1'],
    ['tbl' => 'wp_options', 'idx' => 'wp_options_autoload_name', 'stat' => '10000 5000 1'],
    ['tbl' => 'wp_postmeta', 'idx' => null, 'stat' => '250000'],
    ['tbl' => 'wp_postmeta', 'idx' => 'wp_postmeta_post_key', 'stat' => '250000 50 3'],
];

$indexes = [
    ['name' => 'wp_options_autoload', 'table' => 'wp_options', 'columns' => ['autoload']],
    ['name' => 'wp_options_name', 'table' => 'wp_options', 'columns' => ['option_name'], 'unique' => true],
    ['name' => 'wp_options_autoload_name', 'table' => 'wp_options', 'columns' => ['autoload', 'option_name']],
    ['name' => 'wp_postmeta_post_key', 'table' => 'wp_postmeta', 'columns' => ['post_id', 'meta_key']],
];

$plans = [
    'active_plugins lookup' => SQLiteAnalyzeStatPlanner::choose($statRows, $indexes, 'wp_options', [
        ['column' => 'option_name', 'operator' => '=', 'value' => 'active_plugins'],
    ]),
    'transient cleanup range' => SQLiteAnalyzeStatPlanner::choose($statRows, $indexes, 'wp_options', [
        ['column' => 'autoload', 'operator' => '=', 'value' => 'no'],
        ['column' => 'option_name', 'operator' => '>=', 'value' => '_transient_'],
    ]),
    'attached file postmeta lookup' => SQLiteAnalyzeStatPlanner::choose($statRows, $indexes, 'wp_postmeta', [
        ['column' => 'post_id', 'operator' => '=', 'value' => 42],
        ['column' => 'meta_key', 'operator' => '=', 'value' => '_wp_attached_file'],
    ]),
];

echo json_encode([
    'plans' => $plans,
    'applicationUse' => 'Copied Application SQLite databases can reuse ANALYZE sqlite_stat1 cardinality to choose bounded native PHP option/postmeta index scans before row decoding when ext/sqlite is unavailable.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
