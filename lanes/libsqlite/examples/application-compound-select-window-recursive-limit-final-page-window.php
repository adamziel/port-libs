<?php

declare(strict_types=1);

foreach (glob(dirname(__DIR__) . '/src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$current = [
    ['setting_id' => 1, 'key_name' => 'siteurl', 'load_policy' => 'yes', 'score' => 130],
    ['setting_id' => 2, 'key_name' => 'home', 'load_policy' => 'yes', 'score' => 112],
    ['setting_id' => 3, 'key_name' => 'plugin_old', 'load_policy' => 'yes', 'score' => 94],
    ['setting_id' => 4, 'key_name' => 'cache_seed', 'load_policy' => 'no', 'score' => 40],
];
$next = [
    ...$current,
    ['setting_id' => 5, 'key_name' => 'plugin_prime', 'load_policy' => 'yes', 'score' => 124],
    ['setting_id' => 6, 'key_name' => 'theme_mods_next', 'load_policy' => 'yes', 'score' => 105],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 144)
    UNION ALL
    SELECT setting_id, key_name, score
      FROM app_settings
     WHERE load_policy = 'yes'
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
SELECT setting_id AS id,
       key_name AS label,
       first_value(score) OVER (
           PARTITION BY load_policy
           ORDER BY score DESC, setting_id
           ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING
       ) AS metric
  FROM app_settings
 WHERE load_policy = 'yes'
INTERSECT
SELECT id, label, metric FROM (
    SELECT id, label, row_number() OVER (ORDER BY score DESC) AS metric FROM q
    UNION
    SELECT setting_id AS id,
           key_name AS label,
           first_value(score) OVER (
               PARTITION BY load_policy
               ORDER BY score DESC, setting_id
               ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING
           ) AS metric
      FROM app_settings
     WHERE load_policy = 'yes'
)
EXCEPT
SELECT id, label, metric FROM (
    SELECT id, label, row_number() OVER (ORDER BY score DESC) AS metric FROM q WHERE id = 4
    UNION
    SELECT setting_id AS id,
           key_name AS label,
           first_value(score) OVER (
               PARTITION BY load_policy
               ORDER BY score DESC, setting_id
               ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING
           ) AS metric
      FROM app_settings
     WHERE key_name = 'plugin_old'
)
 ORDER BY metric DESC, id
 LIMIT 6 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareFinalPageWindowLimit(
    $sql,
    ['app_settings' => $current],
    ['app_settings' => $next],
);

$payload = [
    'scenario' => 'application-compound-select-window-recursive-limit-current-source-final-page-window-limit',
    'applicationUse' => 'Copied app_settings preview queries can keep multi-anchor recursive CTE rows sourced from the current settings table fenced before next-source load_policy rows shift window output and the final compound LIMIT page.',
    'status' => $plan['status'],
    'currentLabels' => $plan['sourceWindow']['currentAdmittedLabels'],
    'nextOnlyLabels' => $plan['sourceWindow']['nextOnlyAdmittedLabels'],
    'skippedAnchorRows' => $plan['recursiveQueue']['currentSkippedLabels'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($payload['status'] !== 'compound-select-window-recursive-limit-current-source-final-page-window-limit-ready') {
    fwrite(STDERR, "application-compound-select-window-recursive-limit-current-source-final-page-window-limit self-test failed\n");
    exit(1);
}
if ($payload['skippedAnchorRows'] !== ['seed']) {
    fwrite(STDERR, "application-compound-select-window-recursive-limit-current-source-final-page-window-limit anchor offset guard failed\n");
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
