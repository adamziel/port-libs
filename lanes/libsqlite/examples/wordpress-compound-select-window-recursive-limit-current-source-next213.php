<?php

declare(strict_types=1);

foreach (glob(dirname(__DIR__) . '/src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$current = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 124],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 96],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 74],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 42],
];
$next = [
    ...$current,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 116],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 86],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 140)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 8
     ORDER BY score DESC
     LIMIT 7 OFFSET 1
)
SELECT id,
       label,
       max(score) OVER (ORDER BY score DESC, id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric,
       min(score) OVER (ORDER BY score DESC, id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS floor_metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       max(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric,
       min(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS floor_metric
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT option_id AS id,
       option_name AS label,
       max(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric,
       min(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS floor_metric
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY metric DESC, id
 LIMIT 3 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareMinMaxIntersectLimit(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);

$payload = [
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-next213',
    'wordpressUse' => 'Copied wp_options preview queries can combine recursive dependency queues with min/max window extrema, then fence current-source resume cursors across an INTERSECT membership boundary before next-source rows shift the final LIMIT.',
    'status' => $plan['status'],
    'windowFunctions' => $plan['windows']['functions'],
    'currentAdmittedLabels' => $plan['sourceWindow']['currentAdmittedLabels'],
    'nextAdmittedLabels' => $plan['sourceWindow']['nextAdmittedLabels'],
    'nextOnlyAdmittedLabels' => $plan['sourceWindow']['nextOnlyAdmittedLabels'],
    'currentTokenLength' => strlen($plan['sourceWindow']['currentToken']),
    'nextTokenLength' => strlen($plan['sourceWindow']['nextToken']),
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($payload['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-next213-ready') {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next213 self-test failed\n");
    exit(1);
}
if (array_values(array_unique($payload['windowFunctions'])) !== ['max', 'min']) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next213 window dispatch failed\n");
    exit(1);
}
if ($payload['currentTokenLength'] !== 64 || $payload['nextTokenLength'] !== 64) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next213 token guard failed\n");
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
