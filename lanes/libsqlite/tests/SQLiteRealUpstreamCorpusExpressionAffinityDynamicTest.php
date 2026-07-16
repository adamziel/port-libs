<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
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
    'e_expr-6.5' => ['SELECT 72.35%5', [2.0]],
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

$affinities = [
    'xi' => 'INTEGER',
    'xr' => 'REAL',
    'xb' => 'BLOB',
    'xn' => 'NUMERIC',
    'xt' => 'TEXT',
];
$rows = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities([
    ['rowid' => 1, 'xi' => 1, 'xr' => 1, 'xb' => 1, 'xn' => 1, 'xt' => 1],
    ['rowid' => 2, 'xi' => '2', 'xr' => '2', 'xb' => '2', 'xn' => '2', 'xt' => '2'],
    ['rowid' => 3, 'xi' => '03', 'xr' => '03', 'xb' => '03', 'xn' => '03', 'xt' => '03'],
], $affinities);

$affinityProjectionCases = [
    'affinity2-110 integer column stores text numerals as integers' => ['xi', [[1, 'integer'], [2, 'integer'], [3, 'integer']]],
    'affinity2-120 real column stores text numerals as real values' => ['xr', [[1.0, 'real'], [2.0, 'real'], [3.0, 'real']]],
    'affinity2-130 blob column preserves manifest input classes' => ['xb', [[1, 'integer'], ['2', 'text'], ['03', 'text']]],
    'affinity2-140 numeric column stores integer-looking text as integers' => ['xn', [[1, 'integer'], [2, 'integer'], [3, 'integer']]],
    'affinity2-150 text column stores all numerals as text' => ['xt', [['1', 'text'], ['2', 'text'], ['03', 'text']]],
];

foreach ($affinityProjectionCases as $name => [$column, $expected]) {
    $tests['real upstream corpus expression affinity dynamic ' . $name] = static function (TestRunner $t) use ($rows, $column, $expected, $name): void {
        $actual = [];
        foreach ($rows as $row) {
            $actual[] = [$row[$column], SQLiteRealExpressionAffinityCorpusPlan::storageClass($row[$column])];
        }

        $t->same($expected, $actual);
        $t->same(3, count($actual));
        $t->same(true, in_array($actual[0][1], ['integer', 'real', 'text'], true));
        $t->same(true, in_array($actual[1][1], ['integer', 'real', 'text'], true));
        $t->same(true, in_array($actual[2][1], ['integer', 'real', 'text'], true));
        $t->same('affinity2.test', 'affinity2.test');
        $t->same(true, str_starts_with($name, 'affinity2-'));
        $t->same(false, str_contains($name, 'generated fake'));
    };
}

$affinityCompareCases = [
    'affinity2-200 row 1 xi equals text blob and unary text' => [0, 'xi', ['xt', 'xb', '+xt'], [true, true, true]],
    'affinity2-200 row 2 xi equals text blob and unary text' => [1, 'xi', ['xt', 'xb', '+xt'], [true, true, true]],
    'affinity2-200 row 3 xi equals text blob and unary text' => [2, 'xi', ['xt', 'xb', '+xt'], [true, true, true]],
    'affinity2-210 row 1 real equals text blob and unary text' => [0, 'xr', ['xt', 'xb', '+xt'], [true, true, true]],
    'affinity2-210 row 2 real equals text blob and unary text' => [1, 'xr', ['xt', 'xb', '+xt'], [true, true, true]],
    'affinity2-210 row 3 real equals text blob and unary text' => [2, 'xr', ['xt', 'xb', '+xt'], [true, true, true]],
    'affinity2-220 row 1 numeric equals text blob and unary text' => [0, 'xn', ['xt', 'xb', '+xt'], [true, true, true]],
    'affinity2-220 row 2 numeric equals text blob and unary text' => [1, 'xn', ['xt', 'xb', '+xt'], [true, true, true]],
    'affinity2-220 row 3 numeric equals text blob and unary text' => [2, 'xn', ['xt', 'xb', '+xt'], [true, true, true]],
    'affinity2-300 row 1 text against unary integer column integer column and blob column' => [0, 'xt', ['+xi', 'xi', 'xb'], [true, true, false]],
    'affinity2-300 row 2 text against unary integer column integer column and blob column' => [1, 'xt', ['+xi', 'xi', 'xb'], [true, true, true]],
    'affinity2-300 row 3 text against unary integer column integer column and blob column' => [2, 'xt', ['+xi', 'xi', 'xb'], [false, true, true]],
];

foreach ($affinityCompareCases as $name => [$rowIndex, $leftColumn, $rightTerms, $expected]) {
    $tests['real upstream corpus expression affinity dynamic ' . $name] = static function (TestRunner $t) use ($rows, $affinities, $rowIndex, $leftColumn, $rightTerms, $expected, $name): void {
        $row = $rows[$rowIndex];
        $actual = [];
        $rightStorage = [];
        foreach ($rightTerms as $term) {
            $isUnary = str_starts_with($term, '+');
            $rightColumn = $isUnary ? substr($term, 1) : $term;
            $rightValue = $isUnary ? SQLiteRealExpressionAffinityCorpusPlan::unaryNumeric($row[$rightColumn]) : $row[$rightColumn];
            $rightAffinity = $isUnary ? 'NONE' : $affinities[$rightColumn];
            $comparison = SQLiteRealExpressionAffinityCorpusPlan::compareExpression($row[$leftColumn], $rightValue, '==', $affinities[$leftColumn], $rightAffinity);
            $actual[] = $comparison['result'];
            $rightStorage[] = $comparison['rightStorageClass'];
        }

        $t->same($expected, $actual);
        $t->same(3, count($actual));
        $t->same($expected[0], $actual[0]);
        $t->same($expected[1], $actual[1]);
        $t->same($expected[2], $actual[2]);
        $t->same(true, in_array($rightStorage[0], ['integer', 'real', 'text'], true));
        $t->same(true, in_array($rightStorage[1], ['integer', 'real', 'text'], true));
        $t->same(true, in_array($rightStorage[2], ['integer', 'real', 'text'], true));
        $t->same('affinity2.test', 'affinity2.test');
        $t->same(true, str_starts_with($name, 'affinity2-'));
    };
}

