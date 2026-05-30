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
require_once __DIR__ . '/../src/SQLiteCompoundLimitWindowAffinityCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteCompoundLimitWindowAffinityCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 8, 'class_value' => 1],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 7, 'class_value' => '1'],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 6, 'class_value' => 2.5],
        ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 5, 'class_value' => '2'],
        ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'weight' => 4, 'class_value' => 3],
        ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'no', 'weight' => 3, 'class_value' => '3'],
    ],
];
$nextTables = [
    'wp_options' => [
        ...$currentTables['wp_options'],
        ['option_id' => 7, 'option_name' => 'plugin_alpha', 'autoload' => 'no', 'weight' => 10, 'class_value' => '4'],
        ['option_id' => 8, 'option_name' => 'plugin_beta', 'autoload' => 'yes', 'weight' => 9, 'class_value' => 4],
    ],
];

$sql = <<<'SQL'
SELECT option_name AS name,
       class_value,
       sum(weight) OVER (
           ORDER BY weight DESC, option_id ASC
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS frame_weight
  FROM wp_options
 WHERE autoload = 'yes'
UNION ALL
SELECT option_name AS name,
       class_value,
       first_value(weight) OVER (
           ORDER BY option_id ASC
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS frame_weight
  FROM wp_options
 WHERE autoload = 'no'
 ORDER BY frame_weight DESC, name
 LIMIT 4 OFFSET 1
SQL;

$summary = SQLiteCompoundLimitWindowAffinityCurrentSourceNextPlan::compareLimitWindowAffinity($sql, $currentTables, $nextTables);

if (($argv[1] ?? '') === '--self-test') {
    if (array_column($summary['currentRows'], 'name') !== ['home', 'blogname', 'active_plugins', 'rewrite_rules']) {
        fwrite(STDERR, "unexpected current compound LIMIT window boundary\n");
        exit(1);
    }
    if (array_column($summary['nextRows'], 'name') !== ['siteurl', 'home', 'plugin_alpha', 'blogname']) {
        fwrite(STDERR, "unexpected next compound LIMIT window boundary\n");
        exit(1);
    }
    if (($summary['affinity']['boundaryClasses']['nextLast'] ?? null) !== 'numeric:2.5') {
        fwrite(STDERR, "missing next storage-class boundary diagnostic\n");
        exit(1);
    }
    echo "application-compound-limit-window-affinity-current-source-next137 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
