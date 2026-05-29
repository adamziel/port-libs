<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 20],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 18],
        ['option_id' => 3, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 14],
    ],
];
$nextTables = $currentTables;
$nextTables['wp_options'][] = ['option_id' => 4, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 28];
$nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 17];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, weight) AS (
    VALUES (1, 'seed', 40)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), weight - 3
      FROM q
     WHERE id < 9
     LIMIT 1,5
)
SELECT id,
       label,
       sum(weight) OVER (
           ORDER BY id
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       first_value(weight) OVER (
           PARTITION BY autoload
           ORDER BY weight DESC, option_id
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS metric
  FROM wp_options
 ORDER BY metric DESC, id
 LIMIT 1,4
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext168($sql, $currentTables, $nextTables);
$result = [
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-next168',
    'sqlShape' => 'WITH RECURSIVE queue LIMIT offset,count feeding windowed UNION ALL with final LIMIT offset,count',
    'wordpressUse' => 'Copied wp_options import previews can use SQLite comma-form LIMIT syntax while preserving recursive queue skipping, per-arm window metrics, and final compound current/next boundaries.',
    'currentLabels' => array_column($plan['currentRows'], 'label'),
    'nextLabels' => array_column($plan['nextRows'], 'label'),
    'recursiveSkippedLabels' => $plan['recursive']['currentSkippedLabels'],
    'gainedLabels' => $plan['boundary']['gainedLabels'],
    'lostLabels' => $plan['boundary']['lostLabels'],
    'replanReasons' => $plan['replanReasons'],
    'dependency' => 'native PHP SELECT SQL recursive CTE/window/compound comma-LIMIT execution; no ext/sqlite required',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if ($result['currentLabels'] !== ['seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6']) {
        fwrite(STDERR, "unexpected current compound labels\n");
        exit(1);
    }
    if ($result['nextLabels'] !== ['seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'plugin_alpha']) {
        fwrite(STDERR, "unexpected next compound labels\n");
        exit(1);
    }
    if (!in_array('compound-final-comma-limit-offset', $result['replanReasons'], true)) {
        fwrite(STDERR, "missing comma LIMIT reason\n");
        exit(1);
    }
    echo "wordpress-compound-select-window-recursive-limit-current-source-next168 self-test passed\n";
}

return $result;
