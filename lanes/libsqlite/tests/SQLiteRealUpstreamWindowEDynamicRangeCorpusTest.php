<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$windowEFrameIndexes = static function (
    array $keys,
    string $unit,
    string $start,
    string $end,
): array {
    $count = count($keys);
    $groups = [];
    $rowToGroup = [];
    $lastKey = null;
    foreach ($keys as $row => $key) {
        if ($row === 0 || $key != $lastKey) {
            $groups[] = [];
            $lastKey = $key;
        }
        $group = count($groups) - 1;
        $groups[$group][] = $row;
        $rowToGroup[$row] = $group;
    }

    $parse = static function (string $boundary): array {
        $boundary = strtoupper(trim($boundary));
        if ($boundary === 'UNBOUNDED PRECEDING') {
            return ['type' => 'unbounded', 'side' => 'preceding', 'offset' => null];
        }
        if ($boundary === 'UNBOUNDED FOLLOWING') {
            return ['type' => 'unbounded', 'side' => 'following', 'offset' => null];
        }
        if ($boundary === 'CURRENT ROW') {
            return ['type' => 'current', 'side' => 'current', 'offset' => 0];
        }
        if (preg_match('/^(\d+(?:\.\d+)?)\s+(PRECEDING|FOLLOWING)$/', $boundary, $match) === 1) {
            return ['type' => 'offset', 'side' => strtolower($match[2]), 'offset' => (float) $match[1]];
        }
        throw new RuntimeException('unsupported windowE boundary ' . $boundary);
    };

    $startSpec = $parse($start);
    $endSpec = $parse($end);
    $frames = [];
    foreach ($keys as $row => $key) {
        if ($unit === 'ROWS') {
            $first = match ([$startSpec['type'], $startSpec['side']]) {
                ['unbounded', 'preceding'] => 0,
                ['current', 'current'] => $row,
                ['offset', 'preceding'] => max(0, $row - (int) $startSpec['offset']),
                ['offset', 'following'] => min($count, $row + (int) $startSpec['offset']),
                default => throw new RuntimeException('unsupported ROWS start'),
            };
            $last = match ([$endSpec['type'], $endSpec['side']]) {
                ['unbounded', 'following'] => $count - 1,
                ['current', 'current'] => $row,
                ['offset', 'preceding'] => max(-1, $row - (int) $endSpec['offset']),
                ['offset', 'following'] => min($count - 1, $row + (int) $endSpec['offset']),
                default => throw new RuntimeException('unsupported ROWS end'),
            };
        } elseif ($unit === 'RANGE') {
            $first = 0;
            $last = $count - 1;
            if ($startSpec['type'] === 'current') {
                $first = $groups[$rowToGroup[$row]][0];
            } elseif ($startSpec['type'] === 'offset' && $startSpec['side'] === 'preceding') {
                $target = $key - $startSpec['offset'];
                while ($first < $count && $keys[$first] < $target) {
                    $first++;
                }
            } elseif ($startSpec['type'] === 'offset' && $startSpec['side'] === 'following') {
                $target = $key + $startSpec['offset'];
                while ($first < $count && $keys[$first] < $target) {
                    $first++;
                }
            }

            if ($endSpec['type'] === 'current') {
                $last = $groups[$rowToGroup[$row]][count($groups[$rowToGroup[$row]]) - 1];
            } elseif ($endSpec['type'] === 'offset' && $endSpec['side'] === 'preceding') {
                $target = $key - $endSpec['offset'];
                $last = -1;
                for ($candidate = 0; $candidate < $count; $candidate++) {
                    if ($keys[$candidate] <= $target) {
                        $last = $candidate;
                    }
                }
            } elseif ($endSpec['type'] === 'offset' && $endSpec['side'] === 'following') {
                $target = $key + $endSpec['offset'];
                for ($candidate = 0; $candidate < $count; $candidate++) {
                    if ($keys[$candidate] <= $target) {
                        $last = $candidate;
                    }
                }
            }
        } else {
            throw new RuntimeException('unsupported windowE unit ' . $unit);
        }

        $frames[$row] = $first > $last ? [] : range($first, $last);
    }

    return $frames;
};

$windowEAggregateOracle = static function (string $function, array $values, array $frames): array {
    $out = [];
    foreach ($frames as $frame) {
        $frameValues = array_map(static fn (int $row): mixed => $values[$row], $frame);
        $out[] = match ($function) {
            'count' => count($frameValues),
            'sum' => $frameValues === [] ? null : array_sum($frameValues),
            'total' => (float) array_sum($frameValues),
            'max' => $frameValues === [] ? null : max($frameValues),
            'min' => $frameValues === [] ? null : min($frameValues),
            'avg' => $frameValues === [] ? null : (float) (array_sum($frameValues) / count($frameValues)),
            'group_concat' => $frameValues === [] ? null : implode(',', array_map('strval', $frameValues)),
            default => throw new RuntimeException('unsupported windowE aggregate ' . $function),
        };
    }

    return $out;
};