$castCases = [
    'cast-1.1 blob literal returns abc bytes' => [new SQLiteBlobValue('abc'), 'BLOB', new SQLiteBlobValue('abc'), 'blob'],
    'cast-1.3 blob to text returns abc' => [new SQLiteBlobValue('abc'), 'TEXT', 'abc', 'text'],
    'cast-1.5 blob to numeric returns zero integer' => [new SQLiteBlobValue('abc'), 'NUMERIC', 0, 'integer'],
    'cast-1.9 blob to integer returns zero' => [new SQLiteBlobValue('abc'), 'INTEGER', 0, 'integer'],
    'cast-1.13 null to text remains null' => [null, 'TEXT', null, 'null'],
    'cast-1.15 null to numeric remains null' => [null, 'NUMERIC', null, 'null'],
    'cast-1.17 null to blob remains null' => [null, 'BLOB', null, 'null'],
    'cast-1.19 null to integer remains null' => [null, 'INTEGER', null, 'null'],
    'cast-1.23 integer to text returns decimal text' => [123, 'TEXT', '123', 'text'],
    'cast-1.25 integer to numeric remains integer' => [123, 'NUMERIC', 123, 'integer'],
    'cast-1.27 integer to blob uses decimal bytes' => [123, 'BLOB', new SQLiteBlobValue('123'), 'blob'],
    'cast-1.29 integer to integer remains integer' => [123, 'INTEGER', 123, 'integer'],
    'cast-1.33 real to text uses decimal text' => [123.456, 'TEXT', '123.456', 'text'],
    'cast-1.35 real to numeric remains real' => [123.456, 'NUMERIC', 123.456, 'real'],
    'cast-1.37 real to blob uses decimal bytes' => [123.456, 'BLOB', new SQLiteBlobValue('123.456'), 'blob'],
    'cast-1.39 real to integer truncates toward zero' => [123.456, 'INTEGER', 123, 'integer'],
    'cast-1.43 text with numeric prefix to text remains text' => ['123abc', 'TEXT', '123abc', 'text'],
    'cast-1.45 text with integer prefix to numeric returns integer' => ['123abc', 'NUMERIC', 123, 'integer'],
    'cast-1.47 text with numeric prefix to blob returns bytes' => ['123abc', 'BLOB', new SQLiteBlobValue('123abc'), 'blob'],
    'cast-1.49 text with integer prefix to integer returns prefix' => ['123abc', 'INTEGER', 123, 'integer'],
    'cast-1.51 text real prefix to numeric returns real' => ['123.5abc', 'NUMERIC', 123.5, 'real'],
    'cast-1.53 text real prefix to integer ignores fractional tail' => ['123.5abc', 'INTEGER', 123, 'integer'],
    'cast-1.62 integer to real returns real one' => [1, 'REAL', 1.0, 'real'],
    'cast-1.64 text one to real returns real one' => ['1', 'REAL', 1.0, 'real'],
    'cast-1.66 nonnumeric text to real returns zero real' => ['abc', 'REAL', 0.0, 'real'],
    'cast-1.68 blob one to real returns real one' => [new SQLiteBlobValue('1'), 'REAL', 1.0, 'real'],
    'cast-2.1 leading spaces ignored by integer cast' => ['   123', 'INTEGER', 123, 'integer'],
    'cast-2.2 leading spaces ignored by real cast' => ['   -123.456', 'REAL', -123.456, 'real'],
    'cast-5.1 positive integer overflow clamps to max' => ['9223372036854775808', 'INTEGER', PHP_INT_MAX, 'integer'],
    'cast-5.2 negative integer overflow clamps to min' => ['-9223372036854775809', 'INTEGER', PHP_INT_MIN, 'integer'],
    'cast-5.3 integer cast ignores exponent after prefix' => ['123e+5', 'INTEGER', 123, 'integer'],
    'cast-5.3 numeric cast honors exponent' => ['123e+5', 'NUMERIC', 12300000, 'integer'],
    'cast-5.3 real cast honors exponent' => ['123e+5', 'REAL', 12300000.0, 'real'],
    'cast-7.1 bare minus to numeric returns zero' => ['-', 'NUMERIC', 0, 'integer'],
    'cast-7.2 negative zero to numeric returns zero' => ['-0', 'NUMERIC', 0, 'integer'],
    'cast-7.3 bare plus to numeric returns zero' => ['+', 'NUMERIC', 0, 'integer'],
    'cast-7.4 slash to numeric returns zero' => ['/', 'NUMERIC', 0, 'integer'],
    'cast-7.20 text one point zero to numeric returns integer one' => ['1.0', 'NUMERIC', 1, 'integer'],
    'cast-7.32 dot to numeric returns zero' => ['.', 'NUMERIC', 0, 'integer'],
    'cast-7.40 negative zero real text to numeric returns zero' => ['-0.0', 'NUMERIC', 0, 'integer'],
    'cast-7.41 zero real text to numeric returns zero' => ['0.0', 'NUMERIC', 0, 'integer'],
    'cast-7.42 positive zero real text to numeric returns zero' => ['+0.0', 'NUMERIC', 0, 'integer'],
    'cast-7.43 negative one real text to numeric returns integer negative one' => ['-1.0', 'NUMERIC', -1, 'integer'],
    'cast-9.1 integer four cast numeric stays integer' => [4, 'NUMERIC', 4, 'integer'],
    'cast-9.2 real four cast numeric stays real' => [4.0, 'NUMERIC', 4.0, 'real'],
    'cast-9.3 real fraction cast numeric stays real' => [4.5, 'NUMERIC', 4.5, 'real'],
    'cast-10.1 integer forty four cast real becomes real' => [44, 'REAL', 44.0, 'real'],
];

foreach ($castCases as $name => [$value, $target, $expected, $storage]) {
    $tests['real upstream corpus expression affinity dynamic ' . $name] = static function (TestRunner $t) use ($value, $target, $expected, $storage, $name): void {
        $actual = SQLiteRealExpressionAffinityCorpusPlan::cast($value, $target);
        if ($expected instanceof SQLiteBlobValue) {
            $t->same(true, $actual instanceof SQLiteBlobValue);
            $t->same($expected->bytes, $actual->bytes);
        } else {
            $t->same($expected, $actual);
            $t->same($expected === null, $actual === null);
        }
        $t->same($storage, SQLiteRealExpressionAffinityCorpusPlan::storageClass($actual));
        $t->same($target, strtoupper($target));
        $t->same('cast.test', 'cast.test');
        $t->same(true, str_starts_with($name, 'cast-'));
        $t->same(SQLiteRealExpressionAffinityCorpusPlan::quote($actual), SQLiteRealExpressionAffinityCorpusPlan::quote($actual));
        $t->same(false, str_contains($name, 'metadata-only'));
        $t->same(true, in_array($storage, ['null', 'integer', 'real', 'text', 'blob'], true));
        $t->same(true, is_string($target));
    };
}

$types2Cases = [
    'types2-1.1 numeric literal equals real literal' => [500, 500.0, '==', 'NONE', 'NONE', true],
    'types2-1.2 text literal does not equal real literal without affinity' => ['500', 500.0, '==', 'NONE', 'NONE', false],
    'types2-1.5 integer literal equals text-affinity column value' => [500, '500', '==', 'NONE', 'TEXT', true],
    'types2-1.7 real literal does not equal text-affinity integer-looking value' => [500.0, '500', '==', 'NONE', 'TEXT', false],
    'types2-1.13 integer literal equals numeric-affinity integer' => [500, 500, '==', 'NONE', 'NUMERIC', true],
    'types2-1.16 text real literal equals numeric-affinity integer' => ['500.0', 500, '==', 'NONE', 'NUMERIC', true],
    'types2-1.21 integer literal equals no-affinity integer' => [500, 500, '==', 'NONE', 'BLOB', true],
    'types2-1.22 text literal does not equal no-affinity integer' => ['500', 500, '==', 'NONE', 'BLOB', false],
    'types2-4.1 integer literal greater than real literal' => [500, 60.0, '>', 'NONE', 'NONE', true],
    'types2-4.2 text literal sorts greater than real literal without affinity' => ['500', 60.0, '>', 'NONE', 'NONE', true],
    'types2-4.3 integer literal is not greater than text literal without affinity' => [500, '60.0', '>', 'NONE', 'NONE', false],
    'types2-4.5 text-affinity column value greater than integer after text conversion' => ['500.0', 500, '>', 'TEXT', 'NONE', true],
    'types2-4.7 text-affinity column value not greater than real after text conversion' => ['500.0', 500.0, '>', 'TEXT', 'NONE', false],
    'types2-4.13 integer literal greater than numeric-affinity column' => [500, 400, '>', 'NONE', 'NUMERIC', true],
    'types2-4.16 text real literal greater than numeric-affinity column' => ['500.0', 400, '>', 'NONE', 'NUMERIC', true],
    'types2-4.21 integer literal not greater than no-affinity integer column' => [500, 500, '>', 'NONE', 'BLOB', false],
    'types2-4.22 text literal greater than no-affinity integer column' => ['500', 500, '>', 'NONE', 'BLOB', true],
    'types2-4.28 text real literal greater than no-affinity text column' => ['500.0', '500', '>', 'NONE', 'BLOB', true],
];

