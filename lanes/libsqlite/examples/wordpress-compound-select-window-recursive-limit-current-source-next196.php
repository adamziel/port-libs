<?php

declare(strict_types=1);

foreach (glob(dirname(__DIR__) . '/src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$current = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 118],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 94],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 76],
];
$next = [
    ...$current,
    ['option_id' => 4, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 109],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 136)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 8
     ORDER BY score DESC
     LIMIT 6 OFFSET 1
)
SELECT id, label, ntile(4) OVER (ORDER BY score DESC, id) AS metric FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       first_value(score) OVER (
           PARTITION BY autoload
           ORDER BY score DESC, option_id
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
UNION
SELECT id, label, score AS metric FROM q
 ORDER BY metric DESC, id
 LIMIT 6 OFFSET 2
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNtileFirstValueUnionDistinct(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);

$payload = [
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-ntileFirstValueUnionDistinct',
    'wordpressUse' => 'Copied wp_options preview queries can run recursive dependency queues, evaluate ntile and first_value windows before UNION distinct and final LIMIT, and reject stale current-source resume cursors before next-source rows are admitted.',
    'status' => $plan['status'],
    'windowFunctions' => $plan['windows']['functions'],
    'ntileBuckets' => $plan['windows']['ntileBuckets'],
    'currentAdmittedLabels' => $plan['sourceWindow']['currentAdmittedLabels'],
    'nextOnlyAdmittedLabels' => $plan['sourceWindow']['nextOnlyAdmittedLabels'],
    'currentTokenLength' => strlen($plan['sourceWindow']['currentToken']),
    'nextTokenLength' => strlen($plan['sourceWindow']['nextToken']),
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($payload['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-ntileFirstValueUnionDistinct-ready') {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-ntileFirstValueUnionDistinct self-test failed\n");
    exit(1);
}
if ($payload['windowFunctions'] !== ['ntile', 'first_value']) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-ntileFirstValueUnionDistinct window dispatch failed\n");
    exit(1);
}
if ($payload['currentTokenLength'] !== 64 || $payload['nextTokenLength'] !== 64) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-ntileFirstValueUnionDistinct token guard failed\n");
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
