<?php

declare(strict_types=1);

use PortLibs\Dolt\TableSchema;

$schema = TableSchema::fromColumns([
    ['name' => 'audit_id', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'import_status', 'tag' => 2, 'type' => 'varchar(20)'],
    ['name' => 'source_post_id', 'tag' => 3, 'type' => 'bigint'],
    ['name' => 'failure_note', 'tag' => 4, 'type' => 'text'],
], [
    'checks' => [
        [
            'name' => 'wp_import_audit_chk_status',
            'expression' => "(`import_status` in ('queued','ready','failed'))",
        ],
        [
            'name' => 'wp_import_audit_chk_failed_note',
            'expression' => "((`import_status` <> 'failed') or (`failure_note` is not null))",
        ],
    ],
]);

$rows = [
    [
        'audit_id' => 1001,
        'import_status' => 'ready',
        'source_post_id' => 701,
        'failure_note' => null,
    ],
    [
        'audit_id' => 1002,
        'import_status' => 'orphaned',
        'source_post_id' => 702,
        'failure_note' => 'Plugin returned an unmapped import status.',
    ],
    [
        'audit_id' => 1003,
        'import_status' => 'failed',
        'source_post_id' => 703,
        'failure_note' => null,
    ],
];

return [
    'tableName' => 'wp_import_audit',
    'fromRootIsh' => 'review-import-branch',
    'schema' => $schema,
    'rows' => $rows,
    'expectedSummaryRows' => [
        ['table' => 'wp_import_audit', 'num_violations' => 2],
    ],
    'expectedViolationRows' => [
        [
            'from_root_ish' => 'review-import-branch',
            'violation_type' => 'check constraint',
            'audit_id' => 1002,
            'import_status' => 'orphaned',
            'source_post_id' => 702,
            'failure_note' => 'Plugin returned an unmapped import status.',
            'violation_info' => [
                'Name' => 'wp_import_audit_chk_status',
                'Expression' => "(`import_status` in ('queued','ready','failed'))",
            ],
        ],
        [
            'from_root_ish' => 'review-import-branch',
            'violation_type' => 'check constraint',
            'audit_id' => 1003,
            'import_status' => 'failed',
            'source_post_id' => 703,
            'failure_note' => null,
            'violation_info' => [
                'Name' => 'wp_import_audit_chk_failed_note',
                'Expression' => "((`import_status` <> 'failed') or (`failure_note` is not null))",
            ],
        ],
    ],
];
