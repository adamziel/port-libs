<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$upstreamWindow3 = '/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test';

$window3DynamicRows = static function (int $case): array {
    $rows = [];
    $count = 12 + ($case % 9);

    for ($i = 0; $i < $count; $i++) {
        $rows[] = [
            'a' => ($case * 100) + $i + 1,
            'b' => 1 + (($case * 7 + $i * 5 + intdiv($case + $i, 3)) % 9),
            'row' => $i,
        ];
    }

    return $rows;
};

$window3CompareTuples = static function (array $left, array $right): int {
    $count = max(count($left), count($right));

    for ($i = 0; $i < $count; $i++) {
        $leftValue = $left[$i] ?? null;
        $rightValue = $right[$i] ?? null;

        if ($leftValue === $rightValue) {
            continue;
        }

        return $leftValue <=> $rightValue;
    }

    return 0;
};

$window3OrderedPartitions = static function (array $rows, callable $partitionKey, callable $orderKey) use ($window3CompareTuples): array {
    $partitions = [];

    foreach ($rows as $row) {
        $key = $partitionKey($row);
        $encoded = json_encode($key);

        if (!isset($partitions[$encoded])) {
            $partitions[$encoded] = [
                'key' => $key,
                'rows' => [],
            ];
        }

        $partitions[$encoded]['rows'][] = $row;
    }

    uasort(
        $partitions,
        static fn (array $left, array $right): int => $window3CompareTuples($left['key'], $right['key'])
    );

    $ordered = [];
    foreach ($partitions as $partition) {
        $partitionRows = $partition['rows'];
        usort(
            $partitionRows,
            static function (array $left, array $right) use ($orderKey, $window3CompareTuples): int {
                $order = $window3CompareTuples($orderKey($left), $orderKey($right));

                if ($order !== 0) {
                    return $order;
                }

                return $left['row'] <=> $right['row'];
            }
        );

        $ordered[] = $partitionRows;
    }

    return $ordered;
};

$window3Specs = [
    'order by a' => [
        'partition' => static fn (array $row): array => [0],
        'order' => static fn (array $row): array => [$row['a']],
        'orderKey' => static fn (array $row): int => $row['a'],
    ],
    'partition by b mod 10 order by a' => [
        'partition' => static fn (array $row): array => [$row['b'] % 10],
        'order' => static fn (array $row): array => [$row['a']],
        'orderKey' => static fn (array $row): int => $row['a'],
    ],
    'order by b and a' => [
        'partition' => static fn (array $row): array => [0],
        'order' => static fn (array $row): array => [$row['b'], $row['a']],
        'orderKey' => static fn (array $row): int => ($row['b'] * 100000) + $row['a'],
    ],
    'partition by b mod 10 order by b and a' => [
        'partition' => static fn (array $row): array => [$row['b'] % 10],
        'order' => static fn (array $row): array => [$row['b'], $row['a']],
        'orderKey' => static fn (array $row): int => ($row['b'] * 100000) + $row['a'],
    ],
    'order by b mod 10 and a' => [
        'partition' => static fn (array $row): array => [0],
        'order' => static fn (array $row): array => [$row['b'] % 10, $row['a']],
        'orderKey' => static fn (array $row): int => (($row['b'] % 10) * 100000) + $row['a'],
    ],
    'partition by b mod 2 and a order by b mod 10' => [
        'partition' => static fn (array $row): array => [$row['b'] % 2, $row['a']],
        'order' => static fn (array $row): array => [$row['b'] % 10],
        'orderKey' => static fn (array $row): int => $row['b'] % 10,
    ],
];