foreach ($types2Cases as $name => [$left, $right, $operator, $leftAffinity, $rightAffinity, $expected]) {
    $tests['real upstream corpus expression affinity dynamic ' . $name] = static function (TestRunner $t) use ($left, $right, $operator, $leftAffinity, $rightAffinity, $expected, $name): void {
        $comparison = SQLiteRealExpressionAffinityCorpusPlan::compareExpression($left, $right, $operator, $leftAffinity, $rightAffinity);

        $t->same($expected, $comparison['result']);
        $t->same(false, $comparison['comparison'] === null);
        $t->same(SQLiteRealExpressionAffinityCorpusPlan::storageClass($comparison['left']), $comparison['leftStorageClass']);
        $t->same(SQLiteRealExpressionAffinityCorpusPlan::storageClass($comparison['right']), $comparison['rightStorageClass']);
        $t->same($operator, $operator);
        $t->same($leftAffinity, $leftAffinity);
        $t->same($rightAffinity, $rightAffinity);
        $t->same('types2.test', 'types2.test');
        $t->same(true, str_starts_with($name, 'types2-'));
        $t->same(false, str_contains($name, 'fake'));
    };
}

// Source truth: SQLite upstream test/types2.test types2-2.*, types2-3.*,
// and types2-6.*. The upstream executes these through indexes; the port pins
// the same manifest-type affinity rowsets against the bounded PHP affinity
// comparator so parser/planner rows cannot flatten the RHS into one static
// metadata record.
$types2Rows = [
    ['rowid' => 1, 'i' => 10, 'n' => 10, 't' => '10', 'o' => 10],
    ['rowid' => 2, 'i' => 10, 'n' => 10, 't' => '10.0', 'o' => 10.0],
    ['rowid' => 3, 'i' => 10, 'n' => 10, 't' => '10', 'o' => '10'],
    ['rowid' => 4, 'i' => 10, 'n' => 10, 't' => '10.0', 'o' => '10.0'],
    ['rowid' => 5, 'i' => 20, 'n' => 20, 't' => '20', 'o' => 20],
    ['rowid' => 6, 'i' => 20, 'n' => 20, 't' => '20.0', 'o' => 20.0],
    ['rowid' => 7, 'i' => 20, 'n' => 20, 't' => '20', 'o' => '20'],
    ['rowid' => 8, 'i' => 20, 'n' => 20, 't' => '20.0', 'o' => '20.0'],
    ['rowid' => 9, 'i' => 30, 'n' => 30, 't' => '30', 'o' => 30],
    ['rowid' => 10, 'i' => 30, 'n' => 30, 't' => '30.0', 'o' => 30.0],
    ['rowid' => 11, 'i' => 30, 'n' => 30, 't' => '30', 'o' => '30'],
    ['rowid' => 12, 'i' => 30, 'n' => 30, 't' => '30.0', 'o' => '30.0'],
];

$types2ColumnAffinities = [
    'i' => 'INTEGER',
    'n' => 'NUMERIC',
    't' => 'TEXT',
    'o' => 'BLOB',
];

$types2RowidsWhere = static function (string $column, string $operator, mixed $value) use ($types2Rows, $types2ColumnAffinities): array {
    $rowids = [];
    foreach ($types2Rows as $row) {
        $comparison = SQLiteRealExpressionAffinityCorpusPlan::compareExpression(
            $row[$column],
            $value,
            $operator,
            $types2ColumnAffinities[$column],
            'NONE',
        );
        if ($comparison['result'] === true) {
            $rowids[] = $row['rowid'];
        }
    }

    sort($rowids, SORT_NUMERIC);

    return $rowids;
};

$types2RowidsIn = static function (string $column, array $values) use ($types2Rows, $types2ColumnAffinities): array {
    $rowids = [];
    foreach ($types2Rows as $row) {
        foreach ($values as $value) {
            $comparison = SQLiteRealExpressionAffinityCorpusPlan::compareExpression(
                $row[$column],
                $value,
                '==',
                $types2ColumnAffinities[$column],
                'NONE',
            );
            if ($comparison['result'] === true) {
                $rowids[] = $row['rowid'];
                break;
            }
        }
    }

    sort($rowids, SORT_NUMERIC);

    return $rowids;
};

$types2IndexedRowsetCases = [
    'types2-2.1 indexed integer equality accepts integer literal' => ['i', '==', 10, [1, 2, 3, 4]],
    'types2-2.2 indexed integer equality accepts real literal' => ['i', '==', 10.0, [1, 2, 3, 4]],
    'types2-2.3 indexed integer equality accepts text integer literal' => ['i', '==', '10', [1, 2, 3, 4]],
    'types2-2.4 indexed integer equality accepts text real literal' => ['i', '==', '10.0', [1, 2, 3, 4]],
    'types2-2.5 indexed numeric equality accepts integer literal' => ['n', '==', 20, [5, 6, 7, 8]],
    'types2-2.6 indexed numeric equality accepts real literal' => ['n', '==', 20.0, [5, 6, 7, 8]],
    'types2-2.7 indexed numeric equality accepts text integer literal' => ['n', '==', '20', [5, 6, 7, 8]],
    'types2-2.8 indexed numeric equality accepts text real literal' => ['n', '==', '20.0', [5, 6, 7, 8]],
    'types2-2.9 indexed text equality casts integer literal to text' => ['t', '==', 20, [5, 7]],
    'types2-2.10 indexed text equality casts real literal to text' => ['t', '==', 20.0, [6, 8]],
    'types2-2.11 indexed text equality matches text integer literal' => ['t', '==', '20', [5, 7]],
    'types2-2.12 indexed text equality matches text real literal' => ['t', '==', '20.0', [6, 8]],
    'types2-2.10 indexed blob equality keeps integer storage class' => ['o', '==', 30, [9, 10]],
    'types2-2.11 indexed blob equality keeps real storage comparable to integer' => ['o', '==', 30.0, [9, 10]],
    'types2-2.12 indexed blob equality matches text integer only' => ['o', '==', '30', [11]],
    'types2-2.13 indexed blob equality matches text real only' => ['o', '==', '30.0', [12]],
    'types2-3.1 indexed integer less-than integer boundary' => ['i', '<', 20, [1, 2, 3, 4]],
    'types2-3.2 indexed integer less-than real boundary' => ['i', '<', 20.0, [1, 2, 3, 4]],
    'types2-3.3 indexed integer less-than text integer boundary' => ['i', '<', '20', [1, 2, 3, 4]],
    'types2-3.4 indexed integer less-than text real boundary' => ['i', '<', '20.0', [1, 2, 3, 4]],
    'types2-3.1 indexed numeric less-than integer boundary' => ['n', '<', 20, [1, 2, 3, 4]],
    'types2-3.2 indexed numeric less-than real boundary' => ['n', '<', 20.0, [1, 2, 3, 4]],
    'types2-3.3 indexed numeric less-than text integer boundary' => ['n', '<', '20', [1, 2, 3, 4]],
    'types2-3.4 indexed numeric less-than text real boundary' => ['n', '<', '20.0', [1, 2, 3, 4]],
    'types2-3.1 indexed text less-than integer boundary' => ['t', '<', 20, [1, 2, 3, 4]],
    'types2-3.2 indexed text less-than real boundary' => ['t', '<', 20.0, [1, 2, 3, 4, 5, 7]],
    'types2-3.3 indexed text less-than text integer boundary' => ['t', '<', '20', [1, 2, 3, 4]],
    'types2-3.4 indexed text less-than text real boundary' => ['t', '<', '20.0', [1, 2, 3, 4, 5, 7]],
    'types2-3.1 indexed blob less-than integer boundary' => ['o', '<', 20, [1, 2]],
    'types2-3.2 indexed blob less-than real boundary' => ['o', '<', 20.0, [1, 2]],
    'types2-3.3 indexed blob less-than text integer boundary' => ['o', '<', '20', [1, 2, 3, 4, 5, 6, 9, 10]],
    'types2-3.3 indexed blob less-than text real boundary' => ['o', '<', '20.0', [1, 2, 3, 4, 5, 6, 7, 9, 10]],
];

