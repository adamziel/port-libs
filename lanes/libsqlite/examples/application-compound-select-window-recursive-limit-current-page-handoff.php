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
    VALUES (1, 'seed', 140)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 8
     LIMIT 5 OFFSET 2
)
SELECT id,
       label,
       dense_rank() OVER (ORDER BY score DESC) AS rn
  FROM q
UNION
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS rn
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS rn
  FROM wp_options
 WHERE option_name IN ('siteurl')
 ORDER BY rn, label
 LIMIT 3 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCurrentPageHandoff(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);

$payload = [
    'scenario' => 'application-compound-select-window-recursive-limit-current-source-current-page-handoff',
    'applicationUse' => 'Copied wp_options preview queries can hold a next-source dense_rank UNION/EXCEPT page until the current recursive/window limited page has exact row acknowledgements.',
    'status' => $plan['status'],
    'currentLabels' => $plan['currentSourceHandoffCurrentPageHandoff']['currentLabels'],
    'nextLabels' => $plan['currentSourceHandoffCurrentPageHandoff']['nextLabels'],
    'nextOnlyLabels' => $plan['currentSourceHandoffCurrentPageHandoff']['nextOnlyLabels'],
    'requiredAckCount' => $plan['currentSourceHandoffCurrentPageHandoff']['requiredAckCount'],
    'pageTokenLength' => strlen($plan['currentSourceHandoffCurrentPageHandoff']['currentPageToken']),
    'nextSourceEpochLength' => strlen($plan['currentSourceHandoffCurrentPageHandoff']['nextSourceCursor']['sourceEpoch']),
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($payload['status'] !== 'compound-select-window-recursive-limit-current-source-current-page-handoff-ready') {
    fwrite(STDERR, "application-compound-select-window-recursive-limit-current-source-current-page-handoff self-test failed\n");
    exit(1);
}
if ($payload['requiredAckCount'] !== 3 || $payload['pageTokenLength'] !== 64 || $payload['nextSourceEpochLength'] !== 64) {
    fwrite(STDERR, "application-compound-select-window-recursive-limit-current-source-current-page-handoff handoff guard failed\n");
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