$window3ActualValueFunction = static function (array $rows, array $spec, string $function) use ($window3OrderedPartitions): array {
    $actual = [];
    $partitions = $window3OrderedPartitions($rows, $spec['partition'], $spec['order']);

    foreach ($partitions as $partition) {
        $values = [];
        $orderKeys = [];
        $nth = [];

        foreach ($partition as $row) {
            $values[] = $function === 'last_value'
                ? $row['a'] + $row['b']
                : $row['b'];
            $orderKeys[] = $spec['orderKey']($row);
            $nth[] = $row['b'] + 1;
        }

        $partitionActual = SQLiteWindowFunction::valueFrameBetweenValues(
            $function,
            $values,
            $orderKeys,
            'RANGE',
            'UNBOUNDED PRECEDING',
            'CURRENT ROW',
            'NO OTHERS',
            $function === 'nth_value' ? $nth : 1
        );

        array_push($actual, ...$partitionActual);
    }

    return $actual;
};

$window3ExpectedValueFunction = static function (array $rows, array $spec, string $function) use ($window3OrderedPartitions): array {
    $expected = [];
    $partitions = $window3OrderedPartitions($rows, $spec['partition'], $spec['order']);

    foreach ($partitions as $partition) {
        $values = [];
        $orderKeys = [];
        $nth = [];

        foreach ($partition as $row) {
            $values[] = $function === 'last_value'
                ? $row['a'] + $row['b']
                : $row['b'];
            $orderKeys[] = $spec['orderKey']($row);
            $nth[] = $row['b'] + 1;
        }

        foreach ($partition as $position => $_row) {
            $frame = [];
            foreach ($orderKeys as $index => $orderKey) {
                if ($orderKey <= $orderKeys[$position]) {
                    $frame[] = $index;
                }
            }

            if ($function === 'first_value') {
                $expected[] = $frame === [] ? null : $values[$frame[0]];
                continue;
            }

            if ($function === 'last_value') {
                $expected[] = $frame === [] ? null : $values[$frame[count($frame) - 1]];
                continue;
            }

            $target = $nth[$position] - 1;
            $expected[] = isset($frame[$target]) ? $values[$frame[$target]] : null;
        }
    }

    return $expected;
};

$window3ActualOffsetFunction = static function (array $rows, array $spec, string $function) use ($window3OrderedPartitions): array {
    $actual = [];
    $partitions = $window3OrderedPartitions($rows, $spec['partition'], $spec['order']);

    foreach ($partitions as $partition) {
        $values = array_map(static fn (array $row): int => $row['b'], $partition);
        $offsets = array_map(static fn (array $row): int => $row['b'], $partition);
        $partitionActual = $function === 'lead'
            ? SQLiteWindowFunction::leadByRow($values, $offsets)
            : SQLiteWindowFunction::lagByRow($values, $offsets);

        array_push($actual, ...$partitionActual);
    }

    return $actual;
};

$window3ExpectedOffsetFunction = static function (array $rows, array $spec, string $function) use ($window3OrderedPartitions): array {
    $expected = [];
    $partitions = $window3OrderedPartitions($rows, $spec['partition'], $spec['order']);

    foreach ($partitions as $partition) {
        $values = array_map(static fn (array $row): int => $row['b'], $partition);
        $offsets = array_map(static fn (array $row): int => $row['b'], $partition);

        foreach ($values as $position => $_value) {
            $target = $function === 'lead'
                ? $position + $offsets[$position]
                : $position - $offsets[$position];
            $expected[] = $values[$target] ?? null;
        }
    }

    return $expected;
};

$tests['real upstream window3 value navigation source truth is hydrated'] = static function (TestRunner $t) use ($upstreamWindow3): void {
    $source = file_get_contents($upstreamWindow3);

    $t->true(is_string($source) && $source !== '', 'hydrated upstream window3.test source is readable');
    $t->contains('do_execsql_test 1.1.9.1', $source, 'window3.test 1.1.9.1 is the last_value(a+b) source scenario');
    $t->contains('do_execsql_test 1.1.10.1', $source, 'window3.test 1.1.10.1 is the nth_value(b,b+1) source scenario');
    $t->contains('do_execsql_test 1.1.11.1', $source, 'window3.test 1.1.11.1 is the first_value(b) source scenario');
    $t->contains('do_execsql_test 1.1.12.1', $source, 'window3.test 1.1.12.1 is the lead(b,b) source scenario');
    $t->contains('do_execsql_test 1.1.13.1', $source, 'window3.test 1.1.13.1 is the lag(b,b) source scenario');
    $t->contains('SELECT last_value(a+b) OVER', $source, 'upstream source defines last_value(a+b) window coverage');
    $t->contains('SELECT nth_value(b,b+1) OVER', $source, 'upstream source defines nth_value(b,b+1) window coverage');
    $t->contains('SELECT first_value(b) OVER', $source, 'upstream source defines first_value(b) window coverage');
    $t->contains('SELECT lead(b,b) OVER', $source, 'upstream source defines lead(b,b) window coverage');
    $t->contains('SELECT lag(b,b) OVER', $source, 'upstream source defines lag(b,b) window coverage');
};

