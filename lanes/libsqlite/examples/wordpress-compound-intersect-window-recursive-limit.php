<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundIntersectWindowRecursiveLimitCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 80],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 70],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 60],
    ['option_id' => 4, 'option_name' => 'cache', 'autoload' => 'no', 'weight' => 50],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 40],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'siteurl', 100)
    UNION ALL
    SELECT id + 1,
           CASE id + 1
             WHEN 2 THEN 'home'
             WHEN 3 THEN 'blogname'
             WHEN 4 THEN 'cache'
             WHEN 5 THEN 'plugin_alpha'
             ELSE 'extra'
           END,
           score - 10
      FROM q
     WHERE id < 6
     ORDER BY 3 DESC
     LIMIT 5
)
SELECT id,
       label,
       row_number() OVER (ORDER BY score DESC, id) AS win
  FROM q
INTERSECT
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (ORDER BY weight DESC, option_id) AS win
  FROM wp_options
 ORDER BY win, id
 LIMIT 3 OFFSET 2
SQL;

$plan = SQLiteCompoundIntersectWindowRecursiveLimitCurrentSourceNextPlan::compareIntersectWindowRecursiveLimit(
    $sql,
    ['wp_options' => $currentOptions],
    ['wp_options' => $nextOptions],
);

echo json_encode([
    'status' => $plan['status'],
    'current_labels' => array_column($plan['currentRows'], 'label'),
    'next_labels' => array_column($plan['nextRows'], 'label'),
    'replan_reasons' => $plan['replanReasons'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
