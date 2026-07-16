<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAlterTableRenamePlan;

$schemaSql = [
    'CREATE VIEW active_option_keys AS SELECT option_name AS option_name, lower(option_name) AS normalized FROM wp_options WHERE option_name <> \'\' ORDER BY lower(option_name)',
    'CREATE VIEW option_key_meta AS SELECT o.option_name FROM wp_options o LEFT JOIN wp_postmeta m ON m.meta_key = o.option_name WHERE EXISTS (SELECT 1 FROM wp_options i WHERE i.option_name = o.option_name)',
    'CREATE TRIGGER audit_option_key AFTER UPDATE OF option_name ON wp_options WHEN old.option_name <> new.option_name BEGIN INSERT INTO wp_option_audit(label, option_name) VALUES(\'option_name\', new.option_name); END',
    'CREATE TRIGGER normalize_option_key AFTER UPDATE ON wp_options BEGIN UPDATE wp_options SET option_name = lower(new.option_name) WHERE option_id = new.option_id; END',
    'CREATE INDEX wp_options_option_key_expr ON wp_options(lower(option_name), option_id) WHERE option_name IS NOT NULL',
];

$renamed = array_map(
    static fn (string $sql): string => SQLiteAlterTableRenamePlan::renameColumnSql($sql, 'wp_options', 'option_name', 'option_key'),
    $schemaSql,
);

echo json_encode([
    'renamedColumnCurrentSourceSql' => $renamed,
    'applicationUse' => 'Preview ALTER TABLE wp_options RENAME COLUMN option_name TO option_key current-source schema rewrites for copied Application views, triggers, and expression indexes without requiring ext/sqlite; explicit SELECT aliases and string literals are preserved while source references, old/new trigger references, UPDATE OF lists, and expression predicates are rewritten.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
