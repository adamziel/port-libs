<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
];
$nextOptions = [
    ...$currentOptions,
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
SELECT id,
       label,
       metric
  FROM (
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

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareFinalPageYieldWatermark(
    $sql,
    ['wp_options' => $currentOptions],
    ['wp_options' => $nextOptions],
);

if (($argv[1] ?? null) === '--self-test') {
    if ($plan['status'] !== 'compound-select-window-recursive-limit-current-source-final-page-yield-watermark-ready') {
        throw new RuntimeException('unexpected final-page-yield-watermark status');
    }
    if ($plan['compoundFinalPageYieldWatermarkFinalPageYieldWatermark']['nextOnlyLabels'] !== ['plugin_prime']) {
        throw new RuntimeException('unexpected next-only final-page label');
    }
    if ($plan['compoundFinalPageYieldWatermarkFinalPageYieldWatermark']['currentOnlyLabels'] !== ['rewrite_rules']) {
        throw new RuntimeException('unexpected current-only final-page label');
    }
    if ($plan['compoundFinalPageYieldWatermarkFinalPageYieldWatermark']['requiredFinalPageYieldAckCount'] !== 5) {
        throw new RuntimeException('unexpected final-page acknowledgement count');
    }

    echo "wordpress-compound-select-window-recursive-limit-current-source-final-page-yield-watermark self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'nextOnlyLabels' => $plan['compoundFinalPageYieldWatermarkFinalPageYieldWatermark']['nextOnlyLabels'],
    'currentOnlyLabels' => $plan['compoundFinalPageYieldWatermarkFinalPageYieldWatermark']['currentOnlyLabels'],
    'requiredFinalPageYieldAckCount' => $plan['compoundFinalPageYieldWatermarkFinalPageYieldWatermark']['requiredFinalPageYieldAckCount'],
    'nextExposure' => $plan['compoundFinalPageYieldWatermarkFinalPageYieldWatermark']['nextExposure'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
