<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonTableCursor.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteGroupedAggregate.php';
require_once __DIR__ . '/../src/SQLiteWindowFunction.php';
require_once __DIR__ . '/../src/SQLiteSelectExpression.php';
require_once __DIR__ . '/../src/SQLiteSelectPredicate.php';
require_once __DIR__ . '/../src/SQLiteSelectProjection.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteSelectQuery.php';
require_once __DIR__ . '/../src/SQLiteSelectCompound.php';
require_once __DIR__ . '/../src/SQLiteSelectSql.php';
require_once __DIR__ . '/../src/SQLiteCompoundIntersectRecursiveWindowLimitCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteCompoundIntersectRecursiveWindowLimitCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'seq' => 1],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'seq' => 2],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'seq' => 4],
        ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'seq' => 3],
    ],
];
$nextTables = [
    'wp_options' => [
        ...$currentTables['wp_options'],
        ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'seq' => 3],
    ],
];

$sql = <<<'SQL'
WITH RECURSIVE wanted(pos, name) AS (
    VALUES (1, 'siteurl')
    UNION ALL
    SELECT pos + 1,
           CASE pos + 1
                WHEN 2 THEN 'home'
                WHEN 3 THEN 'rewrite_rules'
                WHEN 4 THEN 'blogname'
           END
      FROM wanted
     WHERE pos < 4
     LIMIT 4
)
SELECT name,
       pos,
       row_number() OVER (ORDER BY pos) AS rank
  FROM wanted
INTERSECT
SELECT option_name AS name,
       seq AS pos,
       row_number() OVER (ORDER BY seq) AS rank
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY rank, name
 LIMIT 2 OFFSET 1
SQL;

$summary = SQLiteCompoundIntersectRecursiveWindowLimitCurrentSourceNextPlan::compareIntersectRecursiveWindowLimit($sql, $currentTables, $nextTables);

if (($argv[1] ?? '') === '--self-test') {
    if (array_column($summary['currentRows'], 'name') !== ['home']) {
        fwrite(STDERR, "unexpected current INTERSECT recursive window boundary\n");
        exit(1);
    }
    if (array_column($summary['nextRows'], 'name') !== ['home', 'rewrite_rules']) {
        fwrite(STDERR, "unexpected next INTERSECT recursive window boundary\n");
        exit(1);
    }
    if (($summary['recursive']['currentLimitRemaining'] ?? null) !== 0) {
        fwrite(STDERR, "missing recursive queue LIMIT exhaustion diagnostic\n");
        exit(1);
    }
    if (($summary['intersectTrace']['currentRemovedNames'] ?? []) !== ['rewrite_rules', 'blogname']) {
        fwrite(STDERR, "unexpected current INTERSECT removal trace\n");
        exit(1);
    }
    echo "application-compound-intersect-recursive-window-limit self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
