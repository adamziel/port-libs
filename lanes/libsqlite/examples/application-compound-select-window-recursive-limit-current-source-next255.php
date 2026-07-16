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
SELECT id, label, rank() OVER (ORDER BY score DESC) AS metric FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT id,
       label,
       metric
  FROM (
       SELECT id, label, rank() OVER (ORDER BY score DESC) AS metric FROM q
       UNION ALL
       SELECT option_id AS id,
              option_name AS label,
              row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
         FROM wp_options
        WHERE autoload = 'yes'
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

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareContinuationResume(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);
$resume = $plan['compoundWindowRecursiveContinuationResumeContinuationResume'];

$payload = [
    'scenario' => 'application-compound-select-window-recursive-limit-current-source-continuation-resume',
    'applicationUse' => 'Copied wp_options retry scans can hold next-source compound rows until the current recursive/window LIMIT page and the queued next page both acknowledge the continuation resume fence.',
    'status' => $plan['status'],
    'continuationResumeTokenLength' => strlen($resume['continuationResumeToken']),
    'requiredContinuationAckCount' => $resume['requiredContinuationAckCount'],
    'currentLabels' => $resume['currentLabels'],
    'nextLabels' => $resume['nextLabels'],
    'nextExposure' => $resume['nextExposure'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($payload['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-continuation-resume-ready') {
    fwrite(STDERR, "application-compound-select-window-recursive-limit-current-source-continuation-resume self-test failed\n");
    exit(1);
}
if ($payload['continuationResumeTokenLength'] !== 64 || $payload['requiredContinuationAckCount'] !== 4) {
    fwrite(STDERR, "application-compound-select-window-recursive-limit-current-source-continuation-resume continuation guard failed\n");
    exit(1);
}

if (in_array('--self-test', $argv, true)) {
    echo "application-compound-select-window-recursive-limit-current-source-continuation-resume self-test passed\n";
    exit(0);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
