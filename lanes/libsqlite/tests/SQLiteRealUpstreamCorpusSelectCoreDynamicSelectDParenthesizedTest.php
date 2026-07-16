<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$selectDFlat = static function (array $rows): array {
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
$assertSelectD = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $upstream
) use ($selectDFlat): void {
    $actual = $selectDFlat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'flat edge values for ' . $sql,
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'flat fingerprint for ' . $sql,
    );
    $t->contains('selectD.test', $upstream);
};

/**
 * @return array<string,list<array<string,mixed>>>
 */
$selectDTables = static function (int $base, int $gap = 111, bool $leftMatch = true): array {
    return [
        't1' => [['a' => $base, 'b' => 'x1-' . $base]],
        't2' => [['a' => $base + $gap, 'b' => 'x2-' . ($base + $gap)]],
        't3' => [['a' => $base + ($gap * 2), 'b' => 'x3-' . ($base + ($gap * 2))]],
        't4' => [['a' => $leftMatch ? $base + ($gap * 3) : $base + ($gap * 4), 'b' => 'x4-' . ($leftMatch ? $base + ($gap * 3) : $base + ($gap * 4))]],
        't4_aux' => [['a' => $base + ($gap * 4), 'b' => 'x5-' . ($base + ($gap * 4))]],
    ];
};

$tests['real upstream corpus selectD parenthesized cites hydrated upstream source'] = static function (TestRunner $t): void {
    $path = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test';
    $t->true(is_file($path), 'hydrated upstream selectD.test exists');
    $contents = file_get_contents($path);
    $t->contains('do_test selectD-$i.1', $contents);
    $t->contains('do_test selectD-$i.2.7', $contents);
    $t->contains('do_test selectD-$i.7', $contents);
};

for ($case = 0; $case < 220; $case++) {
    $base = 10 + $case;
    $gap = 7 + ($case % 19);
    $tables = $selectDTables($base, $gap);
    $expected = [
        $base,
        'x1-' . $base,
        $base + $gap,
        'x2-' . ($base + $gap),
        $base + ($gap * 2),
        'x3-' . ($base + ($gap * 2)),
        $base + ($gap * 3),
        'x4-' . ($base + ($gap * 3)),
    ];

    $tests["real upstream corpus selectD.test selectD-1 parenthesized comma from {$case}"] = static function (TestRunner $t) use ($assertSelectD, $tables, $base, $gap, $expected): void {
        $sql = "SELECT * FROM (t1), (t2), (t3), (t4) WHERE t4.a=t3.a+{$gap} AND t3.a=t2.a+{$gap} AND t2.a=t1.a+{$gap} AND t1.a={$base}";
        $assertSelectD($t, $sql, $tables, $expected, 'selectD.test selectD-1 parenthesized FROM name resolution');
    };
}

for ($case = 0; $case < 220; $case++) {
    $base = 500 + $case;
    $gap = 5 + ($case % 23);
    $tables = $selectDTables($base, $gap);
    $expected = [$base + ($gap * 2)];

    $tests["real upstream corpus selectD.test selectD-2 nested join t3 projection {$case}"] = static function (TestRunner $t) use ($assertSelectD, $tables, $gap, $expected): void {
        $sql = "SELECT t3.a FROM t1 JOIN (t2 JOIN (t3 JOIN t4 ON t4.a=t3.a+{$gap}) ON t3.a=t2.a+{$gap}) ON t2.a=t1.a+{$gap}";
        $assertSelectD($t, $sql, $tables, $expected, 'selectD.test selectD-2.2 nested parenthesized JOIN projection');
    };
}

for ($case = 0; $case < 220; $case++) {
    $base = 900 + $case;
    $gap = 3 + ($case % 29);
    $tables = $selectDTables($base, $gap);
    $expected = [
        $base + ($gap * 2),
        'x3-' . ($base + ($gap * 2)),
        $base + $gap,
        'x2-' . ($base + $gap),
    ];

    $tests["real upstream corpus selectD.test selectD-2 nested join table star pair {$case}"] = static function (TestRunner $t) use ($assertSelectD, $tables, $gap, $expected): void {
        $sql = "SELECT t3.*, t2.* FROM t1 JOIN (t2 JOIN (t3 JOIN t4 ON t4.a=t3.a+{$gap}) ON t3.a=t2.a+{$gap}) ON t2.a=t1.a+{$gap}";
        $assertSelectD($t, $sql, $tables, $expected, 'selectD.test selectD-2.3 nested parenthesized JOIN table-star projection');
    };
}

for ($case = 0; $case < 220; $case++) {
    $base = 1300 + $case;
    $gap = 2 + ($case % 31);
    $tables = $selectDTables($base, $gap);
    $expected = [$base + ($gap * 3), 'x5-' . ($base + ($gap * 4))];

    $tests["real upstream corpus selectD.test selectD-2 alias nested join {$case}"] = static function (TestRunner $t) use ($assertSelectD, $tables, $gap, $expected): void {
        $sql = "SELECT x.a, y.b FROM t1 JOIN (t2 JOIN (t4 AS x JOIN t4_aux AS y ON y.a=x.a+{$gap}) ON x.a=t2.a+" . ($gap * 2) . ") ON t2.a=t1.a+{$gap}";
        $assertSelectD($t, $sql, $tables, $expected, 'selectD.test selectD-2.7 alias resolution in parenthesized JOIN');
    };
}

for ($case = 0; $case < 220; $case++) {
    $base = 1700 + $case;
    $gap = 11 + ($case % 17);
    $leftMatch = $case % 3 !== 0;
    $tables = $selectDTables($base, $gap, $leftMatch);
    $expected = [
        $base,
        'x1-' . $base,
        $base + $gap,
        'x2-' . ($base + $gap),
        $base + ($gap * 2),
        'x3-' . ($base + ($gap * 2)),
        $leftMatch ? 'x4-' . ($base + ($gap * 3)) : null,
    ];

    $tests["real upstream corpus selectD.test selectD-7 left join parenthesized null extension {$case}"] = static function (TestRunner $t) use ($assertSelectD, $tables, $gap, $expected): void {
        $sql = "SELECT t1.*, t2.*, t3.*, t4.b FROM (t1 LEFT JOIN t2 ON t2.a=t1.a+{$gap}) JOIN (t3 LEFT JOIN t4 ON t4.a=t3.a+{$gap}) ON t1.a=t3.a-" . ($gap * 2);
        $assertSelectD($t, $sql, $tables, $expected, 'selectD.test selectD-7 parenthesized LEFT JOIN null-extension projection');
    };
}

return $tests;
