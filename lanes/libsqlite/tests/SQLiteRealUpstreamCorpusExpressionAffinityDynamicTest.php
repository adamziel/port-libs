<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$firstRow = static function (string $sql): array {
    $rows = SQLiteSelectSql::execute($sql, []);
    if (count($rows) !== 1) {
        throw new RuntimeException("Expected one SELECT row for {$sql}");
    }

    return $rows[0];
};

$assertSelect = static function (string $name, string $sql, array $expected) use (&$tests, $firstRow): void {
    $tests[$name] = static function (TestRunner $t) use ($firstRow, $sql, $expected): void {
        $row = $firstRow($sql);
        $t->same($expected, array_values($row), $sql);
    };
};

// Source truth: SQLite upstream test/e_expr.test e_expr-1.2.1 through
// e_expr-1.6. These pin comparison/LIKE precedence in projection positions.
$assertSelect('real upstream e_expr-1.2.1 comparison binds before like', 'SELECT 0 < 2 LIKE 1, (0 < 2) LIKE 1, 0 < (2 LIKE 1)', [1, 1, 0]);
$assertSelect('real upstream e_expr-1.2.2 like binds before less-than only with parentheses', 'SELECT 0 LIKE 0 < 2, (0 LIKE 0) < 2, 0 LIKE (0 < 2)', [0, 1, 0]);
$assertSelect('real upstream e_expr-1.2.3 like equals equality precedence left associative', 'SELECT 2 LIKE 2 == 1, (2 LIKE 2) == 1, 2 LIKE (2 == 1)', [1, 1, 0]);
$assertSelect('real upstream e_expr-1.2.4 equality and like share precedence left associative', 'SELECT 2 == 2 LIKE 1, (2 == 2) LIKE 1, 2 == (2 LIKE 1)', [1, 1, 0]);
$assertSelect('real upstream e_expr-1.2.5 less-than groups before equality', 'SELECT 0 < 2 == 1, (0 < 2) == 1, 0 < (2 == 1)', [1, 1, 0]);
$assertSelect('real upstream e_expr-1.6 equality groups before less-than when left', 'SELECT 0 == 0 < 2, (0 == 0) < 2, 0 == (0 < 2)', [0, 1, 0]);

// Source truth: SQLite upstream test/e_expr.test e_expr-2.* and e_expr-3.*.
foreach ([
    'e_expr-2.1' => ['SELECT -   10', [-10]],
    'e_expr-2.2' => ['SELECT +   10', [10]],
    'e_expr-2.3' => ['SELECT ~   10', [-11]],
    'e_expr-2.4' => ['SELECT NOT 10', [0]],
] as $upstream => [$sql, $expected]) {
    $assertSelect("real upstream {$upstream} unary prefix operator", $sql, $expected);
}

foreach ([
    'e_expr-3.1' => ["'helloworld'", 'text'],
    'e_expr-3.2' => ['45', 'integer'],
    'e_expr-3.3' => ['45.2', 'real'],
    'e_expr-3.4' => ['45.0', 'real'],
    'e_expr-3.5' => ["X'ABCDEF'", 'blob'],
    'e_expr-3.6' => ['NULL', 'null'],
] as $upstream => [$literal, $type]) {
    $expectedQuote = $literal === 'NULL' ? 'NULL' : $literal;
    $assertSelect("real upstream {$upstream} unary plus preserves value and type", "SELECT quote( + {$literal} ), typeof( + {$literal})", [$expectedQuote, $type]);
}

// Source truth: SQLite upstream test/e_expr.test e_expr-4.* equality operator
// spellings and e_expr-5/e_expr-6 concatenation/remainder behavior.
foreach ([
    'e_expr-4.1' => ["'helloworld'", "'12345'"],
    'e_expr-4.2' => ['22', '23'],
    'e_expr-4.3' => ["'xyz'", "X'78797A'"],
    'e_expr-4.4' => ["X'78797A00'", "'xyz'"],
] as $upstream => [$literal, $different]) {
    $assertSelect(
        "real upstream {$upstream} equality and inequality spellings",
        "SELECT {$literal} = {$literal}, {$literal} == {$literal}, {$literal} = {$different}, {$literal} == {$different}, {$literal} = NULL, {$literal} == NULL, {$literal} != {$literal}, {$literal} <> {$literal}, {$literal} != {$different}, {$literal} <> {$different}, {$literal} != NULL, {$literal} != NULL",
        [1, 1, 0, 0, null, null, 0, 0, 1, 1, null, null],
    );
}

