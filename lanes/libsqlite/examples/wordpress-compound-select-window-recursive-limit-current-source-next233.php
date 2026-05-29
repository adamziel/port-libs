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
];
$next = [
    ...$current,
    ['option_id' => 4, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 124],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 144)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 8
      FROM q
     WHERE id < 8
     ORDER BY score DESC
     LIMIT 7 OFFSET 1
)
SELECT id,
       label,
       avg(score) OVER (
           ORDER BY score DESC
           ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING
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
       SELECT id, label, avg(score) OVER (ORDER BY score DESC ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS metric FROM q
       UNION
       SELECT option_id AS id, option_name AS label, first_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS metric FROM wp_options WHERE autoload = 'yes'
  )
EXCEPT
SELECT id, label, metric
  FROM (
       SELECT id, label, avg(score) OVER (ORDER BY score DESC ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS metric FROM q WHERE id = 4
       UNION
       SELECT option_id AS id, option_name AS label, first_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS metric FROM wp_options WHERE option_name = 'plugin_old'
  )
 ORDER BY metric DESC, id
 LIMIT 6 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext233(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);

$payload = [
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-next233',
    'wordpressUse' => 'Copied wp_options preview queries can hold next-source compound rows until the current final ORDER BY page ordinals are acknowledged.',
    'status' => $plan['status'],
    'currentRows' => $plan['currentSourceResumeNext233']['currentFinalOrderLabels'],
    'nextRows' => $plan['currentSourceResumeNext233']['nextFinalOrderLabels'],
    'nextOnlyRows' => $plan['currentSourceResumeNext233']['nextOnlyLabels'],
    'requiredAckCount' => $plan['currentSourceResumeNext233']['requiredAckCount'],
    'resumeTokenLength' => strlen($plan['currentSourceResumeNext233']['currentResumeToken']),
    'nextExposure' => $plan['currentSourceResumeNext233']['nextExposure'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($payload['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-next233-ready') {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next233 self-test failed\n");
    exit(1);
}
if ($payload['requiredAckCount'] !== 6) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next233 ordinal guard failed\n");
    exit(1);
}
if ($payload['resumeTokenLength'] !== 64) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next233 token guard failed\n");
    exit(1);
}
if ($payload['nextExposure'] !== 'held-until-current-final-order-ordinals-acked') {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next233 exposure guard failed\n");
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
