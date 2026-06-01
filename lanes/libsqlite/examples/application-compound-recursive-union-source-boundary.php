<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundRecursiveAffinityWindowCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOptions = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'load_policy' => 'yes', 'weight' => 1, 'priority' => 50],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'load_policy' => 'yes', 'weight' => 1.0, 'priority' => 40],
    ['setting_id' => 3, 'key_name' => 'site_title', 'load_policy' => 'yes', 'weight' => '1', 'priority' => 30],
    ['setting_id' => 4, 'key_name' => 'module_registry', 'load_policy' => 'no', 'weight' => 2, 'priority' => 20],
];
$nextOptions = [
    ...$currentOptions,
    ['setting_id' => 5, 'key_name' => 'module_alpha', 'load_policy' => 'no', 'weight' => '2', 'priority' => 10],
    ['setting_id' => 6, 'key_name' => 'module_beta', 'load_policy' => 'yes', 'weight' => 3, 'priority' => 5],
];
$currentEdges = [
    ['src' => 1, 'dst' => 2, 'weight' => 1.0],
    ['src' => 2, 'dst' => 3, 'weight' => '1'],
    ['src' => 3, 'dst' => 4, 'weight' => 2],
];
$nextEdges = [
    ...$currentEdges,
    ['src' => 4, 'dst' => 5, 'weight' => '2'],
    ['src' => 5, 'dst' => 6, 'weight' => 3],
];

$sql = <<<'SQL'
WITH RECURSIVE setting_walk(item_id, key_value, source, score) AS MATERIALIZED (
    VALUES (1, 1, 'seed', 50)
    UNION
    SELECT app_setting_edges.dst, app_setting_edges.weight, 'edge', score - 7
      FROM app_setting_edges JOIN setting_walk ON app_setting_edges.src = item_id
     WHERE item_id < 6
    UNION
    SELECT item_id, key_value + 0.0, source, score
      FROM setting_walk
     WHERE item_id = 1
)
SELECT item_id AS id,
       key_value,
       source,
       sum(score) FILTER (WHERE key_value = 1) OVER (
           ORDER BY item_id, source
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS window_score
  FROM setting_walk
UNION
SELECT setting_id AS id,
       weight AS key_value,
       key_name AS source,
       sum(priority) FILTER (WHERE load_policy = 'no') OVER (
           ORDER BY setting_id
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS window_score
  FROM app_settings
 WHERE setting_id IN (SELECT item_id FROM setting_walk)
 ORDER BY id, key_value, source
SQL;

$plan = SQLiteCompoundRecursiveAffinityWindowCurrentSourceNextPlan::compareRecursiveUnionSourceBoundary(
    $sql,
    ['app_settings' => $currentOptions, 'app_setting_edges' => $currentEdges],
    ['app_settings' => $nextOptions, 'app_setting_edges' => $nextEdges],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'compound-recursive-affinity-window-current-source-source-boundary-ready');
    assert($plan['sourceDelta']['newSources'] === ['module_alpha', 'module_beta']);
    assert(array_column($plan['recursive']['nextRows'], 'item_id') === [1, 2, 3, 4, 5, 6]);
    echo "application-compound-recursive-affinity-window-current-source-source-boundary self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-compound-recursive-affinity-window-current-source-source-boundary',
    'sqlShape' => 'WITH RECURSIVE ... UNION ... SELECT window(...) FROM cte UNION SELECT window(...) FROM app_settings ORDER BY left-most columns',
    'applicationUse' => 'Copied app_settings repair/import diagnostics can compare current and next option dependency walks while preserving recursive UNION numeric-affinity deduplication, per-arm window evaluation, and left-most compound output names before committing import changes.',
    'currentRows' => $plan['currentRows'],
    'nextRows' => $plan['nextRows'],
    'sourceDelta' => $plan['sourceDelta'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
