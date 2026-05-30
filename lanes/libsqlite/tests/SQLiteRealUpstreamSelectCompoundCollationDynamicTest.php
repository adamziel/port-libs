<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @param list<string> $left
 * @param list<string> $right
 * @return list<string>
 */
$exceptValues = static function (array $left, array $right, string $comparisonCollation, string $orderCollation): array {
    $key = static function (string $value) use ($comparisonCollation): string {
        return strtoupper($comparisonCollation) === 'NOCASE' ? strtolower($value) : $value;
    };
    $orderKey = static function (string $value) use ($orderCollation): string {
        return strtoupper($orderCollation) === 'NOCASE' ? strtolower($value) : $value;
    };

    $rightKeys = [];
    foreach ($right as $value) {
        $rightKeys[$key($value)] = true;
    }

    $seen = [];
    $result = [];
    foreach ($left as $value) {
        $valueKey = $key($value);
        if (isset($rightKeys[$valueKey]) || isset($seen[$valueKey])) {
            continue;
        }
        $seen[$valueKey] = true;
        $result[] = $value;
    }

    usort($result, static function (string $leftValue, string $rightValue) use ($orderKey): int {
        $leftKey = $orderKey($leftValue);
        $rightKey = $orderKey($rightValue);
        if ($leftKey === $rightKey) {
            return $leftValue <=> $rightValue;
        }

        return $leftKey <=> $rightKey;
    });

    return $result;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<string> $expected
 */
$assertCompound = static function (TestRunner $t, string $sql, array $tables, array $expected) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'first/last value guard for ' . $sql
    );
    $t->same(md5(json_encode($expected, JSON_THROW_ON_ERROR)), md5(json_encode($actual, JSON_THROW_ON_ERROR)), 'fingerprint for ' . $sql);
};

$tests = [];

$selectETables = [
    't1' => [
        ['a' => 'abc'],
        ['a' => 'def'],
        ['a' => 'ghi'],
    ],
    't2' => [
        ['a' => 'DEF'],
        ['a' => 'abc'],
    ],
    't3' => [
        ['a' => 'def'],
        ['a' => 'jkl'],
    ],
];

$canonicalSelectECases = [
    'selectE-1.0 except binary comparison ordered nocase' => [
        'SELECT a FROM t1 EXCEPT SELECT a FROM t2 ORDER BY a COLLATE nocase',
        ['def', 'ghi'],
    ],
    'selectE-1.1 except binary comparison from second source ordered nocase' => [
        'SELECT a FROM t2 EXCEPT SELECT a FROM t3 ORDER BY a COLLATE nocase',
        ['abc', 'DEF'],
    ],
    'selectE-1.2 except binary comparison ordered binary' => [
        'SELECT a FROM t2 EXCEPT SELECT a FROM t3 ORDER BY a COLLATE binary',
        ['DEF', 'abc'],
    ],
    'selectE-1.3 except binary comparison default order' => [
        'SELECT a FROM t2 EXCEPT SELECT a FROM t3 ORDER BY a',
        ['DEF', 'abc'],
    ],
    'selectE-2.1 collated left arm controls except comparison' => [
        'SELECT a COLLATE nocase FROM t2 EXCEPT SELECT a FROM t3 ORDER BY 1',
        [],
        [
            't2' => [
                ['a' => 'ABC'],
                ['a' => 'def'],
                ['a' => 'GHI'],
                ['a' => 'jkl'],
            ],
            't3' => [
                ['a' => 'abc'],
                ['a' => 'def'],
                ['a' => 'ghi'],
                ['a' => 'jkl'],
            ],
        ],
    ],
    'selectE-2.2 collated left arm still controls comparison with binary order' => [
        'SELECT a COLLATE nocase FROM t2 EXCEPT SELECT a FROM t3 ORDER BY 1 COLLATE binary',
        [],
        [
            't2' => [
                ['a' => 'ABC'],
                ['a' => 'def'],
                ['a' => 'GHI'],
                ['a' => 'jkl'],
            ],
            't3' => [
                ['a' => 'abc'],
                ['a' => 'def'],
                ['a' => 'ghi'],
                ['a' => 'jkl'],
            ],
        ],
    ],
];

foreach ($canonicalSelectECases as $name => $case) {
    $tests['real upstream selectE.test ' . $name] = static function (TestRunner $t) use ($case, $selectETables, $assertCompound): void {
        $assertCompound($t, $case[0], $case[2] ?? $selectETables, $case[1]);
        $t->contains('selectE.test', 'selectE.test compound collation source');
    };
}

