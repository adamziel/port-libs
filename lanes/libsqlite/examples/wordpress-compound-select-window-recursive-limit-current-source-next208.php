<?php

declare(strict_types=1);

foreach (glob(dirname(__DIR__) . '/src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$current = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 120],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 95],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 70],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 38],
];
$next = [
    ...$current,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 112],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 84],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 140)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 9
     ORDER BY score DESC
     LIMIT 8 OFFSET 1
)
SELECT id, label, rank() OVER (ORDER BY score DESC, id) AS metric FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (
           PARTITION BY autoload
           ORDER BY score DESC, option_id
       ) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT id, label, metric
  FROM (
       SELECT id, label, rank() OVER (ORDER BY score DESC, id) AS metric
         FROM q
        WHERE id = 2
       UNION ALL
       SELECT option_id AS id,
              option_name AS label,
              dense_rank() OVER (
                  PARTITION BY autoload
                  ORDER BY score DESC, option_id
              ) AS metric
         FROM wp_options
        WHERE option_name = 'rewrite_rules'
  )
 ORDER BY metric DESC, id
 LIMIT 6 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext208(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);

$payload = [
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-next208',
    'wordpressUse' => 'Copied wp_options preview queries can combine recursive dependency queues with rank and dense_rank windows, then fence current-source resume cursors across an EXCEPT membership boundary before next-source rows shift the final LIMIT.',
    'status' => $plan['status'],
    'windowFunctions' => $plan['windows']['functions'],
    'rankingFunctions' => $plan['windows']['rankingFunctions'],
    'currentAdmittedLabels' => $plan['sourceWindow']['currentAdmittedLabels'],
    'nextOnlyAdmittedLabels' => $plan['sourceWindow']['nextOnlyAdmittedLabels'],
    'currentTokenLength' => strlen($plan['sourceWindow']['currentToken']),
    'nextTokenLength' => strlen($plan['sourceWindow']['nextToken']),
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($payload['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-next208-ready') {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next208 self-test failed\n");
    exit(1);
}
if ($payload['windowFunctions'] !== ['rank', 'dense_rank']) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next208 window dispatch failed\n");
    exit(1);
}
if ($payload['currentTokenLength'] !== 64 || $payload['nextTokenLength'] !== 64) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next208 token guard failed\n");
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
