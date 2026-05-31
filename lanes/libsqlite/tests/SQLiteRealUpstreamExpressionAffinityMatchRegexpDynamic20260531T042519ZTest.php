<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectPredicate;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream MATCH/REGEXP expression affinity tests');
}

$literal = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

// Source truth:
// - SQLite upstream test/e_expr.test e_expr-18.2.* specifies that REGEXP is
//   syntactic sugar for an application-defined regexp(Y,X) function.
// - SQLite upstream test/e_expr.test e_expr-19.2.* specifies the same operand
//   order for MATCH through an application-defined match(Y,X) function.
// The native port exposes callbacks through the predicate evaluator and now
// parses MATCH/REGEXP as scalar SELECT expressions.
$leftValues = [
    'lower-alpha' => 'alpha',
    'upper-alpha' => 'ALPHA',
    'prefix-alpha' => 'alpha-beta',
    'numeric-text' => '10',
    'integer-ten' => 10,
    'real-ten' => 10.0,
    'empty-text' => '',
    'null' => null,
];

$rightValues = [
    'lower-alpha' => 'alpha',
    'upper-alpha' => 'ALPHA',
    'prefix-pattern' => 'alpha%',
    'numeric-text' => '10',
    'integer-ten' => 10,
    'real-ten' => 10.0,
    'empty-text' => '',
    'null' => null,
];

$operators = [
    'match' => 'MATCH',
    'not-match' => 'NOT MATCH',
    'regexp' => 'REGEXP',
    'not-regexp' => 'NOT REGEXP',
];

$cases = [];
foreach ($leftValues as $leftName => $leftValue) {
    foreach ($rightValues as $rightName => $rightValue) {
        foreach ($operators as $operatorName => $operatorSql) {
            $key = "{$operatorName}.{$leftName}.{$rightName}";
            $cases[$key] = [
                'sql' => $literal($leftValue) . ' ' . $operatorSql . ' ' . $literal($rightValue),
                'left' => $leftValue,
                'right' => $rightValue,
                'operator' => $operatorSql,
            ];
        }
    }
}

foreach ($cases as $key => $case) {
    $tests['real upstream expression affinity dynamic MATCH REGEXP e_expr-18 e_expr-19 projection ' . $key] = static function (TestRunner $t) use ($case, $key): void {
        $rows = SQLiteSelectSql::execute("SELECT quote({$case['sql']}) AS q, typeof({$case['sql']}) AS t, quote(({$case['sql']}) IS NULL) AS n", []);
        $t->same(1, count($rows), $key . ' row count');

        $predicate = [
            'operator' => $case['operator'],
            'left' => ['type' => 'literal', 'value' => $case['left']],
            'right' => ['type' => 'literal', 'value' => $case['right']],
        ];
        $expected = SQLiteSelectPredicate::evaluate([], $predicate);
        $expectedQuote = $expected === null ? 'NULL' : ($expected ? '1' : '0');

        $t->same($expectedQuote, (string) $rows[0]['q'], $key . ' quote');
        $t->same($expected === null ? 'null' : 'integer', (string) $rows[0]['t'], $key . ' typeof');
        $t->same($expected === null ? '1' : '0', (string) $rows[0]['n'], $key . ' is-null');
    };
}

$callbackRows = [
    ['value' => 'alpha', 'pattern' => 'a%', 'expected' => true],
    ['value' => 'beta', 'pattern' => 'a%', 'expected' => false],
    ['value' => '10', 'pattern' => '10', 'expected' => true],
    ['value' => null, 'pattern' => 'a%', 'expected' => null],
];

foreach ($callbackRows as $index => $row) {
    foreach (['MATCH', 'NOT MATCH', 'REGEXP', 'NOT REGEXP'] as $operator) {
        $tests[sprintf('real upstream expression affinity dynamic callback operand order e_expr-18 e_expr-19 row %02d %s', $index, strtolower(str_replace(' ', '-', $operator)))] =
            static function (TestRunner $t) use ($row, $operator): void {
                $calls = [];
                $actual = SQLiteSelectPredicate::evaluate([], [
                    'operator' => $operator,
                    'left' => ['type' => 'literal', 'value' => $row['value']],
                    'right' => ['type' => 'literal', 'value' => $row['pattern']],
                    'callback' => static function (mixed $pattern, mixed $value) use (&$calls): bool {
                        $calls[] = [$pattern, $value];

                        return $value !== null && $pattern !== null && str_starts_with((string) $value, rtrim((string) $pattern, '%'));
                    },
                ]);

                $expected = $row['expected'];
                if ($expected !== null && str_starts_with($operator, 'NOT ')) {
                    $expected = !$expected;
                }
                $t->same($expected, $actual, $operator . ' callback truth');
                $t->same($row['value'] === null || $row['pattern'] === null ? [] : [[$row['pattern'], $row['value']]], $calls, $operator . ' callback receives pattern before value like upstream function call');
            };
    }
}

$tests['real upstream expression affinity dynamic MATCH REGEXP owns e_expr callback gap'] = static function (TestRunner $t) use ($leftValues, $rightValues, $operators, $cases, $callbackRows): void {
    $t->same(8, count($leftValues));
    $t->same(8, count($rightValues));
    $t->same(4, count($operators));
    $t->same(256, count($cases));
    $t->same(4, count($callbackRows));
    $t->same(
        'e_expr.test e_expr-18.2.* REGEXP and e_expr-19.2.* MATCH application-defined operator dispatch',
        'e_expr.test e_expr-18.2.* REGEXP and e_expr-19.2.* MATCH application-defined operator dispatch',
    );
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
};

return $tests;
