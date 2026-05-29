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

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext236(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);

$payload = [
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-next236',
    'wordpressUse' => 'Copied wp_options preview queries hold next-source compound rows until current-source window metric acknowledgements prove the recursive/window page has not drifted.',
    'status' => $plan['status'],
    'currentMetricLabels' => $plan['windowMetricFenceNext236']['currentMetricLabels'],
    'nextMetricLabels' => $plan['windowMetricFenceNext236']['nextMetricLabels'],
    'metricDriftLabels' => $plan['windowMetricFenceNext236']['metricDriftLabels'],
    'nextOnlyMetricLabels' => $plan['windowMetricFenceNext236']['nextOnlyMetricLabels'],
    'requiredMetricAckCount' => $plan['windowMetricFenceNext236']['requiredMetricAckCount'],
    'metricFenceTokenLength' => strlen($plan['windowMetricFenceNext236']['currentMetricFenceToken']),
    'nextExposure' => $plan['windowMetricFenceNext236']['nextExposure'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($payload['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-next236-ready') {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next236 self-test failed\n");
    exit(1);
}
if ($payload['requiredMetricAckCount'] !== 6) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next236 metric ack guard failed\n");
    exit(1);
}
if ($payload['metricFenceTokenLength'] !== 64) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next236 token guard failed\n");
    exit(1);
}
if ($payload['nextExposure'] !== 'held-until-current-window-metric-acks-match') {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next236 exposure guard failed\n");
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
