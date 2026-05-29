<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 90],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 75],
        ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 60],
        ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 20],
    ],
];
$nextTables = [
    'wp_options' => [
        ...$currentTables['wp_options'],
        ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'score' => 85],
        ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'score' => 65],
    ],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 100)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 6
     LIMIT 5 OFFSET 1
)
SELECT id,
       label,
       row_number() OVER (ORDER BY score DESC, id) AS metric
  FROM q
UNION
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (ORDER BY score DESC) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT id,
       label,
       metric
  FROM (
        SELECT option_id AS id,
               option_name AS label,
               dense_rank() OVER (ORDER BY score DESC) AS metric
          FROM wp_options
         WHERE score >= 60
        UNION ALL
        SELECT id,
               label,
               row_number() OVER (ORDER BY score DESC, id) AS metric
          FROM q
         WHERE id >= 3
  )
 ORDER BY metric, id
 LIMIT 4 OFFSET 1
SQL;

echo json_encode(
    SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext175($sql, $currentTables, $nextTables),
    JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
) . PHP_EOL;
