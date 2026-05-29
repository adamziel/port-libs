<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 32],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 24],
        ['option_id' => 3, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 18],
    ],
];
$nextTables = $currentTables;
$nextTables['wp_options'][] = ['option_id' => 4, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 34];
$nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'weight' => 21];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, weight) AS (
    VALUES (1, 'seed', 31)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), weight - 3
      FROM q
     WHERE id < 7
     LIMIT 4 OFFSET 1
)
SELECT id,
       label,
       dense_rank() OVER qwin AS bucket
  FROM q
 WINDOW qwin AS (ORDER BY weight DESC)
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER optwin AS bucket
  FROM wp_options
 WHERE autoload = 'yes'
 WINDOW optwin AS (PARTITION BY autoload ORDER BY weight DESC, option_id)
 ORDER BY bucket, id
 LIMIT 5 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext165($sql, $currentTables, $nextTables);
$result = [
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-next165',
    'sqlShape' => 'WITH RECURSIVE queue LIMIT/OFFSET plus named WINDOW clauses in both compound arms and a final ORDER BY/LIMIT/OFFSET',
    'wordpressUse' => 'Copied wp_options import previews can rank recursive seed rows and autoloaded option rows through named windows before the final compound SELECT boundary decides which current-source rows yield to next-source rows.',
    'namedWindows' => $plan['namedWindows'],
    'currentLabels' => array_column($plan['currentRows'], 'label'),
    'nextLabels' => array_column($plan['nextRows'], 'label'),
    'gainedRows' => $plan['boundary']['gainedRows'],
    'replanReasons' => $plan['replanReasons'],
    'dependency' => 'native PHP SELECT SQL named-window, recursive CTE, compound SELECT, and LIMIT/OFFSET execution; no ext/sqlite required',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if ($result['namedWindows'] !== ['qwin', 'optwin']) {
        fwrite(STDERR, "unexpected named window metadata\n");
        exit(1);
    }
    if ($result['currentLabels'] !== ['seed:2', 'home', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5']) {
        fwrite(STDERR, "unexpected current compound labels\n");
        exit(1);
    }
    if ($result['nextLabels'] !== ['plugin_alpha', 'siteurl', 'seed:2:3', 'home', 'seed:2:3:4']) {
        fwrite(STDERR, "unexpected next compound labels\n");
        exit(1);
    }
    if (!in_array('compound-named-window-arm-expansion', $result['replanReasons'], true)) {
        fwrite(STDERR, "missing named-window reason\n");
        exit(1);
    }
    echo "wordpress-compound-select-window-recursive-limit-current-source-next165 self-test passed\n";
}

return $result;