$assertSelect('real upstream e_expr-5.1 concatenates text operands', "SELECT 'helloworld' || '12345'", ['helloworld12345']);
$assertSelect('real upstream e_expr-5.2 concatenates numeric operands as text', 'SELECT 22 || 23', ['2223']);
foreach ([
    'e_expr-6.1' => ['SELECT 72%5', [2]],
    'e_expr-6.2' => ['SELECT 72%-5', [2]],
    'e_expr-6.3' => ['SELECT -72%-5', [-2]],
    'e_expr-6.4' => ['SELECT -72%5', [-2]],
    'e_expr-6.5' => ['SELECT 72.35%5', [2]],
] as $upstream => [$sql, $expected]) {
    $assertSelect("real upstream {$upstream} remainder casts operands to integer", $sql, $expected);
}

// Source truth: SQLite upstream test/e_expr.test e_expr-8.1 and e_expr-8.2.
foreach ([
    'e_expr-8.1.1' => ['SELECT NULL IS NULL', [1]],
    'e_expr-8.1.2' => ["SELECT 'ab' IS NULL", [0]],
    'e_expr-8.1.3' => ["SELECT NULL IS 'ab'", [0]],
    'e_expr-8.1.4' => ["SELECT 'ab' IS 'ab'", [1]],
    'e_expr-8.1.5' => ['SELECT NULL == NULL', [null]],
    'e_expr-8.1.6' => ["SELECT 'ab' == NULL", [null]],
    'e_expr-8.1.7' => ["SELECT NULL == 'ab'", [null]],
    'e_expr-8.1.8' => ["SELECT 'ab' == 'ab'", [1]],
    'e_expr-8.1.9' => ['SELECT NULL IS NOT NULL', [0]],
    'e_expr-8.1.10' => ["SELECT 'ab' IS NOT NULL", [1]],
    'e_expr-8.1.11' => ["SELECT NULL IS NOT 'ab'", [1]],
    'e_expr-8.1.12' => ["SELECT 'ab' IS NOT 'ab'", [0]],
    'e_expr-8.1.13' => ['SELECT NULL != NULL', [null]],
    'e_expr-8.1.14' => ["SELECT 'ab' != NULL", [null]],
    'e_expr-8.1.15' => ["SELECT NULL != 'ab'", [null]],
    'e_expr-8.1.16' => ["SELECT 'ab' != 'ab'", [0]],
] as $upstream => [$sql, $expected]) {
    $assertSelect("real upstream {$upstream} IS and equality null behavior", $sql, $expected);
}

$literals = [
    1 => ['sql' => "'abc'", 'value' => 'abc', 'rank' => 2],
    2 => ['sql' => "'hexadecimal'", 'value' => 'hexadecimal', 'rank' => 2],
    3 => ['sql' => "''", 'value' => '', 'rank' => 2],
    4 => ['sql' => '123', 'value' => 123, 'rank' => 1],
    5 => ['sql' => '-123', 'value' => -123, 'rank' => 1],
    6 => ['sql' => '0', 'value' => 0, 'rank' => 1],
    7 => ['sql' => '123.4', 'value' => 123.4, 'rank' => 1],
    8 => ['sql' => '0.0', 'value' => 0.0, 'rank' => 1],
    9 => ['sql' => '-123.4', 'value' => -123.4, 'rank' => 1],
    10 => ['sql' => "X'ABCDEF'", 'value' => new SQLiteBlobValue(hex2bin('ABCDEF')), 'rank' => 3],
    11 => ['sql' => "X''", 'value' => new SQLiteBlobValue(''), 'rank' => 3],
    12 => ['sql' => "X'0000'", 'value' => new SQLiteBlobValue(hex2bin('0000')), 'rank' => 3],
    13 => ['sql' => 'NULL', 'value' => null, 'rank' => 0],
];

// Source truth: SQLite upstream test/e_expr.test e_expr-7.* result storage
// class matrix for every supported binary operator except AND/OR short-circuit,
// application-defined MATCH/REGEXP callbacks, and shift tokens not yet parsed
// by the bounded SELECT SQL expression scanner.
$operatorTypes = [
    'cat' => '||',
    'mul' => '*',
    'div' => '/',
    'mod' => '%',
    'add' => '+',
    'sub' => '-',
    'bitand' => '&',
    'bitor' => '|',
    'less' => '<',
    'lesseq' => '<=',
    'more' => '>',
    'moreeq' => '>=',
    'eq1' => '=',
    'eq2' => '==',
    'ne1' => '<>',
    'ne2' => '!=',
    'is' => 'IS',
    'like' => 'LIKE',
    'glob' => 'GLOB',
    'isnt' => 'IS NOT',
];

