<?php

declare(strict_types=1);

foreach (glob(dirname(__DIR__) . '/src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$current = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 120],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 120],
    ['option_id' => 3, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'score' => 90],
];
$next = [
    ...$current,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 120],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 140)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 8
     ORDER BY score DESC
     LIMIT 6 OFFSET 1
)
SELECT id, label, rank() OVER (ORDER BY score DESC) AS metric FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT id, label, metric
  FROM (
        SELECT id, label, rank() OVER (ORDER BY score DESC) AS metric FROM q
        UNION ALL
        SELECT option_id AS id,
               option_name AS label,
               dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS metric
          FROM wp_options
         WHERE autoload = 'yes'
       )
 ORDER BY metric ASC, id
 LIMIT 6 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRankDenseRankIntersect(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);

$payload = [
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-rankDenseRankIntersect',
    'wordpressUse' => 'Copied wp_options preview queries can rank recursive dependency rows and dense-rank autoload rows, intersect only materialized window output, and reject stale current-source cursors before next-source rows alter the final LIMIT boundary.',
    'status' => $plan['status'],
    'windowFunctions' => $plan['windows']['functions'],
    'rankingFunctions' => $plan['windows']['rankingFunctions'],
    'currentAdmittedLabels' => $plan['sourceWindow']['currentAdmittedLabels'],
    'nextOnlyAdmittedLabels' => $plan['sourceWindow']['nextOnlyAdmittedLabels'],
    'currentTokenLength' => strlen($plan['sourceWindow']['currentToken']),
    'nextTokenLength' => strlen($plan['sourceWindow']['nextToken']),
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($payload['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-rankDenseRankIntersect-ready') {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-rankDenseRankIntersect self-test failed\n");
    exit(1);
}
if ($payload['rankingFunctions'] !== ['rank', 'dense_rank']) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-rankDenseRankIntersect window dispatch failed\n");
    exit(1);
}
if ($payload['currentTokenLength'] !== 64 || $payload['nextTokenLength'] !== 64) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-rankDenseRankIntersect token guard failed\n");
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
