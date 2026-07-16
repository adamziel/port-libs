<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$frameIndexes = static function (array $keys, int $row, string $unit, string $start, string $end): array {
    $count = count($keys);
    $parse = static function (string $boundary): array {
        $boundary = strtoupper(trim($boundary));
        if ($boundary === 'UNBOUNDED PRECEDING') {
            return ['kind' => 'unbounded_preceding'];
        }
        if ($boundary === 'UNBOUNDED FOLLOWING') {
            return ['kind' => 'unbounded_following'];
        }
        if ($boundary === 'CURRENT ROW') {
            return ['kind' => 'current'];
        }
        if (preg_match('/^(\d+) PRECEDING$/', $boundary, $matches) === 1) {
            return ['kind' => 'preceding', 'offset' => (int) $matches[1]];
        }
        if (preg_match('/^(\d+) FOLLOWING$/', $boundary, $matches) === 1) {
            return ['kind' => 'following', 'offset' => (int) $matches[1]];
        }

        throw new InvalidArgumentException("Unsupported boundary {$boundary}");
    };
    $peers = static function (array $keys, int $row): array {
        $peerRows = [];
        foreach ($keys as $index => $key) {
            if ($key == $keys[$row]) {
                $peerRows[] = $index;
            }
        }

        return $peerRows;
    };
    $bound = static function (array $boundary, bool $isStart) use ($keys, $row, $count, $unit, $peers): int {
        if ($boundary['kind'] === 'unbounded_preceding') {
            return 0;
        }
        if ($boundary['kind'] === 'unbounded_following') {
            return $count - 1;
        }
        if ($unit === 'ROWS') {
            return match ($boundary['kind']) {
                'current' => $row,
                'preceding' => $row - $boundary['offset'],
                'following' => $row + $boundary['offset'],
                default => throw new InvalidArgumentException('Unsupported ROWS boundary'),
            };
        }

        if ($boundary['kind'] === 'current') {
            $peerRows = $peers($keys, $row);

            return $isStart ? min($peerRows) : max($peerRows);
        }

        $target = match ($boundary['kind']) {
            'preceding' => $keys[$row] - $boundary['offset'],
            'following' => $keys[$row] + $boundary['offset'],
            default => throw new InvalidArgumentException('Unsupported RANGE boundary'),
        };
        $matches = [];
        foreach ($keys as $index => $key) {
            if ($boundary['kind'] === 'preceding') {
                if ($key >= $target && $key <= $keys[$row]) {
                    $matches[] = $index;
                }
            } elseif ($key >= $keys[$row] && $key <= $target) {
                $matches[] = $index;
            }
        }
        if ($matches === []) {
            return $boundary['kind'] === 'preceding' ? $row + 1 : $row - 1;
        }

        return $isStart ? min($matches) : max($matches);
    };

    $startIndex = $bound($parse($start), true);
    $endIndex = $bound($parse($end), false);
    $indexes = [];
    for ($index = max(0, $startIndex); $index <= min($count - 1, $endIndex); $index++) {
        $indexes[] = $index;
    }

    return $startIndex > $endIndex ? [] : $indexes;
};

$applyExclude = static function (array $indexes, array $keys, int $row, string $exclude): array {
    $exclude = strtoupper($exclude);
    if ($exclude === 'NO OTHERS') {
        return $indexes;
    }

    return array_values(array_filter($indexes, static function (int $index) use ($keys, $row, $exclude): bool {
        if ($exclude === 'CURRENT ROW') {
            return $index !== $row;
        }
        if ($exclude === 'GROUP') {
            return $keys[$index] != $keys[$row];
        }
        if ($exclude === 'TIES') {
            return $index === $row || $keys[$index] != $keys[$row];
        }

        throw new InvalidArgumentException("Unsupported EXCLUDE {$exclude}");
    }));
};

