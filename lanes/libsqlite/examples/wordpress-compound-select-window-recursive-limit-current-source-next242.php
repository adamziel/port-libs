<?php

declare(strict_types=1);

foreach (glob(dirname(__DIR__) . '/src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$current = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
        ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
        ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
    ],
];
$next = $current;
$next['wp_options'][] = ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 140)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 8
     LIMIT 5 OFFSET 2
)
SELECT id, label, dense_rank() OVER (ORDER BY score DESC) AS rn FROM q
UNION
SELECT option_id AS id, option_name AS label, dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS rn
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT option_id AS id, option_name AS label, dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS rn
  FROM wp_options
 WHERE option_name IN ('siteurl')
 ORDER BY rn, label
 LIMIT 3 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext242($sql, $current, $next);
$fence = $plan['recursiveLimitWindowCommitFenceNext242'];

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'compound-select-window-recursive-limit-current-source-next242-ready');
    assert($fence['nextOnlyLabels'] === ['plugin_prime']);
    assert($fence['currentOnlyLabels'] === ['rewrite_rules']);
    assert($fence['requiredCommitFenceAckCount'] === 3);
    assert($fence['windowFunctions'] === ['dense_rank']);
    assert($fence['admissionState'] === 'held-until-recursive-window-and-final-page-acks-match');
    echo "wordpress-compound-select-window-recursive-limit-current-source-next242 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'currentLabels' => $fence['currentLabels'],
    'nextLabels' => $fence['nextLabels'],
    'nextOnlyLabels' => $fence['nextOnlyLabels'],
    'requiredCommitFenceAckCount' => $fence['requiredCommitFenceAckCount'],
    'admissionState' => $fence['admissionState'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