for ($seed = 1; $seed <= 320; $seed++) {
    $left = [
        'alpha' . ($seed % 7),
        'Beta' . ($seed % 5),
        'gamma' . ($seed % 3),
        'DELTA' . ($seed % 4),
        'epsilon' . ($seed % 6),
    ];
    $right = [
        strtoupper($left[0]),
        strtolower($left[1]),
        'zeta' . ($seed % 5),
    ];
    $tables = [
        'left_values' => array_map(static fn (string $value): array => ['a' => $value], $left),
        'right_values' => array_map(static fn (string $value): array => ['a' => $value], $right),
    ];

    $expectedBinaryNocaseOrder = $exceptValues($left, $right, 'BINARY', 'NOCASE');
    $expectedNocaseBinaryOrder = $exceptValues($left, $right, 'NOCASE', 'BINARY');
    $expectedNocaseNocaseOrder = $exceptValues($left, $right, 'NOCASE', 'NOCASE');

    $tests[sprintf('real upstream selectE.test dynamic binary-except nocase-order seed %03d', $seed)] =
        static function (TestRunner $t) use ($tables, $expectedBinaryNocaseOrder, $assertCompound): void {
            $assertCompound(
                $t,
                'SELECT a FROM left_values EXCEPT SELECT a FROM right_values ORDER BY a COLLATE nocase',
                $tables,
                $expectedBinaryNocaseOrder
            );
        };

    $tests[sprintf('real upstream selectE.test dynamic left-collate except binary-order seed %03d', $seed)] =
        static function (TestRunner $t) use ($tables, $expectedNocaseBinaryOrder, $assertCompound): void {
            $assertCompound(
                $t,
                'SELECT a COLLATE nocase FROM left_values EXCEPT SELECT a FROM right_values ORDER BY 1 COLLATE binary',
                $tables,
                $expectedNocaseBinaryOrder
            );
        };

    $tests[sprintf('real upstream selectE.test dynamic left-collate except nocase-order seed %03d', $seed)] =
        static function (TestRunner $t) use ($tables, $expectedNocaseNocaseOrder, $assertCompound): void {
            $assertCompound(
                $t,
                'SELECT a COLLATE nocase FROM left_values EXCEPT SELECT a FROM right_values ORDER BY 1 COLLATE nocase',
                $tables,
                $expectedNocaseNocaseOrder
            );
        };
}

$selectFTables = [
    't1' => [
        ['a' => 1, 'b' => 'one', 'c' => 'I'],
    ],
    't2' => [
        ['d' => 5, 'e' => 'ten', 'f' => 'XX'],
        ['d' => 6, 'e' => null, 'f' => null],
    ],
];

$tests['real upstream selectF.test compound order copies nullable source registers'] =
    static function (TestRunner $t) use ($selectFTables, $assertCompound): void {
        $assertCompound(
            $t,
            'SELECT d,e,f FROM t2 UNION ALL SELECT a,b,c FROM t1 WHERE a<5 ORDER BY 2, 1',
            $selectFTables,
            [6, null, null, 1, 'one', 'I', 5, 'ten', 'XX']
        );
        $t->contains('selectF.test', 'selectF.test compound copy source');
    };

for ($seed = 1; $seed <= 320; $seed++) {
    $tables = [
        't1' => [
            ['a' => $seed, 'b' => 'one' . ($seed % 7), 'c' => 'I' . ($seed % 3)],
        ],
        't2' => [
            ['d' => $seed + 4, 'e' => 'ten' . ($seed % 5), 'f' => 'XX' . ($seed % 2)],
            ['d' => $seed + 5, 'e' => null, 'f' => null],
        ],
    ];
    $expectedRows = [
        [$seed + 5, null, null],
        [$seed, 'one' . ($seed % 7), 'I' . ($seed % 3)],
        [$seed + 4, 'ten' . ($seed % 5), 'XX' . ($seed % 2)],
    ];
    $expected = [];
    foreach ($expectedRows as $row) {
        foreach ($row as $value) {
            $expected[] = $value;
        }
    }

    $tests[sprintf('real upstream selectF.test dynamic compound order nullable copy seed %03d', $seed)] =
        static function (TestRunner $t) use ($tables, $expected, $assertCompound): void {
            $assertCompound(
                $t,
                'SELECT d,e,f FROM t2 UNION ALL SELECT a,b,c FROM t1 WHERE a<9999 ORDER BY 2, 1',
                $tables,
                $expected
            );
        };
}

return $tests;
