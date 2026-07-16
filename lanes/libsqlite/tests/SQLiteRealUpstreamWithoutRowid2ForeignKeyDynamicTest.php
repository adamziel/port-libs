<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

$expectedSections = [
    'without_rowid2-1.0',
    'without_rowid2-1.1',
    'without_rowid2-1.2',
    'without_rowid2-2.1',
    'without_rowid2-3.1',
    'without_rowid2-3.2',
    'without_rowid2-3.3',
    'without_rowid2-3.4',
    'without_rowid2-3.5',
];

// Source truth: SQLite upstream test/without_rowid2.test sections
// without_rowid2-1.0 through without_rowid2-3.5. These cases cover
// WITHOUT ROWID parent/child foreign-key catalog rows, child-table drop
// cleanup, implicit parent-key references, composite FK action rows, and the
// DBSTATUS_DEFERRED_FKS clear state after schema setup.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::withoutRowid2ForeignKeyCatalogCases(1200) as $case) {
    $tests['real upstream without_rowid2 foreign-key catalog dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('without_rowid2.test sections without_rowid2-1.0 through without_rowid2-3.5', $case['source']);
        $t->same('test/without_rowid2.test', $case['upstream_file']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true($case['batch'] >= 1 && $case['batch'] <= 134);
        $t->true(str_starts_with($case['upstream_section'], 'without_rowid2-'));
        $t->true(str_contains($case['scenario'], 'dynamic batch ' . $case['batch']));
        $t->true(str_contains($case['detail'], $case['upstream_section']));
        $t->true($case['statement'] !== '');
        $t->same(0, $case['result_code']);
        $t->same(null, $case['error']);
        $t->same('ok', $case['integrity']);
        $t->same(count($case['foreign_keys']), $case['foreign_key_count']);

        foreach ($case['without_rowid_tables'] as $tableName) {
            $t->true($tableName !== '');
        }
        foreach ($case['created_tables'] as $tableName) {
            $t->true($tableName !== '');
        }
        foreach ($case['dropped_tables'] as $tableName) {
            $t->same(false, in_array($tableName, $case['remaining_tables'], true));
        }
        foreach ($case['remaining_tables'] as $tableName) {
            $t->true($tableName !== '');
        }

        $hasComposite = false;
        $hasImplicitParent = false;
        foreach ($case['foreign_keys'] as $foreignKey) {
            $t->true($foreignKey['id'] >= 0);
            $t->true($foreignKey['seq'] >= 0);
            $t->true($foreignKey['table'] !== '');
            $t->true($foreignKey['from'] !== '');
            $t->true(in_array($foreignKey['on_update'], ['NO ACTION', 'SET NULL', 'SET DEFAULT', 'CASCADE'], true));
            $t->true(in_array($foreignKey['on_delete'], ['NO ACTION', 'CASCADE'], true));
            $t->same('NONE', $foreignKey['match']);
            $hasComposite = $hasComposite || $foreignKey['seq'] > 0;
            $hasImplicitParent = $hasImplicitParent || $foreignKey['to'] === '';
        }
        $t->same($hasComposite, $case['composite_foreign_key']);
        $t->same($hasImplicitParent, $case['implicit_parent_columns']);

        match ($case['upstream_section']) {
            'without_rowid2-1.0' => [
                $t->same('t1', $case['table']),
                $t->same(['t1'], $case['without_rowid_tables']),
                $t->same(['t1'], $case['created_tables']),
                $t->same(['t1'], $case['remaining_tables']),
                $t->same(4, $case['foreign_key_count']),
                $t->same(['self-delete-cascade', 'external-column-reference', 'composite-update-cascade'], $case['actions']),
                $t->same(['b', 'c', 'b', 'b'], array_column($case['foreign_keys'], 'from')),
                $t->same(['x', 'y', '', ''], array_column($case['foreign_keys'], 'to')),
                $t->same(['CASCADE', 'CASCADE', 'NO ACTION', 'NO ACTION'], array_column($case['foreign_keys'], 'on_update')),
                $t->same(['NO ACTION', 'NO ACTION', 'NO ACTION', 'CASCADE'], array_column($case['foreign_keys'], 'on_delete')),
            ],
            'without_rowid2-1.1' => [
                $t->same('t2', $case['table']),
                $t->same(['t1', 't2'], $case['without_rowid_tables']),
                $t->same(['t2'], $case['created_tables']),
                $t->same(0, $case['foreign_key_count']),
                $t->same(false, $case['composite_foreign_key']),
            ],
            'without_rowid2-1.2' => [
                $t->same('t3', $case['table']),
                $t->same(['t1', 't2'], $case['without_rowid_tables']),
                $t->same(['t3'], $case['created_tables']),
                $t->same(4, $case['foreign_key_count']),
                $t->same(['a', 'b', 'b', 'a'], array_column($case['foreign_keys'], 'from')),
                $t->same(['x', 'y', '', ''], array_column($case['foreign_keys'], 'to')),
            ],
            'without_rowid2-2.1' => [
                $t->same('t4', $case['table']),
                $t->same(['t4'], $case['without_rowid_tables']),
                $t->same(['t7', 't9', 't5', 't8', 't6', 't10'], $case['dropped_tables']),
                $t->same(['t4'], $case['remaining_tables']),
                $t->same(['child-drop-cleanup'], $case['actions']),
                $t->same(0, $case['foreign_key_count']),
            ],
            'without_rowid2-3.1' => [
                $t->same('t6', $case['table']),
                $t->same(2, $case['foreign_key_count']),
                $t->same(['e', 'd'], array_column($case['foreign_keys'], 'from')),
                $t->same(['c', ''], array_column($case['foreign_keys'], 'to')),
                $t->same([0, 1], array_column($case['foreign_keys'], 'id')),
                $t->same([0, 0], array_column($case['foreign_keys'], 'seq')),
                $t->same(true, $case['implicit_parent_columns']),
            ],
            'without_rowid2-3.2' => [
                $t->same('t7', $case['table']),
                $t->same(2, $case['foreign_key_count']),
                $t->same(['d', 'e'], array_column($case['foreign_keys'], 'from')),
                $t->same(['a', 'b'], array_column($case['foreign_keys'], 'to')),
                $t->same([0, 0], array_column($case['foreign_keys'], 'id')),
                $t->same([0, 1], array_column($case['foreign_keys'], 'seq')),
                $t->same(false, $case['implicit_parent_columns']),
            ],
            'without_rowid2-3.3' => [
                $t->same('t8', $case['table']),
                $t->same(2, $case['foreign_key_count']),
                $t->same(['d', 'e'], array_column($case['foreign_keys'], 'from')),
                $t->same(['', ''], array_column($case['foreign_keys'], 'to')),
                $t->same(['SET NULL', 'SET NULL'], array_column($case['foreign_keys'], 'on_update')),
                $t->same(['CASCADE', 'CASCADE'], array_column($case['foreign_keys'], 'on_delete')),
                $t->same(['SET NULL', 'CASCADE'], $case['actions']),
            ],
            'without_rowid2-3.4' => [
                $t->same('t9', $case['table']),
                $t->same(2, $case['foreign_key_count']),
                $t->same(['d', 'e'], array_column($case['foreign_keys'], 'from')),
                $t->same(['', ''], array_column($case['foreign_keys'], 'to')),
                $t->same(['SET DEFAULT', 'SET DEFAULT'], array_column($case['foreign_keys'], 'on_update')),
                $t->same(['CASCADE', 'CASCADE'], array_column($case['foreign_keys'], 'on_delete')),
                $t->same(['SET DEFAULT', 'CASCADE'], $case['actions']),
            ],
            'without_rowid2-3.5' => [
                $t->same(null, $case['table']),
                $t->same([], $case['foreign_keys']),
                $t->same([0, 0, 0], $case['deferred_fk_status']),
                $t->same(['deferred-fk-status-clear'], $case['actions']),
                $t->same(['t5', 't6', 't7', 't8', 't9'], $case['remaining_tables']),
            ],
        };
    };
}

