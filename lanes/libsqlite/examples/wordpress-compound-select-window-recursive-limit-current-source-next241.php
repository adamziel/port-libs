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

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareResumeAdmissionReceipt($sql, $current, $next);
$receipt = $plan['resumeAdmissionReceiptNext241'];

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'compound-select-window-recursive-limit-current-source-next241-ready');
    assert($receipt['nextOnlyLabels'] === ['plugin_prime']);
    assert($receipt['currentOnlyLabels'] === ['rewrite_rules']);
    assert($receipt['requiredResumeAdmissionAckCount'] === 3);
    assert($receipt['currentRowCount'] === 3);
    assert($receipt['nextRowCount'] === 3);
    echo "wordpress-compound-select-window-recursive-limit-current-source-next241 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'currentLabels' => $receipt['currentLabels'],
    'nextLabels' => $receipt['nextLabels'],
    'nextOnlyLabels' => $receipt['nextOnlyLabels'],
    'requiredResumeAdmissionAckCount' => $receipt['requiredResumeAdmissionAckCount'],
    'resumeState' => $receipt['resumeState'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
