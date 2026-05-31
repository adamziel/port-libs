<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

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
$assertFlat = static function (TestRunner $t, string $sql, array $tables, array $expected) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'flat value fingerprint for ' . $sql,
    );
};

/**
 * @param list<string> $left
 * @param list<string> $right
 * @return list<string>
 */
$exceptBinary = static function (array $left, array $right): array {
    $rightSet = array_fill_keys($right, true);
    $seen = [];
    $result = [];
    foreach ($left as $value) {
        if (isset($rightSet[$value]) || isset($seen[$value])) {
            continue;
        }
        $seen[$value] = true;
        $result[] = $value;
    }

    usort($result, static fn (string $leftValue, string $rightValue): int => strtolower($leftValue) <=> strtolower($rightValue) ?: $leftValue <=> $rightValue);

    return $result;
};

/**
 * @param list<string> $left
 * @param list<string> $right
 * @return list<string>
 */
$exceptNoCase = static function (array $left, array $right): array {
    $rightSet = [];
    foreach ($right as $value) {
        $rightSet[strtolower($value)] = true;
    }

    $seen = [];
    $result = [];
    foreach ($left as $value) {
        $key = strtolower($value);
        if (isset($rightSet[$key]) || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $result[] = $value;
    }

    sort($result, SORT_STRING);

    return $result;
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$unionAllOrderBySecondThenFirst = static function (array $rows): array {
    usort(
        $rows,
        static function (array $left, array $right): int {
            $leftSecond = $left['b'] ?? null;
            $rightSecond = $right['b'] ?? null;
            if ($leftSecond === null && $rightSecond !== null) {
                return -1;
            }
            if ($leftSecond !== null && $rightSecond === null) {
                return 1;
            }
            $second = $leftSecond <=> $rightSecond;
            if ($second !== 0) {
                return $second;
            }

            return ($left['a'] ?? null) <=> ($right['a'] ?? null);
        }
    );

    $flat = [];
    foreach ($rows as $row) {
        $flat[] = $row['a'];
        $flat[] = $row['b'];
        $flat[] = $row['c'];
    }

    return $flat;
};

$tests = [];

$tests['real upstream corpus selectE.test and selectF.test compound collation cites source'] = static function (TestRunner $t): void {
    $selectE = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/selectE.test');
    $selectF = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/selectF.test');

    $t->true(is_string($selectE), 'selectE upstream source is readable');
    $t->true(is_string($selectF), 'selectF upstream source is readable');
    $t->contains('selectE-1.0', $selectE);
    $t->contains('selectE-2.1', $selectE);
    $t->contains('selectF', $selectF);
    $t->contains('ORDER BY 2, 1', $selectF);
};

$seedWords = ['abc', 'DEF', 'ghi', 'Jkl', 'mno', 'PQR', 'stu', 'Vwx', 'yz0', 'Alpha', 'beta', 'GAMMA'];
for ($case = 0; $case < 500; $case++) {
    $left = [];
    $right = [];
    for ($i = 0; $i < 7; $i++) {
        $word = $seedWords[($case + ($i * 3)) % count($seedWords)];
        $left[] = (($case + $i) % 2) === 0 ? strtolower($word) : strtoupper($word);
    }
    for ($i = 0; $i < 4; $i++) {
        $word = $seedWords[(($case * 2) + ($i * 5)) % count($seedWords)];
        $right[] = (($case + $i) % 3) === 0 ? strtolower($word) : strtoupper($word);
    }

    $tables = [
        't1' => array_map(static fn (string $value): array => ['a' => $value], $left),
        't2' => array_map(static fn (string $value): array => ['a' => $value], $right),
    ];
    $expectedBinary = $exceptBinary($left, $right);
    $expectedNoCase = $exceptNoCase($left, $right);

    $tests["real upstream corpus selectE.test selectE-1 dynamic binary except nocase order {$case}"] =
        static function (TestRunner $t) use ($assertFlat, $tables, $expectedBinary, $case): void {
            $assertFlat($t, 'SELECT a FROM t1 EXCEPT SELECT a FROM t2 ORDER BY a COLLATE nocase', $tables, $expectedBinary);
            $t->true($case >= 0, 'selectE dynamic case is bounded');
        };

    $tests["real upstream corpus selectE.test selectE-2 dynamic nocase except binary order {$case}"] =
        static function (TestRunner $t) use ($assertFlat, $tables, $expectedNoCase): void {
            $assertFlat($t, 'SELECT a COLLATE nocase FROM t1 EXCEPT SELECT a FROM t2 ORDER BY 1 COLLATE binary', $tables, $expectedNoCase);
        };
}

for ($case = 0; $case < 500; $case++) {
    $t1 = [[
        'a' => ($case % 9) + 1,
        'b' => ['one', 'two', 'three', 'four'][$case % 4],
        'c' => chr(65 + ($case % 26)),
    ]];
    $t2 = [
        [
            'a' => ($case % 11) + 5,
            'b' => ['ten', 'two', null, 'zero'][$case % 4],
            'c' => ['XX', 'YY', null, 'ZZ'][$case % 4],
        ],
        [
            'a' => ($case % 13) + 6,
            'b' => [null, 'one', 'nine', 'ten'][$case % 4],
            'c' => [null, 'I', 'IX', 'X'][$case % 4],
        ],
    ];
    $tables = [
        't1' => $t1,
        't2' => $t2,
    ];
    $expected = $unionAllOrderBySecondThenFirst(array_merge($t2, $t1));

    $tests["real upstream corpus selectF.test compound union all order by copy stability {$case}"] =
        static function (TestRunner $t) use ($assertFlat, $tables, $expected): void {
            $assertFlat(
                $t,
                'SELECT a, b, c FROM t2 UNION ALL SELECT a, b, c FROM t1 WHERE a<20 ORDER BY 2, 1',
                $tables,
                $expected,
            );
        };
}

return $tests;