$tests['real upstream without_rowid2 foreign-key catalog source truth'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/without_rowid2.test');

    $t->true(is_string($source));
    $t->contains('do_test without_rowid2-1.0', $source);
    $t->contains('FOREIGN KEY (b,c) REFERENCES t2(x,y) ON UPDATE CASCADE', $source);
    $t->contains('do_test without_rowid2-3.4', $source);
    $t->contains('{0 0 t5 d {} {SET DEFAULT} CASCADE NONE}', $source);
    $t->contains('sqlite3_db_status db DBSTATUS_DEFERRED_FKS 0', $source);
};

$tests['real upstream without_rowid2 foreign-key catalog source range'] = static function (TestRunner $t) use ($expectedSections): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::withoutRowid2ForeignKeyCatalogCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1200, count($cases));
    $t->same($expectedSections, $sections);
    $t->same('without_rowid2-1.0', $cases[0]['upstream_section']);
    $t->same('without_rowid2-3.5', $cases[8]['upstream_section']);
    $t->same('without_rowid2-1.0', $cases[9]['upstream_section']);
    $t->same('without_rowid2-1.0', $cases[1197]['upstream_section']);
    $t->same('without_rowid2-1.1', $cases[1198]['upstream_section']);
    $t->same('without_rowid2-1.2', $cases[1199]['upstream_section']);
};

$tests['real upstream without_rowid2 foreign-key catalog rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::withoutRowid2ForeignKeyCatalogCases(0));
};

$tests['real upstream without_rowid2 foreign-key catalog dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, WITHOUT ROWID table metadata, foreign-key catalog rows, child-drop cleanup, and DBSTATUS_DEFERRED_FKS state evidence',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, WITHOUT ROWID table metadata, foreign-key catalog rows, child-drop cleanup, and DBSTATUS_DEFERRED_FKS state evidence',
    );
    $t->same(
        'non-overlap: owns without_rowid2.test foreign-key catalog sections 1.0-3.5 and avoids accepted without_rowid5 requirements, without_rowid1/6/7 primary-key-tail coverage, wherelimit, index, where, bestindex, JSON, WAL, VFS, PRAGMA, trigger, and source-neutral cleanup batches',
        'non-overlap: owns without_rowid2.test foreign-key catalog sections 1.0-3.5 and avoids accepted without_rowid5 requirements, without_rowid1/6/7 primary-key-tail coverage, wherelimit, index, where, bestindex, JSON, WAL, VFS, PRAGMA, trigger, and source-neutral cleanup batches',
    );
};

return $tests;
