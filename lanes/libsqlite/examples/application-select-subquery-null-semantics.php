<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bytes' => 110],
    ['option_id' => 6, 'option_name' => 'orphaned', 'autoload' => null, 'bytes' => null],
];

$metadata = [
    ['meta_option_id' => 1, 'meta_key' => 'public', 'meta_value' => '1'],
    ['meta_option_id' => 2, 'meta_key' => 'public', 'meta_value' => null],
    ['meta_option_id' => 4, 'meta_key' => 'expired', 'meta_value' => null],
    ['meta_option_id' => 5, 'meta_key' => 'plugin', 'meta_value' => 'cache'],
    ['meta_option_id' => null, 'meta_key' => 'dangling', 'meta_value' => null],
];

$notInWithNull = "SELECT option_name FROM wp_options WHERE option_id NOT IN (SELECT meta_option_id FROM option_meta) ORDER BY option_id";
$notInWithoutNull = "SELECT option_name FROM wp_options WHERE option_id NOT IN (SELECT meta_option_id FROM option_meta WHERE meta_option_id IS NOT NULL) ORDER BY option_id";
$existsNullProjection = "SELECT option_name FROM wp_options WHERE EXISTS (SELECT meta_value FROM option_meta WHERE meta_option_id = option_id AND meta_value IS NULL) ORDER BY option_id";

echo json_encode([
    'applicationUse' => 'Preview copied wp_options rows with SQLite EXISTS and NOT IN NULL semantics, proving null-bearing subqueries filter unknown NOT IN results without requiring ext/sqlite.',
    'notInWithNull' => array_column(SQLiteSelectSql::execute($notInWithNull, ['wp_options' => $options, 'option_meta' => $metadata]), 'option_name'),
    'notInWithoutNull' => array_column(SQLiteSelectSql::execute($notInWithoutNull, ['wp_options' => $options, 'option_meta' => $metadata]), 'option_name'),
    'existsNullProjection' => array_column(SQLiteSelectSql::execute($existsNullProjection, ['wp_options' => $options, 'option_meta' => $metadata]), 'option_name'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
