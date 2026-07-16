<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateFromSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Example Site', 'autoload' => 'yes', 'bytes' => 9],
    ['option_id' => 4, 'option_name' => 'legacy_name', 'option_value' => 'legacy', 'autoload' => 'no', 'bytes' => 6],
];
$staged = [
    ['option_id' => 2, 'new_name' => 'home_preview', 'new_value' => 'draft-home', 'seq' => 1],
    ['option_id' => 2, 'new_name' => 'home_current', 'new_value' => 'current-home', 'seq' => 2],
    ['option_id' => 3, 'new_name' => 'legacy_name', 'new_value' => 'current-blog', 'seq' => 3],
];

$sql = "UPDATE OR REPLACE wp_options SET option_name = staged_options.new_name, option_value = staged_options.new_value, autoload = 'no', bytes = length(staged_options.new_value) FROM staged_options WHERE staged_options.option_id = wp_options.option_id";
$result = SQLiteUpdateFromSql::execute(
    $sql,
    ['wp_options' => $options, 'staged_options' => $staged],
    [],
    [['option_name']],
);

echo json_encode([
    'applicationUse' => 'Preview UPDATE FROM staging-row imports for wp_options where duplicate staged rows use SQLite current last-match behavior and OR REPLACE deletes current option_name conflicts without requiring ext/sqlite.',
    'sql' => $sql,
    'changes' => $result['changes'],
    'updatedNames' => array_column($result['updated_rows'], 'option_name'),
    'deletedNames' => array_column($result['deleted_rows'], 'option_name'),
    'afterNames' => array_column($result['after'], 'option_name'),
    'after' => $result['after'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
