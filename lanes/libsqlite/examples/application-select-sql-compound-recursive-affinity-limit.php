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
    ['setting_id' => 1, 'key_name' => 'base_url', 'load_policy' => 'yes', 'rank_value' => 1],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'load_policy' => 'yes', 'rank_value' => '1'],
    ['setting_id' => 3, 'key_name' => 'module_registry', 'load_policy' => 'no', 'rank_value' => 2],
    ['setting_id' => 4, 'key_name' => 'theme_variant', 'load_policy' => 'no', 'rank_value' => '2'],
];
$nextOptions = [
    ...$currentOptions,
    ['setting_id' => 5, 'key_name' => 'module_cache', 'load_policy' => 'no', 'rank_value' => 3],
    ['setting_id' => 6, 'key_name' => 'module_cache_text', 'load_policy' => 'no', 'rank_value' => '3'],
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
WITH RECURSIVE setting_walk(item_id, key_value, source) AS MATERIALIZED (
    VALUES (1, 1, 'seed')
    UNION
    SELECT app_setting_edges.dst, app_setting_edges.weight, 'edge'
      FROM app_setting_edges JOIN setting_walk ON app_setting_edges.src = item_id
     WHERE item_id < 8
    UNION
    SELECT item_id, key_value + 0.0, source
      FROM setting_walk
     WHERE item_id = 1
)
SELECT item_id AS id,
       key_value,
       source
  FROM setting_walk
UNION
SELECT setting_id AS id,
       rank_value AS key_value,
       key_name AS source
  FROM app_settings
 ORDER BY id, key_value, source
 LIMIT 5 OFFSET 1
SQL;

$summary = SQLiteCompoundSelectRecursiveAffinityLimitPlan::compareRecursiveAffinityLimit(
    $sql,
    ['app_settings' => $currentOptions, 'app_setting_edges' => $currentEdges],
    ['app_settings' => $nextOptions, 'app_setting_edges' => $nextEdges],
);

return [
    'applicationUse' => 'Preview recursive option dependency walks during app_settings imports where UNION duplicate handling keeps numeric and text storage classes distinct before final ORDER BY/LIMIT pagination.',
    'status' => $summary['status'],
    'currentRows' => $summary['currentRows'],
    'nextRows' => $summary['nextRows'],
    'nextDeferredBoundary' => array_slice($summary['nextUnlimitedRows'], -4),
    'affinityChangedClasses' => $summary['affinity']['changedKeyClasses'],
    'replanReasons' => $summary['replanReasons'],
];
