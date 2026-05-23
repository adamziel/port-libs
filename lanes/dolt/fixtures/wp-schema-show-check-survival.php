<?php

declare(strict_types=1);

use PortLibs\Dolt\TableSchema;

$schema = TableSchema::fromColumns([
    ['name' => 'audit_id', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'import_status', 'tag' => 2, 'type' => 'varchar(20)'],
    ['name' => 'failure_note', 'tag' => 3, 'type' => 'text'],
    ['name' => 'reviewed_at', 'tag' => 4, 'type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP'],
], [
    'checks' => [
        [
            'name' => 'wp_import_audit_chk_status',
            'expression' => "(`import_status` in ('queued','ready','failed'))",
        ],
        [
            'name' => 'wp_import_audit_chk_failure_note',
            'expression' => "((`import_status` <> 'failed') or (`failure_note` is not null))",
        ],
    ],
]);

return [
    'commit' => 'WORKING',
    'requestedTables' => ['wp_import_audit_review'],
    'tables' => [
        'wp_import_audit_review' => $schema,
        'dolt_docs' => TableSchema::fromColumns([
            ['name' => 'doc_pk', 'tag' => 100, 'type' => 'varchar(120)', 'primaryKey' => true],
        ]),
    ],
    'expectedTableHeader' => 'wp_import_audit_review @ WORKING',
    'expectedCheckFragments' => [
        "CONSTRAINT `wp_import_audit_chk_status` CHECK ((`import_status` in ('queued','ready','failed')))",
        "CONSTRAINT `wp_import_audit_chk_failure_note` CHECK (((`import_status` <> 'failed') or (`failure_note` is not null)))",
    ],
];
