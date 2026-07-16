<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectAffinityRecursiveOrderCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'parent_id' => 0, 'sort_key' => '10', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'parent_id' => 1, 'sort_key' => 2, 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin_alpha', 'parent_id' => 1, 'sort_key' => '1', 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => 'theme_child', 'parent_id' => 2, 'sort_key' => 3, 'autoload' => 'yes'],
    ['option_id' => 5, 'option_name' => 'string_child', 'parent_id' => 2, 'sort_key' => '0', 'autoload' => 'no'],
    ['option_id' => 50, 'option_name' => 'direct_numeric', 'parent_id' => -1, 'sort_key' => 1.25, 'autoload' => 'no'],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 6, 'option_name' => 'plugin_beta', 'parent_id' => 1, 'sort_key' => 1.5, 'autoload' => 'yes'],
    ['option_id' => 7, 'option_name' => 'plugin_beta_child', 'parent_id' => 6, 'sort_key' => '2', 'autoload' => 'no'],
];

$sql = <<<'SQL'
WITH RECURSIVE walk(id, name, sort_key, depth) AS (
    SELECT option_id, option_name, sort_key, 0
      FROM wp_options
     WHERE parent_id = 0
    UNION ALL
    SELECT child.option_id, child.option_name, child.sort_key, walk.depth + 1
      FROM wp_options AS child
      JOIN walk ON child.parent_id = walk.id
     WHERE walk.depth < 3
     ORDER BY 3 ASC, 1 ASC
     LIMIT 8
)
SELECT name, sort_key, depth, 'walk' AS source
  FROM walk
UNION ALL
SELECT option_name AS name, sort_key, 0 AS depth, 'direct' AS source
  FROM wp_options
 WHERE parent_id = -1
 ORDER BY sort_key ASC, name ASC
 LIMIT 6
SQL;

$plan = SQLiteCompoundSelectAffinityRecursiveOrderCurrentSourceNextPlan::compareRecursiveAffinityOrder(
    $sql,
    ['wp_options' => $currentOptions],
    ['wp_options' => $nextOptions],
);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options hierarchy rows where recursive queue ORDER BY must keep numeric sort keys before text keys before the final compound SELECT order is applied.',
    'status' => $plan['status'],
    'currentRecursiveNames' => $plan['recursive']['currentVisitedNames'],
    'nextRecursiveNames' => $plan['recursive']['nextVisitedNames'],
    'currentFinalNames' => array_column($plan['currentRows'], 'name'),
    'nextFinalNames' => array_column($plan['nextRows'], 'name'),
    'replanReasons' => $plan['replanReasons'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
