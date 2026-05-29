<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 112],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 96],
        ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 82],
        ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 38],
    ],
];
$nextTables = [
    'wp_options' => [
        ...$currentTables['wp_options'],
        ['option_id' => 5, 'option_name' => 'plugin_search', 'autoload' => 'yes', 'score' => 108],
        ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 74],
    ],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 130)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 7
      FROM q
     WHERE id < 9
     LIMIT (2 + 4) OFFSET (1 + 1)
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
UNION ALL
SELECT id,
       label,
       first_value(score) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS metric
  FROM q
UNION
SELECT option_id AS id,
       option_name AS label,
       score AS metric
  FROM wp_options
 WHERE score >= 74
 ORDER BY metric DESC, id
 LIMIT (2 * 3) OFFSET (1 + 1)
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext190($sql, $currentTables, $nextTables);

if (in_array('--self-test', $argv, true)) {
    if (($plan['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-next190-ready') {
        fwrite(STDERR, "unexpected compound next190 status\n");
        exit(1);
    }
    if (($plan['compound']['limit'] ?? null) !== 6 || ($plan['compound']['offset'] ?? null) !== 2) {
        fwrite(STDERR, "expression-valued final LIMIT/OFFSET did not evaluate as expected\n");
        exit(1);
    }
    if (array_column($plan['nextRows'], 'label') !== ['siteurl', 'theme_mods_next', 'seed:2:3:4', 'seed:2:3:4:5', 'siteurl', 'plugin_search']) {
        fwrite(STDERR, "unexpected compound next190 next rows\n");
        exit(1);
    }
    echo "wordpress-compound-select-window-recursive-limit-current-source-next190 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-next190',
    'wordpressUse' => 'Copied wp_options import previews can evaluate expression-valued recursive and final compound LIMIT/OFFSET terms while window functions run before UNION distinct suppression shifts the current/next source boundary.',
    'dependencyClosure' => 'no new support component needed; reuses native SELECT SQL expression LIMIT/OFFSET evaluation, recursive CTE tracing, compound UNION execution, and window result dispatch',
    'status' => $plan['status'],
    'compoundLimit' => $plan['compound']['limit'],
    'compoundOffset' => $plan['compound']['offset'],
    'recursiveLimitExpression' => $plan['recursive']['limitExpression'],
    'currentLabels' => array_column($plan['currentRows'], 'label'),
    'nextLabels' => array_column($plan['nextRows'], 'label'),
    'gainedLabels' => $plan['expressionLimitBoundary']['gainedAdmittedLabels'],
    'replanReasons' => $plan['replanReasons'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
