<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 18],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 16],
        ['option_id' => 3, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 14],
    ],
];
$nextTables = $currentTables;
$nextTables['wp_options'][] = ['option_id' => 4, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 25];
$nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'plugin_beta', 'autoload' => 'no', 'weight' => 21];

$sql = <<<'SQL'
WITH RECURSIVE option_queue(id, label, weight) AS (
    VALUES (1, 'seed', 20)
    UNION ALL
    SELECT id + 1, 'seed:' || (id + 1), weight - 2
      FROM option_queue
     WHERE id < 8
     LIMIT 5 OFFSET 1
)
SELECT id,
       label,
       sum(weight) OVER (
           ORDER BY id
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS score
  FROM option_queue
UNION
SELECT option_id AS id,
       option_name AS label,
       first_value(weight) OVER (
           PARTITION BY autoload
           ORDER BY weight DESC, option_id
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS score
  FROM wp_options
 ORDER BY score DESC, id
 LIMIT 4 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareWindowRecursiveLimitOffset($sql, $currentTables, $nextTables);
$result = [
    'scenario' => 'application-compound-select-window-recursive-limit-current-source-window-recursive-limit-offset',
    'sqlShape' => 'WITH RECURSIVE queue LIMIT/OFFSET feeding windowed compound SELECT with final ORDER BY/LIMIT/OFFSET',
    'applicationUse' => 'Copied wp_options import previews can skip a recursive seed row, rank recursive and option-source rows with window functions, then apply the final compound SELECT boundary before showing current-source/next changes.',
    'currentLabels' => array_column($plan['currentRows'], 'label'),
    'nextLabels' => array_column($plan['nextRows'], 'label'),
    'recursiveEmitted' => $plan['recursive']['currentEmitted'],
    'newAdmittedLabels' => $plan['boundary']['newAdmittedLabels'],
    'replanReasons' => $plan['replanReasons'],
    'dependency' => 'native PHP SELECT SQL recursive CTE/window/compound LIMIT execution; no ext/sqlite required',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if ($result['currentLabels'] !== ['seed:3', 'seed:4', 'seed:5', 'siteurl']) {
        fwrite(STDERR, "unexpected current compound labels\n");
        exit(1);
    }
    if ($result['nextLabels'] !== ['seed:3', 'seed:4', 'plugin_alpha', 'seed:5']) {
        fwrite(STDERR, "unexpected next compound labels\n");
        exit(1);
    }
    if (!in_array('recursive-cte-offset-skipped-anchor', $result['replanReasons'], true)) {
        fwrite(STDERR, "missing recursive offset reason\n");
        exit(1);
    }
    echo "application-compound-select-window-recursive-limit-current-source-window-recursive-limit-offset self-test passed\n";
}

return $result;
