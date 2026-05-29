<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonTableCursor.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteGroupedAggregate.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteWindowFunction.php';
require_once __DIR__ . '/../src/SQLiteSelectExpression.php';
require_once __DIR__ . '/../src/SQLiteSelectPredicate.php';
require_once __DIR__ . '/../src/SQLiteSelectProjection.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteSelectQuery.php';
require_once __DIR__ . '/../src/SQLiteSelectCompound.php';
require_once __DIR__ . '/../src/SQLiteSelectSql.php';
require_once __DIR__ . '/../src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 12],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 10],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 8],
        ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 6],
        ['option_id' => 5, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 5],
    ],
];
$nextTables = [
    'wp_options' => [
        ...$currentTables['wp_options'],
        ['option_id' => 6, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 14],
        ['option_id' => 7, 'option_name' => 'plugin_beta', 'autoload' => 'no', 'weight' => 9],
    ],
];

$sql = <<<'SQL'
WITH RECURSIVE option_queue(id, label, score) AS (
    VALUES (1, 'seed', 20)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 4
      FROM option_queue
     WHERE id < 7
     LIMIT 5
)
SELECT id,
       label,
       row_number() OVER (ORDER BY score DESC, id) AS win_value
  FROM option_queue
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       lag(weight, 1, weight) OVER (ORDER BY weight DESC, option_id) AS win_value
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY win_value DESC, id
 LIMIT 6 OFFSET 2
SQL;

$summary = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareWindowRecursiveLimit($sql, $currentTables, $nextTables);

if (($argv[1] ?? '') === '--self-test') {
    if (array_column($summary['currentRows'], 'label') !== ['blogname', 'theme_mods', 'seed:2:3:4:5', 'seed:2:3:4', 'seed:2:3', 'seed:2']) {
        fwrite(STDERR, "unexpected current compound/window recursive LIMIT boundary\n");
        exit(1);
    }
    if (array_column($summary['nextRows'], 'label') !== ['home', 'blogname', 'theme_mods', 'seed:2:3:4:5', 'seed:2:3:4', 'seed:2:3']) {
        fwrite(STDERR, "unexpected next compound/window recursive LIMIT boundary\n");
        exit(1);
    }
    if (($summary['recursive']['currentLimitRemaining'] ?? null) !== 0) {
        fwrite(STDERR, "missing recursive queue LIMIT exhaustion diagnostic\n");
        exit(1);
    }
    echo "wordpress-compound-window-recursive-limit-current-source-window-recursive-limit self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
