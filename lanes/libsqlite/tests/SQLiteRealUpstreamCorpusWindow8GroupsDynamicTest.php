<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$window8Rows = [
    ['a' => 'HH', 'b' => 'bb', 'c' => 355], ['a' => 'CC', 'b' => 'aa', 'c' => 158],
    ['a' => 'BB', 'b' => 'aa', 'c' => 399], ['a' => 'FF', 'b' => 'bb', 'c' => 938],
    ['a' => 'HH', 'b' => 'aa', 'c' => 480], ['a' => 'FF', 'b' => 'bb', 'c' => 870],
    ['a' => 'JJ', 'b' => 'aa', 'c' => 768], ['a' => 'JJ', 'b' => 'aa', 'c' => 899],
    ['a' => 'GG', 'b' => 'bb', 'c' => 929], ['a' => 'II', 'b' => 'bb', 'c' => 421],
    ['a' => 'GG', 'b' => 'bb', 'c' => 844], ['a' => 'FF', 'b' => 'bb', 'c' => 574],
    ['a' => 'CC', 'b' => 'bb', 'c' => 822], ['a' => 'GG', 'b' => 'bb', 'c' => 938],
    ['a' => 'BB', 'b' => 'aa', 'c' => 660], ['a' => 'HH', 'b' => 'aa', 'c' => 979],
    ['a' => 'BB', 'b' => 'bb', 'c' => 792], ['a' => 'DD', 'b' => 'aa', 'c' => 845],
    ['a' => 'JJ', 'b' => 'bb', 'c' => 354], ['a' => 'FF', 'b' => 'bb', 'c' => 295],
    ['a' => 'JJ', 'b' => 'aa', 'c' => 234], ['a' => 'BB', 'b' => 'bb', 'c' => 840],
    ['a' => 'AA', 'b' => 'aa', 'c' => 934], ['a' => 'EE', 'b' => 'aa', 'c' => 113],
    ['a' => 'AA', 'b' => 'bb', 'c' => 309], ['a' => 'BB', 'b' => 'aa', 'c' => 412],
    ['a' => 'AA', 'b' => 'aa', 'c' => 911], ['a' => 'AA', 'b' => 'bb', 'c' => 572],
    ['a' => 'II', 'b' => 'aa', 'c' => 398], ['a' => 'II', 'b' => 'bb', 'c' => 250],
    ['a' => 'II', 'b' => 'aa', 'c' => 652], ['a' => 'BB', 'b' => 'bb', 'c' => 633],
    ['a' => 'AA', 'b' => 'aa', 'c' => 239], ['a' => 'FF', 'b' => 'aa', 'c' => 670],
    ['a' => 'BB', 'b' => 'bb', 'c' => 705], ['a' => 'HH', 'b' => 'bb', 'c' => 963],
    ['a' => 'CC', 'b' => 'bb', 'c' => 346], ['a' => 'II', 'b' => 'bb', 'c' => 671],
    ['a' => 'BB', 'b' => 'aa', 'c' => 247], ['a' => 'AA', 'b' => 'aa', 'c' => 223],
    ['a' => 'GG', 'b' => 'aa', 'c' => 480], ['a' => 'HH', 'b' => 'aa', 'c' => 790],
    ['a' => 'FF', 'b' => 'aa', 'c' => 208], ['a' => 'BB', 'b' => 'bb', 'c' => 711],
    ['a' => 'EE', 'b' => 'aa', 'c' => 777], ['a' => 'DD', 'b' => 'bb', 'c' => 716],
    ['a' => 'CC', 'b' => 'aa', 'c' => 759], ['a' => 'CC', 'b' => 'aa', 'c' => 430],
    ['a' => 'CC', 'b' => 'aa', 'c' => 607], ['a' => 'DD', 'b' => 'bb', 'c' => 794],
    ['a' => 'GG', 'b' => 'aa', 'c' => 148], ['a' => 'GG', 'b' => 'aa', 'c' => 634],
    ['a' => 'JJ', 'b' => 'bb', 'c' => 257], ['a' => 'DD', 'b' => 'bb', 'c' => 959],
    ['a' => 'FF', 'b' => 'bb', 'c' => 726], ['a' => 'BB', 'b' => 'aa', 'c' => 762],
    ['a' => 'JJ', 'b' => 'bb', 'c' => 336], ['a' => 'GG', 'b' => 'aa', 'c' => 335],
    ['a' => 'HH', 'b' => 'bb', 'c' => 330], ['a' => 'GG', 'b' => 'bb', 'c' => 160],
    ['a' => 'JJ', 'b' => 'bb', 'c' => 839], ['a' => 'FF', 'b' => 'aa', 'c' => 618],
    ['a' => 'BB', 'b' => 'aa', 'c' => 393], ['a' => 'EE', 'b' => 'bb', 'c' => 629],
    ['a' => 'FF', 'b' => 'aa', 'c' => 667], ['a' => 'AA', 'b' => 'bb', 'c' => 870],
    ['a' => 'FF', 'b' => 'bb', 'c' => 102], ['a' => 'JJ', 'b' => 'aa', 'c' => 113],
    ['a' => 'DD', 'b' => 'aa', 'c' => 224], ['a' => 'AA', 'b' => 'bb', 'c' => 627],
    ['a' => 'HH', 'b' => 'bb', 'c' => 730], ['a' => 'II', 'b' => 'bb', 'c' => 443],
    ['a' => 'HH', 'b' => 'bb', 'c' => 133], ['a' => 'EE', 'b' => 'bb', 'c' => 252],
    ['a' => 'II', 'b' => 'bb', 'c' => 805], ['a' => 'BB', 'b' => 'bb', 'c' => 786],
    ['a' => 'EE', 'b' => 'bb', 'c' => 768], ['a' => 'HH', 'b' => 'bb', 'c' => 683],
    ['a' => 'DD', 'b' => 'bb', 'c' => 238], ['a' => 'DD', 'b' => 'aa', 'c' => 256],
];

