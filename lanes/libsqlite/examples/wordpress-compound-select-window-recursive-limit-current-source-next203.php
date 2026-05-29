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
];
$next = [
    ...$current,
    ['option_id' => 4, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 112],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 140)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 9
      FROM q
     WHERE id < 9
     ORDER BY score DESC
     LIMIT 7 OFFSET 1
)
SELECT id, label, lag(score, 1, -1) OVER (ORDER BY score DESC, id) AS metric FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       last_value(score) OVER (
           PARTITION BY autoload
           ORDER BY score DESC, option_id
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT id, label, score AS metric FROM q
 ORDER BY metric DESC, id
 LIMIT 6 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext203(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);

$payload = [
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-next203',
    'wordpressUse' => 'Copied wp_options preview queries can run recursive dependency queues, evaluate lag defaults and last_value frames before an EXCEPT tail and final LIMIT, and reject stale current-source resume cursors before next-source rows are admitted.',
    'status' => $plan['status'],
    'windowFunctions' => $plan['windows']['functions'],
    'lagDefaults' => $plan['windows']['lagDefaults'],
    'currentAdmittedLabels' => $plan['sourceWindow']['currentAdmittedLabels'],
    'nextOnlyAdmittedLabels' => $plan['sourceWindow']['nextOnlyAdmittedLabels'],
    'currentTokenLength' => strlen($plan['sourceWindow']['currentToken']),
    'nextTokenLength' => strlen($plan['sourceWindow']['nextToken']),
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($payload['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-next203-ready') {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next203 self-test failed\n");
    exit(1);
}
if ($payload['windowFunctions'] !== ['lag', 'last_value']) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next203 window dispatch failed\n");
    exit(1);
}
if ($payload['currentTokenLength'] !== 64 || $payload['nextTokenLength'] !== 64) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next203 token guard failed\n");
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
