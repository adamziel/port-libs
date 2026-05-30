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

$metadata = [
    ['option_id' => 1, 'source' => 'core', 'priority' => 10],
    ['option_id' => 2, 'source' => 'core', 'priority' => 20],
    ['option_id' => 3, 'source' => 'theme', 'priority' => 30],
    ['option_id' => 5, 'source' => 'plugin', 'priority' => 40],
];

$sql = "SELECT wp_options.option_name AS name, m.source AS source, coalesce(m.priority, 0) AS priority FROM wp_options LEFT JOIN option_meta AS m ON wp_options.option_id = m.option_id WHERE wp_options.option_id >= 3 ORDER BY priority DESC, name ASC LIMIT 4";
$rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options, 'option_meta' => $metadata]);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options rows joined to bounded option metadata from SQLite SELECT text through the native query-plan executor, including LEFT JOIN null-extension, table-qualified predicates, aliases, ORDER BY, and LIMIT without requiring ext/sqlite.',
    'sql' => $sql,
    'selectedNames' => array_column($rows, 'name'),
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
