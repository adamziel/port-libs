<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonTableCursor.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteGroupedAggregate.php';
require_once __DIR__ . '/../src/SQLiteWindowFunction.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteSelectExpression.php';
require_once __DIR__ . '/../src/SQLiteSelectPredicate.php';
require_once __DIR__ . '/../src/SQLiteSelectProjection.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteSelectQuery.php';
require_once __DIR__ . '/../src/SQLiteSelectCompound.php';
require_once __DIR__ . '/../src/SQLiteSelectSql.php';
require_once __DIR__ . '/../src/SQLiteCompoundRecursiveOrderLimitCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteCompoundRecursiveOrderLimitCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'parent_id' => null, 'priority' => 90],
        ['option_id' => 2, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'parent_id' => 1, 'priority' => 88],
        ['option_id' => 3, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'parent_id' => 2, 'priority' => 70],
        ['option_id' => 4, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'parent_id' => 1, 'priority' => 60],
        ['option_id' => 5, 'option_name' => 'widget_text', 'autoload' => 'yes', 'parent_id' => 4, 'priority' => 50],
        ['option_id' => 6, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'parent_id' => 1, 'priority' => 40],
    ],
];
$nextTables = [
    'wp_options' => [
        ...$currentTables['wp_options'],
        ['option_id' => 7, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'parent_id' => 1, 'priority' => 95],
        ['option_id' => 8, 'option_name' => 'plugin_beta', 'autoload' => 'yes', 'parent_id' => 7, 'priority' => 85],
    ],
];

$sql = <<<'SQL'
WITH RECURSIVE ranked(id, name, priority, depth) AS (
    VALUES (1, 'siteurl', 90, 0)
    UNION ALL
    SELECT option_id, option_name, wp_options.priority, depth + 1
      FROM wp_options JOIN ranked ON parent_id = id
     WHERE autoload = 'yes'
     ORDER BY priority DESC, name
     LIMIT 5
)
SELECT id, name, priority, 'recursive' AS source FROM ranked
UNION ALL
SELECT option_id AS id, option_name AS name, priority, 'autoload' AS source
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY priority DESC, name
 LIMIT 6 OFFSET 1
SQL;

$summary = SQLiteCompoundRecursiveOrderLimitCurrentSourceNextPlan::compareRecursiveOrderLimit($sql, $currentTables, $nextTables);

if (($argv[1] ?? '') === '--self-test') {
    if (($summary['recursive']['currentVisitOrder'] ?? null) !== ['siteurl', 'active_plugins', 'plugin_cache', 'theme_mods', 'widget_text']) {
        fwrite(STDERR, "unexpected current recursive priority visit order\n");
        exit(1);
    }
    if (($summary['recursive']['nextVisitOrder'] ?? null) !== ['siteurl', 'plugin_alpha', 'active_plugins', 'plugin_beta', 'plugin_cache']) {
        fwrite(STDERR, "unexpected next recursive priority visit order\n");
        exit(1);
    }
    if (($summary['boundary']['nextLabels'] ?? null) !== ['plugin_alpha', 'siteurl', 'siteurl', 'active_plugins', 'active_plugins', 'plugin_beta']) {
        fwrite(STDERR, "unexpected next compound ORDER/LIMIT boundary\n");
        exit(1);
    }

    echo "wordpress-compound-recursive-order-limit-current-source-next146 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
