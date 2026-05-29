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
require_once __DIR__ . '/../src/SQLiteCompoundWindowExceptLimitCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteCompoundWindowExceptLimitCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 9, 'class_value' => 1],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 7, 'class_value' => '1'],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 6, 'class_value' => 2.5],
        ['option_id' => 4, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 5, 'class_value' => '2'],
        ['option_id' => 5, 'option_name' => 'transient_cache', 'autoload' => 'yes', 'weight' => 4, 'class_value' => 3],
        ['option_id' => 6, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 3, 'class_value' => '3'],
    ],
    'network_options' => [
        ['option_id' => 20, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 9, 'class_value' => 1],
        ['option_id' => 21, 'option_name' => 'transient_cache', 'autoload' => 'yes', 'weight' => 4, 'class_value' => 3],
    ],
];
$nextTables = $currentTables;
$nextTables['wp_options'][] = ['option_id' => 7, 'option_name' => 'plugin_beta', 'autoload' => 'yes', 'weight' => 11, 'class_value' => '4'];
$nextTables['wp_options'][] = ['option_id' => 8, 'option_name' => 'network_banner', 'autoload' => 'yes', 'weight' => 8, 'class_value' => 4];
$nextTables['network_options'][] = ['option_id' => 22, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 6, 'class_value' => 2.5];
$nextTables['network_options'][] = ['option_id' => 23, 'option_name' => 'network_banner', 'autoload' => 'yes', 'weight' => 8, 'class_value' => 4];

$sql = <<<'SQL'
SELECT option_name AS name,
       class_value,
       sum(weight) OVER (
           ORDER BY weight DESC, option_id ASC
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS frame_weight
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT option_name AS name,
       class_value,
       sum(weight) OVER (
           ORDER BY weight DESC, option_id ASC
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS frame_weight
  FROM network_options
 WHERE autoload = 'yes'
 ORDER BY frame_weight DESC, name
 LIMIT 3 OFFSET 1
SQL;

$summary = SQLiteCompoundWindowExceptLimitCurrentSourceNextPlan::compareWindowExceptLimit($sql, $currentTables, $nextTables);

if (($argv[1] ?? '') === '--self-test') {
    if (array_column($summary['currentRows'], 'name') !== ['home', 'blogname', 'theme_mods']) {
        fwrite(STDERR, "unexpected current compound window EXCEPT LIMIT boundary\n");
        exit(1);
    }
    if (array_column($summary['nextRows'], 'name') !== ['network_banner', 'home', 'blogname']) {
        fwrite(STDERR, "unexpected next compound window EXCEPT LIMIT boundary\n");
        exit(1);
    }
    if (!in_array('except-removal-set-changed', $summary['replanReasons'], true)) {
        fwrite(STDERR, "missing EXCEPT removal delta diagnostic\n");
        exit(1);
    }
    echo "wordpress-compound-window-except-limit-current-source-next141 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