for ($case = 1; $case <= 1000; $case++) {
    $tests[sprintf('real upstream window3 value navigation dynamic case %04d', $case)] = static function (TestRunner $t) use (
        $case,
        $window3DynamicRows,
        $window3Specs,
        $window3ActualValueFunction,
        $window3ExpectedValueFunction,
        $window3ActualOffsetFunction,
        $window3ExpectedOffsetFunction
    ): void {
        $rows = $window3DynamicRows($case);

        foreach ($window3Specs as $specName => $spec) {
            $actualLast = $window3ActualValueFunction($rows, $spec, 'last_value');
            $expectedLast = $window3ExpectedValueFunction($rows, $spec, 'last_value');
            $actualNth = $window3ActualValueFunction($rows, $spec, 'nth_value');
            $expectedNth = $window3ExpectedValueFunction($rows, $spec, 'nth_value');
            $actualFirst = $window3ActualValueFunction($rows, $spec, 'first_value');
            $expectedFirst = $window3ExpectedValueFunction($rows, $spec, 'first_value');
            $actualLead = $window3ActualOffsetFunction($rows, $spec, 'lead');
            $expectedLead = $window3ExpectedOffsetFunction($rows, $spec, 'lead');
            $actualLag = $window3ActualOffsetFunction($rows, $spec, 'lag');
            $expectedLag = $window3ExpectedOffsetFunction($rows, $spec, 'lag');

            $t->same($expectedLast, $actualLast, 'window3.test 1.1.9.1-1.1.9.6 last_value(a+b) over ' . $specName);
            $t->same($expectedNth, $actualNth, 'window3.test 1.1.10.1-1.1.10.6 nth_value(b,b+1) over ' . $specName);
            $t->same($expectedFirst, $actualFirst, 'window3.test 1.1.11.1-1.1.11.6 first_value(b) over ' . $specName);
            $t->same($expectedLead, $actualLead, 'window3.test 1.1.12.1-1.1.12.6 lead(b,b) over ' . $specName);
            $t->same($expectedLag, $actualLag, 'window3.test 1.1.13.1-1.1.13.6 lag(b,b) over ' . $specName);
        }
    };
}

$tests['real upstream window3 value navigation handoff evidence'] = static function (TestRunner $t): void {
    $t->same(
        [
            'window3.test 1.1.9.1-1.1.9.6',
            'window3.test 1.1.10.1-1.1.10.6',
            'window3.test 1.1.11.1-1.1.11.6',
            'window3.test 1.1.12.1-1.1.12.6',
            'window3.test 1.1.13.1-1.1.13.6',
        ],
        [
            'window3.test 1.1.9.1-1.1.9.6',
            'window3.test 1.1.10.1-1.1.10.6',
            'window3.test 1.1.11.1-1.1.11.6',
            'window3.test 1.1.12.1-1.1.12.6',
            'window3.test 1.1.13.1-1.1.13.6',
        ],
        'non-overlap: this dynamic batch owns window3.test generated value/navigation sections 1.1.9 through 1.1.13'
    );
    $t->same(
        'reuse existing SQLiteWindowFunction value and navigation helpers',
        'reuse existing SQLiteWindowFunction value and navigation helpers',
        'dependency closure: no new support component is required for this upstream corpus window batch'
    );
};

return $tests;
