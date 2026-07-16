<?php

declare(strict_types=1);

foreach (glob(dirname(__DIR__) . '/src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$current = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
];
$next = [
    ...$current,
    ['option_id' => 6, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 140)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 8
     LIMIT 5 OFFSET 2
)
SELECT id, label, dense_rank() OVER (ORDER BY score DESC) AS rn FROM q
UNION
SELECT option_id AS id, option_name AS label, dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS rn
  FROM wp_options WHERE autoload = 'yes'
EXCEPT
SELECT option_id AS id, option_name AS label, dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS rn
  FROM wp_options WHERE option_name IN ('siteurl')
 ORDER BY rn, label
 LIMIT 3 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareUnionExceptDenseRankLimit(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);

$payload = [
    'scenario' => 'application-compound-select-window-recursive-limit-current-source-union-except-dense-rank-limit',
    'applicationUse' => 'Copied wp_options preview queries can reprepare when a staged option changes dense_rank() output across a UNION DISTINCT / EXCEPT compound page after recursive dependency rows are bounded.',
    'status' => $plan['status'],
    'currentLabels' => array_column($plan['currentRows'], 'label'),
    'nextLabels' => array_column($plan['nextRows'], 'label'),
    'tokenChanged' => $plan['sourceWindow']['currentToken'] !== $plan['sourceWindow']['nextToken'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($payload['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-union-except-dense-rank-limit-ready') {
    fwrite(STDERR, "application-compound-select-window-recursive-limit-current-source-union-except-dense-rank-limit self-test failed\n");
    exit(1);
}
if ($payload['tokenChanged'] !== true || $payload['nextLabels'] !== ['plugin_prime', 'seed:2:3:4', 'home']) {
    fwrite(STDERR, "application-compound-select-window-recursive-limit-current-source-union-except-dense-rank-limit boundary guard failed\n");
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