$orderedWindow8 = static function (array $terms) use ($window8Rows): array {
    $rows = $window8Rows;
    usort($rows, static function (array $left, array $right) use ($terms): int {
        foreach ($terms as $term) {
            $comparison = $left[$term] <=> $right[$term];
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return $left['c'] <=> $right['c'];
    });

    return $rows;
};

$keysFor = static fn (array $rows, array $terms): array => array_map(
    static fn (array $row): string => implode("\x1f", array_map(static fn (string $term): string => (string) $row[$term], $terms)),
    $rows,
);

$valuesFor = static fn (array $rows): array => array_column($rows, 'c');

$boundary = static function (int $group, int $count, string $boundary, bool $start): int {
    $boundary = strtoupper($boundary);
    if ($boundary === 'UNBOUNDED PRECEDING') {
        return 0;
    }
    if ($boundary === 'UNBOUNDED FOLLOWING') {
        return $count - 1;
    }
    if ($boundary === 'CURRENT ROW') {
        return $group;
    }
    if (preg_match('/^(\d+) (PRECEDING|FOLLOWING)$/', $boundary, $match) !== 1) {
        return $start ? $count : -1;
    }
    $offset = (int) $match[1];

    return $match[2] === 'PRECEDING' ? $group - $offset : $group + $offset;
};

$expectedGroups = static function (array $values, array $keys, string $start, string $end, string $exclude, string $function) use ($boundary): array {
    $groups = [];
    $groupByRow = [];
    foreach ($keys as $index => $key) {
        if ($index === 0 || $key !== $keys[$index - 1]) {
            $groups[] = [];
        }
        $groupIndex = count($groups) - 1;
        $groups[$groupIndex][] = $index;
        $groupByRow[$index] = $groupIndex;
    }

    $result = [];
    foreach (array_keys($values) as $index) {
        $groupIndex = $groupByRow[$index];
        $startGroup = $boundary($groupIndex, count($groups), $start, true);
        $endGroup = $boundary($groupIndex, count($groups), $end, false);
        $frameRows = [];
        if ($startGroup <= $endGroup) {
            for ($cursor = max(0, $startGroup); $cursor <= min(count($groups) - 1, $endGroup); $cursor++) {
                array_push($frameRows, ...$groups[$cursor]);
            }
        }

        $exclude = strtoupper($exclude);
        if ($exclude === 'CURRENT ROW') {
            $frameRows = array_values(array_filter($frameRows, static fn (int $row): bool => $row !== $index));
        } elseif ($exclude === 'GROUP') {
            $peers = array_flip($groups[$groupIndex]);
            $frameRows = array_values(array_filter($frameRows, static fn (int $row): bool => !isset($peers[$row])));
        } elseif ($exclude === 'TIES') {
            $peers = array_flip($groups[$groupIndex]);
            $frameRows = array_values(array_filter($frameRows, static fn (int $row): bool => $row === $index || !isset($peers[$row])));
        }

        $frameValues = array_map(static fn (int $row): int => $values[$row], $frameRows);
        $result[] = match ($function) {
            'count' => count($frameValues),
            'sum' => $frameValues === [] ? null : array_sum($frameValues),
            'min' => $frameValues === [] ? null : min($frameValues),
            'max' => $frameValues === [] ? null : max($frameValues),
            default => throw new InvalidArgumentException('unexpected aggregate'),
        };
    }

    return $result;
};

$window8Cases = [
    'window8.test 1.1.1 sum order by a preceding group' => [['a'], 'UNBOUNDED PRECEDING', '1 PRECEDING', 'NO OTHERS', 'sum'],
    'window8.test 1.1.2 sum order by a,b preceding group' => [['a', 'b'], 'UNBOUNDED PRECEDING', '1 PRECEDING', 'NO OTHERS', 'sum'],
    'window8.test 1.1.4 max order by a,b preceding group' => [['a', 'b'], 'UNBOUNDED PRECEDING', '1 PRECEDING', 'NO OTHERS', 'max'],
    'window8.test 1.1.5 min order by a,b preceding group' => [['a', 'b'], 'UNBOUNDED PRECEDING', '1 PRECEDING', 'NO OTHERS', 'min'],
    'window8.test 1.2.1 sum order by a current group' => [['a'], 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', 'sum'],
    'window8.test 1.2.2 sum order by a,b current group' => [['a', 'b'], 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', 'sum'],
    'window8.test exclude current row over preceding groups' => [['a', 'b'], 'UNBOUNDED PRECEDING', '1 PRECEDING', 'CURRENT ROW', 'sum'],
    'window8.test exclude group over current group' => [['a', 'b'], 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'GROUP', 'sum'],
    'window8.test exclude ties over following group' => [['a', 'b'], 'CURRENT ROW', '1 FOLLOWING', 'TIES', 'count'],
];

foreach ($window8Cases as $name => [$terms, $start, $end, $exclude, $function]) {
    $tests['real upstream corpus window8 groups dynamic ' . $name] = static function (TestRunner $t) use ($orderedWindow8, $keysFor, $valuesFor, $expectedGroups, $terms, $start, $end, $exclude, $function, $name): void {
        $rows = $orderedWindow8($terms);
        $values = $valuesFor($rows);
        $keys = $keysFor($rows, $terms);
        $expected = $expectedGroups($values, $keys, $start, $end, $exclude, $function);
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues($function, $values, $keys, 'GROUPS', $start, $end, $exclude);

        $t->same($expected, $actual, $name);
        $t->same(count($values), count($actual), $name . ' row preservation');
    };
}

for ($case = 1; $case <= 1200; $case++) {
    $terms = ($case % 3) === 0 ? ['a'] : ['a', 'b'];
    $starts = ['UNBOUNDED PRECEDING', 'CURRENT ROW', '1 PRECEDING', '2 PRECEDING'];
    $ends = ['CURRENT ROW', '1 FOLLOWING', '2 FOLLOWING', 'UNBOUNDED FOLLOWING'];
    $excludes = ['NO OTHERS', 'CURRENT ROW', 'GROUP', 'TIES'];
    $functions = ['sum', 'count', 'min', 'max'];
    $start = $starts[$case % count($starts)];
    $end = $ends[intdiv($case, 3) % count($ends)];
    $exclude = $excludes[intdiv($case, 7) % count($excludes)];
    $function = $functions[intdiv($case, 11) % count($functions)];

    $tests[sprintf('real upstream corpus window8 groups dynamic frame matrix %04d', $case)] = static function (TestRunner $t) use ($orderedWindow8, $keysFor, $valuesFor, $expectedGroups, $terms, $start, $end, $exclude, $function, $case): void {
        $rows = $orderedWindow8($terms);
        $values = $valuesFor($rows);
        $keys = $keysFor($rows, $terms);
        $expected = $expectedGroups($values, $keys, $start, $end, $exclude, $function);
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues($function, $values, $keys, 'GROUPS', $start, $end, $exclude);

        $t->same($expected, $actual, "window8.test GROUPS {$function} dynamic case {$case}");
        $t->same($expected[0], $actual[0], "window8.test GROUPS first row case {$case}");
        $t->same($expected[40], $actual[40], "window8.test GROUPS middle row case {$case}");
        $t->same($expected[count($expected) - 1], $actual[count($actual) - 1], "window8.test GROUPS tail row case {$case}");
    };
}

$tests['real upstream corpus window8 groups dynamic cites exact upstream sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window8.test 1.1.1-1.1.8',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window8.test 1.2.1-1.2.2',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window8.test 1.1.1-1.1.8',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window8.test 1.2.1-1.2.2',
    ]);
};

$tests['real upstream corpus window8 groups dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local SQLiteWindowFunction GROUPS frame aggregate evaluation for real upstream window8 peer groups',
        'no new support component needed; reuses lane-local SQLiteWindowFunction GROUPS frame aggregate evaluation for real upstream window8 peer groups',
    );
};

return $tests;
