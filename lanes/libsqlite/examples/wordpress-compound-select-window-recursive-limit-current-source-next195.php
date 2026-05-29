<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
        ['option_id' => 3, 'option_name' => 'transient_cleanup', 'autoload' => 'yes', 'score' => 80],
        ['option_id' => 9, 'option_name' => 'plugin_only', 'autoload' => 'yes', 'score' => 10],
    ],
];
$nextTables = [
    'wp_options' => [
        ...$currentTables['wp_options'],
        ['option_id' => 4, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'score' => 70],
        ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 60],
    ],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'siteurl', 100)
    UNION ALL
    SELECT id + 1,
           CASE id + 1
             WHEN 2 THEN 'home'
             WHEN 3 THEN 'transient_cleanup'
             WHEN 4 THEN 'theme_mods'
             ELSE 'rewrite_rules'
           END,
           score - 10
      FROM q
     WHERE id < 5
     ORDER BY 3 DESC
     LIMIT 5
)
SELECT id,
       label,
       row_number() OVER (ORDER BY score DESC) AS pos
  FROM q
INTERSECT
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (ORDER BY score DESC, option_id) AS pos
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT 3 AS id,
       'transient_cleanup' AS label,
       3 AS pos
 ORDER BY pos DESC, id
 LIMIT 3 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext195($sql, $currentTables, $nextTables);

if (($argv[1] ?? null) === '--self-test') {
    if (($plan['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-next195-ready') {
        fwrite(STDERR, "unexpected compound/window recursive LIMIT next195 status\n");
        exit(1);
    }
    if (array_column($plan['nextRows'], 'label') !== ['theme_mods', 'home', 'siteurl']) {
        fwrite(STDERR, "unexpected next compound/window recursive LIMIT next195 boundary\n");
        exit(1);
    }
    if (($plan['compound']['intersectBeforeExcept'] ?? null) !== true) {
        fwrite(STDERR, "unexpected compound INTERSECT/EXCEPT chain\n");
        exit(1);
    }

    echo "wordpress-compound-select-window-recursive-limit-current-source-next195 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-next195',
    'currentLabels' => array_column($plan['currentRows'], 'label'),
    'nextLabels' => array_column($plan['nextRows'], 'label'),
    'nextPreLimitLabels' => array_column($plan['nextPreLimitRows'], 'label'),
    'removedLabels' => $plan['intersectExcept']['nextRemovedLabels'],
    'replanReasons' => $plan['replanReasons'],
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
