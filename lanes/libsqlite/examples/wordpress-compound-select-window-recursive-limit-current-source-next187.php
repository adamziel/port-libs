<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 90],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 75],
        ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 64],
        ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 40],
    ],
];
$nextTables = [
    'wp_options' => [
        ...$currentTables['wp_options'],
        ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'score' => 88],
        ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'score' => 70],
    ],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 110)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 9
      FROM q
     WHERE id < 6
     LIMIT -1 OFFSET 2
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
 WHERE score >= 70
 ORDER BY metric DESC, id
 LIMIT 5 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext187($sql, $currentTables, $nextTables);

if (in_array('--self-test', $argv, true)) {
    if (($plan['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-next187-ready') {
        fwrite(STDERR, "unexpected compound next187 status\n");
        exit(1);
    }
    if (!array_key_exists('currentLimitRemaining', $plan['recursive']) || $plan['recursive']['currentLimitRemaining'] !== null || ($plan['recursive']['currentOffsetRemaining'] ?? null) !== 0) {
        fwrite(STDERR, "recursive negative LIMIT/OFFSET did not drain as expected\n");
        exit(1);
    }
    if (array_column($plan['nextRows'], 'label') !== ['seed:2:3:4', 'siteurl', 'rewrite_rules', 'siteurl', 'plugin_alpha']) {
        fwrite(STDERR, "unexpected compound next187 next rows\n");
        exit(1);
    }
    echo "wordpress-compound-select-window-recursive-limit-current-source-next187 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-next187',
    'wordpressUse' => 'Copied wp_options import previews can use a recursive CTE with SQLite LIMIT -1 OFFSET semantics, evaluate lag/lead windows before UNION distinct suppression, and let the final compound LIMIT choose the current/next source boundary.',
    'dependencyClosure' => 'no new support component needed; reuses native PHP SELECT SQL recursive CTE tracing, window execution, UNION distinct, and final LIMIT/OFFSET helpers',
    'status' => $plan['status'],
    'currentLabels' => array_column($plan['currentRows'], 'label'),
    'nextLabels' => array_column($plan['nextRows'], 'label'),
    'recursiveSkipped' => $plan['negativeLimitOffset']['next']['skippedLabels'],
    'changedRecursiveLabels' => $plan['negativeLimitOffset']['changedRecursiveLabels'],
    'replanReasons' => $plan['replanReasons'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
