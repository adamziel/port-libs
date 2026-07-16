<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl'],
    ['option_id' => 2, 'option_name' => 'home'],
    ['option_id' => 3, 'option_name' => 'blogname'],
    ['option_id' => 4, 'option_name' => '_transient_feed'],
    ['option_id' => 9, 'option_name' => '_site_transient_update_plugins'],
];
$edges = [
    ['src' => 1, 'dst' => 2],
    ['src' => 2, 'dst' => 3],
    ['src' => 3, 'dst' => 1],
    ['src' => 3, 'dst' => 4],
    ['src' => 4, 'dst' => 4],
];

$sql = 'WITH RECURSIVE reachable(id) AS (VALUES (1), (1) UNION SELECT option_edges.dst FROM option_edges JOIN reachable ON option_edges.src = reachable.id) SELECT wp_options.option_id AS option_id, wp_options.option_name AS option_name FROM wp_options JOIN reachable ON reachable.id = wp_options.option_id ORDER BY wp_options.option_id';
$rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options, 'option_edges' => $edges]);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options dependency traversal through a cycle-safe SQLite WITH RECURSIVE UNION CTE without requiring ext/sqlite.',
    'sql' => $sql,
    'optionNames' => array_column($rows, 'option_name'),
    'rowCount' => count($rows),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
