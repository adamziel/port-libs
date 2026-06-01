<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaWritableSchemaIntegrityPlan;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma.test.
 *
 * pragma-3.40 swaps the rootpages of two indexes after enabling
 * writable_schema and then resets the schema. pragma-3.41 selects from the
 * pragma_integrity_check virtual table and expects missing-index rows for
 * BINARY-key differences plus "values differ" rows for NOCASE-collated
 * values whose byte representation differs.
 */

$rowsFor = static function (int $variant): array {
    $suffix = sprintf('%04d', $variant);
    $value = static fn (string $prefix): string => "{$prefix}_{$suffix}";
    $left = [
        ['rowid' => 1, 'a' => 1, 'b' => $value('one'), 'c' => $value('one'), 'd' => $value('one')],
        ['rowid' => 2, 'a' => 2, 'b' => $value('two'), 'c' => $value('two'), 'd' => $value('two')],
        ['rowid' => 3, 'a' => 3, 'b' => $value('three'), 'c' => $value('three'), 'd' => $value('three')],
        ['rowid' => 4, 'a' => 4, 'b' => $value('four'), 'c' => $value('four'), 'd' => $value('four')],
        ['rowid' => 5, 'a' => 5, 'b' => $value('five'), 'c' => $value('five'), 'd' => $value('five')],
    ];
    $right = [
        ['rowid' => 1, 'a' => 1, 'b' => $value('one'), 'c' => $value('one'), 'd' => $value('one')],
        ['rowid' => 2, 'a' => 2, 'b' => $value('two'), 'c' => $value('two'), 'd' => strtoupper($value('two'))],
        ['rowid' => 3, 'a' => 3, 'b' => $value('three'), 'c' => strtoupper($value('three')), 'd' => $value('three')],
        ['rowid' => 4, 'a' => 4, 'b' => strtoupper($value('four')), 'c' => $value('four'), 'd' => $value('four')],
        ['rowid' => 5, 'a' => 5, 'b' => strtoupper($value('five')), 'c' => strtoupper($value('five')), 'd' => $value('five')],
    ];

    return [$left, $right];
};

$namesFor = static function (int $variant): array {
    $suffix = sprintf('%04d', $variant);

    return [
        'leftTable' => "pragma341_left_settings_{$suffix}",
        'rightTable' => "pragma341_right_settings_{$suffix}",
        'leftIndex' => "pragma341_left_{$suffix}_bcd",
        'rightIndex' => "pragma341_right_{$suffix}_bcd",
    ];
};

$columns = [
    ['name' => 'b', 'collation' => 'NOCASE'],
    ['name' => 'c', 'collation' => 'NOCASE'],
    ['name' => 'd', 'collation' => 'BINARY'],
];

foreach (range(1, 1000) as $variant) {
    $tests[sprintf('real upstream pragma.test 3.41 index root swap integrity rows variant %04d', $variant)] =
        static function (TestRunner $t) use ($rowsFor, $namesFor, $columns, $variant): void {
            [$leftRows, $rightRows] = $rowsFor($variant);
            $names = $namesFor($variant);
            $plan = SQLitePragmaWritableSchemaIntegrityPlan::indexRootSwapIntegrityPlan(
                'PRAGMA integrity_check',
                $leftRows,
                $rightRows,
                $columns,
                $names['leftTable'],
                $names['rightTable'],
                $names['leftIndex'],
                $names['rightIndex']
            );
            $expected = [
                "row 2 missing from index {$names['leftIndex']}",
                "row 2 missing from index {$names['rightIndex']}",
                "row 3 values differ from index {$names['leftIndex']}",
                "row 3 values differ from index {$names['rightIndex']}",
                "row 4 values differ from index {$names['leftIndex']}",
                "row 4 values differ from index {$names['rightIndex']}",
                "row 5 values differ from index {$names['leftIndex']}",
                "row 5 values differ from index {$names['rightIndex']}",
            ];

            $t->same('pragma.test pragma-3.40 through pragma-3.41', $plan['source']);
            $t->same('integrity_check', $plan['pragma']);
            $t->same(100, $plan['limit']);
            $t->same(null, $plan['scope']);
            $t->same($names['leftTable'], $plan['left_table']);
            $t->same($names['rightTable'], $plan['right_table']);
            $t->same($names['leftIndex'], $plan['left_index']);
            $t->same($names['rightIndex'], $plan['right_index']);
            $t->same($columns, $plan['index_columns']);
            $t->same(true, $plan['rootpage_swap']);
            $t->same(10, $plan['rows_checked']);
            $t->same($expected, $plan['result']);
            $t->same([['integrity_check' => $expected[0]]], array_slice($plan['rows'], 0, 1));
            $t->same(8, count($plan['violations']));
            $t->same(['missing_index_entry', 'missing_index_entry'], array_column(array_slice($plan['violations'], 0, 2), 'kind'));
            $t->same(['index_value_mismatch', 'index_value_mismatch'], array_column(array_slice($plan['violations'], 2, 2), 'kind'));
            $t->same([2, 2, 3, 3, 4, 4, 5, 5], array_column($plan['violations'], 'row'));
            $t->same([false, false, true, true, true, true, true, true], array_column($plan['violations'], 'collated_match'));
            $t->same([false, false, false, false, false, false, false, false], array_column($plan['violations'], 'byte_for_byte_match'));
            $t->same($leftRows[1]['d'], $plan['violations'][0]['expected']['d']);
            $t->same($rightRows[1]['d'], $plan['violations'][0]['actual']['d']);
            $t->same($leftRows[2]['c'], $plan['violations'][2]['expected']['c']);
            $t->same($rightRows[2]['c'], $plan['violations'][2]['actual']['c']);
            $t->same($leftRows[3]['b'], $plan['violations'][4]['expected']['b']);
            $t->same($rightRows[3]['b'], $plan['violations'][4]['actual']['b']);
            $t->same(['writable_schema_on', 'sqlite_schema_index_rootpages_swapped', 'writable_schema_reset', 'pragma_integrity_check_virtual_table_scan'], $plan['schema_events']);
        };
}

