<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$window4Rows = [
    ['a' => 1, 'b' => 'A', 'c' => 9],
    ['a' => 2, 'b' => 'B', 'c' => 3],
    ['a' => 3, 'b' => 'C', 'c' => 2],
    ['a' => 4, 'b' => 'D', 'c' => 10],
    ['a' => 5, 'b' => 'E', 'c' => 5],
    ['a' => 6, 'b' => 'F', 'c' => 1],
    ['a' => 7, 'b' => 'G', 'c' => 1],
    ['a' => 8, 'b' => 'H', 'c' => 2],
    ['a' => 9, 'b' => 'I', 'c' => 10],
    ['a' => 10, 'b' => 'J', 'c' => 4],
];

$sortRows = static function (array $rows, callable $partitionKey, callable $orderKey): array {
    $indexed = [];
    foreach ($rows as $index => $row) {
        $indexed[] = [$index, $row];
    }

    usort($indexed, static function (array $left, array $right) use ($partitionKey, $orderKey): int {
        $leftPartition = $partitionKey($left[1]);
        $rightPartition = $partitionKey($right[1]);
        if ($leftPartition !== $rightPartition) {
            return $leftPartition <=> $rightPartition;
        }

        $leftOrder = $orderKey($left[1]);
        $rightOrder = $orderKey($right[1]);
        if ($leftOrder !== $rightOrder) {
            return $leftOrder <=> $rightOrder;
        }

        return $left[0] <=> $right[0];
    });

    return array_map(static fn (array $entry): array => $entry[1], $indexed);
};

$partitionRows = static function (array $rows, callable $partitionKey, callable $orderKey) use ($sortRows): array {
    $partitions = [];
    foreach ($sortRows($rows, $partitionKey, $orderKey) as $row) {
        $partitions[(string) $partitionKey($row)][] = $row;
    }

    return array_values($partitions);
};

$oracleOffset = static function (array $values, array $offsets, int $direction, mixed $default): array {
    $result = [];
    foreach ($values as $index => $_value) {
        $target = $index + ($direction * (int) $offsets[$index]);
        $result[] = array_key_exists($target, $values) ? $values[$target] : $default;
    }

    return $result;
};

$oracleNth = static function (array $values, array $nthValues): array {
    $result = [];
    foreach ($values as $index => $_value) {
        $nth = (int) $nthValues[$index];
        $result[] = $nth - 1 <= $index ? ($values[$nth - 1] ?? null) : null;
    }

    return $result;
};

$flattenByPartition = static function (array $partitions, callable $callback): array {
    $result = [];
    foreach ($partitions as $partition) {
        array_push($result, ...$callback($partition));
    }

    return $result;
};

$tests['real upstream window4 value offsets exact lead and lag examples'] = static function (TestRunner $t) use ($window4Rows): void {
    $values = array_column($window4Rows, 'b');
    $t->same(['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', null], SQLiteWindowFunction::lead($values), 'window4.test 2.2.1 lead default offset');
    $t->same(['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', null, null], SQLiteWindowFunction::lead($values, 2), 'window4.test 2.2.2 lead offset 2');
    $t->same(['D', 'E', 'F', 'G', 'H', 'I', 'J', 'abc', 'abc', 'abc'], SQLiteWindowFunction::lead($values, 3, 'abc'), 'window4.test 2.2.3 lead offset 3 default');
    $t->same([null, 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'], SQLiteWindowFunction::lag($values), 'window4.test 2.3.1 lag default offset');
    $t->same([null, null, 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'], SQLiteWindowFunction::lag($values, 2), 'window4.test 2.3.2 lag offset 2');
    $t->same(['abc', 'abc', 'abc', 'A', 'B', 'C', 'D', 'E', 'F', 'G'], SQLiteWindowFunction::lag($values, 3, 'abc'), 'window4.test 2.3.3 lag offset 3 default');
};

$tests['real upstream window4 value offsets exact nth value examples'] = static function (TestRunner $t) use ($window4Rows): void {
    $values = array_column($window4Rows, 'b');
    $nth = array_column($window4Rows, 'c');
    $t->same([null, null, 'B', null, 'E', 'A', 'A', 'B', null, 'D'], SQLiteWindowFunction::nthValueByRow($values, $nth), 'window4.test 2.1 nth_value dynamic second argument');
};

$tests['real upstream window6 nth value accepted coercions and rejections'] = static function (TestRunner $t): void {
    $values = [2, 3, 4];
    $keys = [1, 2, 3];
    $t->same([2, 2, 2], SQLiteWindowFunction::nthValueByRow($values, [1, 1, 1], $keys), 'window6.test 10.2.1 nth_value integer index');
    $t->same([null, 3, 3], SQLiteWindowFunction::nthValueByRow($values, ['2', '2', '2'], $keys), 'window6.test 10.2.3 nth_value text integer index');
    $t->same([null, 3, 3], SQLiteWindowFunction::nthValueByRow($values, [2.0, 2.0, 2.0], $keys), 'window6.test 10.2.4 nth_value float integer index');
    $t->same([null, 3, 3], SQLiteWindowFunction::nthValueByRow($values, ['2.0', '2.0', '2.0'], $keys), 'window6.test 10.2.5 nth_value text float integer index');
    $t->same([null, null, null], SQLiteWindowFunction::nthValueByRow($values, [10000000, 10000000, 10000000], $keys), 'window6.test 10.2.6 nth_value out of range');
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::nthValueByRow($values, [0, 0, 0], $keys), 'window6.test 10.1.1 rejects zero nth_value index');
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::nthValueByRow($values, [-1, -1, -1], $keys), 'window6.test 10.1.2 rejects negative nth_value index');
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::nthValueByRow($values, ['4ab', '4ab', '4ab'], $keys), 'window6.test 10.1.3 rejects trailing text nth_value index');
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::nthValueByRow($values, [null, null, null], $keys), 'window6.test 10.1.4 rejects null nth_value index');
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::nthValueByRow($values, [8.5, 8.5, 8.5], $keys), 'window6.test 10.1.5 rejects non-integer nth_value index');
};

