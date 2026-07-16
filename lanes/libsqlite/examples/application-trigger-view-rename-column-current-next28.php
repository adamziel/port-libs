<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAlterTableRenamePlan;

$schemaSql = [
    'CREATE VIEW copied_option_aliases AS SELECT option_name option_name, lower(option_name) normalized FROM wp_options option_name WHERE option_name.option_name <> \'\' ORDER BY option_name.option_name',
    'CREATE VIEW copied_option_cte AS WITH option_name AS (SELECT option_name FROM wp_options) SELECT option_name FROM option_name',
    'CREATE TRIGGER copied_option_audit AFTER UPDATE OF option_name ON wp_options BEGIN SELECT new.option_name option_name, old.option_name FROM wp_options option_name WHERE option_name.option_name = new.option_name; END',
    'CREATE TRIGGER copied_option_guard BEFORE INSERT ON wp_options WHEN EXISTS(SELECT 1 FROM wp_options option_name WHERE option_name.option_name = new.option_name) BEGIN SELECT raise(abort, \'option_name\'); END',
];

$renamed = array_map(
    static fn (string $sql): string => SQLiteAlterTableRenamePlan::renameColumnSql($sql, 'wp_options', 'option_name', 'option_key'),
    $schemaSql,
);

echo json_encode([
    'renamedTriggerViewSql' => $renamed,
    'applicationUse' => 'Preview ALTER TABLE wp_options RENAME COLUMN option_name TO option_key across copied Application trigger/view SQL where implicit aliases, table aliases, and CTE names happen to match the old column name; source column references are rewritten while alias/source names and raise() messages stay stable without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
