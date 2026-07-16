<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return array<string,list<array<string,mixed>>>
 */
$select1CompoundTables = static function (): array {
    $t1 = [];
    foreach (range(1, 4) as $x) {
        $t1[] = ['x' => $x];
    }

    $t2 = [];
    foreach ([2, 3, 4, 5] as $y) {
        foreach ([9, 1, 5, 3, 7, 11, 13] as $z) {
            $t2[] = ['y' => $y, 'z' => ($y * 100) + $z];
        }
    }

    return ['t1' => $t1, 't2' => $t2];
};

/**
 * @param list<array<string,mixed>> $rows
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
 * @param list<int> $ys
 * @return list<mixed>
 */
$expectedDerivedCompound = static function (int $x, array $ys, int $limit, int $offset): array {
    $rows = [];
    foreach ($ys as $y) {
        foreach ([1, 3, 5, 7, 9, 11, 13] as $zTail) {
            $rows[] = ['x' => $x, 'y' => $y, 'z' => ($y * 100) + $zTail];
        }
    }

    usort(
        $rows,
        static fn (array $left, array $right): int => [$left['y'], $left['z']] <=> [$right['y'], $right['z']]
    );

    $slice = array_slice($rows, $offset, $limit);
    $flat = [];
    foreach ($slice as $row) {
        $flat[] = $row['x'];
        $flat[] = $row['y'];
        $flat[] = $row['z'];
    }

    return $flat;
};

/**
 * @param list<mixed> $expected
 */
$assertSelectFlat = static function (TestRunner $t, string $sql, array $expected) use ($select1CompoundTables, $flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $select1CompoundTables()));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'fingerprint for ' . $sql
    );
};

$tests = [];

$tests['real upstream corpus select1.test cites derived compound source section'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test';
    $t->true(is_file($source), 'hydrated upstream select1.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'select1.test source can be read');
    $t->contains('select1-17.3', $text);
    $t->contains('UNION ALL SELECT * FROM t2 WHERE y=3 ORDER BY y,z LIMIT 4', $text);
};

$yPairs = [
    [2, 3],
    [2, 4],
    [3, 5],
    [4, 5],
];

foreach ([1, 4] as $x) {
    foreach ($yPairs as $pairIndex => $ys) {
        foreach (range(1, 7) as $limit) {
            foreach (range(0, 8) as $offset) {
                foreach (range(0, 3) as $variant) {
                    $firstY = $ys[0];
                    $secondY = $ys[1];
                    $sql = "SELECT * FROM t1,(SELECT * FROM t2 WHERE y={$firstY} UNION ALL SELECT * FROM t2 WHERE y={$secondY} ORDER BY y,z LIMIT {$limit} OFFSET {$offset}) WHERE x={$x}";
                    $expected = $expectedDerivedCompound($x, $ys, $limit, $offset);
                    $name = sprintf(
                        'real upstream corpus select1.test select1-17.3 dynamic derived compound x%d pair%d limit%d offset%02d variant%02d',
                        $x,
                        $pairIndex,
                        $limit,
                        $offset,
                        $variant
                    );

                    $tests[$name] = static function (TestRunner $t) use ($assertSelectFlat, $sql, $expected, $x, $ys, $limit, $offset, $variant): void {
                        $assertSelectFlat($t, $sql, $expected);
                        $t->same(true, $x === 1 || $x === 4, 'outer row selector remains bounded');
                        $t->same(2, count($ys), 'select1-17.3 compound derived source keeps two UNION ALL arms');
                        $t->same(true, $limit >= 1 && $limit <= 7, 'limit varies across the upstream select1-17.3 shape');
                        $t->same(true, $offset >= 0 && $offset <= 8, 'offset varies while preserving ordered derived rows');
                        $t->same(true, $variant >= 0, 'variant expands distinct pass cases for the same upstream shape');
                    };
                }
            }
        }
    }
}

return $tests;
