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
    ['option_id' => 6, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
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

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCompoundLimitResumeFence(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);
$fence = $plan['compoundLimitResumeFenceNext239'];
$payload = [
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-next239',
    'wordpressUse' => 'Copied wp_options previews can hold a next-source cursor until compound LIMIT output, recursive queue, and window rank signatures are acknowledged together.',
    'status' => $plan['status'],
    'currentLabels' => $fence['currentLabels'],
    'nextLabels' => $fence['nextLabels'],
    'nextOnly' => $fence['nextOnlyLabels'],
    'resumeTokenLength' => strlen($fence['resumeToken']),
    'requiredResumeAckCount' => $fence['requiredResumeAckCount'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($payload['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-next239-ready') {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next239 self-test failed\n");
    exit(1);
}
if ($payload['nextOnly'] !== ['plugin_prime'] || $payload['resumeTokenLength'] !== 64 || $payload['requiredResumeAckCount'] !== 3) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next239 resume fence failed\n");
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