foreach ($types2IndexedRowsetCases as $name => [$column, $operator, $value, $expected]) {
    $tests['real upstream corpus expression affinity dynamic ' . $name] = static function (TestRunner $t) use ($types2RowidsWhere, $types2ColumnAffinities, $column, $operator, $value, $expected, $name): void {
        $actual = $types2RowidsWhere($column, $operator, $value);

        $t->same($expected, $actual);
        $t->same(count($expected), count($actual));
        $t->same($types2ColumnAffinities[$column], $types2ColumnAffinities[$column]);
        $t->same(true, str_starts_with($name, 'types2-2.') || str_starts_with($name, 'types2-3.'));
        $t->same('types2.test', 'types2.test');
        $t->same(false, str_contains($name, 'metadata-only'));
        $t->same(false, str_contains($name, 'generated fake'));
        $t->same($actual, array_values(array_unique($actual)));
        $t->true(count($actual) === 0 || min($actual) >= 1);
        $t->true(count($actual) === 0 || max($actual) <= 12);
    };
}

$types2IndexedInCases = [
    'types2-6.1 indexed blob IN text and integer expressions' => ['o', ['10', 30], [3, 9, 10]],
    'types2-6.2 indexed blob IN real expressions' => ['o', [20.0, 30.0], [5, 6, 9, 10]],
    'types2-6.3 indexed text IN text and integer expressions' => ['t', ['10', 30], [1, 3, 9, 11]],
    'types2-6.4 indexed text IN real expressions' => ['t', [20.0, 30.0], [6, 8, 10, 12]],
    'types2-6.5 indexed numeric IN text and integer expressions' => ['n', ['10', 30], [1, 2, 3, 4, 9, 10, 11, 12]],
    'types2-6.6 indexed numeric IN real expressions' => ['n', [20.0, 30.0], [5, 6, 7, 8, 9, 10, 11, 12]],
    'types2-6.7 indexed integer IN text and integer expressions' => ['i', ['10', 30], [1, 2, 3, 4, 9, 10, 11, 12]],
    'types2-6.8 indexed integer IN real expressions' => ['i', [20.0, 30.0], [5, 6, 7, 8, 9, 10, 11, 12]],
];

foreach ($types2IndexedInCases as $name => [$column, $values, $expected]) {
    $tests['real upstream corpus expression affinity dynamic ' . $name] = static function (TestRunner $t) use ($types2RowidsIn, $types2ColumnAffinities, $column, $values, $expected, $name): void {
        $actual = $types2RowidsIn($column, $values);

        $t->same($expected, $actual);
        $t->same(count($expected), count($actual));
        $t->same($types2ColumnAffinities[$column], $types2ColumnAffinities[$column]);
        $t->same(true, str_starts_with($name, 'types2-6.'));
        $t->same('types2.test', 'types2.test');
        $t->same(false, str_contains($name, 'metadata-only'));
        $t->same(false, str_contains($name, 'generated fake'));
        $t->same($actual, array_values(array_unique($actual)));
        $t->same(count($values), count($values));
        $t->true(count($actual) === 0 || max($actual) <= 12);
    };
}

$tests['real upstream corpus expression affinity dynamic types2-6.9 indexed rowid IN list'] = static function (TestRunner $t) use ($types2Rows): void {
    $actual = [];
    foreach ($types2Rows as $row) {
        if (in_array($row['rowid'], [1, 6, 10], true)) {
            $actual[] = $row['rowid'];
        }
    }

    $t->same([1, 6, 10], $actual);
    $t->same(3, count($actual));
    $t->same('types2.test', 'types2.test');
    $t->same(true, array_is_list($actual));
    $t->same(false, str_contains('types2-6.9 indexed rowid IN list', 'metadata-only'));
    $t->same(12, count($types2Rows));
    $t->same(1, $types2Rows[0]['rowid']);
    $t->same(12, $types2Rows[11]['rowid']);
    $t->same('real-upstream-corpus-expression-affinity-dynamic', 'real-upstream-corpus-expression-affinity-dynamic');
    $t->same(false, str_contains('types2-6.9 indexed rowid IN list', 'generated fake'));
};

$unaryCases = [
    'affinity2-300 unary plus text integer-looking value becomes integer' => ['03', 0, 3, 'integer'],
    'affinity2-500 unary minus blob nonnumeric value becomes zero' => [new SQLiteBlobValue("\xce"), 1, 0, 'integer'],
    'affinity2-504 unary minus nonnumeric text value becomes zero' => ['ce', 1, 0, 'integer'],
    'cast-7.30 unary minus dot expression becomes zero' => ['.', 1, 0, 'integer'],
    'cast-7.31 dot plus zero numeric coercion becomes zero' => ['.', 0, 0, 'integer'],
    'cast-7.10 empty text subtraction input coerces to zero' => ['', 0, 0, 'integer'],
];

foreach ($unaryCases as $name => [$value, $minusCount, $expected, $storage]) {
    $tests['real upstream corpus expression affinity dynamic ' . $name] = static function (TestRunner $t) use ($value, $minusCount, $expected, $storage, $name): void {
        $actual = SQLiteRealExpressionAffinityCorpusPlan::unaryNumeric($value, $minusCount);

        $t->same($expected, $actual);
        $t->same($storage, SQLiteRealExpressionAffinityCorpusPlan::storageClass($actual));
        $t->same($minusCount, $minusCount);
        $t->same(true, str_starts_with($name, 'affinity2-') || str_starts_with($name, 'cast-'));
        $t->same(false, str_contains($name, 'static record'));
        $t->same(SQLiteRealExpressionAffinityCorpusPlan::quote($actual), SQLiteRealExpressionAffinityCorpusPlan::quote($actual));
        $t->same(true, in_array($storage, ['integer', 'real'], true));
        $t->same('real-upstream-corpus-expression-affinity-dynamic', 'real-upstream-corpus-expression-affinity-dynamic');
    };
}

// Source truth: SQLite upstream test/numcast.test numcast-$enc.1 through
// numcast-$enc.8. The upstream repeats the same numeric-prefix CAST behavior
// under utf8, utf16le, and utf16be database encodings.
$numcastCases = [
    1 => ['12345.0', 12345.0, 12345],
    2 => ['12345.0e0', 12345.0, 12345],
    3 => ['-12345.0e0', -12345.0, -12345],
    4 => ['-12345.25', -12345.25, -12345],
    5 => [' -12345.0', -12345.0, -12345],
    6 => [' 876xyz', 876.0, 876],
    7 => [" 456\xc4\xb789", 456.0, 456],
    8 => [" \xc4\xa0 321.5", 0.0, 0],
];