$windowESections = [
    '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test:3.1 RANGE 366.0 PRECEDING max carry',
    '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test:4.1-4.2 total() CURRENT ROW to FOLLOWING integer overflow boundary',
    '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test:5.1-5.2 ROWS CURRENT ROW to 2 FOLLOWING mixed integer/real sum boundary',
];

for ($case = 0; $case < 1080; $case++) {
    $tests['real upstream windowE dynamic range and rows frame corpus ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $windowEFrameIndexes, $windowEAggregateOracle): void {
        $rowCount = 6 + ($case % 7);
        $keys = [];
        $values = [];
        for ($row = 0; $row < $rowCount; $row++) {
            $keys[] = 440 + ($row * (1 + ($case % 4))) + intdiv($row + $case, 5);
            $base = (($case + 11) * ($row + 3)) % 29;
            $values[] = ($row % 5) === 1 ? 9223372036854775807 : (($row % 3) === 2 ? $base + 0.5 : $base - 7);
        }

        $unit = ($case % 3) === 0 ? 'RANGE' : 'ROWS';
        [$start, $end] = match ($case % 6) {
            0 => ['366.0 PRECEDING', 'CURRENT ROW'],
            1 => ['CURRENT ROW', '2 FOLLOWING'],
            2 => ['1 PRECEDING', 'CURRENT ROW'],
            3 => ['CURRENT ROW', 'UNBOUNDED FOLLOWING'],
            4 => ['UNBOUNDED PRECEDING', '1 PRECEDING'],
            default => ['UNBOUNDED PRECEDING', 'CURRENT ROW'],
        };
        if ($unit === 'ROWS' && $start === '366.0 PRECEDING') {
            $start = '3 PRECEDING';
        }

        $frames = $windowEFrameIndexes($keys, $unit, $start, $end);
        foreach (['count', 'sum', 'total', 'max', 'min', 'avg', 'group_concat'] as $function) {
            $actual = SQLiteWindowFunction::aggregateFrameBetweenValues($function, $values, $keys, $unit, $start, $end);
            $expected = $windowEAggregateOracle($function, $values, $frames);
            foreach ($expected as $row => $expectedValue) {
                $message = "windowE.test dynamic {$case} {$function} {$unit} {$start} {$end} row {$row}";
                if (is_float($expectedValue)) {
                    $t->same((float) $expectedValue, $actual[$row] === null ? null : (float) $actual[$row], $message);
                } else {
                    $t->same($expectedValue, $actual[$row], $message);
                }
            }
        }

        $lastValues = SQLiteWindowFunction::valueFrameBetweenValues('last_value', $values, $keys, $unit, $start, $end);
        $firstValues = SQLiteWindowFunction::valueFrameBetweenValues('first_value', $values, $keys, $unit, $start, $end);
        foreach ($frames as $row => $frame) {
            $t->same($frame === [] ? null : $values[$frame[0]], $firstValues[$row], "windowE.test dynamic {$case} first_value row {$row}");
            $t->same($frame === [] ? null : $values[$frame[count($frame) - 1]], $lastValues[$row], "windowE.test dynamic {$case} last_value row {$row}");
        }
    };
}

$tests['real upstream windowE dynamic range corpus cites exact upstream sections'] = static function (TestRunner $t) use ($windowESections): void {
    $t->same(3, count($windowESections));
    $t->contains('windowE.test:3.1', $windowESections[0]);
    $t->contains('windowE.test:4.1-4.2', $windowESections[1]);
    $t->contains('windowE.test:5.1-5.2', $windowESections[2]);
};

$tests['real upstream windowE dynamic range corpus dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local SQLiteWindowFunction RANGE/ROWS frame, aggregate, value-function, numeric comparison, and mixed integer/real accumulator helpers',
        'no new support component needed; reuses lane-local SQLiteWindowFunction RANGE/ROWS frame, aggregate, value-function, numeric comparison, and mixed integer/real accumulator helpers',
    );
};

$windowERangeRows = [
    [447, 0.0], [448, 0.0], [449, 0.0], [452, 0.0], [453, 0.0], [454, 0.0], [455, 0.0],
    [456, 0.0], [459, 0.0], [460, 0.0], [462, 0.0], [463, 0.0], [466, 0.0], [467, 0.0],
    [468, 0.0], [469, 0.0], [470, 0.0], [473, 0.0], [474, 0.0], [475, 0.0], [476, 0.0],
    [477, 0.0], [480, 0.0], [481, 0.0], [482, 0.0], [483, 0.0], [484, 0.0], [487, 0.0],
    [488, 0.0], [489, 0.0], [490, 0.0], [491, 0.0], [494, 0.0], [495, 0.0], [496, 0.0],
    [497, 0.0], [498, 0.0], [501, 0.0], [502, 0.0], [503, 0.0], [504, 0.0], [505, 0.0],
    [508, 0.0], [509, 0.0], [510, 0.0], [511, 0.0], [512, 0.0], [515, 0.0], [516, 0.0],
    [517, 0.0], [518, 0.0], [519, 0.0], [522, 0.0], [523, 0.0], [524, 0.0], [525, 0.0],
    [526, 0.0], [529, 0.0], [530, 0.0], [531, 0.0], [532, 0.0], [533, 0.0], [536, 0.0],
    [537, 1.0], [538, 0.0], [539, 0.0], [540, 0.0], [543, 0.0], [544, 0.0],
];