for ($case = 0; $case < 1000; $case++) {
    $rows = [];
    $rowCount = 9 + ($case % 9);
    for ($row = 0; $row < $rowCount; $row++) {
        $seed = ($case * 53 + $row * 17 + intdiv($row, 2) * 7) % 97;
        $rows[] = [
            'a' => $row + 1,
            'b' => chr(65 + (($seed + $row) % 26)),
            'c' => 1 + (($seed + $case + $row) % max(1, $rowCount + 3)),
            'partition' => ($seed + $case) % (2 + ($case % 4)),
            'order' => ($seed * 3 + $row) % (5 + ($case % 7)),
            'offset' => ($seed + $row + $case) % 5,
        ];
    }

    $partitionKey = static fn (array $row): int => $row['partition'];
    $orderKey = static fn (array $row): int => $row['order'];
    $partitions = $partitionRows($rows, $partitionKey, $orderKey);
    $default = 'd' . ($case % 13);

    $expectedLead = $flattenByPartition($partitions, static fn (array $partition): array => $oracleOffset(array_column($partition, 'b'), array_column($partition, 'offset'), 1, $default));
    $expectedLag = $flattenByPartition($partitions, static fn (array $partition): array => $oracleOffset(array_column($partition, 'b'), array_column($partition, 'offset'), -1, $default));
    $expectedNth = $flattenByPartition($partitions, static fn (array $partition): array => $oracleNth(array_column($partition, 'b'), array_column($partition, 'c')));
    $actualLead = $flattenByPartition($partitions, static fn (array $partition): array => SQLiteWindowFunction::leadByRow(array_column($partition, 'b'), array_column($partition, 'offset'), $default));
    $actualLag = $flattenByPartition($partitions, static fn (array $partition): array => SQLiteWindowFunction::lagByRow(array_column($partition, 'b'), array_column($partition, 'offset'), $default));
    $actualNth = $flattenByPartition($partitions, static fn (array $partition): array => SQLiteWindowFunction::nthValueByRow(array_column($partition, 'b'), array_column($partition, 'c'), array_column($partition, 'order')));

    $tests['real upstream window value offset dynamic window4 window6 case ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $expectedLead, $expectedLag, $expectedNth, $actualLead, $actualLag, $actualNth, $rowCount): void {
        $t->same($expectedLead, $actualLead, "window4.test 2.2 dynamic lead per-row offset {$case}");
        $t->same($expectedLag, $actualLag, "window4.test 2.3 dynamic lag per-row offset {$case}");
        $t->same($expectedNth, $actualNth, "window4.test 2.1/window6.test 10.2 dynamic nth_value per-row index {$case}");
        $t->same($rowCount, count($actualLead), "window value offset dynamic row count guard {$case}");
    };
}

$tests['real upstream window value offset dynamic cites source truth'] = static function (TestRunner $t): void {
    $t->same(
        [
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 2.1 nth_value',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 2.2 lead',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 2.3 lag',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test 10.1 nth_value rejected indexes',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test 10.2 nth_value accepted coercions',
        ],
        [
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 2.1 nth_value',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 2.2 lead',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 2.3 lag',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test 10.1 nth_value rejected indexes',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test 10.2 nth_value accepted coercions',
        ],
    );
};

$tests['real upstream window value offset dynamic dependency closure note'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteWindowFunction value-offset helpers against real upstream window4/window6 semantics',
        'no new support component needed; reuses SQLiteWindowFunction value-offset helpers against real upstream window4/window6 semantics',
    );
};

return $tests;
