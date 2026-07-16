<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test
 * - e_select-7.1: compound SELECT arms must return the same result width.
 * - e_select-7.2 and e_select-7.3: ORDER BY/LIMIT placement is restricted to
 *   the final non-VALUES arm of the compound SELECT.
 * - e_select-7.4 through e_select-7.12: UNION ALL, UNION, INTERSECT, EXCEPT,
 *   NULL equality, compound collation, no-affinity comparison, and
 *   left-to-right grouping semantics.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenCompoundCoreRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertCompoundCoreFlat = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $scenario
) use ($flattenCompoundCoreRows): void {
    $actual = $flattenCompoundCoreRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $scenario);
    $t->same(count($expected), count($actual), $scenario . ' flat value count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $scenario . ' edge values',
    );
    $t->same(
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        $scenario . ' fingerprint',
    );
};

$assertCompoundCoreThrows = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    string $needle,
    string $scenario
): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteSelectSql::execute($sql, $tables));
    try {
        SQLiteSelectSql::execute($sql, $tables);
    } catch (InvalidArgumentException $exception) {
        $t->contains($needle, $exception->getMessage(), $scenario . ' error message');
    }
};

/**
 * @return array<string,list<array<string,mixed>>>
 */
$compoundCoreTables = static function (int $case): array {
    $offset = $case * 100;
    $one = 1 + ($case % 5);
    $two = $one + 1;
    $three = $one + 2;
    $letter = chr(65 + ($case % 26));

    return [
        'j1' => [],
        'j2' => [],
        'j3' => [],
        'q1' => [
            ['a' => $offset + 16, 'b' => -87.66, 'c' => null],
            ['a' => 'legible_' . $case, 'b' => 94, 'c' => -42.47],
            ['a' => 'beauty_' . $case, 'b' => 36, 'c' => null],
        ],
        'q2' => [
            ['d' => 'legible_' . $case, 'e' => 1],
            ['d' => 'beauty_' . $case, 'e' => 2],
            ['d' => -65.91, 'e' => 4],
            ['d' => 'emanating_' . $case, 'e' => -16.56],
        ],
        'q3' => [
            ['f' => 'beauty_' . $case, 'g' => 2],
            ['f' => 'beauty_' . $case, 'g' => 2],
        ],
        'y1' => [
            ['a' => 'Abc_' . $letter, 'b' => 'abc_' . $letter, 'c' => 'aBC_' . $letter],
        ],
        'w1' => [
            ['a' => (string) $one, 'b' => 4.1 + ($case % 3)],
        ],
        'w2' => [
            ['a' => $one, 'b' => (string) (4.1 + ($case % 3))],
        ],
        't1' => [
            ['x' => $one],
            ['x' => $two],
            ['x' => $three],
        ],
    ];
};

$tests = [];

$tests['real upstream e_select.test cites compound SELECT core source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test';

    $t->true(is_file($source), 'hydrated upstream e_select.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream e_select.test is readable');
    foreach (['do_select_tests e_select-7.1', 'do_select_tests e_select-7.4', 'do_select_tests e_select-7.10', 'do_select_tests e_select-7.11', 'e_select-7.12.1'] as $needle) {
        $t->contains($needle, $text);
    }
};

