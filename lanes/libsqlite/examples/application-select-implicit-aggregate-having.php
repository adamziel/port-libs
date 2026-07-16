<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bytes' => 110],
    ['option_id' => 6, 'option_name' => 'orphaned', 'autoload' => null, 'bytes' => null],
];

$autoloadBudgetSql = "SELECT count(*) AS option_rows FROM wp_options WHERE autoload = 'yes' HAVING sum(bytes) > 50";
$emptyImportSql = "SELECT count(*) AS missing_rows FROM wp_options WHERE option_name = 'pending_import_marker' HAVING count(*) = 0";
$transientBudgetSql = "SELECT sum(bytes) AS transient_bytes FROM wp_options WHERE option_name LIKE '%transient%' HAVING sum(bytes) >= 100";

echo json_encode([
    'applicationUse' => 'Preview copied wp_options aggregate preflight checks that use SQLite implicit single-group HAVING without GROUP BY, including empty import-marker detection.',
    'autoloadBudgetSql' => $autoloadBudgetSql,
    'autoloadBudgetRows' => SQLiteSelectSql::execute($autoloadBudgetSql, ['wp_options' => $options]),
    'emptyImportSql' => $emptyImportSql,
    'emptyImportRows' => SQLiteSelectSql::execute($emptyImportSql, ['wp_options' => $options]),
    'transientBudgetSql' => $transientBudgetSql,
    'transientBudgetRows' => SQLiteSelectSql::execute($transientBudgetSql, ['wp_options' => $options]),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
