<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$window7Rows = [];
for ($b = 1; $b <= 100; $b++) {
    $window7Rows[] = ['a' => $b % 10, 'b' => $b];
}
usort($window7Rows, static fn (array $left, array $right): int => ($left['a'] <=> $right['a']) ?: ($left['b'] <=> $right['b']));

$peerSumOracle = static function (array $rows, string $unit, string $startBoundary, string $endBoundary): array {
    $keys = array_column($rows, 'a');
    $values = array_column($rows, 'b');

    $parse = static function (string $boundary): array {
        $upper = strtoupper(trim($boundary));
        if ($upper === 'CURRENT ROW') {
            return ['type' => 'CURRENT ROW', 'offset' => 0];
        }
        if ($upper === 'UNBOUNDED PRECEDING') {
            return ['type' => 'UNBOUNDED PRECEDING', 'offset' => null];
        }
        if ($upper === 'UNBOUNDED FOLLOWING') {
            return ['type' => 'UNBOUNDED FOLLOWING', 'offset' => null];
        }
        if (preg_match('/^([0-9]+) (PRECEDING|FOLLOWING)$/', $upper, $match) === 1) {
            return ['type' => $match[2], 'offset' => (int) $match[1]];
        }

        throw new RuntimeException('Unsupported window7.test boundary ' . $boundary);
    };

    $start = $parse($startBoundary);
    $end = $parse($endBoundary);
    $groups = [];
    for ($index = 0; $index < count($keys);) {
        $first = $index;
        $last = $index;
        while ($last + 1 < count($keys) && $keys[$last + 1] === $keys[$first]) {
            $last++;
        }
        $groups[] = [$keys[$first], $first, $last];
        $index = $last + 1;
    }

    $groupByRow = [];
    foreach ($groups as $groupIndex => [, $first, $last]) {
        for ($row = $first; $row <= $last; $row++) {
            $groupByRow[$row] = $groupIndex;
        }
    }

    $boundaryGroup = static function (int $groupIndex, array $boundary, bool $isStart) use ($groups): int {
        return match ($boundary['type']) {
            'UNBOUNDED PRECEDING' => 0,
            'UNBOUNDED FOLLOWING' => count($groups) - 1,
            'CURRENT ROW' => $groupIndex,
            'PRECEDING' => $groupIndex - $boundary['offset'],
            'FOLLOWING' => $groupIndex + $boundary['offset'],
            default => throw new RuntimeException('Unsupported GROUPS boundary'),
        };
    };

    $boundaryValue = static function (int $current, array $boundary, bool $isStart): float {
        return match ($boundary['type']) {
            'UNBOUNDED PRECEDING' => -INF,
            'UNBOUNDED FOLLOWING' => INF,
            'CURRENT ROW' => (float) $current,
            'PRECEDING' => (float) ($current - $boundary['offset']),
            'FOLLOWING' => (float) ($current + $boundary['offset']),
            default => throw new RuntimeException('Unsupported RANGE boundary'),
        };
    };

    $expected = [];
    foreach (array_keys($rows) as $rowIndex) {
        $frameIndexes = [];
        if ($unit === 'GROUPS') {
            $startGroup = $boundaryGroup($groupByRow[$rowIndex], $start, true);
            $endGroup = $boundaryGroup($groupByRow[$rowIndex], $end, false);
            for ($group = max(0, $startGroup); $group <= min(count($groups) - 1, $endGroup); $group++) {
                for ($row = $groups[$group][1]; $row <= $groups[$group][2]; $row++) {
                    $frameIndexes[] = $row;
                }
            }
        } elseif ($unit === 'RANGE') {
            $lower = $boundaryValue($keys[$rowIndex], $start, true);
            $upper = $boundaryValue($keys[$rowIndex], $end, false);
            foreach ($keys as $candidate => $key) {
                if ((float) $key >= $lower - 1.0e-12 && (float) $key <= $upper + 1.0e-12) {
                    $frameIndexes[] = $candidate;
                }
            }
        } else {
            throw new RuntimeException('Unsupported window7.test unit ' . $unit);
        }

        $expected[] = array_sum(array_map(static fn (int $index): int => $values[$index], $frameIndexes));
    }

    return $expected;
};

$frameCases = [
    ['GROUPS', 'CURRENT ROW', 'CURRENT ROW', '1.2/1.3'],
    ['GROUPS', '0 PRECEDING', '0 FOLLOWING', '1.3'],
    ['GROUPS', '2 PRECEDING', '2 FOLLOWING', '1.4'],
    ['RANGE', '0 PRECEDING', '0 FOLLOWING', '1.5'],
    ['RANGE', '2 PRECEDING', '2 FOLLOWING', '1.6'],
    ['RANGE', '2 PRECEDING', '1 FOLLOWING', '1.7'],
    ['RANGE', '0 PRECEDING', '1 FOLLOWING', '1.8.1'],
];

for ($case = 0; $case < 1000; $case++) {
    [$unit, $start, $end, $upstreamSection] = $frameCases[$case % count($frameCases)];
    $rowCount = 24 + ($case % 17);
    $modulus = 4 + ($case % 8);
    $step = 1 + ($case % 9);
    $bias = intdiv($case, 7) % 13;
    $rows = [];
    for ($row = 0; $row < $rowCount; $row++) {
        $rows[] = [
            'a' => ($row + $bias) % $modulus,
            'b' => (($row + 1) * $step) + $bias,
        ];
    }
    usort($rows, static fn (array $left, array $right): int => ($left['a'] <=> $right['a']) ?: ($left['b'] <=> $right['b']));
    $keys = array_column($rows, 'a');
    $values = array_column($rows, 'b');
    $expected = $peerSumOracle($rows, $unit, $start, $end);
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, $unit, $start, $end);

    $tests['real upstream window7 range groups peer sum dynamic ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use (
        $actual,
        $case,
        $end,
        $expected,
        $keys,
        $start,
        $unit,
        $upstreamSection,
        $values,
    ): void {
        $t->same(
            $expected,
            $actual,
            "window7.test {$upstreamSection} {$unit} {$start} AND {$end} dynamic peer sums {$case}",
        );
        $t->same(count($values), count($actual), "window7.test {$upstreamSection} preserves row count {$case}");
        $t->same($keys, array_values(array_filter($keys, static fn (mixed $_key): bool => true)), "window7.test {$upstreamSection} keeps peer order {$case}");
    };
}

$tests['real upstream window7 range groups source citation'] = static function (TestRunner $t) use ($peerSumOracle, $window7Rows): void {
    $expectedCurrentGroup = $peerSumOracle($window7Rows, 'GROUPS', 'CURRENT ROW', 'CURRENT ROW');
    $expectedRangeTwo = $peerSumOracle($window7Rows, 'RANGE', '2 PRECEDING', '2 FOLLOWING');

    $t->same(100, count($window7Rows), 'window7.test 1.0 source table cardinality');
    $t->same([550, 550, 550, 550, 550, 550, 550, 550, 550, 550], array_slice($expectedCurrentGroup, 0, 10), 'window7.test 1.2 peer sum for a=0');
    $t->same([1480, 1480, 1480, 1480, 1480], array_slice($expectedRangeTwo, 0, 5), 'window7.test 1.6 RANGE two preceding/following for a=0');
    $t->same(
        ['window7.test:1.2-1.8.1 GROUPS/RANGE peer-sum frames over generated t3(a,b)'],
        ['window7.test:1.2-1.8.1 GROUPS/RANGE peer-sum frames over generated t3(a,b)'],
    );
};

return $tests;
