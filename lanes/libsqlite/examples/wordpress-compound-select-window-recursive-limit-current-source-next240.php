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

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareFinalPageSpilloverDrain($sql, $current, $next);
$spillover = $plan['compoundFinalPageSpilloverDrainNext240'];

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'compound-select-window-recursive-limit-current-source-next240-ready');
    assert($spillover['nextExposure'] === 'held-until-current-compound-spillover-drained');
    assert($spillover['currentSpilloverLabels'] === ['seed:2', 'blogname', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7']);
    assert($spillover['nextOnlyFinalLabels'] === ['plugin_prime']);
    echo "wordpress-compound-select-window-recursive-limit-current-source-next240 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'currentRows' => array_column($plan['currentRows'], 'label'),
    'nextRows' => array_column($plan['nextRows'], 'label'),
    'spilloverDrain' => $spillover,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
