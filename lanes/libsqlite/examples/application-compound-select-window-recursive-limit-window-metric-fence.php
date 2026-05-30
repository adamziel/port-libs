<?php

declare(strict_types=1);

foreach (glob(dirname(__DIR__) . '/src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$current = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 140],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 118],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 96],
    ['option_id' => 4, 'option_name' => 'plugin_old', 'autoload' => 'yes', 'score' => 82],
];
$next = [
    ...$current,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 128],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 107],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 152)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 9
      FROM q
     WHERE id < 9
     ORDER BY score DESC
     LIMIT 8 OFFSET 1
)
SELECT id,
       label,
       avg(score) OVER (
           ORDER BY score DESC
           ROWS BETWEEN 1 PRECEDING AND CURRENT ROW
       ) AS metric
  FROM q
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
SELECT id, label, metric
  FROM (
       SELECT id, label, avg(score) OVER (ORDER BY score DESC ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS metric FROM q
       UNION
       SELECT option_id AS id, option_name AS label, first_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS metric FROM wp_options WHERE autoload = 'yes'
  )
EXCEPT
SELECT id, label, metric
  FROM (
       SELECT id, label, avg(score) OVER (ORDER BY score DESC ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS metric FROM q WHERE id = 4
       UNION
       SELECT option_id AS id, option_name AS label, first_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS metric FROM wp_options WHERE option_name = 'plugin_old'
  )
 ORDER BY metric DESC, id
 LIMIT 6 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareWindowMetricFence(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);

$payload = [
    'scenario' => 'application-compound-select-window-recursive-limit-current-source-window-metric-fence',
    'applicationUse' => 'Copied wp_options preview queries hold next-source compound rows until current-source window metric acknowledgements prove the recursive/window page has not drifted.',
    'status' => $plan['status'],
    'currentMetricLabels' => $plan['windowMetricFenceWindowMetricFence']['currentMetricLabels'],
    'nextMetricLabels' => $plan['windowMetricFenceWindowMetricFence']['nextMetricLabels'],
    'metricDriftLabels' => $plan['windowMetricFenceWindowMetricFence']['metricDriftLabels'],
    'nextOnlyMetricLabels' => $plan['windowMetricFenceWindowMetricFence']['nextOnlyMetricLabels'],
    'requiredMetricAckCount' => $plan['windowMetricFenceWindowMetricFence']['requiredMetricAckCount'],
    'metricFenceTokenLength' => strlen($plan['windowMetricFenceWindowMetricFence']['currentMetricFenceToken']),
    'nextExposure' => $plan['windowMetricFenceWindowMetricFence']['nextExposure'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($payload['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-window-metric-fence-ready') {
    fwrite(STDERR, "application-compound-select-window-recursive-limit-current-source-window-metric-fence self-test failed\n");
    exit(1);
}
if ($payload['requiredMetricAckCount'] !== 6) {
    fwrite(STDERR, "application-compound-select-window-recursive-limit-current-source-window-metric-fence metric ack guard failed\n");
    exit(1);
}
if ($payload['metricFenceTokenLength'] !== 64) {
    fwrite(STDERR, "application-compound-select-window-recursive-limit-current-source-window-metric-fence token guard failed\n");
    exit(1);
}
if ($payload['nextExposure'] !== 'held-until-current-window-metric-acks-match') {
    fwrite(STDERR, "application-compound-select-window-recursive-limit-current-source-window-metric-fence exposure guard failed\n");
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
