<?php

declare(strict_types=1);

foreach (glob(dirname(__DIR__) . '/src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$current = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 120],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 95],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 72],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 40],
];
$next = [
    ...$current,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 112],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 84],
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
SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS metric FROM q
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
INTERSECT
SELECT id, label, metric FROM (
    SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS metric FROM q
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
)
EXCEPT
SELECT id, label, metric FROM (
    SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS metric FROM q WHERE id = 3
    UNION ALL
    SELECT option_id AS id,
           option_name AS label,
           last_value(score) OVER (
               PARTITION BY autoload
               ORDER BY score DESC, option_id
               ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
           ) AS metric
      FROM wp_options
     WHERE option_name = 'home'
)
 ORDER BY metric DESC, id
 LIMIT 6 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext210(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);

$payload = [
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-next210',
    'wordpressUse' => 'Copied wp_options preview queries can combine a recursive queue with row_number and last_value window arms, then fence INTERSECT before EXCEPT and final LIMIT/OFFSET with a current-source cursor token.',
    'status' => $plan['status'],
    'currentAdmittedLabels' => $plan['sourceWindow']['currentAdmittedLabels'],
    'nextOnlyAdmittedLabels' => $plan['sourceWindow']['nextOnlyAdmittedLabels'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($payload['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-next210-ready') {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next210 self-test failed\n");
    exit(1);
}
if ($payload['nextOnlyAdmittedLabels'] !== ['plugin_prime', 'theme_mods_next']) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next210 next-source fence failed\n");
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