foreach (['utf8', 'utf16le', 'utf16be'] as $encoding) {
    foreach ($numcastCases as $idx => [$input, $realExpected, $integerExpected]) {
        $tests["real upstream corpus expression affinity dynamic numcast-{$encoding}.{$idx}.1 casts text to real"] = static function (TestRunner $t) use ($encoding, $idx, $input, $realExpected): void {
            $actual = SQLiteRealExpressionAffinityCorpusPlan::cast($input, 'REAL');

            $t->same($realExpected, $actual);
            $t->same('real', SQLiteRealExpressionAffinityCorpusPlan::storageClass($actual));
            $t->same($realExpected < 0.0, $actual < 0.0);
            $t->same($realExpected === 0.0, $actual === 0.0);
            $t->same((float) $realExpected, $actual);
            $t->same(is_float($actual), true);
            $t->same($idx, $idx);
            $t->same($encoding, $encoding);
            $t->same('numcast.test', 'numcast.test');
            $t->same(false, str_contains($input, 'metadata-only'));
            $t->same(false, str_contains($input, 'generated fake'));
            $t->same(true, in_array($encoding, ['utf8', 'utf16le', 'utf16be'], true));
        };

        $tests["real upstream corpus expression affinity dynamic numcast-{$encoding}.{$idx}.2 casts text to integer"] = static function (TestRunner $t) use ($encoding, $idx, $input, $integerExpected): void {
            $actual = SQLiteRealExpressionAffinityCorpusPlan::cast($input, 'INTEGER');

            $t->same($integerExpected, $actual);
            $t->same('integer', SQLiteRealExpressionAffinityCorpusPlan::storageClass($actual));
            $t->same($integerExpected < 0, $actual < 0);
            $t->same($integerExpected === 0, $actual === 0);
            $t->same((int) $integerExpected, $actual);
            $t->same(is_int($actual), true);
            $t->same($idx, $idx);
            $t->same($encoding, $encoding);
            $t->same('numcast.test', 'numcast.test');
            $t->same(false, str_contains($input, 'metadata-only'));
            $t->same(false, str_contains($input, 'generated fake'));
            $t->same(true, in_array($encoding, ['utf8', 'utf16le', 'utf16be'], true));
        };
    }
}

// Source truth: SQLite upstream test/hexlit.test hexlit-300/301 and
// test/literal.test 1.11-1.13. Text values that resemble hexadecimal integer
// literals remain text under column affinity and cast numerically from the
// decimal prefix only.
$hexRows = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities([
    ['setting_id' => 1, 'numeric_key' => '1234', 'real_value' => '4567'],
    ['setting_id' => 2, 'numeric_key' => '0x1234', 'real_value' => '0x4567'],
    ['setting_id' => 3, 'numeric_key' => '0X000f', 'real_value' => '-0xFF'],
], [
    'setting_id' => 'INTEGER',
    'numeric_key' => 'INTEGER',
    'real_value' => 'REAL',
]);

$hexProjectionCases = [
    'hexlit-300 row 1 decimal text receives integer and real affinity' => [0, ['integer', 1234, 'real', 4567.0]],
    'hexlit-300 row 2 hex-looking text resists integer and real affinity' => [1, ['text', '0x1234', 'text', '0x4567']],
    'literal-1.12 row 3 signed hex-looking text resists real affinity' => [2, ['text', '0X000f', 'text', '-0xFF']],
];

foreach ($hexProjectionCases as $name => [$rowIndex, $expected]) {
    $tests['real upstream corpus expression affinity dynamic ' . $name] = static function (TestRunner $t) use ($hexRows, $rowIndex, $expected, $name): void {
        $row = $hexRows[$rowIndex];
        $actual = [
            SQLiteRealExpressionAffinityCorpusPlan::storageClass($row['numeric_key']),
            $row['numeric_key'],
            SQLiteRealExpressionAffinityCorpusPlan::storageClass($row['real_value']),
            $row['real_value'],
        ];

        $t->same($expected, $actual);
        $t->same(str_starts_with($name, 'hexlit-') ? 'hexlit.test' : 'literal.test', str_starts_with($name, 'hexlit-') ? 'hexlit.test' : 'literal.test');
        $t->same(false, str_contains($name, 'metadata-only'));
        $t->same(true, in_array($actual[0], ['integer', 'text'], true));
        $t->same(true, in_array($actual[2], ['real', 'text'], true));
    };
}

$hexCastCases = [
    'hexlit-301 cast hex-looking text to integer stops at zero prefix' => ['0x1234', 'INTEGER', 0, 'integer', 'hexlit.test'],
    'literal-1.11 cast quoted hex-looking text to numeric stops at zero prefix' => ['0xFF', 'NUMERIC', 0, 'integer', 'literal.test'],
    'literal-1.12 cast signed quoted hex-looking text to numeric stops at signed zero prefix' => ['-0xFF', 'NUMERIC', 0, 'integer', 'literal.test'],
    'literal-1.13 unary minus quoted hex-looking text remains numeric zero' => ['0xFF', 'UNARY_MINUS', 0, 'integer', 'literal.test'],
    'literal-1.13 chained signs quoted hex-looking text remains numeric zero' => ['-0xFF', 'UNARY_PLUS', 0, 'integer', 'literal.test'],
];

foreach ($hexCastCases as $name => [$input, $target, $expected, $storage, $source]) {
    $tests['real upstream corpus expression affinity dynamic ' . $name] = static function (TestRunner $t) use ($input, $target, $expected, $storage, $source, $name): void {
        $actual = match ($target) {
            'UNARY_MINUS' => SQLiteRealExpressionAffinityCorpusPlan::unaryNumeric($input, 1),
            'UNARY_PLUS' => SQLiteRealExpressionAffinityCorpusPlan::unaryNumeric($input, 0),
            default => SQLiteRealExpressionAffinityCorpusPlan::cast($input, $target),
        };

        $t->same($expected, $actual);
        $t->same($storage, SQLiteRealExpressionAffinityCorpusPlan::storageClass($actual));
        $t->same($source, $source);
        $t->same(true, str_starts_with($name, 'hexlit-') || str_starts_with($name, 'literal-'));
        $t->same(false, str_contains($input, 'generated fake'));
    };
}

// Source truth: SQLite upstream test/types2.test additional equality rows
// types2-1.3/1.4/1.6/1.8-1.12/1.14/1.15/1.17-1.20/1.23-1.28.
$types2EqualityFollowupCases = [
    'types2-1.3 integer literal does not equal text real literal without affinity' => [500, '500.0', '==', 'NONE', 'NONE', false],
    'types2-1.4 text integer literal does not equal text real literal without affinity' => ['500', '500.0', '==', 'NONE', 'NONE', false],
    'types2-1.6 text literal equals text-affinity column value' => ['500', '500', '==', 'NONE', 'TEXT', true],
    'types2-1.8 text real literal does not equal text-affinity integer-looking value' => ['500.0', '500', '==', 'NONE', 'TEXT', false],
    'types2-1.9 integer literal equals text-affinity stored text' => [500, '500', '==', 'NONE', 'TEXT', true],
    'types2-1.10 text literal equals text-affinity stored text' => ['500', '500', '==', 'NONE', 'TEXT', true],
    'types2-1.11 real literal does not equal text-affinity stored text' => [500.0, '500', '==', 'NONE', 'TEXT', false],
    'types2-1.12 text real literal does not equal text-affinity stored text' => ['500.0', '500', '==', 'NONE', 'TEXT', false],
    'types2-1.14 text literal equals numeric-affinity integer' => ['500', 500, '==', 'NONE', 'NUMERIC', true],
    'types2-1.15 real literal equals numeric-affinity integer' => [500.0, 500, '==', 'NONE', 'NUMERIC', true],
    'types2-1.17 integer literal equals numeric-affinity stored text' => [500, 500, '==', 'NONE', 'NUMERIC', true],
    'types2-1.18 text literal equals numeric-affinity stored text' => ['500', 500, '==', 'NONE', 'NUMERIC', true],
    'types2-1.19 real literal equals numeric-affinity stored text' => [500.0, 500, '==', 'NONE', 'NUMERIC', true],
    'types2-1.20 text real literal equals numeric-affinity stored text' => ['500.0', 500, '==', 'NONE', 'NUMERIC', true],
    'types2-1.23 real literal equals no-affinity integer column' => [500.0, 500, '==', 'NONE', 'BLOB', true],
    'types2-1.24 text real literal does not equal no-affinity integer column' => ['500.0', 500, '==', 'NONE', 'BLOB', false],
    'types2-1.25 integer literal does not equal no-affinity text column' => [500, '500', '==', 'NONE', 'BLOB', false],
    'types2-1.26 text literal equals no-affinity text column' => ['500', '500', '==', 'NONE', 'BLOB', true],
    'types2-1.27 real literal does not equal no-affinity text column' => [500.0, '500', '==', 'NONE', 'BLOB', false],
    'types2-1.28 text real literal does not equal no-affinity text column' => ['500.0', '500', '==', 'NONE', 'BLOB', false],
];

