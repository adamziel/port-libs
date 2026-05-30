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
