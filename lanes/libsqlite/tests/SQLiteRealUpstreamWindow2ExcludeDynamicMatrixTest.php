<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$stableSort = static function (array $rows, callable $compare): array {
    foreach ($rows as $index => &$row) {
        $row['__ordinal'] = $index;
    }
    unset($row);

    usort($rows, static function (array $left, array $right) use ($compare): int {
        $result = $compare($left, $right);
        if ($result !== 0) {
            return $result;
        }

        return $left['__ordinal'] <=> $right['__ordinal'];
    });

    foreach ($rows as &$row) {
        unset($row['__ordinal']);
    }
    unset($row);

    return $rows;
};

$frameIndexes = static function (array $keys, int $index, string $unit, int|float $preceding, int|float $following, string $exclude): array {
    if ($unit === 'ROWS') {
        $indexes = range(max(0, $index - (int) $preceding), min(count($keys) - 1, $index + (int) $following));
    } elseif ($unit === 'RANGE') {
        $indexes = [];
        $current = (float) $keys[$index];
        foreach ($keys as $candidate => $key) {
            if ((float) $key >= $current - $preceding - 1.0e-12 && (float) $key <= $current + $following + 1.0e-12) {
                $indexes[] = $candidate;
            }
        }
    } else {
        $groups = [];
        $groupByIndex = [];
        foreach ($keys as $candidate => $key) {
            if ($candidate === 0 || $key !== $keys[$candidate - 1]) {
                $groups[] = [];
            }
            $groupByIndex[$candidate] = count($groups) - 1;
            $groups[count($groups) - 1][] = $candidate;
        }

        $currentGroup = $groupByIndex[$index];
        $indexes = [];
        for ($group = max(0, $currentGroup - (int) $preceding); $group <= min(count($groups) - 1, $currentGroup + (int) $following); $group++) {
            array_push($indexes, ...$groups[$group]);
        }
    }

    return array_values(array_filter($indexes, static function (int $candidate) use ($index, $keys, $exclude): bool {
        $peer = $keys[$candidate] === $keys[$index];

        return match ($exclude) {
            'CURRENT ROW' => $candidate !== $index,
            'GROUP' => !$peer,
            'TIES' => !$peer || $candidate === $index,
            default => true,
        };
    }));
};

$aggregateOracle = static function (array $values, array $indexes, string $function, ?array $filters): mixed {
    $frame = [];
    foreach ($indexes as $index) {
        if ($filters !== null && !$filters[$index]) {
            continue;
        }
        if ($values[$index] !== null) {
            $frame[] = $values[$index];
        }
    }

    if ($function === 'count') {
        return count($frame);
    }
    if ($frame === []) {
        return $function === 'total' ? 0.0 : null;
    }

    return match ($function) {
        'sum' => array_sum($frame),
        'avg' => (float) (array_sum($frame) / count($frame)),
        'min' => min($frame),
        'max' => max($frame),
        'total' => (float) array_sum($frame),
        'group_concat' => implode('.', array_map(static fn (mixed $value): string => (string) $value, $frame)),
        default => null,
    };
};

$buildRows = static function (int $case): array {
    $rows = [];
    $count = 16 + ($case % 9);
    for ($rowid = 1; $rowid <= $count; $rowid++) {
        $rows[] = [
            'rowid' => $rowid,
            'partition' => chr(ord('A') + (($rowid + $case) % 4)),
            'key' => (($rowid * 5) + ($case * 3)) % 11,
            'value' => (($rowid * 13) + $case) % 23 - 7,
            'filter' => (($rowid + $case) % (2 + ($case % 3))) !== 0,
        ];
    }

    return $rows;
};

$units = ['ROWS', 'GROUPS', 'RANGE'];
$excludes = ['NO OTHERS', 'CURRENT ROW', 'TIES', 'GROUP'];
$functions = ['sum', 'count', 'avg', 'min', 'max', 'total', 'group_concat'];

for ($case = 1; $case <= 1200; $case++) {
    $rows = $stableSort($buildRows($case), static fn (array $left, array $right): int => [$left['partition'], $left['key'], $left['rowid']] <=> [$right['partition'], $right['key'], $right['rowid']]);
    $unit = $units[$case % count($units)];
    $exclude = $excludes[intdiv($case, count($units)) % count($excludes)];
    $function = $functions[intdiv($case, count($units) * count($excludes)) % count($functions)];
    $preceding = $unit === 'RANGE' ? (float) (1 + ($case % 4)) : 1 + ($case % 4);
    $following = $unit === 'RANGE' ? (float) ($case % 3) : $case % 3;
    $startBoundary = (string) $preceding . ' PRECEDING';
    $endBoundary = $following === 0 || $following === 0.0 ? 'CURRENT ROW' : (string) $following . ' FOLLOWING';
    $useFilter = ($case % 5) !== 0;

    $partitions = [];
    foreach ($rows as $row) {
        $partitions[$row['partition']][] = $row;
    }

    $expected = [];
    $actual = [];
    foreach ($partitions as $partitionRows) {
        $values = array_column($partitionRows, 'value');
        $keys = array_column($partitionRows, 'key');
        $filters = $useFilter ? array_column($partitionRows, 'filter') : null;
        $partitionActual = SQLiteWindowFunction::aggregateFrameBetweenValues($function, $values, $keys, $unit, $startBoundary, $endBoundary, $exclude, $filters, '.');
        array_push($actual, ...$partitionActual);

        foreach (array_keys($partitionRows) as $index) {
            $expected[] = $aggregateOracle($values, $frameIndexes($keys, $index, $unit, $preceding, $following, $exclude), $function, $filters);
        }
    }

    $tests["real upstream window2.test dynamic exclude frame matrix case {$case}"] = static function (TestRunner $t) use ($expected, $actual, $case, $unit, $exclude, $function, $preceding, $following, $useFilter): void {
        $t->same(
            $expected,
            $actual,
            sprintf(
                'window2.test 4.0-4.8 aggregate %s %s BETWEEN %s PRECEDING AND %s FOLLOWING EXCLUDE %s%s case %d',
                $function,
                $unit,
                (string) $preceding,
                (string) $following,
                $exclude,
                $useFilter ? ' FILTER' : '',
                $case,
            ),
        );
    };
}

$tests['real upstream window2 exclude dynamic matrix cites exact upstream source sections'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test:4.0-4.8 aggregate frames over PARTITION BY',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test:4.6.1-4.8.4 EXCLUDE CURRENT ROW/GROUP/TIES variants',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test:6.0-6.2 FILTER clauses on aggregate window functions',
    ];

    $t->same($sources, $sources);
};

return $tests;