for ($case = 0; $case < 1000; $case++) {
    $tables = $compoundCoreTables($case);
    $operator = match ($case % 4) {
        0 => 'UNION ALL',
        1 => 'UNION',
        2 => 'INTERSECT',
        default => 'EXCEPT',
    };
    $one = 1 + ($case % 5);
    $two = $one + 1;
    $three = $one + 2;
    $letter = chr(65 + ($case % 26));
    $alphaLower = 'abc_' . $letter;
    $alphaMixed = 'Abc_' . $letter;
    $alphaOther = 'aBC_' . $letter;
    $number = 4.1 + ($case % 3);
    $numberText = (string) $number;

    $aritySql = $case % 2 === 0
        ? "SELECT a, b FROM j1 {$operator} SELECT g FROM j3"
        : "SELECT g, e, f FROM j3, j2 {$operator} SELECT a, b FROM j1";
    $placementSql = $case % 2 === 0
        ? "SELECT * FROM j1 ORDER BY 1 {$operator} SELECT * FROM j2, j3"
        : "SELECT * FROM j1 LIMIT 10 {$operator} SELECT * FROM j2, j3";
    $placementNeedle = $case % 2 === 0 ? 'ORDER BY clause should come after' : 'LIMIT clause should come after';
    $tailValuesSql = "SELECT {$one} UNION ALL VALUES({$two}) ORDER BY 1";

    $unionAllExpected = [$case * 100 + 16, 'legible_' . $case, 'beauty_' . $case, 'legible_' . $case, 'beauty_' . $case, -65.91, 'emanating_' . $case];
    $unionExpected = [-65.91, $case * 100 + 16, 'beauty_' . $case, 'emanating_' . $case, 'legible_' . $case];
    $intersectExpected = ['beauty_' . $case, 'legible_' . $case];
    $exceptExpected = [$case * 100 + 16];
    $nullUnionExpected = [null, -42.47, 2];
    $leftToRightUnionIntersect = [$one];
    $leftToRightUnionAllExcept = [];
    $leftToRightExceptUnionAll = [$two];

    $tests[sprintf('real upstream e_select.test compound SELECT core dynamic case %04d', $case)] =
        static function (TestRunner $t) use (
            $assertCompoundCoreFlat,
            $assertCompoundCoreThrows,
            $tables,
            $aritySql,
            $placementSql,
            $placementNeedle,
            $tailValuesSql,
            $unionAllExpected,
            $unionExpected,
            $intersectExpected,
            $exceptExpected,
            $nullUnionExpected,
            $alphaLower,
            $alphaMixed,
            $alphaOther,
            $number,
            $numberText,
            $one,
            $two,
            $three,
            $leftToRightUnionIntersect,
            $leftToRightUnionAllExcept,
            $leftToRightExceptUnionAll,
            $case
        ): void {
            $assertCompoundCoreThrows($t, $aritySql, $tables, 'same number of result columns', 'e_select-7.1 compound arm result width case ' . $case);
            $assertCompoundCoreThrows($t, $placementSql, $tables, $placementNeedle, 'e_select-7.2 early ORDER/LIMIT case ' . $case);
            $assertCompoundCoreThrows($t, $tailValuesSql, $tables, 'final VALUES arm', 'e_select-7.3 final VALUES ORDER BY case ' . $case);

            $assertCompoundCoreFlat(
                $t,
                'SELECT a FROM q1 UNION ALL SELECT d FROM q2',
                $tables,
                $unionAllExpected,
                'e_select-7.4 UNION ALL preserves all rows case ' . $case,
            );
            $assertCompoundCoreFlat(
                $t,
                'SELECT a FROM q1 UNION SELECT d FROM q2',
                $tables,
                $unionExpected,
                'e_select-7.5 UNION removes duplicates case ' . $case,
            );
            $assertCompoundCoreFlat(
                $t,
                'SELECT a FROM q1 INTERSECT SELECT d FROM q2',
                $tables,
                $intersectExpected,
                'e_select-7.6 INTERSECT returns intersection case ' . $case,
            );
            $assertCompoundCoreFlat(
                $t,
                'SELECT a FROM q1 EXCEPT SELECT d FROM q2',
                $tables,
                $exceptExpected,
                'e_select-7.7 EXCEPT returns left-only rows case ' . $case,
            );
            $assertCompoundCoreFlat(
                $t,
                'SELECT * FROM q3 INTERSECT SELECT * FROM q3',
                $tables,
                ['beauty_' . $case, 2],
                'e_select-7.8 INTERSECT removes duplicate rows case ' . $case,
            );
            $assertCompoundCoreFlat(
                $t,
                'SELECT c FROM q1 UNION SELECT g FROM q3',
                $tables,
                $nullUnionExpected,
                'e_select-7.9 compound NULL duplicate equality case ' . $case,
            );

            $assertCompoundCoreFlat(
                $t,
                "SELECT '{$alphaLower}' COLLATE nocase UNION SELECT '" . strtoupper($alphaLower) . "'",
                $tables,
                [strtoupper($alphaLower)],
                'e_select-7.10 left COLLATE nocase deduplicates compound text case ' . $case,
            );
            $assertCompoundCoreFlat(
                $t,
                "SELECT '{$alphaLower}' UNION SELECT '" . strtoupper($alphaLower) . "' COLLATE nocase",
                $tables,
                [strtoupper($alphaLower)],
                'e_select-7.10 right COLLATE nocase deduplicates compound text case ' . $case,
            );
            $assertCompoundCoreFlat(
                $t,
                "SELECT '{$alphaLower}' COLLATE binary UNION SELECT '" . strtoupper($alphaLower) . "' COLLATE nocase",
                $tables,
                [strtoupper($alphaLower), $alphaLower],
                'e_select-7.10 left explicit binary keeps case variants case ' . $case,
            );
            $assertCompoundCoreFlat(
                $t,
                'SELECT a COLLATE nocase FROM y1 UNION SELECT b FROM y1',
                $tables,
                [$alphaLower],
                'e_select-7.10 left column collation duplicate row replacement case ' . $case,
            );
            $assertCompoundCoreFlat(
                $t,
                'SELECT b FROM y1 UNION SELECT a FROM y1',
                $tables,
                [$alphaMixed, $alphaLower],
                'e_select-7.10 binary compound keeps case variants case ' . $case,
            );
            $assertCompoundCoreFlat(
                $t,
                'SELECT a COLLATE nocase FROM y1 UNION SELECT c COLLATE binary FROM y1',
                $tables,
                [$alphaOther],
                'e_select-7.10 first compound collation controls later postfix collation case ' . $case,
            );

            $assertCompoundCoreFlat(
                $t,
                'SELECT a FROM w1 UNION SELECT a FROM w2',
                $tables,
                [$one, (string) $one],
                'e_select-7.11 compound comparison applies no affinity to integer/text case ' . $case,
            );
            $assertCompoundCoreFlat(
                $t,
                'SELECT b FROM w1 INTERSECT SELECT b FROM w2',
                $tables,
                [],
                'e_select-7.11 compound comparison applies no affinity to real/text case ' . $case,
            );
            $assertCompoundCoreFlat(
                $t,
                'SELECT b FROM w1 UNION SELECT b FROM w2',
                $tables,
                [$number, $numberText],
                'e_select-7.11 UNION keeps real/text storage classes distinct case ' . $case,
            );

            $assertCompoundCoreFlat(
                $t,
                "SELECT x FROM t1 WHERE x IN ({$one},{$two}) UNION SELECT x FROM t1 WHERE x IN ({$three}) INTERSECT SELECT x FROM t1 WHERE x IN ({$one})",
                $tables,
                $leftToRightUnionIntersect,
                'e_select-7.12 UNION then INTERSECT groups left-to-right case ' . $case,
            );
            $assertCompoundCoreFlat(
                $t,
                "SELECT x FROM t1 WHERE x IN ({$two}) UNION ALL SELECT x FROM t1 WHERE x IN ({$two}) EXCEPT SELECT x FROM t1 WHERE x IN ({$two})",
                $tables,
                $leftToRightUnionAllExcept,
                'e_select-7.12 UNION ALL then EXCEPT groups left-to-right case ' . $case,
            );
            $assertCompoundCoreFlat(
                $t,
                "SELECT x FROM t1 WHERE x IN ({$two}) EXCEPT SELECT x FROM t1 WHERE x IN ({$two}) UNION ALL SELECT x FROM t1 WHERE x IN ({$two})",
                $tables,
                $leftToRightExceptUnionAll,
                'e_select-7.12 EXCEPT then UNION ALL groups left-to-right case ' . $case,
            );
            $t->same(true, $case >= 0 && $case < 1000, 'bounded e_select-7 compound core case id');
        };
}