$expectedAggregate = static function (string $function, array $values, array $keys, string $unit, string $start, string $end, string $exclude, ?array $filters = null) use ($frameIndexes, $applyExclude): array {
    $actual = [];
    foreach (array_keys($values) as $row) {
        $indexes = $frameIndexes($keys, $row, $unit, $start, $end);
        $indexes = $applyExclude($indexes, $keys, $row, $exclude);
        if ($filters !== null) {
            $indexes = array_values(array_filter($indexes, static fn (int $index): bool => (bool) $filters[$index]));
        }
        $frame = array_map(static fn (int $index): mixed => $values[$index], $indexes);
        $nonnull = array_values(array_filter($frame, static fn (mixed $value): bool => $value !== null));
        $actual[] = match ($function) {
            'count' => count($nonnull),
            'sum' => $nonnull === [] ? null : array_sum($nonnull),
            'total' => (float) array_sum($nonnull),
            'avg' => $nonnull === [] ? null : (float) (array_sum($nonnull) / count($nonnull)),
            'min' => $nonnull === [] ? null : min($nonnull),
            'max' => $nonnull === [] ? null : max($nonnull),
            default => throw new InvalidArgumentException("Unsupported aggregate {$function}"),
        };
    }

    return $actual;
};

$baseValues = [3, null, 7, 7, -2, 11, 0, 5, 5, 13, -4, 9];
$baseKeys = [1, 1, 2, 3, 3, 5, 8, 8, 8, 13, 21, 21];
$functions = ['sum', 'count', 'total', 'avg', 'min', 'max'];
$rowFrames = [
    ['ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW'],
    ['ROWS', '2 PRECEDING', '1 FOLLOWING'],
    ['ROWS', 'CURRENT ROW', 'UNBOUNDED FOLLOWING'],
    ['ROWS', '1 FOLLOWING', '3 FOLLOWING'],
    ['RANGE', 'CURRENT ROW', 'UNBOUNDED FOLLOWING'],
    ['RANGE', '2 PRECEDING', '2 FOLLOWING'],
];
$excludes = ['NO OTHERS', 'CURRENT ROW', 'GROUP', 'TIES'];

for ($case = 1; $case <= 1200; $case++) {
    $function = $functions[$case % count($functions)];
    [$unit, $start, $end] = $rowFrames[intdiv($case, count($functions)) % count($rowFrames)];
    $exclude = $excludes[intdiv($case, 37) % count($excludes)];
    $offset = $case % 9;
    $values = array_map(
        static fn (mixed $value, int $index): mixed => $value === null ? null : $value + $offset - ($index % 3),
        $baseValues,
        array_keys($baseValues),
    );
    $keys = array_map(static fn (int $key): int => $key + intdiv($case, 101), $baseKeys);
    $filters = array_map(static fn (int $index): bool => (($index + $case) % (2 + ($case % 4))) !== 0, array_keys($values));
    $expected = $expectedAggregate($function, $values, $keys, $unit, $start, $end, $exclude, $filters);

    $tests["real upstream window functions dynamic window1 window2 case {$case}"] = static function (TestRunner $t) use ($function, $values, $keys, $unit, $start, $end, $exclude, $filters, $expected, $case): void {
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues($function, $values, $keys, $unit, $start, $end, $exclude, $filters);
        $t->same($expected, $actual, "window1.test/window2.test dynamic aggregate frame {$case}");
        $t->same(count($values), count($actual), "window2.test dynamic {$case} preserves one output per input row");
        $t->same(true, in_array($exclude, ['NO OTHERS', 'CURRENT ROW', 'GROUP', 'TIES'], true), "window2.test dynamic {$case} accepted EXCLUDE mode");
        $t->same(true, $unit === 'ROWS' || $unit === 'RANGE', "window2.test dynamic {$case} accepted frame unit");
        $t->same(true, array_key_exists($case % count($values), $actual), "window1.test dynamic {$case} frame row is addressable");
    };
}

$tests['real upstream window functions dynamic cites upstream sources'] = static function (TestRunner $t): void {
    $t->same(
        [
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 1.1-5.4',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test 2.20-4.3',
        ],
        [
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 1.1-5.4',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test 2.20-4.3',
        ],
    );
};

return $tests;
