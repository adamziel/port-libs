<?php

declare(strict_types=1);

foreach (glob(dirname(__DIR__) . '/src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$current = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 110],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 85],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 75],
];
$next = [
    ...$current,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 105],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 150)
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

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext247(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);
$seal = $plan['recursiveOffsetYieldSealNext247'];

$payload = [
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-next247',
    'wordpressUse' => 'Copied wp_options preview queries can keep recursive LIMIT/OFFSET skipped rows, window metrics, and a yielded next-source cursor sealed until the current compound result page is acknowledged.',
    'status' => $plan['status'],
    'currentSkippedLabels' => $seal['currentSkippedLabels'],
    'nextResultLabels' => $seal['nextResultLabels'],
    'requiredAckCount' => $seal['requiredRecursiveOffsetYieldAckCount'],
    'yieldDecision' => $seal['yieldDecision'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($payload['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-next247-ready') {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next247 self-test failed\n");
    exit(1);
}
if ($payload['currentSkippedLabels'] !== ['seed', 'seed:2'] || $payload['requiredAckCount'] !== 3) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next247 offset seal guard failed\n");
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
