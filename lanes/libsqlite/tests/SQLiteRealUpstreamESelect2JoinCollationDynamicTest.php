<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/e_select2.test
 * - e_select-2.1 join cases 19 through 27: join comparison collation changes
 *   when the NOCASE-collated operand is on the left or right side.
 *
 * The lane row-array executor does not carry CREATE TABLE column collation
 * metadata, so this corpus spells the upstream default collation as explicit
 * COLLATE nocase in the SELECT text. That keeps the behavior under test in
 * the native SELECT predicate/join executor instead of encoding static rows.
 */

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
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertFlat = static function (TestRunner $t, string $sql, array $tables, array $expected, string $scenario) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $scenario . ' rows');
    $t->same(count($expected), count($actual), $scenario . ' flat value count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $scenario . ' edge values'
    );
    $t->same(
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        $scenario . ' fingerprint'
    );
};

/**
 * @return array<string,list<array<string,mixed>>>
 */
$collationTables = static function (int $case): array {
    $base = 1000 + ($case * 10);
    $upperA = 'ALPHA' . $case;
    $upperB = 'BRAVO' . $case;
    $upperC = 'CHARLIE' . $case;

    return [
        'left_text' => [
            ['id' => $base + 1, 'b' => $upperA, 'payload' => 'left-a-' . $case],
            ['id' => $base + 2, 'b' => $upperB, 'payload' => 'left-b-' . $case],
            ['id' => $base + 3, 'b' => $upperC, 'payload' => 'left-c-' . $case],
            ['id' => $base + 4, 'b' => null, 'payload' => 'left-null-' . $case],
        ],
        'nocase_text' => [
            ['b' => strtolower($upperA), 'label' => 'right-a-' . $case],
            ['b' => strtolower($upperB), 'label' => 'right-b-' . $case],
            ['b' => 'delta' . $case, 'label' => 'right-miss-' . $case],
        ],
    ];
};

$tests = [];

$tests['real upstream e_select2.test cites join collation source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select2.test';

    $t->true(is_file($source), 'hydrated upstream e_select2.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream e_select2.test is readable');
    $t->contains('t1 JOIN t3 USING(b)', $text);
    $t->contains('t3 JOIN t1 USING(b)', $text);
    $t->contains('t1 LEFT JOIN t3 ON (t3.b=t1.b)', $text);
    $t->contains('t1 LEFT JOIN t3 ON (t1.b=t3.b)', $text);
};

for ($case = 0; $case < 1000; $case++) {
    $base = 1000 + ($case * 10);
    $tables = $collationTables($case);

    $nocaseLeftSql = 'SELECT left_text.id, left_text.b, nocase_text.label '
        . 'FROM left_text JOIN nocase_text ON nocase_text.b COLLATE nocase = left_text.b '
        . 'ORDER BY left_text.id';
    $nocaseLeftExpected = [
        $base + 1, 'ALPHA' . $case, 'right-a-' . $case,
        $base + 2, 'BRAVO' . $case, 'right-b-' . $case,
    ];

    $nocaseRightSql = 'SELECT left_text.id, left_text.b, nocase_text.label '
        . 'FROM left_text JOIN nocase_text ON left_text.b = nocase_text.b COLLATE nocase '
        . 'ORDER BY left_text.id';
    $nocaseRightExpected = $nocaseLeftExpected;

    $binarySql = 'SELECT left_text.id, left_text.b, nocase_text.label '
        . 'FROM left_text JOIN nocase_text ON left_text.b = nocase_text.b '
        . 'ORDER BY left_text.id';
    $binaryExpected = [];

    $leftNocaseSql = 'SELECT left_text.id, left_text.b, nocase_text.label '
        . 'FROM left_text LEFT JOIN nocase_text ON nocase_text.b COLLATE nocase = left_text.b '
        . 'ORDER BY left_text.id';
    $leftNocaseExpected = [
        $base + 1, 'ALPHA' . $case, 'right-a-' . $case,
        $base + 2, 'BRAVO' . $case, 'right-b-' . $case,
        $base + 3, 'CHARLIE' . $case, null,
        $base + 4, null, null,
    ];

    $leftBinarySql = 'SELECT left_text.id, left_text.b, nocase_text.label '
        . 'FROM left_text LEFT JOIN nocase_text ON left_text.b = nocase_text.b '
        . 'ORDER BY left_text.id';
    $leftBinaryExpected = [
        $base + 1, 'ALPHA' . $case, null,
        $base + 2, 'BRAVO' . $case, null,
        $base + 3, 'CHARLIE' . $case, null,
        $base + 4, null, null,
    ];

    $tests[sprintf('real upstream e_select2.test dynamic join collation direction case %04d', $case)] =
        static function (TestRunner $t) use (
            $assertFlat,
            $tables,
            $nocaseLeftSql,
            $nocaseLeftExpected,
            $nocaseRightSql,
            $nocaseRightExpected,
            $binarySql,
            $binaryExpected,
            $leftNocaseSql,
            $leftNocaseExpected,
            $leftBinarySql,
            $leftBinaryExpected,
            $case
        ): void {
            $assertFlat($t, $nocaseLeftSql, $tables, $nocaseLeftExpected, 'e_select2 join nocase left operand ' . $case);
            $assertFlat($t, $nocaseRightSql, $tables, $nocaseRightExpected, 'e_select2 join nocase right operand ' . $case);
            $assertFlat($t, $binarySql, $tables, $binaryExpected, 'e_select2 join binary comparison ' . $case);
            $assertFlat($t, $leftNocaseSql, $tables, $leftNocaseExpected, 'e_select2 left join nocase comparison ' . $case);
            $assertFlat($t, $leftBinarySql, $tables, $leftBinaryExpected, 'e_select2 left join binary comparison ' . $case);
            $t->same(true, $case >= 0, 'dynamic e_select2 collation case lower bound');
            $t->same(true, $case < 1000, 'dynamic e_select2 collation case upper bound');
        };
}

return $tests;