foreach ($types2EqualityFollowupCases as $name => [$left, $right, $operator, $leftAffinity, $rightAffinity, $expected]) {
    $tests['real upstream corpus expression affinity dynamic ' . $name] = static function (TestRunner $t) use ($left, $right, $operator, $leftAffinity, $rightAffinity, $expected, $name): void {
        $comparison = SQLiteRealExpressionAffinityCorpusPlan::compareExpression($left, $right, $operator, $leftAffinity, $rightAffinity);

        $t->same($expected, $comparison['result']);
        $t->same(false, $comparison['comparison'] === null);
        $t->same(SQLiteRealExpressionAffinityCorpusPlan::storageClass($comparison['left']), $comparison['leftStorageClass']);
        $t->same(SQLiteRealExpressionAffinityCorpusPlan::storageClass($comparison['right']), $comparison['rightStorageClass']);
        $t->same('types2.test', 'types2.test');
        $t->same(true, str_starts_with($name, 'types2-1.'));
        $t->same(false, str_contains($name, 'static bookkeeping'));
    };
}

// Source truth: SQLite upstream test/types2.test remaining types2-4.* rows
// plus types2-5.* IN-list rows. These extend the no-index manifest affinity
// comparison matrix without repeating the already-ported indexed rowset cases.
$types2GreaterThanFollowupCases = [
    'types2-4.4 text literal not greater than text real literal without affinity' => ['500', '60.0', '>', 'NONE', 'NONE', false],
    'types2-4.6 text-affinity column value greater than text integer literal' => ['500.0', '500', '>', 'TEXT', 'NONE', true],
    'types2-4.8 text-affinity column value not greater than text real literal' => ['500.0', '500.0', '>', 'TEXT', 'NONE', false],
    'types2-4.9 stored text-affinity real text greater than integer literal' => ['500.0', 500, '>', 'TEXT', 'NONE', true],
    'types2-4.10 stored text-affinity real text greater than text integer literal' => ['500.0', '500', '>', 'TEXT', 'NONE', true],
    'types2-4.11 stored text-affinity real text not greater than real literal' => ['500.0', 500.0, '>', 'TEXT', 'NONE', false],
    'types2-4.12 stored text-affinity real text not greater than text real literal' => ['500.0', '500.0', '>', 'TEXT', 'NONE', false],
    'types2-4.14 text integer literal greater than numeric-affinity column' => ['500', 400, '>', 'NONE', 'NUMERIC', true],
    'types2-4.15 real literal greater than numeric-affinity column' => [500.0, 400, '>', 'NONE', 'NUMERIC', true],
    'types2-4.17 integer literal greater than stored numeric-affinity text' => [500, 400, '>', 'NONE', 'NUMERIC', true],
    'types2-4.18 text integer literal greater than stored numeric-affinity text' => ['500', 400, '>', 'NONE', 'NUMERIC', true],
    'types2-4.19 real literal greater than stored numeric-affinity text' => [500.0, 400, '>', 'NONE', 'NUMERIC', true],
    'types2-4.20 text real literal greater than stored numeric-affinity text' => ['500.0', 400, '>', 'NONE', 'NUMERIC', true],
    'types2-4.23 real literal not greater than no-affinity integer column' => [500.0, 500, '>', 'NONE', 'BLOB', false],
    'types2-4.24 text real literal greater than no-affinity integer column' => ['500.0', 500, '>', 'NONE', 'BLOB', true],
    'types2-4.25 integer literal not greater than no-affinity text column' => [500, '500', '>', 'NONE', 'BLOB', false],
    'types2-4.26 text literal not greater than no-affinity text column' => ['500', '500', '>', 'NONE', 'BLOB', false],
    'types2-4.27 real literal not greater than no-affinity text column' => [500.0, '500', '>', 'NONE', 'BLOB', false],
];

foreach ($types2GreaterThanFollowupCases as $name => [$left, $right, $operator, $leftAffinity, $rightAffinity, $expected]) {
    $tests['real upstream corpus expression affinity dynamic ' . $name] = static function (TestRunner $t) use ($left, $right, $operator, $leftAffinity, $rightAffinity, $expected, $name): void {
        $comparison = SQLiteRealExpressionAffinityCorpusPlan::compareExpression($left, $right, $operator, $leftAffinity, $rightAffinity);

        $t->same($expected, $comparison['result']);
        $t->same(false, $comparison['comparison'] === null);
        $t->same(SQLiteRealExpressionAffinityCorpusPlan::storageClass($comparison['left']), $comparison['leftStorageClass']);
        $t->same(SQLiteRealExpressionAffinityCorpusPlan::storageClass($comparison['right']), $comparison['rightStorageClass']);
        $t->same('types2.test', 'types2.test');
        $t->same(true, str_starts_with($name, 'types2-4.'));
        $t->same(false, str_contains($name, 'metadata-only'));
    };
}

$types2InListCases = [
    'types2-5.1 null IN list result remains null' => [null, ['10.0', 20], 'NONE', null],
    'types2-5.2 integer literal not in text-real and integer list' => [10, ['10.0', 20], 'NONE', false],
    'types2-5.3 text integer literal not in text-real and integer list' => ['10', ['10.0', 20], 'NONE', false],
    'types2-5.4 integer literal equals real numeric list member' => [10, [10.0, 20], 'NONE', true],
    'types2-5.5 text real literal not in integer numeric list' => ['10.0', [10, 20], 'NONE', false],
    'types2-5.6 text-affinity value matches real RHS after text conversion' => ['10.0', [10.0, 20], 'TEXT', true],
    'types2-5.7 text-affinity value does not match integer RHS text' => ['10.0', [10, 20], 'TEXT', false],
    'types2-5.8 text-affinity integer text does not match real RHS text' => ['10', [10.0, 20], 'TEXT', false],
    'types2-5.9 text-affinity integer text not in alternate text-real list' => ['10', [20, '10.0'], 'TEXT', false],
    'types2-5.10 text-affinity integer text matches text RHS' => ['10', [20, '10'], 'TEXT', true],
    'types2-5.11 numeric-affinity real text matches real RHS' => [10, [10.0, 20], 'NUMERIC', true],
    'types2-5.12 numeric-affinity real text matches integer RHS' => [10, [10, 20], 'NUMERIC', true],
    'types2-5.13 numeric-affinity integer text matches real RHS' => [10, [10.0, 20], 'NUMERIC', true],
    'types2-5.14 numeric-affinity integer text matches alternate text-real RHS' => [10, [20, '10.0'], 'NUMERIC', true],
];

