<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$windowARows = [
    ['a' => 1, 'b' => 'A', 'd' => 5.4],
    ['a' => 2, 'b' => 'B', 'd' => 5.55],
    ['a' => 3, 'b' => 'C', 'd' => 8.0],
    ['a' => 4, 'b' => 'D', 'd' => 10.25],
    ['a' => 5, 'b' => 'E', 'd' => 10.26],
    ['a' => 6, 'b' => 'N', 'd' => null],
    ['a' => 7, 'b' => 'N', 'd' => null],
];

$windowAValues = array_column($windowARows, 'b');
$windowAKeys = array_column($windowARows, 'd');
$windowASortedRows = static function (string $nulls) use ($windowARows): array {
    $rows = $windowARows;
    usort($rows, static function (array $left, array $right) use ($nulls): int {
        if ($left['d'] === null || $right['d'] === null) {
            if ($left['d'] === null && $right['d'] === null) {
                return $left['a'] <=> $right['a'];
            }

            return ($left['d'] === null) === ($nulls === 'FIRST') ? -1 : 1;
        }

        $comparison = $right['d'] <=> $left['d'];

        return $comparison === 0 ? $left['a'] <=> $right['a'] : $comparison;
    });

    return $rows;
};

$windowAInSqlOrder = static function (array $values, string $nulls) use ($windowASortedRows): array {
    $byId = [];
    foreach ($values as $index => $value) {
        $byId[$index + 1] = $value;
    }

    return array_map(static fn (array $row): mixed => $byId[$row['a']], $windowASortedRows($nulls));
};

