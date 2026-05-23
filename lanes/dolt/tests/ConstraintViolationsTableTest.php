<?php

declare(strict_types=1);

use PortLibs\Dolt\ConstraintViolationsTable;
use PortLibs\Dolt\TableSchema;

return [
    'constraint violations summary table emits upstream table counts' => static function (TestRunner $t): void {
        $rows = (new ConstraintViolationsTable())->summaryRows([
            'test' => [
                ['violation_type' => ConstraintViolationsTable::TYPE_UNIQUE_INDEX],
                ['violation_type' => ConstraintViolationsTable::TYPE_UNIQUE_INDEX],
            ],
            'clean_table' => [],
            'child' => [
                ['violation_type' => ConstraintViolationsTable::TYPE_FOREIGN_KEY],
            ],
        ]);

        $t->same([
            ['table' => 'test', 'num_violations' => 2],
            ['table' => 'child', 'num_violations' => 1],
        ], $rows);
    },
    'constraint violations table projects unique index rows with from rootish and metadata' => static function (TestRunner $t): void {
        $schema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
            ['name' => 'v1', 'tag' => 2, 'type' => 'bigint'],
        ]);

        $rows = (new ConstraintViolationsTable())->rowsForTable($schema, [
            [
                'from_root_ish' => 'k8l3h5k5at3hhfpg8gck392vhomstea0',
                'violation_type' => ConstraintViolationsTable::TYPE_UNIQUE_INDEX,
                'row' => ['pk' => 3, 'v1' => 3],
                'violation_info' => ['Name' => 'v1', 'Columns' => ['v1']],
            ],
            [
                'from_root_ish' => 'k8l3h5k5at3hhfpg8gck392vhomstea0',
                'violation_type' => ConstraintViolationsTable::TYPE_UNIQUE_INDEX,
                'row' => ['pk' => 4, 'v1' => 3],
                'violation_info' => ['Name' => 'v1', 'Columns' => ['v1']],
            ],
        ]);

        $t->same([
            [
                'from_root_ish' => 'k8l3h5k5at3hhfpg8gck392vhomstea0',
                'violation_type' => 'unique index',
                'pk' => 3,
                'v1' => 3,
                'violation_info' => ['Name' => 'v1', 'Columns' => ['v1']],
            ],
            [
                'from_root_ish' => 'k8l3h5k5at3hhfpg8gck392vhomstea0',
                'violation_type' => 'unique index',
                'pk' => 4,
                'v1' => 3,
                'violation_info' => ['Name' => 'v1', 'Columns' => ['v1']],
            ],
        ], $rows);
    },
    'constraint violations table maps check validator rows like upstream verify constraints' => static function (TestRunner $t): void {
        $schema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'col1', 'tag' => 2, 'type' => 'int'],
            ['name' => 'col2', 'tag' => 3, 'type' => 'int'],
        ], [
            'checks' => [[
                'name' => 't_chk_jtampqtl',
                'expression' => '(`col1` <> `col2`)',
            ]],
        ]);

        $rows = (new ConstraintViolationsTable())->checkConstraintRows(
            $schema,
            [['pk' => 1, 'col1' => 42, 'col2' => 42]],
            't',
            'right-commit'
        );

        $t->same([
            [
                'from_root_ish' => 'right-commit',
                'violation_type' => 'check constraint',
                'pk' => 1,
                'col1' => 42,
                'col2' => 42,
                'violation_info' => [
                    'Name' => 't_chk_jtampqtl',
                    'Expression' => '(`col1` <> `col2`)',
                ],
            ],
        ], $rows);
    },
    'constraint violations table validates missing keys and unknown violation types' => static function (TestRunner $t): void {
        $schema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'value', 'tag' => 2, 'type' => 'int'],
        ]);
        $table = new ConstraintViolationsTable();

        $t->throws(InvalidArgumentException::class, static fn () => $table->rowsForTable($schema, [[
            'violation_type' => 'bogus',
            'row' => ['pk' => 1, 'value' => 2],
            'violation_info' => [],
        ]]));
        $t->throws(InvalidArgumentException::class, static fn () => $table->rowsForTable($schema, [[
            'violation_type' => ConstraintViolationsTable::TYPE_NOT_NULL,
            'row' => ['value' => 2],
            'columns' => ['value'],
        ]]));
    },
    'wordpress constraint violation example surfaces invalid import audit rows' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-constraint-violation-review.php';
        $output = require __DIR__ . '/../examples/wordpress-constraint-violation-review.php';

        $t->same($fixture['expectedSummaryRows'], $output['summaryRows']);
        $t->same($fixture['expectedViolationRows'], $output['violationRows']);
        $t->same([
            [
                'table' => 'wp_import_audit',
                'audit_id' => 1002,
                'status' => 'orphaned',
                'constraint' => 'wp_import_audit_chk_status',
            ],
            [
                'table' => 'wp_import_audit',
                'audit_id' => 1003,
                'status' => 'failed',
                'constraint' => 'wp_import_audit_chk_failed_note',
            ],
        ], $output['reviewRows']);
        $t->same(2, count($output['violationRows']));
    },
];
