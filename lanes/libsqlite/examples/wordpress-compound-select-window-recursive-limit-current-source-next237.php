<?php

declare(strict_types=1);

foreach (glob(dirname(__DIR__) . '/src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$current = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
];
$next = [
    ...$current,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 130)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 8
     LIMIT 6 OFFSET 1
)
SELECT id,
       label,
       rank() OVER (ORDER BY score DESC) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT id, label, metric
  FROM (
       SELECT id, label, rank() OVER (ORDER BY score DESC) AS metric FROM q
       UNION ALL
       SELECT option_id AS id, option_name AS label, row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric FROM wp_options WHERE autoload = 'yes'
  )
EXCEPT
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
  FROM wp_options
 WHERE option_name IN ('siteurl')
 ORDER BY metric, label
 LIMIT 4 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCurrentSourceDequeue(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);

$payload = [
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-next237',
    'wordpressUse' => 'Copied wp_options preview queries hold next-source compound rows until recursive queue dequeue acknowledgements are sealed for the current source.',
    'status' => $plan['status'],
    'currentRows' => $plan['currentSourceDequeueNext237']['currentFinalPageLabels'],
    'nextRows' => $plan['currentSourceDequeueNext237']['nextFinalPageLabels'],
    'nextOnlyRows' => $plan['currentSourceDequeueNext237']['nextOnlyLabels'],
    'requiredAckCount' => $plan['currentSourceDequeueNext237']['requiredAckCount'],
    'dequeueTokenLength' => strlen($plan['currentSourceDequeueNext237']['currentDequeueToken']),
    'nextExposure' => $plan['currentSourceDequeueNext237']['nextExposure'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($payload['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-next237-ready') {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next237 self-test failed\n");
    exit(1);
}
if ($payload['requiredAckCount'] !== 6) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next237 dequeue guard failed\n");
    exit(1);
}
if ($payload['dequeueTokenLength'] !== 64) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next237 token guard failed\n");
    exit(1);
}
if ($payload['nextExposure'] !== 'held-until-current-recursive-dequeue-acks') {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next237 exposure guard failed\n");
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
