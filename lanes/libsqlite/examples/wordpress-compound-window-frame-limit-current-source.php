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
require_once __DIR__ . '/../src/SQLiteCompoundWindowFrameLimitCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteCompoundWindowFrameLimitCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 9],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 8],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 7],
        ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 6],
        ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'weight' => 5],
        ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'no', 'weight' => 4],
    ],
];
$nextTables = [
    'wp_options' => [
        ...$currentTables['wp_options'],
        ['option_id' => 7, 'option_name' => 'plugin_alpha', 'autoload' => 'no', 'weight' => 10],
        ['option_id' => 8, 'option_name' => 'plugin_beta', 'autoload' => 'yes', 'weight' => 11],
    ],
];

$sql = <<<'SQL'
SELECT option_id AS id,
       option_name AS label,
       last_value(option_name) OVER (
           ORDER BY weight DESC, option_id ASC
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS frame_tail,
       sum(weight) OVER (
           ORDER BY weight DESC, option_id ASC
           ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING
       ) AS frame_weight
  FROM wp_options
 WHERE autoload = 'yes'
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       first_value(option_name) OVER (
           ORDER BY option_id ASC
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS frame_tail,
       sum(weight) OVER (
           ORDER BY option_id ASC
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS frame_weight
  FROM wp_options
 WHERE autoload = 'no'
 ORDER BY frame_weight DESC, id ASC
 LIMIT 5 OFFSET 1
SQL;

$summary = SQLiteCompoundWindowFrameLimitCurrentSourceNextPlan::compareWindowFrameLimit($sql, $currentTables, $nextTables);

if (($argv[1] ?? '') === '--self-test') {
    if (array_column($summary['currentRows'], 'id') !== [2, 4, 5, 3, 6]) {
        fwrite(STDERR, "unexpected current limited compound ids\n");
        exit(1);
    }
    if (array_column($summary['nextRows'], 'id') !== [1, 2, 6, 4, 7]) {
        fwrite(STDERR, "unexpected next limited compound ids\n");
        exit(1);
    }
    if (!in_array('compound-tail-limit', $summary['replanReasons'], true)) {
        fwrite(STDERR, "missing compound tail LIMIT diagnostic\n");
        exit(1);
    }
    echo "wordpress-compound-window-frame-limit-current-source self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