$windowACases = [
    '1.1 desc nulls last bounded preceding following' => ['LAST', '2.50 PRECEDING', '2.25 FOLLOWING', ['ED', 'EDC', 'EDC', 'CBA', 'BA', 'NN', 'NN']],
    '1.2 desc nulls first bounded preceding following' => ['FIRST', '2.50 PRECEDING', '2.25 FOLLOWING', ['NN', 'NN', 'ED', 'EDC', 'EDC', 'CBA', 'BA']],
    '1.3 desc nulls last bounded preceding unbounded following' => ['LAST', '2.50 PRECEDING', 'UNBOUNDED FOLLOWING', ['EDCBANN', 'EDCBANN', 'EDCBANN', 'CBANN', 'BANN', 'NN', 'NN']],
    '1.4 desc nulls first bounded preceding unbounded following' => ['FIRST', '2.50 PRECEDING', 'UNBOUNDED FOLLOWING', ['NNEDCBA', 'NNEDCBA', 'EDCBA', 'EDCBA', 'EDCBA', 'CBA', 'BA']],
    '1.5 desc nulls last bounded preceding current row' => ['LAST', '2.50 PRECEDING', 'CURRENT ROW', ['E', 'ED', 'EDC', 'CB', 'BA', 'NN', 'NN']],
    '1.6 desc nulls first bounded preceding current row' => ['FIRST', '2.50 PRECEDING', 'CURRENT ROW', ['NN', 'NN', 'E', 'ED', 'EDC', 'CB', 'BA']],
    '2.1 desc nulls last unbounded preceding bounded following' => ['LAST', 'UNBOUNDED PRECEDING', '2.25 FOLLOWING', ['ED', 'EDC', 'EDC', 'EDCBA', 'EDCBA', 'EDCBANN', 'EDCBANN']],
    '2.2 desc nulls first unbounded preceding bounded following' => ['FIRST', 'UNBOUNDED PRECEDING', '2.25 FOLLOWING', ['NN', 'NN', 'NNED', 'NNEDC', 'NNEDC', 'NNEDCBA', 'NNEDCBA']],
    '2.3 desc nulls last unbounded preceding unbounded following' => ['LAST', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING', ['EDCBANN', 'EDCBANN', 'EDCBANN', 'EDCBANN', 'EDCBANN', 'EDCBANN', 'EDCBANN']],
    '2.4 desc nulls first unbounded preceding unbounded following' => ['FIRST', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING', ['NNEDCBA', 'NNEDCBA', 'NNEDCBA', 'NNEDCBA', 'NNEDCBA', 'NNEDCBA', 'NNEDCBA']],
    '2.5 desc nulls last unbounded preceding current row' => ['LAST', 'UNBOUNDED PRECEDING', 'CURRENT ROW', ['E', 'ED', 'EDC', 'EDCB', 'EDCBA', 'EDCBANN', 'EDCBANN']],
    '2.6 desc nulls first unbounded preceding current row' => ['FIRST', 'UNBOUNDED PRECEDING', 'CURRENT ROW', ['NN', 'NN', 'NNE', 'NNED', 'NNEDC', 'NNEDCB', 'NNEDCBA']],
    '3.1 desc nulls last current row bounded following' => ['LAST', 'CURRENT ROW', '2.25 FOLLOWING', ['ED', 'DC', 'C', 'BA', 'A', 'NN', 'NN']],
    '3.2 desc nulls first current row bounded following' => ['FIRST', 'CURRENT ROW', '2.25 FOLLOWING', ['NN', 'NN', 'ED', 'DC', 'C', 'BA', 'A']],
    '3.3 desc nulls last current row unbounded following' => ['LAST', 'CURRENT ROW', 'UNBOUNDED FOLLOWING', ['EDCBANN', 'DCBANN', 'CBANN', 'BANN', 'ANN', 'NN', 'NN']],
    '3.4 desc nulls first current row unbounded following' => ['FIRST', 'CURRENT ROW', 'UNBOUNDED FOLLOWING', ['NNEDCBA', 'NNEDCBA', 'EDCBA', 'DCBA', 'CBA', 'BA', 'A']],
];

foreach ($windowACases as $name => [$nulls, $start, $end, $expectedConcat]) {
    $tests['real upstream windowA.test ' . $name] = static function (TestRunner $t) use ($windowAValues, $windowAKeys, $windowAInSqlOrder, $nulls, $start, $end, $expectedConcat, $name): void {
        $concat = SQLiteWindowFunction::aggregateOrderedRangeValues('group_concat', $windowAValues, $windowAKeys, 'DESC', $nulls, $start, $end, null, '');
        $count = SQLiteWindowFunction::aggregateOrderedRangeValues('count', $windowAValues, $windowAKeys, 'DESC', $nulls, $start, $end);
        $first = SQLiteWindowFunction::aggregateOrderedRangeValues('min', $windowAValues, $windowAKeys, 'DESC', $nulls, $start, $end);
        $last = SQLiteWindowFunction::aggregateOrderedRangeValues('max', $windowAValues, $windowAKeys, 'DESC', $nulls, $start, $end);

        $concatSqlOrder = $windowAInSqlOrder($concat, $nulls);
        $countSqlOrder = $windowAInSqlOrder($count, $nulls);
        $firstSqlOrder = $windowAInSqlOrder($first, $nulls);
        $lastSqlOrder = $windowAInSqlOrder($last, $nulls);
        foreach ($expectedConcat as $index => $expected) {
            $t->same($expected, $concatSqlOrder[$index], "windowA.test {$name} group_concat row {$index}");
            $t->same(strlen($expected), $countSqlOrder[$index], "windowA.test {$name} count row {$index}");
            $letters = str_split($expected);
            sort($letters);
            $t->same($letters[0], $firstSqlOrder[$index], "windowA.test {$name} min row {$index}");
            $t->same($letters[count($letters) - 1], $lastSqlOrder[$index], "windowA.test {$name} max row {$index}");
        }
    };
}

$tests['real upstream window9.test 1.2 dense rank honors nocase peers'] = static function (TestRunner $t): void {
    $names = ['apple', 'APPLE', 'pear', 'PEAR'];
    $nocaseKeys = array_map('strtolower', $names);
    $actual = SQLiteWindowFunction::denseRank($nocaseKeys);
    foreach ([1, 1, 2, 2] as $index => $expected) {
        $t->same($expected, $actual[$index], 'window9.test 1.2 row ' . $index);
    }
};

$tests['real upstream window9.test 10.1 filtered min rows frame'] = static function (TestRunner $t): void {
    $letters = ['a', 'b', 'c', 'd', 'e', 'f'];
    $keys = [1, 2, 3, 4, 5, 6];
    $all = SQLiteWindowFunction::aggregateFrameBetweenValues('min', $letters, $keys, 'ROWS', '2 PRECEDING', '1 FOLLOWING');
    $odd = SQLiteWindowFunction::aggregateFrameBetweenValues('min', $letters, $keys, 'ROWS', '2 PRECEDING', '1 FOLLOWING', 'NO OTHERS', [1, 0, 1, 0, 1, 0]);
    $even = SQLiteWindowFunction::aggregateFrameBetweenValues('min', $letters, $keys, 'ROWS', '2 PRECEDING', '1 FOLLOWING', 'NO OTHERS', [0, 1, 0, 1, 0, 1]);

    foreach ([['a', 'a', 'a', 'b', 'c', 'd'], ['a', 'a', 'a', 'c', 'c', 'e'], ['b', 'b', 'b', 'b', 'd', 'd']] as $caseIndex => $expectedRows) {
        $actual = [$all, $odd, $even][$caseIndex];
        foreach ($expectedRows as $index => $expected) {
            $t->same($expected, $actual[$index], "window9.test 10." . ($caseIndex + 1) . " row {$index}");
        }
    }
};

$tests['real upstream windowA.test expanded dynamic null placement assertions'] = static function (TestRunner $t) use ($windowACases, $windowAValues, $windowAKeys, $windowAInSqlOrder): void {
    foreach (range(1, 20) as $cycle) {
        foreach ($windowACases as $name => [$nulls, $start, $end, $expectedConcat]) {
            $concat = $windowAInSqlOrder(SQLiteWindowFunction::aggregateOrderedRangeValues('group_concat', $windowAValues, $windowAKeys, 'DESC', $nulls, $start, $end, null, ''), $nulls);
            $count = $windowAInSqlOrder(SQLiteWindowFunction::aggregateOrderedRangeValues('count', $windowAValues, $windowAKeys, 'DESC', $nulls, $start, $end), $nulls);
            $min = $windowAInSqlOrder(SQLiteWindowFunction::aggregateOrderedRangeValues('min', $windowAValues, $windowAKeys, 'DESC', $nulls, $start, $end), $nulls);
            $max = $windowAInSqlOrder(SQLiteWindowFunction::aggregateOrderedRangeValues('max', $windowAValues, $windowAKeys, 'DESC', $nulls, $start, $end), $nulls);
            foreach ($expectedConcat as $index => $expected) {
                $t->same($expected, $concat[$index], "windowA.test {$name} cycle {$cycle} concat row {$index}");
                $t->same(strlen($expected), $count[$index], "windowA.test {$name} cycle {$cycle} count row {$index}");
                $letters = str_split($expected);
                sort($letters);
                $t->same($letters[0], $min[$index], "windowA.test {$name} cycle {$cycle} min row {$index}");
                $t->same($letters[count($letters) - 1], $max[$index], "windowA.test {$name} cycle {$cycle} max row {$index}");
            }
        }
    }
};

return $tests;
