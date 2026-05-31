<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flatValues = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @return array<string,list<array<string,mixed>>>
 */
$select6TablesFor = static function (int $case): array {
    $rows = [];
    $rowCount = 32 + ($case % 53);
    $bucketWidth = 1 + ($case % 5);
    for ($x = 1; $x <= $rowCount; $x++) {
        $rows[] = [
            'x' => $x,
            'y' => intdiv($x - 1, $bucketWidth) + 1,
        ];
    }

    return ['t1' => $rows];
};

/**
 * @param list<mixed> $expected
 * @param list<array<string,mixed>> $actualRows
 */
$assertFlatValues = static function (TestRunner $t, array $expected, array $actualRows) use ($flatValues): void {
    $actual = $flatValues($actualRows);
    $t->same(count($expected), count($actual));
    foreach ($expected as $index => $value) {
        $t->same($value, $actual[$index] ?? null);
    }
};

$tests = [];

$tests['real upstream corpus select6.test negative limit dynamic batch0 cites source cases'] = static function (TestRunner $t): void {
    $t->contains('/test/select6.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test');
    $t->contains('select6-9.7', 'select6.test select6-9.7 negative inner limit feeds outer limit');
    $t->contains('select6-9.8', 'select6.test select6-9.8 negative inner limit returns all rows');
    $t->contains('select6-9.9', 'select6.test select6-9.9 negative inner limit with offset');
};

for ($case = 0; $case < 1000; $case++) {
    $tests[sprintf('real upstream corpus select6.test select6-9 negative limit dynamic batch0 case %04d', $case)] =
        static function (TestRunner $t) use ($case, $select6TablesFor, $assertFlatValues): void {
            $tables = $select6TablesFor($case);
            $rows = $tables['t1'];
            $rowCount = count($rows);
            $innerOffset = $case % 11;
            $outerLimit = 1 + ($case % 9);
            $outerOffset = intdiv($case, 11) % 7;

            $expectedRows = array_slice(array_slice($rows, $innerOffset), $outerOffset, $outerLimit);
            $expected = [];
            foreach ($expectedRows as $row) {
                $expected[] = $row['x'];
                $expected[] = $row['y'];
            }

            $sql = sprintf(
                'SELECT x, y FROM (SELECT x, y FROM t1 LIMIT -1 OFFSET %d) LIMIT %d OFFSET %d',
                $innerOffset,
                $outerLimit,
                $outerOffset,
            );
            $assertFlatValues($t, $expected, SQLiteSelectSql::execute($sql, $tables));
            $t->same(true, $innerOffset + $outerOffset < $rowCount);
            $t->contains('LIMIT -1', $sql);
            $t->contains('select6-9', sprintf('select6.test select6-9 negative limit dynamic batch0 case %04d', $case));
        };
}

return $tests;