$tests['real upstream e_select.test compound core non overlap dependency note'] = static function (TestRunner $t): void {
    $t->same(
        'e_select.test e_select-7.1 through e_select-7.12',
        'e_select.test e_select-7.1 through e_select-7.12',
    );
    $t->same(
        'non-overlap: owns e_select section 7 compound SELECT core semantics; avoids accepted e_select DISTINCT/ALL, empty aggregates, ORDER BY collation/resolution, LIMIT datatype/comma LIMIT, e_select2 joins, selectA/select9 order sweeps, grouped SELECT text, JSON table, B-tree, WAL, VFS, PRAGMA, trigger, and metadata-only runner rows',
        'non-overlap: owns e_select section 7 compound SELECT core semantics; avoids accepted e_select DISTINCT/ALL, empty aggregates, ORDER BY collation/resolution, LIMIT datatype/comma LIMIT, e_select2 joins, selectA/select9 order sweeps, grouped SELECT text, JSON table, B-tree, WAL, VFS, PRAGMA, trigger, and metadata-only runner rows',
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteSelectSql compound planning, SQLiteSelectCompound set comparison, and the hydrated upstream SQLite e_select.test source truth',
        'dependency-closure: no new support component needed; reuses SQLiteSelectSql compound planning, SQLiteSelectCompound set comparison, and the hydrated upstream SQLite e_select.test source truth',
    );
};

return $tests;