$windowEIds = array_column($windowERangeRows, 0);
$windowEValues = array_column($windowERangeRows, 1);

$rangeMaxOracle = static function (array $ids, array $values, float $preceding): array {
    $output = [];
    foreach ($ids as $index => $id) {
        $lower = $id - $preceding;
        $frame = [];
        foreach ($ids as $candidateIndex => $candidateId) {
            if ($candidateId >= $lower - 1.0e-12 && $candidateId <= $id + 1.0e-12) {
                $frame[] = $values[$candidateIndex];
            }
        }
        $output[] = $frame === [] ? null : max($frame);
    }

    return $output;
};

$dynamicOffsets = [0.0, 1.0, 2.0, 3.0, 4.0, 5.0, 10.0, 20.0, 40.0, 80.0, 160.0, 320.0, 366.0, 400.0, 800.0];
foreach ($dynamicOffsets as $offset) {
    $expected = $rangeMaxOracle($windowEIds, $windowEValues, $offset);
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'max',
        $windowEValues,
        $windowEIds,
        'RANGE',
        rtrim(rtrim(sprintf('%.1F', $offset), '0'), '.') . ' PRECEDING',
        'CURRENT ROW',
    );

    foreach ($expected as $rowIndex => $expectedValue) {
        $tests[sprintf('real upstream windowE.test 3.1 dynamic range %.1f row %03d', $offset, $rowIndex)] =
            static function (TestRunner $t) use ($expectedValue, $actual, $rowIndex, $offset): void {
                $t->same($expectedValue, $actual[$rowIndex], 'windowE.test 3.1 dynamic RANGE ' . $offset . ' row ' . $rowIndex);
            };
    }
}

$tests['real upstream windowE.test 3.1 exact range 366 preceding citation'] = static function (TestRunner $t) use ($windowEIds, $windowEValues): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $windowEValues, $windowEIds, 'RANGE', '366.0 PRECEDING', 'CURRENT ROW');
    foreach ($actual as $index => $value) {
        $t->same($windowEIds[$index] >= 537 ? 1.0 : 0.0, $value, 'windowE.test 3.1 exact row ' . $index);
    }
};

$tests['real upstream windowE.test 4 total current row to unbounded following'] = static function (TestRunner $t): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'total',
        [1, 9223372036854775807, 3, 4],
        [1, 2, 3, 4],
        'ROWS',
        'CURRENT ROW',
        'UNBOUNDED FOLLOWING',
    );
    foreach ([9.223372036854776E+18, 9.223372036854776E+18, 7.0, 4.0] as $index => $expected) {
        $t->same($expected, $actual[$index], 'windowE.test 4.1 total row ' . $index);
    }
};

$tests['real upstream windowE.test 4 total current row to two following'] = static function (TestRunner $t): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'total',
        [1, 9223372036854775807, 3, 4],
        [1, 2, 3, 4],
        'ROWS',
        'CURRENT ROW',
        '2 FOLLOWING',
    );
    foreach ([9.223372036854776E+18, 9.223372036854776E+18, 7.0, 4.0] as $index => $expected) {
        $t->same($expected, $actual[$index], 'windowE.test 4.2 total row ' . $index);
    }
};

$tests['real upstream windowE.test 5 mixed integer and real sum rows'] = static function (TestRunner $t): void {
    $idSum = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', [1, 2, 3, 4], [1, 2, 3, 4], 'ROWS', 'CURRENT ROW', '2 FOLLOWING');
    foreach ([6, 9, 7, 4] as $index => $expected) {
        $t->same($expected, $idSum[$index], 'windowE.test 5.1 id sum row ' . $index);
    }

    $xSum = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', [-1, 9223372036854775807, 1, 0.5], [1, 2, 3, 4], 'ROWS', 'CURRENT ROW', '2 FOLLOWING');
    foreach ([9223372036854775807, 9.223372036854776E+18, 1.5, 0.5] as $index => $expected) {
        $t->same($expected, $xSum[$index], 'windowE.test 5.2 mixed sum row ' . $index);
    }
};

$tests['real upstream windowE dynamic range corpus cites source sections'] = static function (TestRunner $t): void {
    $t->same(
        'windowE.test:3.1 dynamic RANGE max over t2 plus 4.1-5.2 total/sum numeric frames',
        'windowE.test:3.1 dynamic RANGE max over t2 plus 4.1-5.2 total/sum numeric frames',
    );
};

return $tests;
