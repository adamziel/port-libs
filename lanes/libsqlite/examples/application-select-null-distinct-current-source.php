<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'expected_autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'expected_autoload' => 'no'],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'expected_autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'expected_autoload' => 'no'],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'expected_autoload' => null],
    ['option_id' => 6, 'option_name' => 'orphaned', 'autoload' => null, 'expected_autoload' => null],
];

$meta = [
    ['meta_id' => 11, 'option_id' => 1, 'meta_key' => 'autoload', 'meta_value' => 'yes'],
    ['meta_id' => 12, 'option_id' => 2, 'meta_key' => 'autoload', 'meta_value' => 'no'],
    ['meta_id' => 13, 'option_id' => 3, 'meta_key' => 'autoload', 'meta_value' => 'yes'],
    ['meta_id' => 14, 'option_id' => 4, 'meta_key' => 'autoload', 'meta_value' => 'no'],
    ['meta_id' => 15, 'option_id' => 5, 'meta_key' => 'autoload', 'meta_value' => null],
    ['meta_id' => 16, 'option_id' => 6, 'meta_key' => 'autoload', 'meta_value' => null],
];

$driftSql = <<<SQL
SELECT option_name AS name
FROM wp_options AS w
JOIN option_meta AS m ON w.option_id = m.option_id
WHERE autoload IS DISTINCT FROM meta_value
ORDER BY w.option_id
SQL;

$stableSql = <<<SQL
SELECT option_name AS name
FROM wp_options AS w
JOIN option_meta AS m ON w.option_id = m.option_id
WHERE autoload IS NOT DISTINCT FROM meta_value
ORDER BY w.option_id
SQL;

echo json_encode([
    'applicationUse' => 'Preview copied wp_options drift checks where joined current-source rows expose qualified columns but SQL uses unique unqualified names.',
    'driftSql' => $driftSql,
    'driftedOptionNames' => array_column(SQLiteSelectSql::execute($driftSql, ['wp_options' => $options, 'option_meta' => $meta]), 'name'),
    'stableSql' => $stableSql,
    'stableOptionNames' => array_column(SQLiteSelectSql::execute($stableSql, ['wp_options' => $options, 'option_meta' => $meta]), 'name'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
