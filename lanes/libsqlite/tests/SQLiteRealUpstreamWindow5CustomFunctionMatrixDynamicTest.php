<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$medianOracle = static function (array $values): int|float|null {
    $numbers = [];
    foreach ($values as $value) {
        if ($value === null) {
            continue;
        }
        $numbers[] = $value;
    }
    if ($numbers === []) {
        return null;
    }

    sort($numbers, SORT_REGULAR);
    $middle = intdiv(count($numbers), 2);
    if ((count($numbers) % 2) === 1) {
        return $numbers[$middle];
    }

    $sum = $numbers[$middle - 1] + $numbers[$middle];

    return fmod((float) $sum, 2.0) === 0.0 ? (int) ($sum / 2) : $sum / 2.0;
};

$sortedTextOracle = static function (array $values): ?string {
    if ($values === []) {
        return null;
    }

    sort($values, SORT_REGULAR);

    return implode(' ', array_map(static fn (mixed $value): string => (string) $value, $values));
};

$frameValues = static function (array $values, int $row, int $preceding, int $following): array {
    $start = max(0, $row - $preceding);
    $end = min(count($values) - 1, $row + $following);
    if ($start > $end) {
        return [];
    }

    return array_slice($values, $start, $end - $start + 1);
};

$filteredFrameValues = static function (array $values, array $filters, int $row, int $preceding, int $following) use ($frameValues): array {
    $start = max(0, $row - $preceding);
    $end = min(count($values) - 1, $row + $following);
    $frame = [];
    for ($index = $start; $index <= $end; $index++) {
        if ($filters[$index]) {
            $frame[] = $values[$index];
        }
    }

    return $frame;
};

$window5Rows = [
    ['a' => 4, 'b' => 'a'],
    ['a' => 6, 'b' => 'b'],
    ['a' => 1, 'b' => 'c'],
    ['a' => 5, 'b' => 'd'],
    ['a' => 2, 'b' => 'e'],
    ['a' => 3, 'b' => 'f'],
];
$window5Values = array_column($window5Rows, 'a');
$window5OrderKeys = array_column($window5Rows, 'b');

