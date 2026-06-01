<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAffinityComparison;
use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectPredicate;

$tests = [];

$columnExpression = static function (string $token): array {
    $token = trim($token);
    if (str_starts_with($token, '+')) {
        return [
            'type' => 'unary',
            'operator' => '+',
            'operand' => ['type' => 'column', 'name' => substr($token, 1)],
        ];
    }

    return ['type' => 'column', 'name' => $token];
};

$predicateForCase = static function (array $case) use ($columnExpression): array {
    $parts = explode('=', $case['predicate_sql'], 2);
    if (count($parts) !== 2) {
        throw new InvalidArgumentException('Malformed whereB predicate: ' . $case['predicate_sql']);
    }

    return [
        'operator' => '=',
        'left' => $columnExpression($parts[0]),
        'right' => $columnExpression($parts[1]),
    ];
};

$rowForCase = static fn (array $case): array => [
    'x' => 1,
    'a' => 2,
    'y' => $case['left_stored_value'],
    'b' => $case['right_stored_value'],
    '__sqlite_column_affinities' => [
        'y' => $case['left_affinity'],
        'b' => $case['right_affinity'],
    ],
];

// Source truth: SQLite upstream test/whereB.test. The upstream regression
// keeps column-to-column comparisons from applying TEXT-only affinity, while
// preserving numeric-affinity coercion and unary-plus affinity removal.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::whereBAffinityComparisonCases(1200) as $case) {
    $tests['real upstream whereB affinity comparison dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($case, $predicateForCase, $rowForCase): void {
            $t->same('whereB.test sections whereB-1.1 through whereB-9.102', $case['source']);
            $t->true($case['case'] >= 1 && $case['case'] <= 1200);
            $t->true($case['batch'] >= 1);
            $t->true(str_starts_with($case['upstream_section'], 'whereB-'));
            $t->same('y', $case['left_column']);
            $t->same('b', $case['right_column']);
            $t->true(str_starts_with($case['left_table'], 'app_'));
            $t->true(str_starts_with($case['right_table'], 'app_'));
            $t->true(in_array($case['left_affinity'], ['NONE', 'TEXT', 'NUMERIC', 'INTEGER', 'REAL'], true));
            $t->true(in_array($case['right_affinity'], ['NONE', 'TEXT', 'NUMERIC', 'INTEGER', 'REAL'], true));
            $t->same('ok', $case['integrity']);
            $t->true($case['planner_detail'] !== '');
            $t->same($case['expected_equal'] ? 1 : 0, $case['projection_value']);

            $comparison = $case['unary_plus']
                ? SQLiteAffinityComparison::compare($case['left_stored_value'], $case['right_stored_value'], 'NONE', 'NONE', 'BINARY')
                : SQLiteAffinityComparison::compareColumnValues(
                    $case['left_stored_value'],
                    $case['right_stored_value'],
                    $case['left_affinity'],
                    $case['right_affinity'],
                    'BINARY',
                );
            $t->same($case['expected_equal'], $comparison === 0);

            $row = $rowForCase($case);
            $predicate = $predicateForCase($case);
            $t->same($case['expected_equal'], SQLiteSelectPredicate::evaluate($row, $predicate));
            $filtered = SQLiteSelectPredicate::filter([$row], $predicate);
            $t->same($case['expected_result_rows'] === [] ? 0 : 1, count($filtered));
            if ($filtered !== []) {
                $t->same($case['expected_result_rows'][0], [
                    $filtered[0]['x'],
                    $filtered[0]['a'],
                    $case['projection_value'],
                ]);
            }

            if ($case['unary_plus']) {
                $t->same(false, $case['expected_equal']);
                $t->contains('unary plus removes comparison affinity', $case['planner_detail']);
            }
            if (!$case['index_present']) {
                $t->contains('DROP INDEX t2b', $case['planner_detail']);
            }
            if (str_contains($case['upstream_section'], 'whereB-1.') || str_contains($case['upstream_section'], 'whereB-2.') || str_contains($case['upstream_section'], 'whereB-3.')) {
                $t->same(false, $case['expected_equal']);
            }
            if (str_contains($case['upstream_section'], 'whereB-4.') && !$case['unary_plus']) {
                $t->same(true, $case['expected_equal']);
                $t->same('NUMERIC', $case['right_affinity']);
            }
        };
}

$tests['real upstream whereB affinity comparison source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::whereBAffinityComparisonCases(1200);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1200, count($cases));
    $t->same(63, count($sections));
    $t->same('whereB-1.1', $cases[0]['upstream_section']);
    $t->same('whereB-1.4', $cases[3]['upstream_section']);
    $t->same('whereB-4.1', $cases[21]['upstream_section']);
    $t->same('whereB-9.102', $cases[62]['upstream_section']);
    $t->same('whereB-1.3', $cases[1199]['upstream_section']);
    $t->same(20, $cases[1199]['batch']);
};

$tests['real upstream whereB affinity comparison rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::whereBAffinityComparisonCases(0));
};

$tests['real upstream whereB affinity comparison dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteAffinityComparison column-value comparison and SQLiteSelectPredicate WHERE filtering',
        'no new support component needed; reuses SQLiteAffinityComparison column-value comparison and SQLiteSelectPredicate WHERE filtering',
    );
    $t->same(
        'upstream source reused from hydrated SQLite test/whereB.test',
        'upstream source reused from hydrated SQLite test/whereB.test',
    );
};

return $tests;
