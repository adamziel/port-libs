<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 92],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 78],
        ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 64],
        ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 22],
    ],
];
$nextTables = $currentTables;
$nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'score' => 88];
$nextTables['wp_options'][] = ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'score' => 70];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 104)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 9
      FROM q
     WHERE id < 8
     LIMIT 6 OFFSET 2
)
SELECT id,
       label,
       lag(score, 1, score) OVER (ORDER BY id) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       lead(score, 1, score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
UNION
SELECT option_id AS id,
       option_name AS label,
       score AS metric
  FROM wp_options
 WHERE score >= 64
 ORDER BY metric DESC, id
 LIMIT 6 OFFSET 2
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareTailWindowLimitBoundary($sql, $currentTables, $nextTables);
$result = [
    'scenario' => 'application-compound-select-window-recursive-limit-current-source-tail-window-limit-boundary',
    'sqlShape' => 'WITH RECURSIVE LIMIT/OFFSET feeding lag/lead window arms before UNION ALL/UNION and final ORDER BY/LIMIT/OFFSET',
    'applicationUse' => 'Copied wp_options import previews can show when a next-source plugin option crosses the final compound LIMIT boundary after recursive rows and window metrics are evaluated.',
    'currentLabels' => array_column($plan['currentRows'], 'label'),
    'nextLabels' => array_column($plan['nextRows'], 'label'),
    'gainedLabels' => $plan['tailWindowLimit']['gainedLabels'],
    'lostLabels' => $plan['tailWindowLimit']['lostLabels'],
    'recursiveSkipped' => $plan['recursive']['currentSkippedLabels'],
    'replanReasons' => $plan['replanReasons'],
    'dependency' => 'native PHP SELECT SQL recursive CTE/window/compound LIMIT execution; no ext/sqlite required',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if ($result['gainedLabels'] !== ['plugin_alpha']) {
        fwrite(STDERR, "expected plugin option label to enter the next-source final LIMIT boundary\n");
        exit(1);
    }
    if ($result['recursiveSkipped'] !== ['seed', 'seed:2']) {
        fwrite(STDERR, "recursive LIMIT/OFFSET did not skip the anchor and first recursive row\n");
        exit(1);
    }
    echo "application-compound-select-window-recursive-limit-current-source-tail-window-limit-boundary self-test passed\n";
}

return $result;
