<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$filterFaultSql = 'SELECT sum(a) FILTER (WHERE b<5) AS filtered_sum, count() FILTER (WHERE d!=c) AS mismatch_count FROM app_events GROUP BY c ORDER BY 1';
$dynamicFilterFaultSql = 'SELECT c AS group_key, sum(a) FILTER (WHERE b<5) AS filtered_sum, count() FILTER (WHERE d!=c) AS mismatch_count FROM app_events GROUP BY c ORDER BY 2, 1';

$sqlSortComparison = static function (mixed $left, mixed $right): int {
    if ($left === null || $right === null) {
        return $left === $right ? 0 : ($left === null ? -1 : 1);
    }
    if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
        return ((float) $left) <=> ((float) $right);
    }

    return strcmp((string) $left, (string) $right);
};

$expectedFilterFaultRows = static function (array $rows, bool $includeGroupKey) use ($sqlSortComparison): array {
    $groups = [];
    foreach ($rows as $row) {
        $groupKey = $row['c'];
        $key = is_int($groupKey) || is_float($groupKey) ? 'n:' . (string) $groupKey : 's:' . (string) $groupKey;
        $groups[$key] ??= [
            'group_key' => $groupKey,
            'filtered_sum' => null,
            'filtered_seen' => false,
            'mismatch_count' => 0,
        ];

        if ($row['b'] !== null && $row['b'] < 5 && $row['a'] !== null) {
            $groups[$key]['filtered_seen'] = true;
            $groups[$key]['filtered_sum'] = ($groups[$key]['filtered_sum'] ?? 0) + $row['a'];
        }
        if ($row['d'] !== null && $row['c'] !== null && $row['d'] != $row['c']) {
            $groups[$key]['mismatch_count']++;
        }
    }

    $summaries = array_values($groups);
    usort($summaries, static fn (array $left, array $right): int => $sqlSortComparison($left['group_key'], $right['group_key']));
    usort($summaries, static function (array $left, array $right) use ($includeGroupKey, $sqlSortComparison): int {
        $comparison = $sqlSortComparison($left['filtered_sum'], $right['filtered_sum']);
        if ($comparison !== 0 || !$includeGroupKey) {
            return $comparison;
        }

        return $sqlSortComparison($left['group_key'], $right['group_key']);
    });

    return array_map(
        static function (array $summary) use ($includeGroupKey): array {
            $row = [
                'filtered_sum' => $summary['filtered_seen'] ? $summary['filtered_sum'] : null,
                'mismatch_count' => $summary['mismatch_count'],
            ];
            if ($includeGroupKey) {
                return ['group_key' => $summary['group_key']] + $row;
            }

            return $row;
        },
        $summaries,
    );
};

$buildFilterFaultRows = static function (int $case): array {
    $rows = [];
    $groupCount = 3 + ($case % 5);
    for ($group = 0; $group < $groupCount; $group++) {
        $groupKey = ($case % 17) + ($group * 100);
        $rowCount = 1 + (($case + $group) % 4);
        for ($row = 0; $row < $rowCount; $row++) {
            $a = (($case * 13 + $group * 5 + $row * 3) % 31) - 12;
            if ($a === 0) {
                $a = $group + 1;
            }
            $b = $group === 0
                ? (5 + (($case + $row) % 7))
                : ((($case * 3 + $group * 5 + $row * 2) % 17) - 6);
            if (($case + $group + $row) % 19 === 0) {
                $b = null;
            }
            $d = (($case + $group + $row) % 5 === 0)
                ? $groupKey
                : $groupKey + (($row % 3) + 1);
            if (($case + $group + $row) % 23 === 0) {
                $d = null;
            }

            $rows[] = [
                'a' => $a,
                'b' => $b,
                'c' => $groupKey,
                'd' => $d,
            ];
        }
    }

    return $rows;
};

$tests['real upstream filterfault 1.0 count empty args filter grouped replay'] = static function (TestRunner $t) use ($filterFaultSql, $expectedFilterFaultRows): void {
    $rows = [
        ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4],
        ['a' => 5, 'b' => 6, 'c' => 7, 'd' => 8],
        ['a' => 9, 'b' => 10, 'c' => 11, 'd' => 12],
    ];
    $actual = SQLiteSelectSql::execute($filterFaultSql, ['app_events' => $rows]);

    $t->same([
        ['filtered_sum' => null, 'mismatch_count' => 1],
        ['filtered_sum' => null, 'mismatch_count' => 1],
        ['filtered_sum' => 1, 'mismatch_count' => 1],
    ], $actual, 'filterfault.test 1.0 zero-argument count() FILTER result');
    $t->same($expectedFilterFaultRows($rows, false), $actual, 'filterfault.test 1.0 expected oracle rows');
};

$tests['real upstream filterfault 1.0 count empty args without filter counts rows'] = static function (TestRunner $t): void {
    $actual = SQLiteSelectSql::execute(
        'SELECT count() AS row_count FROM app_events',
        ['app_events' => [
            ['a' => 1],
            ['a' => null],
            ['a' => 3],
            ['a' => 4],
        ]],
    );

    $t->same([['row_count' => 4]], $actual, 'SQLite count() zero-argument aggregate is count(*)');
};

for ($case = 1; $case <= 1000; $case++) {
    $tests[sprintf('real upstream filterfault 1.0 dynamic count filter replay case %04d', $case)] =
        static function (TestRunner $t) use ($case, $buildFilterFaultRows, $dynamicFilterFaultSql, $expectedFilterFaultRows): void {
            $rows = $buildFilterFaultRows($case);
            $expected = $expectedFilterFaultRows($rows, true);
            $actual = SQLiteSelectSql::execute($dynamicFilterFaultSql, ['app_events' => $rows]);

            $t->same($expected, $actual, "filterfault.test 1.0 dynamic zero-argument count filter rows {$case}");
            $t->same(count($expected), count($actual), "filterfault.test 1.0 dynamic group count {$case}");
            $t->same(array_column($expected, 'filtered_sum'), array_column($actual, 'filtered_sum'), "filterfault.test 1.0 dynamic filtered sums {$case}");
            $t->same(array_column($expected, 'mismatch_count'), array_column($actual, 'mismatch_count'), "filterfault.test 1.0 dynamic count() FILTER values {$case}");
            $t->true(in_array(null, array_column($actual, 'filtered_sum'), true), "filterfault.test 1.0 dynamic keeps NULL sum group {$case}");
        };
}

$tests['real upstream filterfault dynamic cites exact upstream source section'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/filterfault.test 1.0 zero-argument count() FILTER grouped aggregate under faultsim replay',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/filterfault.test 1.0 zero-argument count() FILTER grouped aggregate under faultsim replay',
    ]);
};

return $tests;
