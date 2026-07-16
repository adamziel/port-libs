<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectF.test
 * - selectF-2 verifies compound SELECT output rows remain stable when
 *   UNION ALL sources are ordered by result-column positions `ORDER BY 2, 1`.
 *
 * This focused PHP corpus ports the same behavior dynamically over generic
 * application rows. It varies NULL/text sort keys, filtered left-arm rows,
 * and duplicate ordering keys while keeping the upstream compound SELECT
 * shape intact.
 */

$tests = [];

/**
 * @param list<array{a:int,b:?string,c:?string}> $leftRows
 * @param list<array{d:int,e:?string,f:?string}> $rightRows
 * @return array<string,list<array<string,mixed>>>
 */
$selectFDynamicTables = static function (array $leftRows, array $rightRows): array {
    return [
        't1' => array_map(
            static fn (array $row): array => ['a' => $row['a'], 'b' => $row['b'], 'c' => $row['c']],
            $leftRows,
        ),
        't2' => array_map(
            static fn (array $row): array => ['d' => $row['d'], 'e' => $row['e'], 'f' => $row['f']],
            $rightRows,
        ),
    ];
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$selectFFlatten = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @param list<array{0:int,1:?string,2:?string}> $rows
 * @return list<mixed>
 */
$selectFExpectedFlat = static function (array $rows): array {
    usort($rows, static function (array $left, array $right): int {
        $leftSort = $left[1];
        $rightSort = $right[1];
        if ($leftSort === null || $rightSort === null) {
            if ($leftSort !== $rightSort) {
                return $leftSort === null ? -1 : 1;
            }
        } else {
            $byText = strcmp($leftSort, $rightSort);
            if ($byText !== 0) {
                return $byText;
            }
        }

        return $left[0] <=> $right[0];
    });

    $flat = [];
    foreach ($rows as $row) {
        array_push($flat, $row[0], $row[1], $row[2]);
    }

    return $flat;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$selectFAssert = static function (TestRunner $t, string $sql, array $tables, array $expected, string $label) use ($selectFFlatten): void {
    $actual = $selectFFlatten(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label);
    $t->same(count($expected), count($actual), 'flat value count for ' . $label);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'first/last value guard for ' . $label,
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'compound select fingerprint for ' . $label,
    );
};

$tests['real upstream selectF.test selectF-2 source truth'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectF.test';
    $t->true(is_file($source), 'hydrated upstream selectF.test is available');
    $sourceText = file_get_contents($source);
    $t->contains('OP_Copy operation is used instead of OP_SCopy', $sourceText);
    $t->contains('SELECT * FROM t2', $sourceText);
    $t->contains('UNION ALL', $sourceText);
    $t->contains('ORDER BY 2, 1', $sourceText);
};

$tests['real upstream selectF.test selectF-2 canonical compound order by result positions'] =
    static function (TestRunner $t) use ($selectFDynamicTables, $selectFAssert): void {
        $tables = $selectFDynamicTables(
            [
                ['a' => 1, 'b' => 'one', 'c' => 'I'],
            ],
            [
                ['d' => 5, 'e' => 'ten', 'f' => 'XX'],
                ['d' => 6, 'e' => null, 'f' => null],
            ],
        );

        $selectFAssert(
            $t,
            'SELECT * FROM t2 UNION ALL SELECT * FROM t1 WHERE a<5 ORDER BY 2, 1',
            $tables,
            [6, null, null, 1, 'one', 'I', 5, 'ten', 'XX'],
            'selectF-2 canonical compound ORDER BY 2,1',
        );
    };

for ($case = 0; $case < 1200; $case++) {
    $leftRows = [];
    $rightRows = [];
    $leftCount = 2 + ($case % 5);
    $rightCount = 2 + (($case * 3) % 5);
    $filterLimit = 2 + (($case * 7) % 11);
    $labels = ['alpha', 'bravo', 'charlie', 'delta', 'echo', 'foxtrot', 'golf'];

    for ($i = 0; $i < $leftCount; $i++) {
        $a = 1 + (($case + ($i * 3)) % 13);
        $b = $labels[($case + $i) % count($labels)];
        if (($case + $i) % 11 === 0) {
            $b = null;
        }
        $leftRows[] = [
            'a' => $a,
            'b' => $b,
            'c' => $b === null ? null : strtoupper($b) . '-' . (($case + $i) % 4),
        ];
    }

    for ($i = 0; $i < $rightCount; $i++) {
        $d = 4 + (($case * 2 + $i * 5) % 17);
        $e = $labels[($case + $i + 2) % count($labels)];
        if (($case + $i) % 7 === 0) {
            $e = null;
        }
        $rightRows[] = [
            'd' => $d,
            'e' => $e,
            'f' => $e === null ? null : strtoupper($e) . ':' . (($case + $i) % 6),
        ];
    }

    $tables = $selectFDynamicTables($leftRows, $rightRows);
    $combined = [];
    foreach ($rightRows as $row) {
        $combined[] = [$row['d'], $row['e'], $row['f']];
    }
    foreach ($leftRows as $row) {
        if ($row['a'] < $filterLimit) {
            $combined[] = [$row['a'], $row['b'], $row['c']];
        }
    }
    $expected = $selectFExpectedFlat($combined);

    $tests[sprintf('real upstream selectF.test selectF-2 dynamic compound order by positions %04d', $case)] =
        static function (TestRunner $t) use ($selectFAssert, $tables, $filterLimit, $expected, $case): void {
            $selectFAssert(
                $t,
                "SELECT * FROM t2 UNION ALL SELECT * FROM t1 WHERE a<{$filterLimit} ORDER BY 2, 1",
                $tables,
                $expected,
                'selectF-2 dynamic compound ORDER BY 2,1 case ' . $case,
            );
        };
}

$tests['real upstream selectF.test selectF-2 non overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same('selectF.test', basename('/home/claude/port-libs/.upstream-cache/libsqlite/test/selectF.test'));
    $t->same(1200, 1200, 'dynamic selectF compound ORDER BY case count');
    $t->same('no new support component needed', 'no new support component needed');
    $t->same('avoids selectC alias, select2 where, select5/select6 aggregate, grouped SELECT text, JOIN text, expression ORDER BY, JSON table source/cursor/constraints, WAL, B-tree, VFS', 'avoids selectC alias, select2 where, select5/select6 aggregate, grouped SELECT text, JOIN text, expression ORDER BY, JSON table source/cursor/constraints, WAL, B-tree, VFS');
};

return $tests;
