<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectRecursiveAffinityLimitPlan;

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteCoreScalarFunction.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteGroupedAggregate.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonValidity.php';
require_once __DIR__ . '/../src/SQLiteSelectCompound.php';
require_once __DIR__ . '/../src/SQLiteSelectExpression.php';
require_once __DIR__ . '/../src/SQLiteSelectPredicate.php';
require_once __DIR__ . '/../src/SQLiteSelectProjection.php';
require_once __DIR__ . '/../src/SQLiteSelectQuery.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteSelectSql.php';
require_once __DIR__ . '/../src/SQLiteCompoundSelectRecursiveAffinityLimitPlan.php';

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'rank_value' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'rank_value' => '1'],
    ['option_id' => 3, 'option_name' => 'active_plugins', 'autoload' => 'no', 'rank_value' => 2],
    ['option_id' => 4, 'option_name' => 'theme_mods', 'autoload' => 'no', 'rank_value' => '2'],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 5, 'option_name' => 'plugin_cache', 'autoload' => 'no', 'rank_value' => 3],
    ['option_id' => 6, 'option_name' => 'plugin_cache_text', 'autoload' => 'no', 'rank_value' => '3'],
];
$currentEdges = [
    ['src' => 1, 'dst' => 2, 'weight' => 1.0],
    ['src' => 2, 'dst' => 3, 'weight' => '2'],
    ['src' => 3, 'dst' => 4, 'weight' => 2.0],
];
$nextEdges = [
    ...$currentEdges,
    ['src' => 4, 'dst' => 5, 'weight' => 3.0],
    ['src' => 5, 'dst' => 6, 'weight' => '3'],
];

$sql = <<<'SQL'
WITH RECURSIVE option_walk(item_id, key_value, source) AS MATERIALIZED (
    VALUES (1, 1, 'seed')
    UNION
    SELECT wp_option_edges.dst, wp_option_edges.weight, 'edge'
      FROM wp_option_edges JOIN option_walk ON wp_option_edges.src = item_id
     WHERE item_id < 8
    UNION
    SELECT item_id, key_value + 0.0, source
      FROM option_walk
     WHERE item_id = 1
)
SELECT item_id AS id,
       key_value,
       source
  FROM option_walk
UNION
SELECT option_id AS id,
       rank_value AS key_value,
       option_name AS source
  FROM wp_options
 ORDER BY id, key_value, source
 LIMIT 5 OFFSET 1
SQL;

$summary = SQLiteCompoundSelectRecursiveAffinityLimitPlan::compareRecursiveAffinityLimit(
    $sql,
    ['wp_options' => $currentOptions, 'wp_option_edges' => $currentEdges],
    ['wp_options' => $nextOptions, 'wp_option_edges' => $nextEdges],
);

return [
    'applicationUse' => 'Preview recursive option dependency walks during wp_options imports where UNION duplicate handling keeps numeric and text storage classes distinct before final ORDER BY/LIMIT pagination.',
    'status' => $summary['status'],
    'currentRows' => $summary['currentRows'],
    'nextRows' => $summary['nextRows'],
    'nextDeferredBoundary' => array_slice($summary['nextUnlimitedRows'], -4),
    'affinityChangedClasses' => $summary['affinity']['changedKeyClasses'],
    'replanReasons' => $summary['replanReasons'],
];
