<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundWindowRecursiveAffinityCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 1, 'priority' => 50],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 1.0, 'priority' => 40],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => '1', 'priority' => 30],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 2, 'priority' => 20],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'no', 'weight' => '2', 'priority' => 10],
    ['option_id' => 6, 'option_name' => 'plugin_beta', 'autoload' => 'yes', 'weight' => 3, 'priority' => 5],
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
WITH RECURSIVE option_walk(item_id, key_value, source, score) AS MATERIALIZED (
    VALUES (1, 1, 'seed', 50)
    UNION
    SELECT wp_option_edges.dst, wp_option_edges.weight, 'edge', score - 7
      FROM wp_option_edges JOIN option_walk ON wp_option_edges.src = item_id
     WHERE item_id < 6
    UNION
    SELECT item_id, key_value + 0.0, source, score
      FROM option_walk
     WHERE item_id = 1
)
SELECT item_id AS id,
       key_value,
       source,
       sum(score) FILTER (WHERE key_value = 1) OVER (
           ORDER BY item_id, source
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS window_score
  FROM option_walk
UNION
SELECT option_id AS id,
       weight AS key_value,
       option_name AS source,
       sum(priority) FILTER (WHERE autoload = 'no') OVER (
           ORDER BY option_id
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS window_score
  FROM wp_options
 WHERE option_id IN (SELECT item_id FROM option_walk)
 ORDER BY id, key_value, source
SQL;

$first = SQLiteCompoundWindowRecursiveAffinityCurrentSourceNextPlan::pageNext147(
    $sql,
    ['wp_options' => $currentOptions, 'wp_option_edges' => $currentEdges],
    ['wp_options' => $nextOptions, 'wp_option_edges' => $nextEdges],
    4,
);
$second = SQLiteCompoundWindowRecursiveAffinityCurrentSourceNextPlan::pageNext147(
    $sql,
    ['wp_options' => $currentOptions, 'wp_option_edges' => $currentEdges],
    ['wp_options' => $nextOptions, 'wp_option_edges' => $nextEdges],
    4,
    4,
    $first['cursor'],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($first['status'] === 'compound-window-recursive-affinity-current-source-next147-ready');
    assert($first['currentPageIds'] === [1, 1, 2, 2]);
    assert($second['nextPageIds'] === [3, 3, 4, 4]);
    assert($first['sourceDelta']['newSources'] === ['plugin_alpha', 'plugin_beta']);
    echo "wordpress-compound-window-recursive-affinity-current-source-next147 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-compound-window-recursive-affinity-current-source-next147',
    'sqlShape' => 'WITH RECURSIVE ... UNION ... SELECT window(...) FROM cte UNION SELECT window(...) FROM wp_options ORDER BY left-most columns LIMIT page resume',
    'wordpressUse' => 'Copied wp_options repair/import diagnostics can page through recursive dependency walks while preserving UNION numeric-affinity deduplication, per-arm window evaluation, left-most compound output names, and stale current/next source cursor rejection.',
    'firstPage' => [
        'currentIds' => $first['currentPageIds'],
        'nextIds' => $first['nextPageIds'],
        'sources' => $first['currentPageSources'],
    ],
    'secondPage' => [
        'currentIds' => $second['currentPageIds'],
        'nextIds' => $second['nextPageIds'],
    ],
    'cursor' => $first['cursor'],
    'sourceDelta' => $first['sourceDelta'],
    'dependencies' => $first['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
