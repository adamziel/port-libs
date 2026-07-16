<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Example Site', 'autoload' => 'yes', 'bytes' => 9],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'option_value' => 'cached', 'autoload' => 'no', 'bytes' => 12],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'option_value' => 'plugins', 'autoload' => 'no', 'bytes' => 110],
    ['option_id' => 6, 'option_name' => 'orphaned', 'option_value' => null, 'autoload' => null, 'bytes' => 3],
];

$sql = "SELECT option_id, lower(option_name) AS normalized_name, coalesce(autoload, 'missing') AS autoload_key, bytes FROM wp_options WHERE autoload = 'yes' AND bytes >= 9 ORDER BY bytes DESC, normalized_name ASC LIMIT 3";
$rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options]);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options rows from bounded SQLite SELECT text through the native query-plan executor, including projection aliases, scalar functions, WHERE predicates, ORDER BY, and LIMIT without requiring ext/sqlite.',
    'sql' => $sql,
    'selectedOptionIds' => array_column($rows, 'option_id'),
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
