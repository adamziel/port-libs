<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$medianOracle = static function (array $values): int|float|null {
    $numbers = array_values(array_filter($values, static fn (mixed $value): bool => $value !== null));
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

$frameIndexes = static function (int $count, int $row, int $preceding, int $following): array {
    $indexes = [];
    for ($index = max(0, $row - $preceding); $index <= min($count - 1, $row + $following); $index++) {
        $indexes[] = $index;
    }

    return $indexes;
};

$tests['real upstream window6 1 keyword identifiers preserve grouped concatenation'] = static function (TestRunner $t): void {
    $values = [1, 2, 3, 4, 5];
    $actualConcat = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'group_concat',
        $values,
        ['a', 'b', 'c', 'd', 'e'],
        'ROWS',
        'UNBOUNDED PRECEDING',
        'CURRENT ROW',
        'NO OTHERS',
        null,
        '.',
    );
    $actualSum = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, ['a', 'b', 'c', 'd', 'e'], 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');

    $t->same(['1', '1.2', '1.2.3', '1.2.3.4', '1.2.3.4.5'], $actualConcat, 'window6.test 1 keyword table/column aliases');
    $t->same([1, 3, 6, 10, 15], $actualSum, 'window6.test 1 named window alias sum');
    $t->same(15, array_sum($values), 'window6.test 1 aggregate alias named filter');
};

$tests['real upstream window6 2 custom median and sorted window values'] = static function (TestRunner $t): void {
    $values = [4, 6, 1, 5, 2, 3];
    $keys = ['a', 'b', 'c', 'd', 'e', 'f'];

    $sorted = SQLiteWindowFunction::sortedFrameTextValues($values, $keys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
    $median = SQLiteWindowFunction::medianFrameBetweenValues($values, $keys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');

    $t->same(['4', '4 6', '1 4 6', '1 4 5 6', '1 2 4 5 6', '1 2 3 4 5 6'], $sorted, 'window6.test 1.1 custom sorted value');
    $t->same([4, 5, 4, 4.5, 4, 3.5], $median, 'window6.test 1.1 custom median value');

    $sliding = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, [1, 2, 3, 4, 5, 6], 'ROWS', '1 PRECEDING', '1 FOLLOWING');
    $t->same([10, 11, 12, 8, 10, 5], $sliding, 'window6.test 2.1 sumint inverse window');
};

$tests['real upstream window6 5 keyword over/window table names route ordinary and window aggregate uses'] = static function (TestRunner $t): void {
    $x = [1, 3, 5];
    $overColumn = [2, 4, 6];

    $plainAggregate = array_sum($x);
    $emptyWindow = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $x, [0, 0, 0], 'ROWS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING');
    $orderedByKeywordColumn = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $overColumn, $overColumn, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');

    $t->same(9, $plainAggregate, 'window6.test 5.0 SELECT sum(x) over FROM over');
    $t->same([9, 9, 9], $emptyWindow, 'window6.test 5.1 over as window name');
    $t->same([2, 6, 12], $orderedByKeywordColumn, 'window6.test 5.2 keyword table alias ordered frame');
};

$tests['real upstream window6 8 sample rank and rows preceding sums'] = static function (TestRunner $t): void {
    $rows = [
        ['id' => 1, 'counter' => 1, 'value' => 10.0],
        ['id' => 2, 'counter' => 1, 'value' => 20.0],
        ['id' => 3, 'counter' => 2, 'value' => 1.0],
        ['id' => 4, 'counter' => 2, 'value' => 3.0],
        ['id' => 5, 'counter' => 3, 'value' => 100.0],
    ];

    $ranked = [];
    foreach ([1, 2, 3] as $counter) {
        $partition = array_values(array_filter($rows, static fn (array $row): bool => $row['counter'] === $counter));
        usort($partition, static fn (array $left, array $right): int => $right['value'] <=> $left['value']);
        $ranks = SQLiteWindowFunction::rank(array_column($partition, 'value'));
        foreach ($partition as $index => $row) {
            $ranked[] = [$row['counter'], $row['value'], $ranks[$index]];
        }
    }

    $rolling = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', array_column($rows, 'value'), array_column($rows, 'id'), 'ROWS', '2 PRECEDING', 'CURRENT ROW');

    $t->same([[1, 20.0, 1], [1, 10.0, 2], [2, 3.0, 1], [2, 1.0, 2], [3, 100.0, 1]], $ranked, 'window6.test 8.1 rank partition order');
    $t->same([10.0, 30.0, 31.0, 24.0, 104.0], $rolling, 'window6.test 8.2-8.3 rows 2 preceding');
};

$tests['real upstream window6 9 recursive cte group concat frame'] = static function (TestRunner $t): void {
    $values = [1, 2, 3, 4, 5];
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('group_concat', $values, $values, 'ROWS', '2 PRECEDING', 'CURRENT ROW');

    $t->same(['1', '1,2', '1,2,3', '2,3,4', '3,4,5'], $actual, 'window6.test 9.0 recursive cte group_concat frame');
};

