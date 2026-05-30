<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonTableCursor.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteGroupedAggregate.php';
require_once __DIR__ . '/../src/SQLiteWindowFunction.php';
require_once __DIR__ . '/../src/SQLiteSelectExpression.php';
require_once __DIR__ . '/../src/SQLiteSelectPredicate.php';
require_once __DIR__ . '/../src/SQLiteSelectProjection.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteSelectQuery.php';
require_once __DIR__ . '/../src/SQLiteSelectCompound.php';
require_once __DIR__ . '/../src/SQLiteSelectSql.php';
require_once __DIR__ . '/../src/SQLiteCompoundWindowRecursiveLimitCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteCompoundWindowRecursiveLimitCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 12],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 10],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 8],
        ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 6],
        ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'weight' => 4],
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
WITH RECURSIVE option_rank(id, label, weight) AS (
    VALUES (1, 'seed', 15)
    UNION ALL
    SELECT id + 1, 'seed:' || (id + 1), weight - 3
      FROM option_rank
     WHERE id < 5
     LIMIT 4
)
SELECT id,
       label,
       sum(weight) OVER (
           ORDER BY id
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS window_weight
  FROM option_rank
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       first_value(weight) OVER (
           ORDER BY weight DESC, option_id
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS window_weight
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY window_weight DESC, id
 LIMIT 5 OFFSET 1
SQL;

$summary = SQLiteCompoundWindowRecursiveLimitCurrentSourceNextPlan::compare($sql, $currentTables, $nextTables);

if (($argv[1] ?? '') === '--self-test') {
    if (array_column($summary['currentRows'], 'label') !== ['seed:2', 'seed:3', 'siteurl', 'home', 'blogname']) {
        fwrite(STDERR, "unexpected current compound recursive window LIMIT boundary\n");
        exit(1);
    }
    if (array_column($summary['nextRows'], 'label') !== ['seed:2', 'seed:3', 'plugin_alpha', 'siteurl', 'home']) {
        fwrite(STDERR, "unexpected next compound recursive window LIMIT boundary\n");
        exit(1);
    }
    if (($summary['recursive']['currentLimitRemaining'] ?? null) !== 0) {
        fwrite(STDERR, "missing recursive queue LIMIT exhaustion diagnostic\n");
        exit(1);
    }
    echo "application-compound-window-recursive-limit-current-source self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
