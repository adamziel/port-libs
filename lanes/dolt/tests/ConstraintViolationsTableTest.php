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
    'constraint violations merge error groups upstream descriptions with row counts' => static function (TestRunner $t): void {
        $table = new ConstraintViolationsTable();
        $violationsByTable = [
            't' => [
                [
                    'violation_type' => ConstraintViolationsTable::TYPE_UNIQUE_INDEX,
                    'violation_info' => ['Name' => 'col1', 'Columns' => ['col1']],
                ],
                [
                    'violation_type' => ConstraintViolationsTable::TYPE_UNIQUE_INDEX,
                    'violation_info' => ['Name' => 'col1', 'Columns' => ['col1']],
                ],
                [
                    'violation_type' => ConstraintViolationsTable::TYPE_UNIQUE_INDEX,
                    'violation_info' => ['Name' => 'col2', 'Columns' => ['col2']],
                ],
                [
                    'violation_type' => ConstraintViolationsTable::TYPE_UNIQUE_INDEX,
                    'violation_info' => ['Name' => 'col2', 'Columns' => ['col2']],
                ],
            ],
        ];

        $expectedSummary = "\nType: Unique Key Constraint Violation,\n"
            . "\tName: col1,\n"
            . "\tColumns: [col1] (2 row(s))"
            . "\nType: Unique Key Constraint Violation,\n"
            . "\tName: col2,\n"
            . "\tColumns: [col2] (2 row(s))";

        $t->same($expectedSummary, $table->mergeViolationSummaryText($violationsByTable));
        $t->same(
            ConstraintViolationsTable::UNRESOLVED_CONSTRAINT_VIOLATIONS_ERROR
                . ConstraintViolationsTable::CONSTRAINT_VIOLATIONS_LIST_PREFIX
                . $expectedSummary,
            $table->unresolvedMergeError($violationsByTable)
        );
    },
    'constraint violations merge error renders foreign key null and check summaries' => static function (TestRunner $t): void {
        $summary = (new ConstraintViolationsTable())->mergeViolationSummaryText([
            'child' => [
                [
                    'violation_type' => ConstraintViolationsTable::TYPE_FOREIGN_KEY,
                    'violation_info' => [
                        'ForeignKey' => 'child_ibfk_1',
                        'Table' => 'child',
                        'ReferencedTable' => 'parent',
                        'Index' => 'fk',
                        'ReferencedIndex' => '',
                    ],
                ],
                [
                    'violation_type' => ConstraintViolationsTable::TYPE_FOREIGN_KEY,
                    'violation_info' => [
                        'ForeignKey' => 'child_ibfk_1',
                        'Table' => 'child',
                        'ReferencedTable' => 'parent',
                        'Index' => 'fk',
                        'ReferencedIndex' => '',
                    ],
                ],
                [
                    'violation_type' => ConstraintViolationsTable::TYPE_FOREIGN_KEY,
                    'violation_info' => [
                        'ForeignKey' => 'child_ibfk_1',
                        'Table' => 'child',
                        'ReferencedTable' => 'parent',
                        'Index' => 'fk',
                        'ReferencedIndex' => '',
                    ],
                ],
            ],
            't' => [
                [
                    'violation_type' => ConstraintViolationsTable::TYPE_NOT_NULL,
                    'violation_info' => ['Columns' => ['c']],
                ],
                [
                    'violation_type' => ConstraintViolationsTable::TYPE_NOT_NULL,
                    'violation_info' => ['Columns' => ['c']],
                ],
            ],
            'checks' => [
                [
                    'violation_type' => ConstraintViolationsTable::TYPE_CHECK_CONSTRAINT,
                    'violation_info' => ['Name' => 'chk_positive', 'Expression' => '(`col` > 0)'],
                ],
                [
                    'violation_type' => ConstraintViolationsTable::TYPE_CHECK_CONSTRAINT,
                    'violation_info' => ['Name' => 'chk_positive', 'Expression' => '(`col` > 0)'],
                ],
            ],
        ]);

        $t->same(
            "\nType: Foreign Key Constraint Violation\n"
                . "\tForeignKey: child_ibfk_1,\n"
                . "\tTable: child,\n"
                . "\tReferencedTable: parent,\n"
                . "\tIndex: fk,\n"
                . "\tReferencedIndex:  (3 row(s)), "
                . "\nType: Null Constraint Violation,\n"
                . "\tColumns: [c] (2 row(s)), "
                . "\nType: Check Constraint Violation,\n"
                . "\tName: chk_positive,\n"
                . "\tExpression: (`col` > 0) (2 row(s))",
            $summary
        );
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
    'constraint violations table builds foreign key metadata like upstream merge violations' => static function (TestRunner $t): void {
        $schema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
            ['name' => 'parent_id', 'tag' => 2, 'type' => 'bigint'],
            ['name' => 'label', 'tag' => 3, 'type' => 'varchar(100)'],
        ]);

        $rows = (new ConstraintViolationsTable())->rowsForTable($schema, [
            [
                'violation_type' => ConstraintViolationsTable::TYPE_FOREIGN_KEY,
                'row' => ['pk' => 7, 'parent_id' => 42, 'label' => 'dangling child'],
                'foreign_key' => 'fk_child_parent',
                'index_name' => 'fk_child_parent',
                'table' => 'child',
                'columns' => ['parent_id'],
                'on_delete' => 'cascade',
                'on_update' => 'restrict',
                'referenced_index' => '',
                'referenced_table' => 'parent',
                'referenced_columns' => ['id'],
            ],
        ], 'their-root');

        $t->same([
            [
                'from_root_ish' => 'their-root',
                'violation_type' => 'foreign key',
                'pk' => 7,
                'parent_id' => 42,
                'label' => 'dangling child',
                'violation_info' => [
                    'Index' => 'fk_child_parent',
                    'Table' => 'child',
                    'Columns' => ['parent_id'],
                    'OnDelete' => 'CASCADE',
                    'OnUpdate' => 'RESTRICT',
                    'ForeignKey' => 'fk_child_parent',
                    'ReferencedIndex' => '',
                    'ReferencedTable' => 'parent',
                    'ReferencedColumns' => ['id'],
                ],
            ],
        ], $rows);
    },
    'constraint violation deletes can target one violation on a multi-violation row' => static function (TestRunner $t): void {
        $schema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'a', 'tag' => 2, 'type' => 'int'],
            ['name' => 'b', 'tag' => 3, 'type' => 'int'],
        ]);
        $table = new ConstraintViolationsTable();
        $violations = [
            [
                'violation_type' => ConstraintViolationsTable::TYPE_UNIQUE_INDEX,
                'row' => ['pk' => 1, 'a' => 0, 'b' => 0],
                'violation_info' => ['Name' => 'ua', 'Columns' => ['a']],
            ],
            [
                'violation_type' => ConstraintViolationsTable::TYPE_UNIQUE_INDEX,
                'row' => ['pk' => 1, 'a' => 0, 'b' => 0],
                'violation_info' => ['Name' => 'ub', 'Columns' => ['b']],
            ],
            [
                'violation_type' => ConstraintViolationsTable::TYPE_UNIQUE_INDEX,
                'row' => ['pk' => 2, 'a' => 0, 'b' => 0],
                'violation_info' => ['Name' => 'ua', 'Columns' => ['a']],
            ],
            [
                'violation_type' => ConstraintViolationsTable::TYPE_UNIQUE_INDEX,
                'row' => ['pk' => 2, 'a' => 0, 'b' => 0],
                'violation_info' => ['Name' => 'ub', 'Columns' => ['b']],
            ],
        ];

        $singleDelete = $table->deleteRowsForTable($schema, $violations, [
            'pk' => 1,
            'violation_type' => ConstraintViolationsTable::TYPE_UNIQUE_INDEX,
            'violation_info.Name' => 'ua',
        ], 'merge-root');

        $t->same(1, $singleDelete['rows_affected']);
        $t->same([
            [
                'from_root_ish' => 'merge-root',
                'violation_type' => 'unique index',
                'pk' => 1,
                'a' => 0,
                'b' => 0,
                'violation_info' => ['Name' => 'ua', 'Columns' => ['a']],
            ],
        ], $singleDelete['deleted_rows']);
        $t->same(3, count($singleDelete['remaining_rows']));
        $t->same(1, count(array_filter(
            $singleDelete['remaining_rows'],
            static fn (array $row): bool => $row['pk'] === 1
        )));

        $rowDelete = $table->deleteRowsForTable($schema, $singleDelete['remaining_violations'], [
            'row' => ['pk' => 1],
        ], 'merge-root');

        $t->same(1, $rowDelete['rows_affected']);
        $t->same(2, count($rowDelete['remaining_rows']));
        $t->same([], array_values(array_filter(
            $rowDelete['remaining_rows'],
            static fn (array $row): bool => $row['pk'] === 1
        )));

        $bulkDelete = $table->deleteRowsForTable($schema, $rowDelete['remaining_violations'], [
            'pk' => 2,
        ], 'merge-root');

        $t->same(2, $bulkDelete['rows_affected']);
        $t->same([], $bulkDelete['remaining_rows']);
        $t->same([], $bulkDelete['remaining_violations']);
    },
    'constraint violation deletes handle keyless row hashes for unique and foreign key cleanup' => static function (TestRunner $t): void {
        $schema = TableSchema::fromColumns([
            ['name' => 'aColumn', 'tag' => 1, 'type' => 'int'],
            ['name' => 'bColumn', 'tag' => 2, 'type' => 'int'],
        ]);
        $table = new ConstraintViolationsTable();
        $violations = [
            [
                'violation_type' => ConstraintViolationsTable::TYPE_UNIQUE_INDEX,
                'dolt_row_hash' => '5A1ED8633E1842FCA8EE529E4F1C5944',
                'row' => ['aColumn' => 1, 'bColumn' => 2],
                'violation_info' => ['Name' => 'aColumn_UNIQUE', 'Columns' => ['aColumn']],
            ],
            [
                'violation_type' => ConstraintViolationsTable::TYPE_FOREIGN_KEY,
                'dolt_row_hash' => '13F8480978D0556FA9AE6DF5745A7ACA',
                'row' => ['aColumn' => 2, 'bColumn' => -1],
                'foreign_key' => 'atable_ibfk_1',
                'index_name' => 'bColumn',
                'table' => 'aTable',
                'columns' => ['bColumn'],
                'referenced_index' => '',
                'referenced_table' => 'parent',
                'referenced_columns' => ['pk'],
            ],
        ];

        $singleDelete = $table->deleteRowsForTable($schema, $violations, [
            'dolt_row_hash' => '5A1ED8633E1842FCA8EE529E4F1C5944',
        ], 'side-root');

        $t->same(1, $singleDelete['rows_affected']);
        $t->same('unique index', $singleDelete['deleted_rows'][0]['violation_type']);
        $t->same([
            [
                'from_root_ish' => 'side-root',
                'violation_type' => 'foreign key',
                'dolt_row_hash' => '13F8480978D0556FA9AE6DF5745A7ACA',
                'aColumn' => 2,
                'bColumn' => -1,
                'violation_info' => [
                    'Index' => 'bColumn',
                    'Table' => 'aTable',
                    'Columns' => ['bColumn'],
                    'OnDelete' => 'RESTRICT',
                    'OnUpdate' => 'RESTRICT',
                    'ForeignKey' => 'atable_ibfk_1',
                    'ReferencedIndex' => '',
                    'ReferencedTable' => 'parent',
                    'ReferencedColumns' => ['pk'],
                ],
            ],
        ], $singleDelete['remaining_rows']);

        $bulkDelete = $table->deleteRowsForTable($schema, $singleDelete['remaining_violations'], [], 'side-root');

        $t->same(1, $bulkDelete['rows_affected']);
        $t->same([], $bulkDelete['remaining_rows']);
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
    'wordpress foreign key constraint violation example surfaces orphaned import relations' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-foreign-key-constraint-violation-review.php';
        $output = require __DIR__ . '/../examples/wordpress-foreign-key-constraint-violation-review.php';

        $t->same($fixture['expectedSummaryRows'], $output['summaryRows']);
        $t->same($fixture['expectedViolationRows'], $output['violationRows']);
        $t->same([
            [
                'table' => 'wp_postmeta',
                'meta_id' => 8102,
                'orphaned_post_id' => 99001,
                'meta_key' => '_thumbnail_id',
                'foreign_key' => 'fk_wp_postmeta_post',
                'referenced_table' => 'wp_posts',
                'resolution' => 'restore parent post or remove dangling postmeta before promotion',
            ],
            [
                'table' => 'wp_postmeta',
                'meta_id' => 8103,
                'orphaned_post_id' => 99002,
                'meta_key' => '_wp_attached_file',
                'foreign_key' => 'fk_wp_postmeta_post',
                'referenced_table' => 'wp_posts',
                'resolution' => 'restore parent post or remove dangling postmeta before promotion',
            ],
        ], $output['reviewRows']);
        $t->same(1, $output['singleDelete']['rows_affected']);
        $t->same($fixture['expectedRemainingAfterSingleDelete'], $output['singleDelete']['remaining_rows']);
        $t->same([
            ['table' => 'wp_postmeta', 'num_violations' => 1],
        ], $output['singleDelete']['remaining_summary']);
        $t->same(1, $output['bulkDelete']['rows_affected']);
        $t->same([], $output['bulkDelete']['remaining_rows']);
        $t->same([], $output['bulkDelete']['remaining_summary']);
    },
];
