<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundRecursiveAffinityWindowCurrentSourceNextPlan;

$currentTables = [
    'app_settings' => [
        ['setting_id' => 1, 'key_name' => 'base_url', 'weight' => 1, 'load_policy' => 'yes'],
        ['setting_id' => 2, 'key_name' => 'landing_url', 'weight' => 1.0, 'load_policy' => 'yes'],
        ['setting_id' => 3, 'key_name' => 'site_title', 'weight' => '1', 'load_policy' => 'yes'],
        ['setting_id' => 4, 'key_name' => 'module_registry', 'weight' => 2, 'load_policy' => 'no'],
    ],
    'app_setting_edges' => [
        ['src' => 1, 'dst' => 2, 'weight' => 1.0],
        ['src' => 2, 'dst' => 3, 'weight' => '1'],
        ['src' => 3, 'dst' => 4, 'weight' => 2],
    ],
];
$nextTables = $currentTables;
$nextTables['app_settings'][] = ['setting_id' => 5, 'key_name' => 'new_module_flag', 'weight' => '2', 'load_policy' => 'no'];
$nextTables['app_setting_edges'][] = ['src' => 4, 'dst' => 5, 'weight' => '2'];

$sql = <<<'SQL'
WITH RECURSIVE wanted(node, weight) AS MATERIALIZED (
    VALUES (1, 1)
    UNION
    SELECT app_setting_edges.dst, app_setting_edges.weight
      FROM app_setting_edges JOIN wanted ON app_setting_edges.src = node
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
SELECT setting_id AS id,
       weight AS class_value,
       sum(CAST(weight AS REAL)) FILTER (WHERE load_policy = 'no') OVER (
           ORDER BY setting_id
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS weighted_total
  FROM app_settings
 WHERE setting_id IN (SELECT node FROM wanted)
 ORDER BY id, class_value
SQL;

$summary = SQLiteCompoundRecursiveAffinityWindowCurrentSourceNextPlan::compareRecursiveAffinityWindow($sql, $currentTables, $nextTables);

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['status'] === 'compound-recursive-affinity-window-current-source-ready');
    assert(array_column($summary['nextRows'], 'id') === [1, 1, 2, 2, 3, 4, 4, 5, 5]);
    assert($summary['recursive']['currentSkipped'] === [['node' => 1, 'weight' => 1.0]]);
    assert(in_array('compound-window-source', $summary['replanReasons'], true));
    echo "application-compound-recursive-affinity-window-current-source self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