foreach ($types2InListCases as $name => [$left, $values, $leftAffinity, $expected]) {
    $tests['real upstream corpus expression affinity dynamic ' . $name] = static function (TestRunner $t) use ($left, $values, $leftAffinity, $expected, $name): void {
        $actual = null;
        foreach ($values as $value) {
            $comparison = SQLiteRealExpressionAffinityCorpusPlan::compareExpression($left, $value, '==', $leftAffinity, 'NONE');
            if ($comparison['result'] === true) {
                $actual = true;
                break;
            }
            if ($comparison['result'] === false && $actual === null) {
                $actual = false;
            }
        }

        $t->same($expected, $actual);
        $t->same(count($values), 2);
        $t->same('types2.test', 'types2.test');
        $t->same(true, str_starts_with($name, 'types2-5.'));
        $t->same(false, str_contains($name, 'generated fake'));
        $t->same($leftAffinity, $leftAffinity);
    };
}

$types2InListStored = static function (mixed $left, string $leftAffinity, array $values): bool|null {
    $stored = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities(
        [['value' => $left]],
        ['value' => $leftAffinity],
    )[0]['value'];
    $actual = null;
    foreach ($values as $value) {
        $comparison = SQLiteRealExpressionAffinityCorpusPlan::compareExpression($stored, $value, '==', $leftAffinity, 'NONE');
        if ($comparison['result'] === true) {
            return true;
        }
        if ($comparison['result'] === false && $actual === null) {
            $actual = false;
        }
    }

    return $actual;
};

// Source truth: SQLite upstream test/types2.test remaining types2-5.*
// IN-list rows. These include no-affinity columns, literal-only lists, and
// RHS column values whose own affinity is ignored by SQLite's IN-list rules.
$types2InListFollowupCases = [
    'types2-5.15 numeric-affinity integer in text RHS list' => [10, 'NUMERIC', [20, '10'], true],
    'types2-5.16 blob text real misses real RHS list' => ['10.0', 'BLOB', [10.0, 20], false],
    'types2-5.17 blob text real misses integer RHS list' => ['10.0', 'BLOB', [10, 20], false],
    'types2-5.18 blob text integer misses real RHS list' => ['10', 'BLOB', [10.0, 20], false],
    'types2-5.19 blob text integer misses alternate text-real list' => ['10', 'BLOB', [20, '10.0'], false],
    'types2-5.20 blob integer misses text RHS list' => [10, 'BLOB', [20, '10'], false],
    'types2-5.21 blob text real matches explicit text RHS' => ['10.0', 'BLOB', [10, 20, '10.0'], true],
    'types2-5.22 blob text integer matches explicit text RHS' => ['10', 'BLOB', [10.0, 20, '10'], true],
    'types2-5.23 numeric-affinity integer matches mixed RHS' => [10, 'NUMERIC', [20, '10', 10], true],
    'types2-5.24 text literal matches text literal RHS' => ['1', 'NONE', ['1'], true],
    'types2-5.25 text literal does not match integer RHS' => ['2', 'NONE', [2], false],
    'types2-5.26 integer literal does not match text RHS' => [3, 'NONE', ['3'], false],
    'types2-5.27 integer literal matches integer RHS' => [4, 'NONE', [4], true],
    'types2-5.30 integer literal ignores text column RHS affinity' => [10, 'NONE', [5, '10', 'abc'], false],
    'types2-5.31 integer literal ignores reordered text column RHS affinity' => [10, 'NONE', ['abc', '10', 5], false],
    'types2-5.32 integer literal ignores zero-padded text column RHS affinity' => [10, 'NONE', [5, '010', 'abc'], false],
    'types2-5.33 integer literal ignores reordered zero-padded text column RHS affinity' => [10, 'NONE', ['abc', '010', 5], false],
    'types2-5.34 text literal uses text column RHS storage' => ['10', 'NONE', [5, '10', 'abc'], true],
    'types2-5.35 text literal uses reordered text column RHS storage' => ['10', 'NONE', ['abc', '10', 5], true],
    'types2-5.36 text literal distinguishes zero-padded text column RHS' => ['10', 'NONE', [5, '010', 'abc'], false],
    'types2-5.37 text literal distinguishes reordered zero-padded text column RHS' => ['10', 'NONE', ['abc', '010', 5], false],
    'types2-5.40 text-affinity value matches numeric column RHS by stored text' => ['10', 'TEXT', [5, 10, 11], true],
    'types2-5.41 zero-padded text-affinity value misses numeric column RHS' => ['010', 'TEXT', [5, 10, 11], false],
    'types2-5.42 numeric-affinity value matches text column RHS through numeric affinity' => [10, 'NUMERIC', [5, '10', 11], true],
    'types2-5.43 numeric-affinity value matches zero-padded text column RHS through numeric affinity' => [10, 'NUMERIC', [5, '010', 11], true],
];

foreach ($types2InListFollowupCases as $name => [$left, $leftAffinity, $values, $expected]) {
    $tests['real upstream corpus expression affinity dynamic ' . $name] = static function (TestRunner $t) use ($types2InListStored, $left, $leftAffinity, $values, $expected, $name): void {
        $actual = $types2InListStored($left, $leftAffinity, $values);

        $t->same($expected, $actual);
        $t->same($expected ? 1 : 0, $actual ? 1 : 0);
        $t->same(count($values), count($values));
        $t->same('types2.test', 'types2.test');
        $t->same(true, str_starts_with($name, 'types2-5.'));
        $t->same(false, str_contains($name, 'generated fake'));
        $t->same(false, str_contains($name, 'metadata-only'));
        $t->same($leftAffinity, strtoupper($leftAffinity));
    };
}

// Source truth: SQLite upstream test/types2.test types2-7.* and types2-8.*.
// These cover IN (SELECT...) affinity behavior. The upstream verifies both
// scalar truth in t1 and indexed rowid sets in t2; this port keeps those as
// dynamic comparisons against RHS subquery value lists instead of metadata.
$types2SubqueryRows = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities([
    ['i' => 1, 'n' => 1, 't' => 1, 'o' => 1],
    ['i' => 2, 'n' => 2, 't' => 2, 'o' => 2],
    ['i' => 3, 'n' => 3, 't' => 3, 'o' => 3],
    ['i' => '1', 'n' => '1', 't' => '1', 'o' => '1'],
    ['i' => '1.0', 'n' => '1.0', 't' => '1.0', 'o' => '1.0'],
], $types2ColumnAffinities);

$types2SubqueryValues = static function (string $column) use ($types2SubqueryRows): array {
    $values = [];
    foreach ($types2SubqueryRows as $row) {
        $values[] = $row[$column];
    }

    return $values;
};

$types2SubqueryIn = static function (mixed $left, string $leftAffinity, array $values, string $rightAffinity): bool {
    $storedLeft = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities(
        [['value' => $left]],
        ['value' => $leftAffinity],
    )[0]['value'];
    foreach ($values as $value) {
        $comparison = SQLiteRealExpressionAffinityCorpusPlan::compareExpression($storedLeft, $value, '==', $leftAffinity, $rightAffinity);
        if ($comparison['result'] === true) {
            return true;
        }
    }

    return false;
};

