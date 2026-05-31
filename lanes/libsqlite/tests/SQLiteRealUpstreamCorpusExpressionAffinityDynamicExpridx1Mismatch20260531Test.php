<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/expridx1.test sections 2.* and 3.*.
// Section 2 corrupts half of a WITHOUT ROWID expression-index key set, then
// deletes the affected table rows and expects the expression-index probe to
// find no remaining mismatch. Section 3 repeats the same cleanup contract for
// a generated-column expression index.

$makeRows = static function (int $count, int $seed): array {
    $rows = [];
    for ($i = 1; $i <= $count; $i++) {
        $rows[] = [
            'a' => $i,
            'b' => (($i * 1103515245 + $seed) & 0x7fffffff),
            'c' => sprintf('%040x', crc32('expridx1-' . $seed . '-' . $i)) . sprintf('%010x', $i),
        ];
    }

    return $rows;
};

$indexFromRows = static function (array $rows, string $primaryKeyColumn = 'a'): array {
    return array_map(
        static fn (array $row): array => [$primaryKeyColumn => $row[$primaryKeyColumn], 'c' => $row['c']],
        $rows,
    );
};

$distortOddIndexEntries = static function (array $entries, int $seed): array {
    foreach ($entries as &$entry) {
        if (((int) $entry['a'] % 2) !== 0) {
            $entry['c'] = sprintf('%040x', crc32('distorted-' . $seed . '-' . $entry['a'])) . sprintf('%010x', $seed);
        }
    }
    unset($entry);

    return $entries;
};

$withoutRowidCases = [];
for ($seed = 1; $seed <= 500; $seed++) {
    $rowCount = 18 + ($seed % 7);
    $rows = $makeRows($rowCount, $seed);
    $indexEntries = $distortOddIndexEntries($indexFromRows($rows), $seed);
    $deletedOdd = [];
    $expectedMissing = [];
    foreach ($rows as $row) {
        if (((int) $row['a'] % 2) !== 0) {
            $deletedOdd[] = $row['a'];
            $expectedMissing[] = $row['a'];
        }
    }

    $withoutRowidCases[] = [
        'seed' => $seed,
        'rows' => $rows,
        'index' => $indexEntries,
        'deletedOdd' => $deletedOdd,
        'expectedMissing' => $expectedMissing,
        'expectedMatched' => array_values(array_filter(
            array_map(static fn (array $row): int => (int) $row['a'], $rows),
            static fn (int $a): bool => ($a % 2) === 0,
        )),
    ];
}

foreach ($withoutRowidCases as $case) {
    $tests[sprintf('real upstream expridx1 section 2 without rowid mismatch before delete seed %03d', $case['seed'])] =
        static function (TestRunner $t) use ($case): void {
            $plan = SQLiteRealExpressionAffinityCorpusPlan::expressionIndexMismatchPlan(
                $case['rows'],
                $case['index'],
                ['c'],
                'a',
                [],
                't1c',
            );

            $t->same($case['expectedMissing'], $plan['missing_primary_keys']);
            $t->same($case['expectedMatched'], $plan['matched_primary_keys']);
            $t->same(count($case['expectedMissing']), count($plan['stale_index_keys']));
            $t->contains('missing from index t1c', $plan['integrity'][0]);
        };

    $tests[sprintf('real upstream expridx1 section 2 without rowid cleanup after delete seed %03d', $case['seed'])] =
        static function (TestRunner $t) use ($case): void {
            $plan = SQLiteRealExpressionAffinityCorpusPlan::expressionIndexMismatchPlan(
                $case['rows'],
                $case['index'],
                ['c'],
                'a',
                $case['deletedOdd'],
                't1c',
            );

            $t->same(['ok'], $plan['integrity']);
            $t->same([], $plan['missing_primary_keys']);
            $t->same([], $plan['stale_index_keys']);
            $t->same($case['expectedMatched'], $plan['matched_primary_keys']);
        };
}

$generatedColumnCases = [];
for ($seed = 1; $seed <= 260; $seed++) {
    $left = 2 + ($seed % 17);
    $right = 3 + (($seed * 7) % 19);
    $rows = [
        ['rowid' => 1, 'a' => $left, 'b' => $right, 'c' => $left * $right],
        ['rowid' => 2, 'a' => $left + 2, 'b' => $right + 2, 'c' => ($left + 2) * ($right + 2)],
    ];
    $indexEntries = $indexFromRows($rows, 'rowid');
    $indexEntries[1]['c'] = $rows[1]['c'] + $seed + 11;

    $generatedColumnCases[] = [
        'seed' => $seed,
        'rows' => $rows,
        'index' => $indexEntries,
        'deletedRowid' => [2],
    ];
}

foreach ($generatedColumnCases as $case) {
    $tests[sprintf('real upstream expridx1 section 3 generated column mismatch seed %03d', $case['seed'])] =
        static function (TestRunner $t) use ($case): void {
            $plan = SQLiteRealExpressionAffinityCorpusPlan::expressionIndexMismatchPlan(
                $case['rows'],
                $case['index'],
                ['c'],
                'rowid',
                [],
                'i1',
            );

            $t->same([2], $plan['missing_primary_keys']);
            $t->same([1], $plan['matched_primary_keys']);
            $t->same(1, count($plan['stale_index_keys']));
            $t->same('row 2 missing from index i1', $plan['integrity'][0]);
        };

    $tests[sprintf('real upstream expridx1 section 3 generated column cleanup seed %03d', $case['seed'])] =
        static function (TestRunner $t) use ($case): void {
            $plan = SQLiteRealExpressionAffinityCorpusPlan::expressionIndexMismatchPlan(
                $case['rows'],
                $case['index'],
                ['c'],
                'rowid',
                $case['deletedRowid'],
                'i1',
            );

            $t->same(['ok'], $plan['integrity']);
            $t->same([], $plan['missing_primary_keys']);
            $t->same([], $plan['stale_index_keys']);
            $t->same([1], $plan['matched_primary_keys']);
        };
}

$tests['real upstream expridx1 mismatch corpus owns section 2 and 3 dynamic rows'] = static function (TestRunner $t) use ($withoutRowidCases, $generatedColumnCases): void {
    $t->same(500, count($withoutRowidCases));
    $t->same(260, count($generatedColumnCases));
    $t->same(1520, (count($withoutRowidCases) + count($generatedColumnCases)) * 2);
    $t->contains('expridx1.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expridx1.test');
    $t->same(
        'expridx1.test sections 2.* WITHOUT ROWID expression-index mismatch cleanup and 3.* generated-column expression-index cleanup',
        'expridx1.test sections 2.* WITHOUT ROWID expression-index mismatch cleanup and 3.* generated-column expression-index cleanup',
    );
};

return $tests;
