<?php

declare(strict_types=1);

use PortLibs\Dolt\TableSchema;

$schema = TableSchema::fromColumns([
    ['name' => 'id', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'status', 'tag' => 2, 'type' => 'varchar(20)'],
    ['name' => 'note', 'tag' => 3, 'type' => 'text'],
], [
    'checks' => [
        [
            'name' => 'wp_import_audit_chk_status',
            'expression' => "(`status` in ('queued','ready','failed'))",
        ],
        [
            'name' => 'wp_import_audit_chk_failed_note',
            'expression' => "((`status` <> 'failed') or (`note` is not null))",
        ],
        [
            'name' => 'wp_import_audit_chk_future_plugin',
            'expression' => "(`status` <> 'plugin-only')",
            'enforced' => false,
        ],
    ],
]);

$rows = [
    ['id' => 1, 'status' => 'queued', 'note' => null],
    ['id' => 2, 'status' => 'failed', 'note' => null],
    ['id' => 3, 'status' => 'unknown', 'note' => 'Plugin returned an unmapped status.'],
    ['id' => 4, 'status' => 'failed', 'note' => 'Needs media retry.'],
];

return [
    'schemaName' => 'wordpress',
    'tableName' => 'wp_import_audit',
    'schema' => $schema,
    'rows' => $rows,
    'expectedCheckConstraintRows' => [
        ['def', 'wordpress', 'wp_import_audit_chk_status', 'wp_import_audit', "(`status` in ('queued','ready','failed'))", 'YES'],
        ['def', 'wordpress', 'wp_import_audit_chk_failed_note', 'wp_import_audit', "((`status` <> 'failed') or (`note` is not null))", 'YES'],
        ['def', 'wordpress', 'wp_import_audit_chk_future_plugin', 'wp_import_audit', "(`status` <> 'plugin-only')", 'NO'],
    ],
    'expectedTableConstraintRows' => [
        ['PRIMARY', 'PRIMARY KEY', 'YES'],
        ['wp_import_audit_chk_status', 'CHECK', 'YES'],
        ['wp_import_audit_chk_failed_note', 'CHECK', 'YES'],
        ['wp_import_audit_chk_future_plugin', 'CHECK', 'NO'],
    ],
    'expectedViolationConstraintNames' => [
        'wp_import_audit_chk_failed_note',
        'wp_import_audit_chk_status',
    ],
    'expectedViolationRowIndexes' => [1, 2],
];
