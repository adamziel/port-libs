<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes'],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes'],
        ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no'],
        ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no'],
        ['option_id' => 6, 'option_name' => 'rewrite_rules', 'autoload' => 'yes'],
    ],
    'edges' => [
        ['src' => 1, 'dst' => 2, 'kind' => 'core'],
        ['src' => 2, 'dst' => 3, 'kind' => 'core'],
        ['src' => 2, 'dst' => 5, 'kind' => 'cache'],
        ['src' => 3, 'dst' => 4, 'kind' => 'cache'],
        ['src' => 5, 'dst' => 6, 'kind' => 'cache'],
    ],
];

$sql = "
WITH RECURSIVE wanted(id, source) AS MATERIALIZED (
    VALUES (1, 'core')
    UNION
    VALUES (4, 'cache')
    UNION
    SELECT edges.dst, wanted.source
      FROM edges JOIN wanted ON edges.src = wanted.id
     WHERE wanted.source IN ('core', 'cache')
    UNION
    SELECT edges.dst, edges.kind
      FROM edges JOIN wanted ON edges.src = wanted.id
     WHERE edges.kind = 'cache'
)
SELECT DISTINCT option_name
  FROM wp_options JOIN wanted ON wanted.id = wp_options.option_id
 ORDER BY option_id";

echo json_encode(array_column(SQLiteSelectSql::execute($sql, $tables), 'option_name'), JSON_PRETTY_PRINT) . PHP_EOL;
