<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$selectCoreBatch3Flat = static function (array $rows): array {
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
$selectCoreBatch3Assert = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $upstream
) use ($selectCoreBatch3Flat): void {
    $actual = $selectCoreBatch3Flat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat result width');
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'result fingerprint',
    );
    $t->contains('.test', $upstream);
};

$selectCoreBatch3Tables = [
    't14' => [
        ['a' => 1, 'b' => 2, 'c' => 3],
        ['a' => 4, 'b' => 5, 'c' => 6],
    ],
    't61' => [['a' => 111]],
    't62' => [['b' => 222]],
];

$tests['real upstream corpus select core dynamic batch3 cites upstream sources'] = static function (TestRunner $t): void {
    foreach (['select4.test', 'select9.test'] as $file) {
        $path = '/home/claude/port-libs/.upstream-cache/libsqlite/test/' . $file;
        $t->true(is_file($path), "hydrated upstream {$file} exists");
        $contents = file_get_contents($path);
        $t->contains($file === 'select4.test' ? 'select4-14.1' : 'select9-6.1', $contents);
    }
};

for ($case = 0; $case < 250; $case++) {
    $includeFirst = $case % 2 === 0;
    $includeSecond = $case % 3 !== 0;
    $extraA = 7 + ($case % 11);
    $extraB = 8 + ($case % 13);
    $extraC = 9 + ($case % 17);
    $values = [];
    if ($includeFirst) {
        $values[] = '(1,2,3)';
    }
    if ($includeSecond) {
        $values[] = '(4,5,6)';
    }
    $values[] = "({$extraA},{$extraB},{$extraC})";

    $expectedRows = [];
    foreach ($selectCoreBatch3Tables['t14'] as $row) {
        foreach ($values as $tuple) {
            if ($tuple === "({$row['a']},{$row['b']},{$row['c']})") {
                $expectedRows[] = [$row['a'], $row['b'], $row['c']];
                break;
            }
        }
    }
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, ...$row);
    }

    $tests["real upstream corpus select core dynamic batch3 select4.test select4-14 intersect values {$case}"] = static function (TestRunner $t) use ($selectCoreBatch3Assert, $selectCoreBatch3Tables, $values, $expected): void {
        $sql = 'SELECT * FROM t14 INTERSECT VALUES' . implode(',', $values);
        $selectCoreBatch3Assert($t, $sql, $selectCoreBatch3Tables, $expected, 'select4.test select4-14.1/14.2 compound SELECT INTERSECT VALUES arms');
    };
}

for ($case = 0; $case < 250; $case++) {
    $dropFirst = $case % 2 === 0;
    $dropSecond = $case % 5 !== 0;
    $values = [];
    if ($dropFirst) {
        $values[] = '(1,2,3)';
    }
    if ($dropSecond) {
        $values[] = '(4,5,6)';
    }
    $values[] = "('x{$case}','y{$case}','z{$case}')";

    $expectedRows = [];
    foreach ($selectCoreBatch3Tables['t14'] as $row) {
        $remove = false;
        foreach ($values as $tuple) {
            if ($tuple === "({$row['a']},{$row['b']},{$row['c']})") {
                $remove = true;
                break;
            }
        }
        if (!$remove) {
            $expectedRows[] = [$row['a'], $row['b'], $row['c']];
        }
    }
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, ...$row);
    }

    $tests["real upstream corpus select core dynamic batch3 select4.test select4-14 except values {$case}"] = static function (TestRunner $t) use ($selectCoreBatch3Assert, $selectCoreBatch3Tables, $values, $expected): void {
        $sql = 'SELECT * FROM t14 EXCEPT VALUES' . implode(',', $values);
        $selectCoreBatch3Assert($t, $sql, $selectCoreBatch3Tables, $expected, 'select4.test select4-14.5/14.6/14.8 compound SELECT EXCEPT VALUES arms');
    };
}

for ($case = 0; $case < 250; $case++) {
    $limit = 1 + ($case % 6);
    $extraA = 2 + ($case % 9);
    $extraB = 3 + ($case % 7);
    $extraC = 1 + ($case % 5);
    $values = [
        "({$extraA},{$extraB},{$extraC})",
        '(1,2,3)',
        '(4,5,6)',
    ];

    $rows = [
        [1, 2, 3],
        [4, 5, 6],
        [$extraA, $extraB, $extraC],
        [1, 2, 3],
        [4, 5, 6],
    ];
    usort($rows, static fn (array $left, array $right): int => ($left[0] <=> $right[0]) ?: ($left[1] <=> $right[1]) ?: ($left[2] <=> $right[2]));
    $unique = [];
    foreach ($rows as $row) {
        $unique[implode("\0", $row)] = $row;
    }
    $expected = [];
    foreach (array_slice(array_values($unique), 0, $limit) as $row) {
        array_push($expected, ...$row);
    }

    $tests["real upstream corpus select core dynamic batch3 select4.test select4-14 union values ordered limit {$case}"] = static function (TestRunner $t) use ($selectCoreBatch3Assert, $selectCoreBatch3Tables, $values, $limit, $expected): void {
        $sql = 'SELECT * FROM t14 UNION VALUES' . implode(',', $values) . " UNION SELECT * FROM t14 ORDER BY 1, 2, 3 LIMIT {$limit}";
        $selectCoreBatch3Assert($t, $sql, $selectCoreBatch3Tables, $expected, 'select4.test select4-14.3/14.4/14.16/14.17 compound SELECT UNION VALUES with final ORDER BY/LIMIT');
    };
}

for ($case = 0; $case < 250; $case++) {
    $leftZero = $case % 2 === 0;
    $rightZero = $case % 5 === 0;
    $operator = $case % 3 === 0 ? 'UNION ALL' : 'UNION';
    $expected = [];
    if (!$leftZero) {
        $expected[] = 111;
    }
    if (!$rightZero && ($operator === 'UNION ALL' || !in_array(222, $expected, true))) {
        $expected[] = 222;
    }
    sort($expected);

    $tests["real upstream corpus select core dynamic batch3 select9.test select9-6 where-zero compound {$case}"] = static function (TestRunner $t) use ($selectCoreBatch3Assert, $selectCoreBatch3Tables, $leftZero, $rightZero, $operator, $expected): void {
        $leftWhere = $leftZero ? ' WHERE 0' : '';
        $rightWhere = $rightZero ? ' WHERE 0' : '';
        $sql = "SELECT a FROM t61{$leftWhere} {$operator} SELECT b FROM t62{$rightWhere}";
        $selectCoreBatch3Assert($t, $sql, $selectCoreBatch3Tables, $expected, 'select9.test select9-6.1/6.2/6.3 WHERE 0 compound SELECT arms');
    };
}

return $tests;
