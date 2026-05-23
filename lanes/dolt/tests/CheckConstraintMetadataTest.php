<?php

declare(strict_types=1);

use PortLibs\Dolt\CheckConstraintValidator;
use PortLibs\Dolt\InformationSchema;
use PortLibs\Dolt\TableSchema;

return [
    'check constraint validator maps upstream basic insert semantics' => static function (TestRunner $t): void {
        $schema = TableSchema::fromColumns([
            ['name' => 'a', 'tag' => 1, 'type' => 'integer', 'primaryKey' => true],
            ['name' => 'b', 'tag' => 2, 'type' => 'integer'],
        ], [
            'checks' => [
                ['name' => 't_chk_a_gt_3', 'expression' => '(`a` > 3)'],
                ['name' => 't_chk_b_gt_a', 'expression' => '(`b` > `a`)'],
                ['name' => 't_chk_not_enforced', 'expression' => '(`a` > 100)', 'enforced' => false],
            ],
        ]);
        $violations = (new CheckConstraintValidator())->violations($schema, [
            ['a' => 5, 'b' => 6],
            ['a' => 3, 'b' => 4],
            ['a' => 4, 'b' => 2],
            ['a' => 6, 'b' => null],
        ], 't');

        $t->same(['t_chk_a_gt_3', 't_chk_b_gt_a'], array_column($violations, 'constraint_name'));
        $t->same([1, 2], array_column($violations, 'row_index'));
        $t->same(['check constraint', 'check constraint'], array_column($violations, 'violation_type'));
    },
    'information schema exposes check constraints like upstream' => static function (TestRunner $t): void {
        $schema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'c1', 'tag' => 2, 'type' => 'int'],
        ], [
            'checks' => [[
                'name' => 'foo_chk_rvgogafi',
                'expression' => '(`c1` > 3)',
            ]],
        ]);
        $informationSchema = new InformationSchema();

        $checkRows = $informationSchema->checkConstraints(['foo' => $schema], 'dolt-repo');
        $tableRows = $informationSchema->tableConstraints(['foo' => $schema], 'dolt-repo');

        $t->same([
            ['def', 'foo_chk_rvgogafi', '(`c1` > 3)'],
        ], array_map(
            static fn (array $row): array => [$row['constraint_catalog'], $row['constraint_name'], $row['check_clause']],
            $checkRows
        ));
        $t->same([
            ['PRIMARY', 'PRIMARY KEY', 'YES'],
            ['foo_chk_rvgogafi', 'CHECK', 'YES'],
        ], array_map(
            static fn (array $row): array => [$row['constraint_name'], $row['constraint_type'], $row['enforced']],
            $tableRows
        ));
    },
    'information schema keeps copied table check names distinct' => static function (TestRunner $t): void {
        $schema1 = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'val', 'tag' => 2, 'type' => 'int'],
        ], [
            'checks' => [['name' => 't1_chk_val', 'expression' => '(`val` > 0)']],
        ]);
        $schema2 = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'val', 'tag' => 2, 'type' => 'int'],
        ], [
            'checks' => [['name' => 't2_chk_val', 'expression' => '(`val` > 0)']],
        ]);

        $tableRows = array_filter(
            (new InformationSchema())->tableConstraints(['t1' => $schema1, 't2' => $schema2], 'dolt-repo'),
            static fn (array $row): bool => $row['constraint_type'] === 'CHECK'
        );

        $t->same(2, count($tableRows));
        $t->same(2, count(array_unique(array_column($tableRows, 'constraint_name'))));
    },
    'wordpress check constraint information schema example exposes invalid import statuses' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-check-constraint-information-schema.php';
        $output = require __DIR__ . '/../examples/wordpress-check-constraint-information-schema.php';

        $t->same($fixture['expectedCheckConstraintRows'], array_map(
            static fn (array $row): array => [
                $row['constraint_catalog'],
                $row['constraint_schema'],
                $row['constraint_name'],
                $row['table_name'],
                $row['check_clause'],
                $row['enforced'],
            ],
            $output['checkConstraints']
        ));
        $t->same($fixture['expectedTableConstraintRows'], array_map(
            static fn (array $row): array => [
                $row['constraint_name'],
                $row['constraint_type'],
                $row['enforced'],
            ],
            $output['tableConstraints']
        ));
        $t->same($fixture['expectedViolationConstraintNames'], $output['violationConstraintNames']);
        $t->same($fixture['expectedViolationRowIndexes'], $output['violationRowIndexes']);
    },
];
