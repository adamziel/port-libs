<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAlterTableRenamePlan;

$schemaSql = [
    'CREATE VIEW active_options AS SELECT option_name FROM wp_options WHERE autoload = \'yes\'',
    'CREATE TRIGGER wp_options AFTER UPDATE ON wp_options BEGIN INSERT INTO wp_option_audit(label, option_name) VALUES(\'wp_options\', new.option_name); END',
    'CREATE INDEX wp_options ON wp_options(option_name) WHERE autoload = \'yes\'',
];

$renamed = array_map(
    static fn (string $sql): string => SQLiteAlterTableRenamePlan::renameTableSql($sql, 'wp_options', 'wp_options_imported'),
    $schemaSql,
);

echo json_encode([
    'renamedSchemaSql' => $renamed,
    'wordpressUse' => 'Preview ALTER TABLE wp_options RENAME TO wp_options_imported schema rewrites for copied WordPress database views, triggers, and indexes without requiring ext/sqlite; dependent trigger/view SQL follows SQLite-style table-reference rewrites while object names and string literals are preserved.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
