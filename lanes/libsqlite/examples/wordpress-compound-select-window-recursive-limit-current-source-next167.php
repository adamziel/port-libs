<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 30],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 26],
        ['option_id' => 3, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 20],
        ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 10],
    ],
];
$nextTables = [
    'wp_options' => [
        ...$currentTables['wp_options'],
        ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 37],
        ['option_id' => 6, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'weight' => 18],
    ],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, weight) AS (
    VALUES (1, 'seed', 34)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), weight - 3
      FROM q
     WHERE id < 8
     LIMIT 6 OFFSET 1
)
SELECT id,
       label,
       lag(weight, 1, weight) OVER (ORDER BY id) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       lead(weight, 1, weight) OVER (ORDER BY weight DESC, option_id) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT id,
       label,
       metric
  FROM (
        SELECT id,
               label,
               lag(weight, 1, weight) OVER (ORDER BY id) AS metric
          FROM q
        UNION ALL
        SELECT option_id AS id,
               option_name AS label,
               lead(weight, 1, weight) OVER (ORDER BY weight DESC, option_id) AS metric
          FROM wp_options
         WHERE autoload = 'yes'
  )
 ORDER BY metric DESC, id
 LIMIT 5 OFFSET 1
SQL;

echo json_encode(
    SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext167($sql, $currentTables, $nextTables),
    JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
) . PHP_EOL;
