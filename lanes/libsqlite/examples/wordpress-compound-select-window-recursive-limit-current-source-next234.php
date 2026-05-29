<?php

declare(strict_types=1);

foreach (glob(dirname(__DIR__) . '/src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$current = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 130],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 112],
    ['option_id' => 3, 'option_name' => 'plugin_old', 'autoload' => 'yes', 'score' => 94],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 40],
];
$next = [
    ...$current,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 124],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 105],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 144)
    UNION ALL
    SELECT option_id, option_name, score
      FROM wp_options
     WHERE autoload = 'yes'
       AND score >= 100
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 8
      FROM q
     WHERE id < 8
     ORDER BY score DESC
     LIMIT 7 OFFSET 1
)
SELECT id, label, row_number() OVER (ORDER BY score DESC) AS metric FROM q
UNION
SELECT option_id AS id,
       option_name AS label,
       first_value(score) OVER (
           PARTITION BY autoload
           ORDER BY score DESC, option_id
           ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING
       ) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT id, label, metric FROM (
    SELECT id, label, row_number() OVER (ORDER BY score DESC) AS metric FROM q
    UNION
    SELECT option_id AS id,
           option_name AS label,
           first_value(score) OVER (
               PARTITION BY autoload
               ORDER BY score DESC, option_id
               ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING
           ) AS metric
      FROM wp_options
     WHERE autoload = 'yes'
)
EXCEPT
SELECT id, label, metric FROM (
    SELECT id, label, row_number() OVER (ORDER BY score DESC) AS metric FROM q WHERE id = 4
    UNION
    SELECT option_id AS id,
           option_name AS label,
           first_value(score) OVER (
               PARTITION BY autoload
               ORDER BY score DESC, option_id
               ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING
           ) AS metric
      FROM wp_options
     WHERE option_name = 'plugin_old'
)
 ORDER BY metric DESC, id
 LIMIT 6 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext234(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);

$payload = [
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-next234',
    'wordpressUse' => 'Copied wp_options preview queries can keep multi-anchor recursive CTE rows sourced from the current option table fenced before next-source autoload rows shift window output and the final compound LIMIT page.',
    'status' => $plan['status'],
    'currentLabels' => $plan['sourceWindow']['currentAdmittedLabels'],
    'nextOnlyLabels' => $plan['sourceWindow']['nextOnlyAdmittedLabels'],
    'skippedAnchorRows' => $plan['recursiveQueue']['currentSkippedLabels'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($payload['status'] !== 'compound-select-window-recursive-limit-current-source-next234-ready') {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next234 self-test failed\n");
    exit(1);
}
if ($payload['skippedAnchorRows'] !== ['seed']) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next234 anchor offset guard failed\n");
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
