<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 16],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 14],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 9],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 4, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'weight' => 18],
];

$sql = <<<'SQL'
WITH RECURSIVE staged(id, label, weight) AS (
    VALUES (1, 'seed', 22)
    UNION ALL
    SELECT id + 1, 'seed:' || (id + 1), weight - 3
      FROM staged
     WHERE id < 7
     LIMIT 1, 4
)
SELECT id,
       label,
       row_number() OVER (ORDER BY id) AS rank
  FROM staged
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (ORDER BY weight DESC, option_id) AS rank
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY rank, id
 LIMIT 5 OFFSET 1
SQL;

$summary = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveCommaLimitWindow(
    $sql,
    ['wp_options' => $currentOptions],
    ['wp_options' => $nextOptions],
);

echo json_encode([
    'applicationUse' => 'Preview a copied wp_options import that combines a recursive staging queue using SQLite LIMIT offset,count syntax with window-ranked option rows before a compound SELECT final LIMIT.',
    'currentLabels' => array_column(SQLiteSelectSql::execute($sql, ['wp_options' => $currentOptions]), 'label'),
    'nextLabels' => array_column(SQLiteSelectSql::execute($sql, ['wp_options' => $nextOptions]), 'label'),
    'recursiveSkippedByCommaLimit' => $summary['recursive']['currentSkippedLabels'],
    'dependencies' => $summary['dependencies'],
    'status' => $summary['status'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
