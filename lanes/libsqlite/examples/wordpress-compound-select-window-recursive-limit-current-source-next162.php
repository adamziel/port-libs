<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 30],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 28],
        ['option_id' => 3, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 24],
        ['option_id' => 4, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'weight' => 18],
    ],
];
$nextTables = [
    'wp_options' => [
        ...$currentTables['wp_options'],
        ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 36],
        ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 17],
    ],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, weight) AS (
    VALUES (1, 'seed', 34)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), weight - 4
      FROM q
     WHERE id < 8
     LIMIT 6 OFFSET 1
)
SELECT id,
       label,
       lead(weight, 1, weight) OVER (ORDER BY id) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       lead(weight, 1, weight) OVER (ORDER BY weight DESC, option_id) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT option_id AS id,
       option_name AS label,
       weight AS metric
  FROM wp_options
 WHERE option_name = 'theme_mods'
 ORDER BY metric DESC, id
 LIMIT 6 OFFSET 1
SQL;

echo json_encode(
    SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext162($sql, $currentTables, $nextTables),
    JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
) . PHP_EOL;
