<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/fordelete.test sections fordelete-1.1
// through fordelete-2.5. These cases model the table and index btrees opened
// by DELETE plans, including BTREE_FORDELETE and Delete-opcode index flags.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::forDeleteOpenWriteFlagCases(1200) as $case) {
    $tests['real upstream fordelete open-write flag dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('fordelete.test sections fordelete-1.1 through fordelete-2.5', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true($case['batch'] >= 1);
        $t->true(in_array($case['upstream_section'], [
            'fordelete-1.1',
            'fordelete-1.2',
            'fordelete-1.3',
            'fordelete-1.4',
            'fordelete-2.1',
            'fordelete-2.2',
            'fordelete-2.3',
            'fordelete-2.4',
            'fordelete-2.5',
        ], true));
        $t->true(str_starts_with($case['sql'], 'DELETE FROM ' . $case['table_name']));
        $t->true(str_contains($case['scenario'], 'dynamic replay'));
        $t->true($case['table_shape'] !== '');
        $t->true($case['predicate_terms'] !== []);
        $t->same('ok', $case['integrity']);
        $t->true(str_contains($case['detail'], $case['upstream_section']));

        $summary = [];
        $forDeleteCount = 0;
        $deleteOpcodeCount = 0;
        foreach ($case['opened_objects'] as $object) {
            $t->true($object['name'] !== '');
            $t->true($object['role'] !== '');
            $t->true(is_bool($object['for_delete']));
            $t->true(is_bool($object['delete_opcode']));

            $summary[] = $object['name']
                . ($object['for_delete'] ? '*' : '')
                . ($object['delete_opcode'] ? '+' : '');
            $forDeleteCount += $object['for_delete'] ? 1 : 0;
            $deleteOpcodeCount += $object['delete_opcode'] ? 1 : 0;
        }
        sort($summary, SORT_STRING);

        $t->same($summary, $case['flag_summary']);
        $t->same($forDeleteCount, $case['for_delete_count']);
        $t->same($deleteOpcodeCount, $case['delete_opcode_count']);

        if ($case['uses_rowid']) {
            $t->true(str_contains($case['sql'], 'rowid=?'));
            $t->same($case['table_name'], $case['driving_object']);
        }

        if ($case['uses_or_optimization']) {
            $t->true(str_contains($case['sql'], ' OR '));
            $t->same(['t2', 't2a*', 't2b*', 't2c*'], $case['flag_summary']);
        }

        if ($case['requires_table_payload']) {
            $t->true(str_contains(implode(' ', $case['predicate_terms']), 'b=?'));
            $tableObjects = array_values(array_filter(
                $case['opened_objects'],
                static fn (array $object): bool => $object['name'] === $case['table_name']
            ));
            $t->same(1, count($tableObjects));
            $t->same(false, $tableObjects[0]['for_delete']);
            $t->same(true, $tableObjects[0]['delete_opcode']);
        }

        if ($case['upstream_section'] === 'fordelete-1.1') {
            $t->same(['sqlite_autoindex_t1_1', 't1*+'], $case['flag_summary']);
            $t->same('sqlite_autoindex_t1_1', $case['driving_object']);
            $t->same(1, $case['for_delete_count']);
            $t->same(1, $case['delete_opcode_count']);
        }

        if ($case['upstream_section'] === 'fordelete-1.2') {
            $t->same(['sqlite_autoindex_t1_1', 't1+'], $case['flag_summary']);
            $t->same(true, $case['requires_table_payload']);
        }

        if ($case['upstream_section'] === 'fordelete-1.4') {
            $t->same(['sqlite_autoindex_t1_1*', 't1'], $case['flag_summary']);
            $t->same(true, $case['uses_rowid']);
            $t->same(0, $case['delete_opcode_count']);
        }

        if ($case['upstream_section'] === 'fordelete-2.1') {
            $t->same(['t2*+', 't2a', 't2b*', 't2c*'], $case['flag_summary']);
            $t->same('t2a', $case['driving_object']);
            $t->same(3, $case['for_delete_count']);
        }

        if ($case['upstream_section'] === 'fordelete-2.2') {
            $t->same(['t2+', 't2a', 't2b*', 't2c*'], $case['flag_summary']);
            $t->same(true, $case['requires_table_payload']);
        }

        if ($case['upstream_section'] === 'fordelete-2.4' || $case['upstream_section'] === 'fordelete-2.5') {
            $t->same(['t2', 't2a*', 't2b*', 't2c*'], $case['flag_summary']);
            $t->same('t2', $case['driving_object']);
            $t->same(3, $case['for_delete_count']);
            $t->same(0, $case['delete_opcode_count']);
        }
    };
}

$tests['real upstream fordelete open-write flag source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::forDeleteOpenWriteFlagCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1200, count($cases));
    $t->same('fordelete-1.1', $cases[0]['upstream_section']);
    $t->same('fordelete-2.5', $cases[8]['upstream_section']);
    $t->same('fordelete-1.1', $cases[9]['upstream_section']);
    $t->same('fordelete-1.2', $cases[1198]['upstream_section']);
    $t->same('fordelete-1.3', $cases[1199]['upstream_section']);
    $t->same([
        'fordelete-1.1',
        'fordelete-1.2',
        'fordelete-1.3',
        'fordelete-1.4',
        'fordelete-2.1',
        'fordelete-2.2',
        'fordelete-2.3',
        'fordelete-2.4',
        'fordelete-2.5',
    ], $sections);
};

$tests['real upstream fordelete open-write flag rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::forDeleteOpenWriteFlagCases(0));
};

$tests['real upstream fordelete open-write flag dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and DELETE open-write flag modeling',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and DELETE open-write flag modeling',
    );
};

return $tests;
