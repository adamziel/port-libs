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
    ['option_id' => 6, 'option_name' => 'orphaned', 'autoload' => null, 'bytes' => 3],
];

$meta = [
    ['option_id' => 1, 'source' => 'core', 'priority' => 10],
    ['option_id' => 2, 'source' => 'core', 'priority' => 20],
    ['option_id' => 3, 'source' => 'theme', 'priority' => 30],
    ['option_id' => 5, 'source' => 'plugin', 'priority' => 40],
];

$sql = "WITH hot_options AS (SELECT option_id, option_name, bytes FROM wp_options WHERE bytes >= 9), ranked_meta AS (SELECT option_id, source, priority FROM option_meta WHERE priority >= 20) SELECT hot_options.option_name AS name, ranked_meta.source AS source, ranked_meta.priority AS priority FROM hot_options JOIN ranked_meta ON hot_options.option_id = ranked_meta.option_id ORDER BY priority DESC LIMIT 3";
$rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options, 'option_meta' => $meta]);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options rows through bounded SQLite WITH CTE text execution, materializing hot options and ranked metadata before native join/order/limit dispatch without ext/sqlite.',
    'sql' => $sql,
    'selectedOptions' => array_column($rows, 'name'),
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
