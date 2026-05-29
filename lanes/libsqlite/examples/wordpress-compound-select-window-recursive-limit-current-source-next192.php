<?php

declare(strict_types=1);

foreach (glob(dirname(__DIR__) . '/src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$current = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 120],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 104],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 75],
];
$next = [
    ...$current,
    ['option_id' => 4, 'option_name' => 'plugin_loaded', 'autoload' => 'yes', 'score' => 112],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 132)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 8
      FROM q
     WHERE id < 9
     ORDER BY score DESC
     LIMIT 6 OFFSET 1
)
SELECT id, label, percent_rank() OVER (ORDER BY score DESC, id) AS metric FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       cume_dist() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
UNION
SELECT id, label, score AS metric FROM q
 ORDER BY metric DESC, id
 LIMIT 5 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext192(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);

$payload = [
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-next192',
    'wordpressUse' => 'Copied wp_options preview queries can combine recursive dependency queues with percent_rank/cume_dist window arms and reject stale current-source resume tokens before staged next-source autoload rows affect the final compound LIMIT.',
    'status' => $plan['status'],
    'distributionFunctions' => $plan['windows']['distributionFunctions'],
    'recursiveQueueFronts' => array_slice($plan['recursiveQueue']['currentQueueFronts'], 0, 3),
    'currentTokenLength' => strlen($plan['sourceWindow']['currentToken']),
    'nextTokenLength' => strlen($plan['sourceWindow']['nextToken']),
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($payload['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-next192-ready') {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next192 self-test failed\n");
    exit(1);
}
if ($payload['distributionFunctions'] !== ['percent_rank', 'cume_dist']) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next192 distribution windows failed\n");
    exit(1);
}
if ($payload['currentTokenLength'] !== 64 || $payload['nextTokenLength'] !== 64) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next192 token guard failed\n");
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
