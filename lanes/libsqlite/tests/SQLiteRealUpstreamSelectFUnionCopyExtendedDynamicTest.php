<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return list<mixed>
 */
$flattenSelectFExtendedRows = static function (array $rows): array {
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
$assertSelectFExtendedFlat = static function (TestRunner $t, string $sql, array $tables, array $expected) use ($flattenSelectFExtendedRows): void {
    $actual = $flattenSelectFExtendedRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        sha1(json_encode($expected, JSON_THROW_ON_ERROR)),
        sha1(json_encode($actual, JSON_THROW_ON_ERROR)),
        'flat value fingerprint for ' . $sql,
    );
};

$tests = [];

$tests['real upstream selectF.test selectF-2 extended dynamic cites source register copy regression'] =
    static function (TestRunner $t): void {
        $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectF.test';

        $t->true(is_file($source), 'hydrated upstream selectF.test is available');
        $text = file_get_contents($source);
        $t->contains('verifies that an OP_Copy operation is used instead of OP_SCopy', $text);
        $t->contains('do_execsql_test 2', $text);
        $t->contains('SELECT * FROM t2', $text);
        $t->contains('UNION ALL', $text);
        $t->contains('ORDER BY 2, 1', $text);
    };

for ($seed = 1; $seed <= 1000; $seed++) {
    $bucket = $seed % 13;
    $threshold = ($seed % 5 === 0) ? $seed : $seed + 20;
    $leftRows = [
        ['a' => $seed, 'b' => 'one-' . $bucket, 'c' => 'I-' . $seed],
        ['a' => $seed + 10, 'b' => 'nine-' . $bucket, 'c' => 'IX-' . $seed],
    ];
    $rightRows = [
        ['d' => $seed + 40, 'e' => 'ten-' . $bucket, 'f' => 'XX-' . $seed],
        ['d' => $seed + 50, 'e' => null, 'f' => null],
    ];
    $tables = [
        't1' => $leftRows,
        't2' => $rightRows,
    ];

    $combinedRows = [
        [$seed + 40, 'ten-' . $bucket, 'XX-' . $seed],
        [$seed + 50, null, null],
    ];
    foreach ($leftRows as $row) {
        if ($row['a'] < $threshold) {
            $combinedRows[] = [$row['a'], $row['b'], $row['c']];
        }
    }

    usort(
        $combinedRows,
        static function (array $left, array $right): int {
            $leftSecond = $left[1];
            $rightSecond = $right[1];
            if ($leftSecond === null && $rightSecond !== null) {
                return -1;
            }
            if ($leftSecond !== null && $rightSecond === null) {
                return 1;
            }
            if ($leftSecond !== $rightSecond) {
                return strcmp((string) $leftSecond, (string) $rightSecond);
            }

            return $left[0] <=> $right[0];
        },
    );

    $expected = [];
    foreach ($combinedRows as $row) {
        array_push($expected, $row[0], $row[1], $row[2]);
    }

    $tests[sprintf('real upstream selectF.test selectF-2 extended union copy order seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFExtendedFlat, $tables, $threshold, $expected): void {
            $sql = "SELECT * FROM t2 UNION ALL SELECT * FROM t1 WHERE a<{$threshold} ORDER BY 2, 1";

            $assertSelectFExtendedFlat($t, $sql, $tables, $expected);
        };
}

return $tests;
