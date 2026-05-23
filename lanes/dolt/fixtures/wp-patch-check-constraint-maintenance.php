<?php

declare(strict_types=1);

use PortLibs\Dolt\TableSchema;

$baseSchema = TableSchema::fromColumns([
    ['name' => 'id', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
    ['name' => 'status', 'tag' => 2, 'type' => 'varchar(20)'],
    ['name' => 'note', 'tag' => 3, 'type' => 'text'],
], [
    'checks' => [
        [
            'name' => 'wp_import_audit_chk_status',
            'expression' => "(`status` in ('queued','ready'))",
        ],
        [
            'name' => 'wp_import_audit_chk_note',
            'expression' => '(`note` is not null)',
        ],
    ],
]);

$workingSchema = TableSchema::fromColumns([
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
            'name' => 'wp_import_audit_chk_terminal_note',
            'expression' => "((`status` <> 'failed') or (`note` is not null))",
        ],
    ],
]);

return [
    'arguments' => ['HEAD', 'WORKING', 'wp_import_audit'],
    'options' => [
        'revisionGraph' => [
            ['commit_hash' => 'review-head-hash', 'parents' => [], 'refs' => ['refs/heads/main']],
        ],
        'headHash' => 'review-head-hash',
        'knownTables' => ['wp_import_audit'],
        'revisionSnapshots' => [
            'review-head-hash' => [
                [
                    'name' => 'wp_import_audit',
                    'schema' => $baseSchema,
                    'rows' => [],
                ],
            ],
            'WORKING' => [
                [
                    'name' => 'wp_import_audit',
                    'schema' => $workingSchema,
                    'rows' => [],
                ],
            ],
        ],
    ],
    'expectedStatements' => [],
    'expectedCheckDiffTypes' => ['removed', 'modified', 'added'],
    'baseSchema' => $baseSchema,
    'workingSchema' => $workingSchema,
];
