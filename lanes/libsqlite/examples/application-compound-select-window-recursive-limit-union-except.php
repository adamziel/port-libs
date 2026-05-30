<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 60],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 55],
        ['option_id' => 3, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 45],
        ['option_id' => 4, 'option_name' => 'obsolete_cache', 'autoload' => 'no', 'weight' => 30],
    ],
];
$nextTables = [
    'wp_options' => [
        ...$currentTables['wp_options'],
        ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 68],
        ['option_id' => 6, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'weight' => 42],
    ],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, weight) AS (
    VALUES (1, 'seed', 70)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), weight - 4
      FROM q
     WHERE id < 8
     LIMIT 6 OFFSET 1
)
SELECT id,
       label,
       row_number() OVER (ORDER BY weight DESC, id) AS metric
  FROM q
UNION
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (ORDER BY weight DESC) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT id,
       label,
       metric
  FROM (
        SELECT option_id AS id,
               option_name AS label,
               dense_rank() OVER (ORDER BY weight DESC) AS metric
          FROM wp_options
         WHERE option_name LIKE 'theme%'
        UNION ALL
        SELECT id,
               label,
               row_number() OVER (ORDER BY weight DESC, id) AS metric
          FROM q
         WHERE id = 2
  )
 ORDER BY metric, id
 LIMIT 5 OFFSET 1
SQL;

echo json_encode(
    SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareUnionExceptRecursiveWindowLimit($sql, $currentTables, $nextTables),
    JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
) . PHP_EOL;
