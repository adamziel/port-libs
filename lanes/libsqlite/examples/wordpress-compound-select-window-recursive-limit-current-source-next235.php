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

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext235(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);
$barrier = $plan['recursiveWindowPromotionBarrierNext235'];

$payload = [
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-next235',
    'wordpressUse' => 'Copied wp_options preview queries can hold the next source until the current compound page, recursive queue trace, and window-frame metadata are all acknowledged.',
    'status' => $plan['status'],
    'currentLabels' => $barrier['currentLabels'],
    'nextLabels' => $barrier['nextLabels'],
    'nextOnlyLabels' => $barrier['nextOnlyLabels'],
    'requiredPromotionAckCount' => $barrier['requiredPromotionAckCount'],
    'barrierTokenLength' => strlen($barrier['barrierToken']),
    'recursiveTraceTokenLength' => strlen($barrier['recursiveTraceToken']),
    'windowFrameTokenLength' => strlen($barrier['windowFrameToken']),
    'promotionState' => $barrier['promotionState'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($payload['status'] !== 'compound-select-window-recursive-limit-current-source-next235-ready') {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next235 self-test failed\n");
    exit(1);
}
if ($payload['requiredPromotionAckCount'] !== 3 || $payload['barrierTokenLength'] !== 64 || $payload['recursiveTraceTokenLength'] !== 64 || $payload['windowFrameTokenLength'] !== 64) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next235 barrier guard failed\n");
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