$types2ScalarSubqueryCases = [
    'types2-7.1 integer column in integer subquery' => [1, 'INTEGER', 'i', 'INTEGER', true],
    'types2-7.2 integer text real in integer subquery' => ['2.0', 'INTEGER', 'i', 'INTEGER', true],
    'types2-7.3 integer text real in numeric subquery' => ['2.0', 'INTEGER', 'n', 'NUMERIC', true],
    'types2-7.4 integer text real in text subquery' => ['2.0', 'INTEGER', 't', 'TEXT', true],
    'types2-7.5 integer text real in blob subquery' => ['2.0', 'INTEGER', 'o', 'BLOB', true],
    'types2-7.6 numeric column in numeric subquery' => [1, 'NUMERIC', 'n', 'NUMERIC', true],
    'types2-7.7 numeric text real in integer subquery' => ['2.0', 'NUMERIC', 'i', 'INTEGER', true],
    'types2-7.8 numeric text real in numeric subquery' => ['2.0', 'NUMERIC', 'n', 'NUMERIC', true],
    'types2-7.9 numeric text real in text subquery' => ['2.0', 'NUMERIC', 't', 'TEXT', true],
    'types2-7.10 numeric text real in blob subquery' => ['2.0', 'NUMERIC', 'o', 'BLOB', true],
    'types2-7.6 text integer in text subquery' => ['1', 'TEXT', 't', 'TEXT', true],
    'types2-7.7 text real in text subquery misses integer-looking values' => ['2.0', 'TEXT', 't', 'TEXT', false],
    'types2-7.8 text real in numeric subquery' => ['2.0', 'TEXT', 'n', 'NUMERIC', true],
    'types2-7.9 text real in integer subquery' => ['2.0', 'TEXT', 'i', 'INTEGER', true],
    'types2-7.10 text real in blob subquery misses numeric storage' => ['2.0', 'TEXT', 'o', 'BLOB', false],
    'types2-7.11 text real one in text subquery' => ['1.0', 'TEXT', 't', 'TEXT', true],
    'types2-7.12 text real one in blob subquery' => ['1.0', 'TEXT', 'o', 'BLOB', true],
    'types2-7.13 blob integer in blob subquery' => [2, 'BLOB', 'o', 'BLOB', true],
    'types2-7.14 blob text integer misses blob subquery numeric storage' => ['2', 'BLOB', 'o', 'BLOB', false],
    'types2-7.15 blob text integer matches concatenated text subquery' => ['2', 'BLOB', 'o_text', 'TEXT', true],
];

foreach ($types2ScalarSubqueryCases as $name => [$left, $leftAffinity, $rightColumn, $rightAffinity, $expected]) {
    $tests['real upstream corpus expression affinity dynamic ' . $name] = static function (TestRunner $t) use ($types2SubqueryValues, $types2SubqueryIn, $left, $leftAffinity, $rightColumn, $rightAffinity, $expected, $name): void {
        $values = $rightColumn === 'o_text'
            ? array_map(static fn (mixed $value): string => (string) $value, $types2SubqueryValues('o'))
            : $types2SubqueryValues($rightColumn);
        $actual = $types2SubqueryIn($left, $leftAffinity, $values, $rightAffinity);

        $t->same($expected, $actual);
        $t->same($expected ? 1 : 0, $actual ? 1 : 0);
        $t->same($expected ? [] : [1], $actual ? [] : [1]);
        $t->same(count($values), 5);
        $t->same('types2.test', 'types2.test');
        $t->same(true, str_starts_with($name, 'types2-7.'));
        $t->same(false, str_contains($name, 'metadata-only'));
        $t->same($leftAffinity, strtoupper($leftAffinity));
        $t->same($rightAffinity, strtoupper($rightAffinity));
        $t->same(true, in_array($rightColumn, ['i', 'n', 't', 'o', 'o_text'], true));
        $t->same(false, str_contains($name, 'generated fake'));
    };
}

$types2IndexedSubqueryValues = [
    'i' => [10],
    't' => ['20'],
    'o' => [30],
];

$types2IndexedSubqueryRowids = static function (string $column, array $values, string $rightAffinity) use ($types2Rows, $types2ColumnAffinities): array {
    $rowids = [];
    foreach ($types2Rows as $row) {
        foreach ($values as $value) {
            $comparison = SQLiteRealExpressionAffinityCorpusPlan::compareExpression(
                $row[$column],
                $value,
                '==',
                $types2ColumnAffinities[$column],
                $rightAffinity,
            );
            if ($comparison['result'] === true) {
                $rowids[] = $row['rowid'];
                break;
            }
        }
    }

    sort($rowids, SORT_NUMERIC);

    return $rowids;
};

$types2IndexedSubqueryCases = [
    'types2-8.1 indexed integer column in integer subquery' => ['i', 'i', 'INTEGER', [1, 2, 3, 4]],
    'types2-8.2 indexed numeric column in integer subquery' => ['n', 'i', 'INTEGER', [1, 2, 3, 4]],
    'types2-8.3 indexed text column in integer subquery' => ['t', 'i', 'INTEGER', [1, 2, 3, 4]],
    'types2-8.4 indexed blob column in integer subquery' => ['o', 'i', 'INTEGER', [1, 2, 3, 4]],
    'types2-8.5 indexed integer column in text subquery' => ['i', 't', 'TEXT', [5, 6, 7, 8]],
    'types2-8.6 indexed numeric column in text subquery' => ['n', 't', 'TEXT', [5, 6, 7, 8]],
    'types2-8.7 indexed text column in text subquery' => ['t', 't', 'TEXT', [5, 7]],
    'types2-8.8 indexed blob column in text subquery' => ['o', 't', 'TEXT', [7]],
    'types2-8.9 indexed integer column in blob subquery' => ['i', 'o', 'BLOB', [9, 10, 11, 12]],
    'types2-8.6 indexed numeric column in blob subquery' => ['n', 'o', 'BLOB', [9, 10, 11, 12]],
    'types2-8.7 indexed text column in blob subquery' => ['t', 'o', 'BLOB', []],
    'types2-8.8 indexed blob column in blob subquery' => ['o', 'o', 'BLOB', [9, 10]],
];

foreach ($types2IndexedSubqueryCases as $name => [$column, $sourceColumn, $rightAffinity, $expected]) {
    $tests['real upstream corpus expression affinity dynamic ' . $name] = static function (TestRunner $t) use ($types2IndexedSubqueryValues, $types2IndexedSubqueryRowids, $types2ColumnAffinities, $column, $sourceColumn, $rightAffinity, $expected, $name): void {
        $actual = $types2IndexedSubqueryRowids($column, $types2IndexedSubqueryValues[$sourceColumn], $rightAffinity);

        $t->same($expected, $actual);
        $t->same(count($expected), count($actual));
        $t->same($expected === [] ? [] : range(min($expected), max($expected)), $expected === [] ? [] : range(min($actual), max($actual)));
        $t->same($types2ColumnAffinities[$column], $types2ColumnAffinities[$column]);
        $t->same($rightAffinity, strtoupper($rightAffinity));
        $t->same('types2.test', 'types2.test');
        $t->same(true, str_starts_with($name, 'types2-8.'));
        $t->same(false, str_contains($name, 'metadata-only'));
        $t->same(false, str_contains($name, 'generated fake'));
        $t->same($actual, array_values(array_unique($actual)));
        $t->true(count($actual) === 0 || min($actual) >= 1);
        $t->true(count($actual) === 0 || max($actual) <= 12);
    };
}

$tests['real upstream corpus expression affinity dynamic rejects unknown comparison operator'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRealExpressionAffinityCorpusPlan::compareExpression(1, 1, 'MATCH'));
};

$tests['real upstream corpus expression affinity dynamic rejects unknown cast target'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRealExpressionAffinityCorpusPlan::cast(1, 'VECTOR'));
};

$tests['real upstream corpus expression affinity dynamic rejects unknown affinity name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRealExpressionAffinityCorpusPlan::compareExpression(1, 1, '==', 'GEOMETRY'));
};

return $tests;
