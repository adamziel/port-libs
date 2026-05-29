<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$current = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 120],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 95],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 72],
];
$next = [
    ...$current,
    ['option_id' => 4, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 112],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 132)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 9
      FROM q
     WHERE id < 8
     ORDER BY score DESC
     LIMIT 7 OFFSET 1
)
SELECT id, label,
       sum(score) OVER (ORDER BY score DESC, id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric
  FROM q
UNION ALL
SELECT option_id AS id, option_name AS label,
       count(*) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT option_id AS id, option_name AS label, 1 AS metric
  FROM wp_options
 WHERE option_name = 'home'
UNION
SELECT id, label, score AS metric
  FROM q
 ORDER BY metric DESC, id
 LIMIT 6 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext209(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);

echo json_encode([
    'status' => $plan['status'],
    'current' => $plan['sourceWindow']['currentAdmittedLabels'],
    'nextTokenChanged' => $plan['sourceWindow']['currentToken'] !== $plan['sourceWindow']['nextToken'],
    'windows' => $plan['windows']['aggregateFunctions'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