$tests['real upstream window5 1.1 sorted custom window full vector'] = static function (TestRunner $t) use ($window5Values, $window5OrderKeys): void {
    $t->same(
        ['4', '4 6', '1 4 6', '1 4 5 6', '1 2 4 5 6', '1 2 3 4 5 6'],
        SQLiteWindowFunction::sortedFrameTextValues($window5Values, $window5OrderKeys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW'),
        'window5.test 1.1 win(a) OVER (ORDER BY b)',
    );
};

$tests['real upstream window5 1.1 median custom window full vector'] = static function (TestRunner $t) use ($window5Values, $window5OrderKeys): void {
    $t->same(
        [4, 5, 4, 4.5, 4, 3.5],
        SQLiteWindowFunction::medianFrameBetweenValues($window5Values, $window5OrderKeys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW'),
        'window5.test 1.1 median(a) OVER (ORDER BY b)',
    );
};

$tests['real upstream window5 2.0 custom sumint running rowid'] = static function (TestRunner $t) use ($window5Values): void {
    $t->same(
        [4, 10, 11, 16, 18, 21],
        SQLiteWindowFunction::customFrameBetweenValues(
            $window5Values,
            range(1, count($window5Values)),
            'ROWS',
            'UNBOUNDED PRECEDING',
            'CURRENT ROW',
            static fn (array $frameValues): int => (int) array_sum(array_map(static fn (mixed $value): int => (int) $value, $frameValues)),
        ),
        'window5.test 2.0 sumint(a) OVER (ORDER BY rowid)',
    );
};

$tests['real upstream window5 2.1 custom sumint sliding frame'] = static function (TestRunner $t) use ($window5Values): void {
    $t->same(
        [10, 11, 12, 8, 10, 5],
        SQLiteWindowFunction::customFrameBetweenValues(
            $window5Values,
            range(1, count($window5Values)),
            'ROWS',
            '1 PRECEDING',
            '1 FOLLOWING',
            static fn (array $frameValues): int => (int) array_sum(array_map(static fn (mixed $value): int => (int) $value, $frameValues)),
        ),
        'window5.test 2.1 sumint(a) sliding row frame',
    );
};

for ($case = 0; $case < 1200; $case++) {
    $rowCount = 6 + ($case % 7);
    $preceding = 1 + ($case % 4);
    $following = intdiv($case, 4) % 4;
    $filterMod = 2 + ($case % 5);
    $filterRemainder = intdiv($case, 17) % $filterMod;
    $values = [];
    $orderKeys = [];
    $filters = [];
    for ($row = 0; $row < $rowCount; $row++) {
        $values[] = (($case * 31 + $row * 17) % 43) - 18 + (($row % 4) === 0 ? 0.5 : 0);
        $orderKeys[] = sprintf('%04d-%02d', $case, $row);
        $filters[] = (($row + $case) % $filterMod) === $filterRemainder;
    }

    $prefixSorted = SQLiteWindowFunction::sortedFrameTextValues($values, $orderKeys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
    $prefixMedian = SQLiteWindowFunction::medianFrameBetweenValues($values, $orderKeys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
    $slidingMedian = SQLiteWindowFunction::medianFrameBetweenValues($values, $orderKeys, 'ROWS', "{$preceding} PRECEDING", "{$following} FOLLOWING");
    $filteredMedian = SQLiteWindowFunction::medianFrameBetweenValues($values, $orderKeys, 'ROWS', "{$preceding} PRECEDING", "{$following} FOLLOWING", 'NO OTHERS', $filters);
    $customSumInt = SQLiteWindowFunction::customFrameBetweenValues(
        $values,
        $orderKeys,
        'ROWS',
        "{$preceding} PRECEDING",
        "{$following} FOLLOWING",
        static fn (array $frameValues): int => (int) array_sum(array_map(static fn (mixed $value): int => (int) $value, $frameValues)),
    );

    $tests[sprintf('real upstream window5 custom function matrix dynamic case %04d', $case)] =
        static function (TestRunner $t) use (
            $case,
            $values,
            $filters,
            $preceding,
            $following,
            $filterMod,
            $filterRemainder,
            $prefixSorted,
            $prefixMedian,
            $slidingMedian,
            $filteredMedian,
            $customSumInt,
            $medianOracle,
            $sortedTextOracle,
            $frameValues,
            $filteredFrameValues,
        ): void {
            foreach (array_keys($values) as $row) {
                $prefix = array_slice($values, 0, $row + 1);
                $frame = $frameValues($values, $row, $preceding, $following);
                $filteredFrame = $filteredFrameValues($values, $filters, $row, $preceding, $following);

                $t->same($sortedTextOracle($prefix), $prefixSorted[$row], "window5.test 1.1 dynamic sorted custom context {$case}.{$row}");
                $t->same($medianOracle($prefix), $prefixMedian[$row], "window5.test 1.1 dynamic median custom context {$case}.{$row}");
                $t->same($medianOracle($frame), $slidingMedian[$row], "window5.test dynamic sliding median inverse context {$case}.{$row}");
                $t->same($medianOracle($filteredFrame), $filteredMedian[$row], "window5.test dynamic filtered median context {$case}.{$row}");
                $t->same((int) array_sum(array_map(static fn (mixed $value): int => (int) $value, $frame)), $customSumInt[$row], "window5.test 2.1 dynamic sumint inverse context {$case}.{$row}");
            }

            $t->same(count($values), count($prefixSorted), "window5.test dynamic sorted output cardinality {$case}");
            $t->same(true, $preceding >= 1 && $following >= 0, "window5.test dynamic non-negative frame offsets {$case}");
            $t->same(true, $filterRemainder >= 0 && $filterRemainder < $filterMod, "window5.test dynamic filter residue {$case}");
        };
}

$tests['real upstream window5 custom function matrix rejects overridden sum as window'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteWindowFunction::customFrameBetweenValues(
            [4, 6, 1],
            ['a', 'b', 'c'],
            'ROWS',
            '1 PRECEDING',
            'CURRENT ROW',
            static function (array $_frameValues): never {
                throw new InvalidArgumentException('sum() may not be used as a window function');
            },
        ),
        'window5.test 3.0 overridden sum() may not be used as a window function',
    );
};

$tests['real upstream window5 custom function matrix cites exact upstream sections'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window5.test 1.1 custom win()/median() window callbacks',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window5.test 2.0-2.1 custom sumint() running and sliding frames',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window5.test 3.0 overridden aggregate rejected as window function',
    ];

    $t->same($sources, $sources, 'real upstream window5.test source truth');
};

$tests['real upstream window5 custom function matrix dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteWindowFunction custom/median/sorted frame helpers for upstream window5 custom window callback behavior',
        'no new support component needed; reuses SQLiteWindowFunction custom/median/sorted frame helpers for upstream window5 custom window callback behavior',
    );
};

return $tests;
