<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundRecursiveAffinityWindowCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'weight' => 1, 'autoload' => 'yes'],
        ['option_id' => 2, 'option_name' => 'home', 'weight' => 1.0, 'autoload' => 'yes'],
        ['option_id' => 3, 'option_name' => 'blogname', 'weight' => '1', 'autoload' => 'yes'],
        ['option_id' => 4, 'option_name' => 'active_plugins', 'weight' => 2, 'autoload' => 'no'],
    ],
    'wp_option_edges' => [
        ['src' => 1, 'dst' => 2, 'weight' => 1.0],
        ['src' => 2, 'dst' => 3, 'weight' => '1'],
        ['src' => 3, 'dst' => 4, 'weight' => 2],
    ],
];
$nextTables = $currentTables;
$nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'new_plugin_flag', 'weight' => '2', 'autoload' => 'no'];
$nextTables['wp_option_edges'][] = ['src' => 4, 'dst' => 5, 'weight' => '2'];

$sql = <<<'SQL'
WITH RECURSIVE wanted(node, weight) AS MATERIALIZED (
    VALUES (1, 1)
    UNION
    SELECT wp_option_edges.dst, wp_option_edges.weight
      FROM wp_option_edges JOIN wanted ON wp_option_edges.src = node
     WHERE node < 5
    UNION
    SELECT node, weight + 0.0
      FROM wanted
     WHERE node = 1
)
SELECT node AS id,
       weight AS class_value,
       sum(CAST(weight AS REAL)) FILTER (WHERE weight = 1) OVER (
           ORDER BY node
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS weighted_total
  FROM wanted
UNION
SELECT option_id AS id,
       weight AS class_value,
       sum(CAST(weight AS REAL)) FILTER (WHERE autoload = 'no') OVER (
           ORDER BY option_id
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS weighted_total
  FROM wp_options
 WHERE option_id IN (SELECT node FROM wanted)
 ORDER BY id, class_value
SQL;

$summary = SQLiteCompoundRecursiveAffinityWindowCurrentSourceNextPlan::compareRecursiveAffinityWindow($sql, $currentTables, $nextTables);

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['status'] === 'compound-recursive-affinity-window-current-source-ready');
    assert(array_column($summary['nextRows'], 'id') === [1, 1, 2, 2, 3, 4, 4, 5, 5]);
    assert($summary['recursive']['currentSkipped'] === [['node' => 1, 'weight' => 1.0]]);
    assert(in_array('compound-window-source', $summary['replanReasons'], true));
    echo "wordpress-compound-recursive-affinity-window-current-source self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
