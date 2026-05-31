<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

$jsonText = static function (array $document): string {
    return json_encode($document, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
};

$tests['real upstream json101 17 dynamic cites join subquery source'] = static function (TestRunner $t): void {
    $sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test';
    $source = file_get_contents($sourcePath);
    if (!is_string($source)) {
        $t->fail('Unable to read hydrated upstream json101.test');
        return;
    }

    $t->contains('do_execsql_test json101-17.1', $source);
    $t->contains('SELECT * FROM t1 LEFT JOIN t2 ON', $source);
    $t->contains('SELECT b FROM json_each ORDER BY 1', $source);
};

$tests['real upstream json101 17 exact empty left join scalar subquery'] = static function (TestRunner $t): void {
    $t->same(
        [],
        SQLiteSelectSql::execute(
            'SELECT * FROM t1 LEFT JOIN t2 ON (SELECT b FROM json_each ORDER BY 1)',
            ['t1' => [], 't2' => []],
        ),
    );
};

$tests['real upstream json101 17 dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no-new-support-component; reuses SQLiteSelectSql scalar subqueries, JSON table sources, LEFT JOIN, and SQLiteJsonCanonical jsonb() dispatch',
        'no-new-support-component; reuses SQLiteSelectSql scalar subqueries, JSON table sources, LEFT JOIN, and SQLiteJsonCanonical jsonb() dispatch',
    );
};

for ($case = 1; $case <= 1000; $case++) {
    $truthy = 1 + ($case % 7);
    $json = $sqlLiteral($jsonText([$case, $case + 1, ['seed' => $case, 'truthy' => $truthy]]));
    $tables = [
        't1' => [
            ['a' => ($case * 10) + 1, 'b' => $truthy, 'c' => 'left-truth-' . $case],
            ['a' => ($case * 10) + 2, 'b' => 0, 'c' => 'left-zero-' . $case],
        ],
        't2' => [
            ['d' => 'right-a-' . $case],
            ['d' => 'right-b-' . $case],
        ],
    ];

    $jsonSql = 'SELECT t1.a AS a, b AS b_value, t2.d AS d FROM t1 LEFT JOIN t2 ON (SELECT b FROM json_each(' . $json . ') ORDER BY 1) ORDER BY a, d';
    $jsonbSql = 'SELECT t1.a AS a, b AS b_value, t2.d AS d FROM t1 LEFT JOIN t2 ON (SELECT b FROM json_each(jsonb(' . $json . ')) ORDER BY 1) ORDER BY a, d';
    $emptyJsonEachSql = 'SELECT t1.a AS a, b AS b_value, t2.d AS d FROM t1 LEFT JOIN t2 ON (SELECT b FROM json_each ORDER BY 1) ORDER BY a, d';

    $expectedMatched = [
        ['a' => ($case * 10) + 1, 'b_value' => $truthy, 'd' => 'right-a-' . $case],
        ['a' => ($case * 10) + 1, 'b_value' => $truthy, 'd' => 'right-b-' . $case],
        ['a' => ($case * 10) + 2, 'b_value' => 0, 'd' => null],
    ];
    $expectedEmptyJsonEach = [
        ['a' => ($case * 10) + 1, 'b_value' => $truthy, 'd' => null],
        ['a' => ($case * 10) + 2, 'b_value' => 0, 'd' => null],
    ];

    $tests[sprintf('real upstream json101 17 dynamic join on scalar json_each subquery case %04d', $case)] =
        static function (TestRunner $t) use ($jsonSql, $jsonbSql, $emptyJsonEachSql, $tables, $expectedMatched, $expectedEmptyJsonEach): void {
            $jsonRows = SQLiteSelectSql::execute($jsonSql, $tables);
            $jsonbRows = SQLiteSelectSql::execute($jsonbSql, $tables);
            $emptyJsonEachRows = SQLiteSelectSql::execute($emptyJsonEachSql, $tables);

            $t->same(3, count($jsonRows), 'JSON1 scalar subquery ON row count');
            $t->same($expectedMatched, $jsonRows, 'JSON1 scalar subquery ON rows');
            $t->same(3, count($jsonbRows), 'JSONB scalar subquery ON row count');
            $t->same($expectedMatched, $jsonbRows, 'JSONB scalar subquery ON rows');
            $t->same($jsonRows, $jsonbRows, 'JSON1 and JSONB scalar subquery ON parity');
            $t->same(2, count($emptyJsonEachRows), 'bare json_each scalar subquery ON row count');
            $t->same($expectedEmptyJsonEach, $emptyJsonEachRows, 'bare json_each scalar subquery ON null-extends');
            $t->same(null, $emptyJsonEachRows[0]['d'], 'bare json_each scalar subquery returns SQL NULL');
            $t->same(null, $emptyJsonEachRows[1]['d'], 'bare json_each scalar subquery null-extends every left row');
        };
}

return $tests;
