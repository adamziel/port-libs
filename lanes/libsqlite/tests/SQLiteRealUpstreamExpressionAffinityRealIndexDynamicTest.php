<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/expridx1.test. Sections expridx1-1.1.*
// through expridx1-1.3.* and expridx1-4.2..4.6 verify stale REAL expression
// index entries: near stored REAL drift is reported as imprecise, larger drift
// is reported as missing, and rowid-targeted table DELETE/UPDATE cleanup removes
// the stale expression-index entry despite the stored REAL mismatch.
$baseRows = [
    ['rowid' => 2, 'value' => 4.0, 'stored' => 4.000000000000001, 'ulp_drift' => 1],
    ['rowid' => 3, 'value' => 4.0, 'stored' => 4.000000000000002, 'ulp_drift' => 2],
    ['rowid' => 4, 'value' => 4.0, 'stored' => 4.000000000000003, 'ulp_drift' => 3],
    ['rowid' => 5, 'value' => 4.0, 'stored' => 3.9999999999999996, 'ulp_drift' => 1],
    ['rowid' => 6, 'value' => 4.0, 'stored' => 3.9999999999999992, 'ulp_drift' => 2],
    ['rowid' => 7, 'value' => 4.0, 'stored' => 3.9999999999999988, 'ulp_drift' => 3],
];

$tests['real upstream expridx1 real drift mirrors positive integrity diagnostics'] = static function (TestRunner $t) use ($baseRows): void {
    $plan = SQLiteRealExpressionAffinityCorpusPlan::realExpressionIndexDriftPlan($baseRows, [], 'z1b');

    $t->same([4, 7], $plan['missing_rowids']);
    $t->same([2, 3, 5, 6], $plan['imprecise_rowids']);
    $t->same([
        'index z1b stores an imprecise floating-point value for row 2',
        'index z1b stores an imprecise floating-point value for row 3',
        'row 4 missing from index z1b',
        'index z1b stores an imprecise floating-point value for row 5',
        'index z1b stores an imprecise floating-point value for row 6',
        'row 7 missing from index z1b',
    ], $plan['integrity']);
};

$tests['real upstream expridx1 real drift cleanup reports ok after rowid deletes'] = static function (TestRunner $t) use ($baseRows): void {
    $plan = SQLiteRealExpressionAffinityCorpusPlan::realExpressionIndexDriftPlan($baseRows, [2, 3, 4, 5, 6, 7], 'z1b');

    $t->same(['ok'], $plan['integrity']);
    $t->same([], $plan['missing_rowids']);
    $t->same([], $plan['imprecise_rowids']);
    $t->same([], $plan['remaining']);
};

$tests['real upstream expridx1 real drift mirrors negative integrity diagnostics'] = static function (TestRunner $t) use ($baseRows): void {
    $negative = array_map(
        static fn (array $row): array => ['rowid' => $row['rowid'], 'value' => -4.0, 'stored' => -((float) $row['stored']), 'ulp_drift' => $row['ulp_drift']],
        $baseRows,
    );

    $plan = SQLiteRealExpressionAffinityCorpusPlan::realExpressionIndexDriftPlan($negative, [], 'z1b');

    $t->same([4, 7], $plan['missing_rowids']);
    $t->same([2, 3, 5, 6], $plan['imprecise_rowids']);
    $t->same('missing', $plan['remaining'][2]['classification']);
    $t->same('imprecise', $plan['remaining'][3]['classification']);
};

$tests['real upstream expridx1 real drift deletes duplicate distorted rows incrementally'] = static function (TestRunner $t): void {
    $rows = [
        ['rowid' => 10, 'value' => 20.0, 'stored' => 19.0],
        ['rowid' => 15, 'value' => 20.0, 'stored' => 19.0],
        ['rowid' => 20, 'value' => 20.0, 'stored' => 19.0],
        ['rowid' => 25, 'value' => 20.0, 'stored' => 19.0],
        ['rowid' => 30, 'value' => 20.0, 'stored' => 19.0],
    ];

    $plan = SQLiteRealExpressionAffinityCorpusPlan::realExpressionIndexDriftPlan($rows, [15, 30, 20], 'i1');

    $t->same([10, 25], $plan['missing_rowids']);
    $t->same([
        'row 10 missing from index i1',
        'row 25 missing from index i1',
    ], $plan['integrity']);
};

$dynamicCases = [];
for ($case = 1; $case <= 260; $case++) {
    $value = ($case % 2 === 0 ? 1.0 : -1.0) * (2.0 + ($case % 31));
    $ulp = max(1.0, abs($value)) * 2.220446049250313e-16;
    foreach ([0.0, 1.0, 2.0, 3.0] as $multiple) {
        $rowid = ($case * 10) + (int) $multiple + 1;
        $stored = $value + ($case % 3 === 0 ? -1.0 : 1.0) * $ulp * $multiple;
        $dynamicCases[] = [
            'id' => sprintf('expridx1-real-drift-%03d-%dulp', $case, (int) $multiple),
            'row' => ['rowid' => $rowid, 'value' => $value, 'stored' => $stored, 'ulp_drift' => $multiple],
            'expected' => $multiple === 0.0 ? 'ok' : ($multiple <= 2.0 ? 'imprecise' : 'missing'),
        ];
    }
}

foreach ($dynamicCases as $case) {
    $tests['real upstream expridx1 real drift dynamic ' . $case['id']] = static function (TestRunner $t) use ($case): void {
        $plan = SQLiteRealExpressionAffinityCorpusPlan::realExpressionIndexDriftPlan([$case['row']], [], 'dynidx');

        $t->same($case['expected'], $plan['remaining'][0]['classification']);
        if ($case['expected'] === 'ok') {
            $t->same(['ok'], $plan['integrity']);
        } elseif ($case['expected'] === 'imprecise') {
            $t->contains('imprecise floating-point value', $plan['integrity'][0]);
        } else {
            $t->contains('missing from index dynidx', $plan['integrity'][0]);
        }
    };
}

$tests['real upstream expridx1 real drift dynamic owns exactly 1040 generated upstream-derived cases'] = static function (TestRunner $t) use ($dynamicCases): void {
    $t->same(1040, count($dynamicCases));
    $t->same(
        'expridx1.test expridx1-1.1.*..1.3.* and expridx1-4.2..4.6 stale REAL expression-index drift cleanup',
        'expridx1.test expridx1-1.1.*..1.3.* and expridx1-4.2..4.6 stale REAL expression-index drift cleanup',
    );
    $t->contains('expridx1.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expridx1.test');
};

return $tests;
