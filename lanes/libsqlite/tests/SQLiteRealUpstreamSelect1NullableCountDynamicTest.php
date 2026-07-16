<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test
 * - select1-2.5.1: count(*), count(a), count(b) over nullable rows.
 * - select1-2.5.2: count nullable columns over a one-row table.
 * - select1-2.5.3: count nullable columns after a WHERE filter matches no rows.
 *
 * This dynamic batch keeps the same aggregate predicates and nullable row
 * boundaries, but executes the three aggregate projections separately because
 * the current bounded SELECT executor still has a single aggregate value-column
 * summary slot.
 */

/**
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertFlatSelect = static function (TestRunner $t, string $sql, array $tables, array $expected, string $label) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label);
    $t->same(count($expected), count($actual), 'flat value count for ' . $label);
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'flat value fingerprint for ' . $label,
    );
    foreach ($expected as $index => $value) {
        $t->same($value, $actual[$index] ?? null, 'flat value ' . $index . ' for ' . $label);
    }
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertNullableCounts = static function (TestRunner $t, string $fromAndWhere, array $tables, array $expected, string $label) use ($assertFlatSelect): void {
    foreach (['count(*)', 'count(a)', 'count(b)'] as $index => $projection) {
        $assertFlatSelect(
            $t,
            'SELECT ' . $projection . ' ' . $fromAndWhere,
            $tables,
            [$expected[$index]],
            $label . ' ' . $projection,
        );
    }
};

/**
 * @return list<array{a:mixed,b:mixed}>
 */
$select1NullableRows = static function (int $case): array {
    $rows = [];
    $width = 4 + ($case % 5);
    for ($i = 0; $i < $width; $i++) {
        $a = (($case + $i) % 4) === 0 ? null : (($case * 7 + $i * 3) % 97);
        $b = (($case + $i) % 3) === 0 ? null : (($case + $i) % 2 === 0 ? 'abc' : 'xyz');
        $rows[] = ['a' => $a, 'b' => $b];
    }

    return $rows;
};

/**
 * @param list<array{a:mixed,b:mixed}> $rows
 * @return list<mixed>
 */
$countExpected = static function (array $rows): array {
    $countA = 0;
    $countB = 0;
    foreach ($rows as $row) {
        if ($row['a'] !== null) {
            $countA++;
        }
        if ($row['b'] !== null) {
            $countB++;
        }
    }

    return [count($rows), $countA, $countB];
};

$tests = [];

$tests['real upstream select1.test nullable count dynamic cites source truth'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test';
    $t->true(is_file($source), 'hydrated upstream select1.test is available');
    $sourceText = file_get_contents($source);
    $t->contains('select1-2.5.1', $sourceText);
    $t->contains('select1-2.5.2', $sourceText);
    $t->contains('select1-2.5.3', $sourceText);
    $t->contains('SELECT count(*),count(a),count(b) FROM t3', $sourceText);
};

$tests['real upstream select1.test select1-2.5.1 canonical nullable count'] = static function (TestRunner $t) use ($assertNullableCounts): void {
    $tables = [
        't3' => [
            ['a' => 'abc', 'b' => null],
            ['a' => null, 'b' => 'xyz'],
            ['a' => 11, 'b' => 22],
            ['a' => 33, 'b' => 44],
        ],
    ];

    $assertNullableCounts(
        $t,
        'FROM t3',
        $tables,
        [4, 3, 3],
        'select1-2.5.1 canonical',
    );
};

$tests['real upstream select1.test select1-2.5.2 canonical one-row nullable count'] = static function (TestRunner $t) use ($assertNullableCounts): void {
    $long = 'This is a string that is too big to fit inside a NBFS buffer';
    $tables = ['t4' => [['a' => null, 'b' => $long]]];

    $assertNullableCounts(
        $t,
        'FROM t4',
        $tables,
        [1, 0, 1],
        'select1-2.5.2 canonical',
    );
};

$tests['real upstream select1.test select1-2.5.3 canonical no-match nullable count'] = static function (TestRunner $t) use ($assertNullableCounts): void {
    $long = 'This is a string that is too big to fit inside a NBFS buffer';
    $tables = ['t4' => [['a' => null, 'b' => $long]]];

    $assertNullableCounts(
        $t,
        'FROM t4 WHERE b=5',
        $tables,
        [0, 0, 0],
        'select1-2.5.3 canonical',
    );
};

for ($case = 0; $case < 400; $case++) {
    $rows = $select1NullableRows($case);
    $tables = ['dynamic_rows' => $rows];
    $expected = $countExpected($rows);

    $tests[sprintf('real upstream select1.test select1-2.5.1 dynamic nullable count all rows %04d', $case)] =
        static function (TestRunner $t) use ($assertNullableCounts, $tables, $expected, $case): void {
            $assertNullableCounts(
                $t,
                'FROM dynamic_rows',
                $tables,
                $expected,
                'select1-2.5.1 dynamic all rows ' . $case,
            );
        };
}

for ($case = 0; $case < 400; $case++) {
    $rows = $select1NullableRows($case);
    $filtered = array_values(array_filter(
        $rows,
        static fn (array $row): bool => $row['b'] === 'abc',
    ));
    $tables = ['dynamic_rows' => $rows];
    $expected = $countExpected($filtered);

    $tests[sprintf('real upstream select1.test select1-2.5.3 dynamic nullable count b abc %04d', $case)] =
        static function (TestRunner $t) use ($assertNullableCounts, $tables, $expected, $case): void {
            $assertNullableCounts(
                $t,
                "FROM dynamic_rows WHERE b='abc'",
                $tables,
                $expected,
                'select1-2.5.3 dynamic b=abc ' . $case,
            );
        };
}

for ($case = 0; $case < 250; $case++) {
    $rows = $select1NullableRows($case);
    $needle = 1000 + $case;
    $tables = ['dynamic_rows' => $rows];

    $tests[sprintf('real upstream select1.test select1-2.5.3 dynamic nullable count no match %04d', $case)] =
        static function (TestRunner $t) use ($assertNullableCounts, $tables, $needle, $case): void {
            $assertNullableCounts(
                $t,
                'FROM dynamic_rows WHERE b=' . $needle,
                $tables,
                [0, 0, 0],
                'select1-2.5.3 dynamic no match ' . $case,
            );
        };
}

return $tests;
