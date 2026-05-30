<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => '_transient_feed', 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => 'theme_mods', 'autoload' => null],
];

$metadata = [
    ['meta_option_id' => 1, 'meta_value' => 'yes', 'source' => 'core'],
    ['meta_option_id' => 2, 'meta_value' => 'yes', 'source' => 'core'],
    ['meta_option_id' => 3, 'meta_value' => 'no', 'source' => 'cache'],
    ['meta_option_id' => 4, 'meta_value' => null, 'source' => 'theme'],
];

$matched = SQLiteSelectSql::execute(
    "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload) IN (SELECT meta_option_id, meta_value FROM option_meta WHERE source IN ('core', 'cache')) ORDER BY id",
    ['wp_options' => $options, 'option_meta' => $metadata],
);

$notMatched = SQLiteSelectSql::execute(
    "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload) NOT IN (SELECT meta_option_id, meta_value FROM option_meta WHERE source IN ('core', 'cache')) ORDER BY id",
    ['wp_options' => $options, 'option_meta' => $metadata],
);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options rows filtered by SQLite row-value IN/NOT IN subqueries over option metadata without requiring ext/sqlite.',
    'matchedNames' => array_column($matched, 'name'),
    'notMatchedNames' => array_column($notMatched, 'name'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
