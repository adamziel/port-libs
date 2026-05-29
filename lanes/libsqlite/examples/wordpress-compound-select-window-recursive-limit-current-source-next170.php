<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundExceptWindowRecursiveLimitCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 40],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 35],
        ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'weight' => 26],
        ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 31],
    ],
];
$nextTables = [
    'wp_options' => [
        ...$currentTables['wp_options'],
        ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 44],
        ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 24],
    ],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, weight) AS (
    VALUES (1, 'seed', 42)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), weight - 4
      FROM q
     WHERE id < 8
     LIMIT 5 OFFSET 2
)
SELECT id,
       label,
       dense_rank() OVER (ORDER BY weight DESC) AS bucket
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (PARTITION BY autoload ORDER BY weight DESC, option_id) AS bucket
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT option_id AS id,
       option_name AS label,
       2 AS bucket
  FROM wp_options
 WHERE option_name IN ('home', 'rewrite_rules')
 ORDER BY bucket, id
 LIMIT 5 OFFSET 1
SQL;

echo json_encode(
    SQLiteCompoundExceptWindowRecursiveLimitCurrentSourceNextPlan::compareNext170($sql, $currentTables, $nextTables),
    JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
) . PHP_EOL;