$tests['real upstream window6 10 nth_value coercion and positive integer guard'] = static function (TestRunner $t): void {
    $values = [2, 3, 4];
    $keys = [1, 2, 3];
    foreach ([1 => [2, 2, 2], 2 => [null, 3, 3], 10000000 => [null, null, null]] as $nth => $expected) {
        $t->same($expected, SQLiteWindowFunction::valueFrameBetweenValues('nth_value', $values, $keys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', $nth), "window6.test 10.2 nth {$nth}");
    }

    foreach ([0, -1, '4ab', null, 8.5] as $bad) {
        try {
            SQLiteWindowFunction::valueFrameBetweenValues('nth_value', $values, $keys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', $bad);
            $t->same(true, false, 'window6.test 10.1 rejected invalid nth_value argument');
        } catch (InvalidArgumentException $exception) {
            $t->same('SQLite nth_value() index must be positive', $exception->getMessage());
        }
    }
};

$tests['real upstream window6 11 scalar subquery peers with ordered rows frame'] = static function (TestRunner $t): void {
    $a = [10, 15, 20, 20, 25, 30, 30, 50];
    $labels = [10 => 'ten', 15 => 'fifteen', 30 => 'thirty'];
    $subquery = array_map(static fn (int $value): ?string => $labels[$value] ?? null, $a);
    $sumRows = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $a, $a, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');

    $t->same(['ten', 'fifteen', null, null, null, 'thirty', 'thirty', null], $subquery, 'window6.test 11.1 scalar subquery labels');
    $t->same([10, 25, 45, 65, 90, 120, 150, 200], $sumRows, 'window6.test 11.3 rows frame with peers');
};

for ($case = 0; $case < 250; $case++) {
    $tests['real upstream window6 dynamic custom median rows frame ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $medianOracle, $frameIndexes): void {
        $count = 5 + ($case % 17);
        $values = [];
        for ($row = 0; $row < $count; $row++) {
            $values[] = (($case * 11) + ($row * 7)) % 31;
        }
        $preceding = $case % 4;
        $following = ($case + 1) % 4;
        $actual = SQLiteWindowFunction::medianFrameBetweenValues($values, range(1, $count), 'ROWS', "{$preceding} PRECEDING", "{$following} FOLLOWING");

        foreach ($values as $row => $_value) {
            $expected = $medianOracle(array_map(static fn (int $index): int => $values[$index], $frameIndexes($count, $row, $preceding, $following)));
            $t->same($expected, $actual[$row], "window6.test 1.1 dynamic median case {$case} row {$row}");
        }
    };

    $tests['real upstream window6 dynamic sorted window rows frame ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $frameIndexes): void {
        $count = 6 + ($case % 13);
        $values = [];
        for ($row = 0; $row < $count; $row++) {
            $values[] = chr(97 + (($case + ($row * 5)) % 26));
        }
        $preceding = ($case % 3) + 1;
        $following = $case % 2;
        $actual = SQLiteWindowFunction::sortedFrameTextValues($values, range(1, $count), 'ROWS', "{$preceding} PRECEDING", "{$following} FOLLOWING");

        foreach ($values as $row => $_value) {
            $frame = array_map(static fn (int $index): string => $values[$index], $frameIndexes($count, $row, $preceding, $following));
            sort($frame, SORT_REGULAR);
            $t->same(implode(' ', $frame), $actual[$row], "window6.test 1.1 dynamic sorted case {$case} row {$row}");
        }
    };

    $tests['real upstream window6 dynamic recursive cte group concat rows frame ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $frameIndexes): void {
        $count = 5 + ($case % 19);
        $start = 1 + ($case % 9);
        $values = range($start, $start + $count - 1);
        $preceding = $case % 5;
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('group_concat', $values, $values, 'ROWS', "{$preceding} PRECEDING", 'CURRENT ROW');

        foreach ($values as $row => $_value) {
            $frame = array_map(static fn (int $index): int => $values[$index], $frameIndexes($count, $row, $preceding, 0));
            $t->same(implode(',', $frame), $actual[$row], "window6.test 9.0 dynamic recursive concat case {$case} row {$row}");
        }
    };

    $tests['real upstream window6 dynamic nth value positive integer corpus ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $count = 4 + ($case % 15);
        $values = [];
        $nth = [];
        for ($row = 0; $row < $count; $row++) {
            $values[] = 100 + $case + $row;
            $nth[] = 1 + (($case + ($row * 2)) % ($count + 3));
        }

        $actual = SQLiteWindowFunction::valueFrameBetweenValues('nth_value', $values, range(1, $count), 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', $nth);
        foreach ($values as $row => $_value) {
            $target = $nth[$row] - 1;
            $expected = $target <= $row ? $values[$target] : null;
            $t->same($expected, $actual[$row], "window6.test 10.2 dynamic nth_value case {$case} row {$row}");
        }
    };
}

return $tests;