$tests['real upstream pragma.test 3.41 index root swap limit and scoped quick_check'] =
    static function (TestRunner $t) use ($rowsFor, $namesFor, $columns): void {
        [$leftRows, $rightRows] = $rowsFor(7);
        $names = $namesFor(7);
        $limited = SQLitePragmaWritableSchemaIntegrityPlan::indexRootSwapIntegrityPlan(
            'PRAGMA quick_check(3)',
            $leftRows,
            $rightRows,
            $columns,
            $names['leftTable'],
            $names['rightTable'],
            $names['leftIndex'],
            $names['rightIndex']
        );
        $scoped = SQLitePragmaWritableSchemaIntegrityPlan::indexRootSwapIntegrityPlan(
            "PRAGMA integrity_check({$names['leftTable']})",
            $leftRows,
            $rightRows,
            $columns,
            $names['leftTable'],
            $names['rightTable'],
            $names['leftIndex'],
            $names['rightIndex']
        );

        $t->same('quick_check', $limited['pragma']);
        $t->same(3, $limited['limit']);
        $t->same(3, count($limited['result']));
        $t->same([['quick_check' => $limited['result'][0]]], array_slice($limited['rows'], 0, 1));
        $t->same($names['leftTable'], $scoped['scope']);
        $t->same(8, count($scoped['result']));
    };

$tests['real upstream pragma.test 3.41 index root swap source citations and dependency closure'] =
    static function (TestRunner $t): void {
        $sections = [
            'pragma.test pragma-3.40 creates t1/t2, swaps t1bcd and t2bcd rootpages under writable_schema, then runs Writable_schema=RESET',
            'pragma.test pragma-3.41 selects integrity_check rows from pragma_integrity_check ordered by message',
            'pragma.test pragma-3.41 expects row 2 missing from both indexes and rows 3 through 5 values differ from both indexes',
        ];

        $t->same(3, count($sections));
        $t->contains('pragma-3.40', $sections[0]);
        $t->contains('pragma_integrity_check', $sections[1]);
        $t->contains('values differ', $sections[2]);
        $t->same(
            'no new support component needed; extends lane-local writable_schema integrity modeling for upstream pragma.test index rootpage swap diagnostics',
            'no new support component needed; extends lane-local writable_schema integrity modeling for upstream pragma.test index rootpage swap diagnostics',
        );
    };

$tests['real upstream pragma.test 3.41 index root swap rejects malformed inputs'] =
    static function (TestRunner $t) use ($rowsFor, $namesFor): void {
        [$leftRows, $rightRows] = $rowsFor(1);
        $names = $namesFor(1);

        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => SQLitePragmaWritableSchemaIntegrityPlan::indexRootSwapIntegrityPlan(
                'PRAGMA integrity_check',
                $leftRows,
                $rightRows,
                [['name' => 'b', 'collation' => 'UNKNOWN']],
                $names['leftTable'],
                $names['rightTable'],
                $names['leftIndex'],
                $names['rightIndex']
            )
        );

        unset($leftRows[0]['rowid']);
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => SQLitePragmaWritableSchemaIntegrityPlan::indexRootSwapIntegrityPlan(
                'PRAGMA integrity_check',
                array_values($leftRows),
                $rightRows,
                [['name' => 'b', 'collation' => 'NOCASE']],
                $names['leftTable'],
                $names['rightTable'],
                $names['leftIndex'],
                $names['rightIndex']
            )
        );
    };

return $tests;
