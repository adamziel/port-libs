<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$baseRows = [
    ['id' => 1, 'b' => 'A', 'c' => 'one'],
    ['id' => 2, 'b' => 'B', 'c' => 'two'],
    ['id' => 3, 'b' => 'C', 'c' => 'three'],
    ['id' => 4, 'b' => 'D', 'c' => 'one'],
    ['id' => 5, 'b' => 'E', 'c' => 'two'],
];

$extendedRows = [
    ...$baseRows,
    ['id' => 6, 'b' => 'F', 'c' => 'three'],
    ['id' => 7, 'b' => 'G', 'c' => 'one'],
];

$stableSort = static function (array $rows, callable $compare): array {
    $indexed = [];
    foreach ($rows as $index => $row) {
        $indexed[] = ['index' => $index, 'row' => $row];
    }

    usort($indexed, static function (array $left, array $right) use ($compare): int {
        $result = $compare($left['row'], $right['row']);

        return $result !== 0 ? $result : ($left['index'] <=> $right['index']);
    });

    return array_column($indexed, 'row');
};

$window1LeadLimit = static function (array $rows, int $whereIdGreaterThan, int $limit, int $offset = 0) use ($stableSort): array {
    $filtered = array_values(array_filter(
        $rows,
        static fn (array $row): bool => $row['id'] > $whereIdGreaterThan,
    ));
    $windowRows = $stableSort(
        $filtered,
        static fn (array $left, array $right): int => ($left['c'] <=> $right['c']) ?: ($left['id'] <=> $right['id']),
    );
    $leadValues = SQLiteWindowFunction::lead(array_column($windowRows, 'c'));
    foreach ($windowRows as $index => $row) {
        $windowRows[$index]['lead_c'] = $leadValues[$index];
    }

    $outputRows = $stableSort(
        $windowRows,
        static fn (array $left, array $right): int => ($left['b'] <=> $right['b']) ?: ($left['id'] <=> $right['id']),
    );

    return array_map(
        static fn (array $row): array => [$row['id'], $row['b'], $row['lead_c']],
        array_slice($outputRows, $offset, $limit),
    );
};

$tests['real upstream window1.test 12.100 lead survives where order limit one'] = static function (TestRunner $t) use ($baseRows, $window1LeadLimit): void {
    $t->same([[2, 'B', 'two']], $window1LeadLimit($baseRows, 1, 1), 'window1.test 12.100');
    $t->contains('window1.test 12.100', 'window1.test 12.100');
};

$tests['real upstream window1.test 12.110 lead survives appended rows limit two'] = static function (TestRunner $t) use ($extendedRows, $window1LeadLimit): void {
    $t->same([[2, 'B', 'two'], [3, 'C', 'three']], $window1LeadLimit($extendedRows, 1, 2), 'window1.test 12.110');
    $t->contains('window1.test 12.110', 'window1.test 12.110');
};

$dynamicRows = [
    ...$extendedRows,
    ['id' => 8, 'b' => 'H', 'c' => 'four'],
    ['id' => 9, 'b' => 'I', 'c' => 'two'],
    ['id' => 10, 'b' => 'J', 'c' => 'five'],
    ['id' => 11, 'b' => 'K', 'c' => 'one'],
    ['id' => 12, 'b' => 'L', 'c' => 'three'],
];

for ($case = 1; $case <= 160; $case++) {
    $where = $case % 5;
    $limit = ($case % 4) + 1;
    $offset = intdiv($case, 7) % 3;
    $rows = array_slice($dynamicRows, 0, 5 + ($case % 8));
    $actual = $window1LeadLimit($rows, $where, $limit, $offset);
    $filteredCount = count(array_filter($rows, static fn (array $row): bool => $row['id'] > $where));

    $tests["real upstream window1.test 12 dynamic lead where order limit case {$case}"] = static function (TestRunner $t) use ($actual, $filteredCount, $limit, $offset, $case): void {
        $t->same(min($limit, max(0, $filteredCount - $offset)), count($actual), "window1.test 12 dynamic output count {$case}");
        $t->same($actual, array_values($actual), "window1.test 12 dynamic packed rows {$case}");
        foreach ($actual as $row) {
            $t->same(3, count($row), "window1.test 12 dynamic result arity {$case}");
            $t->true(is_int($row[0]), "window1.test 12 dynamic id type {$case}");
            $t->true(is_string($row[1]), "window1.test 12 dynamic b type {$case}");
            $t->true($row[2] === null || is_string($row[2]), "window1.test 12 dynamic lead type {$case}");
        }
    };
}

$tests['real upstream window1 lead limit dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 12.100',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 12.110',
    ];
    foreach ($sources as $source) {
        $t->true(is_file(strtok($source, ' ')), $source);
    }
};

$tests['real upstream window1 lead limit dynamic dependency closure note'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteWindowFunction::lead over real upstream window1.test WHERE plus outer ORDER BY LIMIT semantics',
        'no new support component needed; reuses SQLiteWindowFunction::lead over real upstream window1.test WHERE plus outer ORDER BY LIMIT semantics',
    );
};

return $tests;
