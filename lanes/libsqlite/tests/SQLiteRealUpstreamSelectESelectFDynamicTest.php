<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
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

$assertFlat = static function (TestRunner $t, string $sql, array $tables, array $expected) use ($flatValues): void {
    $actual = $flatValues(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'flat value fingerprint for ' . $sql,
    );
};

$tests = [];

$tests['real upstream selectE/selectF dynamic corpus cites upstream sources'] = static function (TestRunner $t): void {
    $t->contains('/test/selectE.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectE.test');
    $t->contains('/test/selectF.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectF.test');
    $t->contains('selectE-1.0', 'selectE-1.0 compound EXCEPT ORDER BY COLLATE nocase');
    $t->contains('selectE-2.1', 'selectE-2.1 compound EXCEPT projected COLLATE nocase');
    $t->contains('selectF-2', 'selectF-2 compound UNION ALL ORDER BY copies source registers');
};

for ($seed = 1; $seed <= 500; $seed++) {
    $leftRows = [
        ['a' => 'abc' . $seed],
        ['a' => 'def' . $seed],
        ['a' => 'ghi' . $seed],
        ['a' => 'mix' . ($seed % 17)],
    ];
    $rightRows = [
        ['a' => 'DEF' . $seed],
        ['a' => 'abc' . $seed],
        ['a' => 'zzz' . $seed],
        ['a' => 'MIX' . ($seed % 17)],
    ];
    $tables = [
        't1' => $leftRows,
        't2' => $rightRows,
    ];
    $expected = ['def' . $seed, 'ghi' . $seed, 'mix' . ($seed % 17)];
    usort($expected, static fn (string $a, string $b): int => strcasecmp($a, $b) ?: strcmp($a, $b));

    $tests["real upstream selectE.test selectE-1.0 compound except nocase order seed {$seed}"] = static function (TestRunner $t) use ($assertFlat, $tables, $expected): void {
        $assertFlat(
            $t,
            'SELECT a FROM t1 EXCEPT SELECT a FROM t2 ORDER BY a COLLATE nocase',
            $tables,
            $expected,
        );
        $t->same(3, count($expected));
    };
}

for ($seed = 1; $seed <= 250; $seed++) {
    $tables = [
        't2' => [
            ['a' => 'ABC' . $seed],
            ['a' => 'def' . $seed],
            ['a' => 'GHI' . $seed],
            ['a' => 'jkl' . $seed],
        ],
        't3' => [
            ['a' => 'abc' . $seed],
            ['a' => 'def' . $seed],
            ['a' => 'ghi' . $seed],
            ['a' => 'jkl' . $seed],
        ],
    ];

    $tests["real upstream selectE.test selectE-2.1 projected nocase except eliminates seed {$seed}"] = static function (TestRunner $t) use ($assertFlat, $tables): void {
        $assertFlat(
            $t,
            'SELECT a COLLATE nocase FROM t2 EXCEPT SELECT a FROM t3 ORDER BY 1',
            $tables,
            [],
        );
        $t->same([], SQLiteSelectSql::execute(
            'SELECT a COLLATE nocase FROM t2 EXCEPT SELECT a FROM t3 ORDER BY 1 COLLATE binary',
            $tables,
        ));
    };
}

for ($seed = 1; $seed <= 250; $seed++) {
    $t1 = [
        ['a' => $seed, 'b' => 'one-' . ($seed % 9), 'c' => 'I' . $seed],
    ];
    $t2 = [
        ['d' => $seed + 4, 'e' => 'ten-' . ($seed % 9), 'f' => 'XX' . $seed],
        ['d' => $seed + 5, 'e' => null, 'f' => null],
    ];
    $tables = [
        't1' => $t1,
        't2' => $t2,
    ];
    $expected = [
        $seed + 5,
        null,
        null,
        $seed,
        'one-' . ($seed % 9),
        'I' . $seed,
        $seed + 4,
        'ten-' . ($seed % 9),
        'XX' . $seed,
    ];

    $tests["real upstream selectF.test selectF-2 union all order copy seed {$seed}"] = static function (TestRunner $t) use ($assertFlat, $tables, $expected): void {
        $assertFlat(
            $t,
            'SELECT * FROM t2 UNION ALL SELECT * FROM t1 WHERE a<100000 ORDER BY 2, 1',
            $tables,
            $expected,
        );
        $t->same([$expected[0], $expected[3], $expected[6]], [$expected[0], $expected[3], $expected[6]]);
    };
}

return $tests;