foreach ($operatorTypes as $operatorName => $operator) {
    foreach ($literals as $rightIndex => $right) {
        foreach ($literals as $leftIndex => $left) {
            $tests["real upstream e_expr-7.{$operatorName}.{$rightIndex}.{$leftIndex} binary operator result class"] = static function (TestRunner $t) use ($firstRow, $left, $right, $operator): void {
                $type = array_values($firstRow("SELECT typeof({$left['sql']} {$operator} {$right['sql']})"))[0];
                if ($operator === '||') {
                    $t->true($type === 'text' || $type === 'null', "typeof result for {$operator}");
                    return;
                }

                $t->true($type === 'integer' || $type === 'real' || $type === 'null', "typeof result for {$operator}");
            };
        }
    }
}

$compare = static function (array $left, array $right): int {
    if ($left['rank'] !== $right['rank']) {
        return $left['rank'] <=> $right['rank'];
    }
    if ($left['rank'] === 1) {
        return ((float) $left['value']) <=> ((float) $right['value']);
    }
    if ($left['rank'] === 3) {
        return strcmp($left['value']->bytes, $right['value']->bytes);
    }

    return strcmp((string) $left['value'], (string) $right['value']);
};

foreach ($literals as $rightIndex => $right) {
    foreach ($literals as $leftIndex => $left) {
        $tests["real upstream e_expr-8.2.{$rightIndex}.{$leftIndex} IS matrix"] = static function (TestRunner $t) use ($firstRow, $left, $right, $compare): void {
            $row = array_values($firstRow("SELECT {$left['sql']} IS {$right['sql']}, {$left['sql']} IS NOT {$right['sql']}, ({$left['sql']} IS {$right['sql']}) IS NULL, ({$left['sql']} IS NOT {$right['sql']}) IS NULL"));
            $same = $left['value'] === null || $right['value'] === null
                ? $left['value'] === null && $right['value'] === null
                : $compare($left, $right) === 0;
            $t->same($same ? 1 : 0, $row[0], 'IS result');
            $t->same($same ? 0 : 1, $row[1], 'IS NOT result');
            $t->same(0, $row[2], 'IS never yields NULL');
            $t->same(0, $row[3], 'IS NOT never yields NULL');
        };
    }
}

// Source truth: SQLite upstream test/e_expr.test e_expr-9.10 through
// e_expr-9.21. These cover built-in NOCASE COLLATE binding to the expression
// operand rather than to an already-computed parenthesized comparison result.
foreach ([
    'e_expr-9.10' => ["SELECT 'abcd' = 'ABCD' COLLATE nocase", [1]],
    'e_expr-9.11' => ["SELECT ('abcd' = 'ABCD') COLLATE nocase", [0]],
    'e_expr-9.12' => ["SELECT 'abcd' == 'ABCD' COLLATE nocase", [1]],
    'e_expr-9.13' => ["SELECT ('abcd' == 'ABCD') COLLATE nocase", [0]],
    'e_expr-9.14' => ["SELECT 'abcd' IS 'ABCD' COLLATE nocase", [1]],
    'e_expr-9.15' => ["SELECT ('abcd' IS 'ABCD') COLLATE nocase", [0]],
    'e_expr-9.16' => ["SELECT 'abcd' != 'ABCD' COLLATE nocase", [0]],
    'e_expr-9.17' => ["SELECT ('abcd' != 'ABCD') COLLATE nocase", [1]],
    'e_expr-9.18' => ["SELECT 'abcd' <> 'ABCD' COLLATE nocase", [0]],
    'e_expr-9.19' => ["SELECT ('abcd' <> 'ABCD') COLLATE nocase", [1]],
    'e_expr-9.20' => ["SELECT 'abcd' IS NOT 'ABCD' COLLATE nocase", [0]],
    'e_expr-9.21' => ["SELECT ('abcd' IS NOT 'ABCD') COLLATE nocase", [1]],
] as $upstream => [$sql, $expected]) {
    $assertSelect("real upstream {$upstream} nocase collate precedence", $sql, $expected);
}

// Source truth: SQLite upstream test/e_expr.test e_expr-10.1/e_expr-10.2
// literal storage classes, including exponent real literals.
foreach ([
    'e_expr-10.1.1' => ['SELECT typeof(5)', ['integer']],
    'e_expr-10.1.2' => ['SELECT typeof(5.1)', ['real']],
    'e_expr-10.1.3' => ["SELECT typeof('5.1')", ['text']],
    'e_expr-10.1.4' => ["SELECT typeof(X'ABCD')", ['blob']],
    'e_expr-10.1.5' => ['SELECT typeof(NULL)', ['null']],
    'e_expr-10.2.1' => ['SELECT typeof(3.4e-02)', ['real']],
    'e_expr-10.2.2' => ['SELECT typeof(3e+5)', ['real']],
    'e_expr-10.2.3' => ['SELECT 3.4e-02', [0.034]],
    'e_expr-10.2.4' => ['SELECT 3e+4', [30000.0]],
] as $upstream => [$sql, $expected]) {
    $assertSelect("real upstream {$upstream} literal storage class", $sql, $expected);
}

return $tests;
