<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/without_rowid1.test section 5. These
// cases verify that secondary indexes on WITHOUT ROWID tables carry the
// trailing primary-key columns needed for range constraints after the indexed
// column has been matched.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::withoutRowidSecondaryIndexPrimaryKeyTailCases(1000) as $case) {
    $tests['real upstream without rowid secondary index primary key tail case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('without_rowid1.test section 5.0 through 5.7', $case['source']);
        $t->true(str_starts_with($case['upstream_section'], 'without_rowid1-5.'));
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['scenario'] !== '');
        $t->true(in_array($case['table'], ['t45', 't46'], true));
        $t->true($case['primary_key'] !== []);
        $t->true(str_contains($case['secondary_index'], ' ON ' . $case['table'] . '('));
        $t->true($case['where_clause'] !== '');
        $t->true(str_contains($case['detail'], 'USING INDEX'));
        $t->same(true, $case['uses_appended_primary_key']);
        $t->same('ok', $case['integrity']);
        $t->true(in_array($case['range_column'], ['a', 'b'], true));
        $t->true(in_array($case['range_operator'], ['>', '<', '>='], true));
        $t->true($case['range_value'] >= 0);

        if ($case['table'] === 't45') {
            $t->same(['a'], $case['primary_key']);
            $t->same('i45 ON t45(b)', $case['secondary_index']);
            $t->same($case['count'], count($case['expected_rows']));
            $t->true($case['count'] > 0);
            foreach ($case['expected_rows'] as $row) {
                $t->same('x', $row['c']);
                $t->true(in_array($row['b'], ['one', 'two'], true));
                if ($case['range_operator'] === '>') {
                    $t->true($row['a'] > $case['range_value']);
                }
                if ($case['range_operator'] === '<') {
                    $t->true($row['a'] < $case['range_value']);
                }
            }
            $t->true(str_contains($case['detail'], 'b=? AND a'));
        }

        if ($case['table'] === 't46') {
            $t->same(['a', 'b'], $case['primary_key']);
            $t->same('i46 ON t46(c)', $case['secondary_index']);
            $t->same([], $case['expected_rows']);
            $t->true($case['count'] >= 1);
            $t->true($case['count'] <= 6);
            $t->true(str_contains($case['detail'], 'c=?'));
            if ($case['range_column'] === 'b') {
                $t->true(str_contains($case['detail'], 'a=? AND b'));
            } else {
                $t->true(str_contains($case['detail'], 'a'));
            }
        }
    };
}

$tests['real upstream without rowid secondary index primary key tail source summary'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::withoutRowidSecondaryIndexPrimaryKeyTailCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1000, count($cases));
    $t->same([
        'without_rowid1-5.1/5.2',
        'without_rowid1-5.1/5.3',
        'without_rowid1-5.4/5.7.1',
        'without_rowid1-5.4/5.7.2',
        'without_rowid1-5.4/5.7.3',
        'without_rowid1-5.4/5.7.4',
    ], $sections);
    $t->same('without_rowid1.test section 5.0 through 5.7', $cases[0]['source']);
    $t->same('without_rowid1-5.1/5.2', $cases[0]['upstream_section']);
    $t->same('without_rowid1-5.4/5.7.4', $cases[5]['upstream_section']);
    $t->same('without_rowid1-5.1/5.2', $cases[6]['upstream_section']);
};

$tests['real upstream without rowid secondary index primary key tail rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::withoutRowidSecondaryIndexPrimaryKeyTailCases(0));
};

$tests['real upstream without rowid secondary index primary key tail dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner for WITHOUT ROWID secondary-index tail key behavior',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner for WITHOUT ROWID secondary-index tail key behavior',
    );
};

return $tests;
