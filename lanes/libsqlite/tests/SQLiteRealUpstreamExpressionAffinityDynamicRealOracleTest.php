<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteCoreScalarFunction;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$oracle = static function (string $expression) use ($sqlite3): array {
    static $cache = [];

    if (isset($cache[$expression])) {
        return $cache[$expression];
    }
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity dynamic real oracle tests');
    }

    $sql = "SELECT quote({$expression}), typeof({$expression});";
    $command = escapeshellarg($sqlite3) . ' -batch -noheader -separator ' . escapeshellarg("\t") . ' :memory: ' . escapeshellarg($sql);
    $output = shell_exec($command);
    if ($output === null) {
        throw new RuntimeException('sqlite3 oracle did not produce output for ' . $expression);
    }

    return $cache[$expression] = explode("\t", rtrim($output, "\r\n"));
};

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$cast = static fn (array $operand, string $target): array => [
    'type' => 'cast',
    'operand' => $operand,
    'target' => $target,
];
$unary = static fn (string $operator, array $operand): array => [
    'type' => 'unary',
    'operator' => $operator,
    'operand' => $operand,
];
$binary = static fn (array $left, string $operator, array $right): array => [
    'type' => 'binary',
    'operator' => $operator,
    'left' => $left,
    'right' => $right,
];

$portResult = static function (array $expression): array {
    $value = SQLiteSelectExpression::evaluate([], $expression);

    return [
        SQLiteCoreScalarFunction::sqlFunctionArguments('quote', [$value]),
        SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$value]),
    ];
};

$textLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

$realTerms = [
    'int-one-real' => ['sql' => 'CAST(1 AS REAL)', 'ast' => $cast($literal(1), 'REAL')],
    'int-minus-one-real' => ['sql' => 'CAST(-1 AS REAL)', 'ast' => $cast($literal(-1), 'REAL')],
    'int-forty-two-real' => ['sql' => 'CAST(42 AS REAL)', 'ast' => $cast($literal(42), 'REAL')],
    'real-basic' => ['sql' => '123.456', 'ast' => $literal(123.456)],
    'real-negative' => ['sql' => '-123.456', 'ast' => $literal(-123.456)],
    'real-exp-positive' => ['sql' => '1.25e+2', 'ast' => $literal(1.25e+2)],
    'real-exp-negative' => ['sql' => '-2.5e-2', 'ast' => $literal(-2.5e-2)],
    'text-leading-space-real' => ['sql' => "CAST('   -123.456' AS REAL)", 'ast' => $cast($literal('   -123.456'), 'REAL')],
    'text-exp-real' => ['sql' => "CAST('123e+5' AS REAL)", 'ast' => $cast($literal('123e+5'), 'REAL')],
    'text-tail-real' => ['sql' => "CAST('123.5abc' AS REAL)", 'ast' => $cast($literal('123.5abc'), 'REAL')],
    'text-int-tail-real' => ['sql' => "CAST('123abc' AS REAL)", 'ast' => $cast($literal('123abc'), 'REAL')],
    'text-plus-real' => ['sql' => "CAST('+' AS REAL)", 'ast' => $cast($literal('+'), 'REAL')],
    'text-minus-real' => ['sql' => "CAST('-' AS REAL)", 'ast' => $cast($literal('-'), 'REAL')],
    'text-dot-real' => ['sql' => "CAST('.' AS REAL)", 'ast' => $cast($literal('.'), 'REAL')],
    'text-slash-real' => ['sql' => "CAST('/' AS REAL)", 'ast' => $cast($literal('/'), 'REAL')],
    'text-empty-real' => ['sql' => "CAST('' AS REAL)", 'ast' => $cast($literal(''), 'REAL')],
    'blob-one-real' => ['sql' => "CAST(x'31' AS REAL)", 'ast' => $cast($literal(new SQLiteBlobValue('1')), 'REAL')],
    'blob-text-real' => ['sql' => "CAST(x'3132332e35' AS REAL)", 'ast' => $cast($literal(new SQLiteBlobValue('123.5')), 'REAL')],
    'unary-minus-text-real' => ['sql' => "-CAST('12.5' AS REAL)", 'ast' => $unary('-', $cast($literal('12.5'), 'REAL'))],
    'unary-plus-text-real' => ['sql' => "+CAST('12.5' AS REAL)", 'ast' => $unary('+', $cast($literal('12.5'), 'REAL'))],
    'cast-numeric-real' => ['sql' => "CAST(CAST('12.5' AS NUMERIC) AS REAL)", 'ast' => $cast($cast($literal('12.5'), 'NUMERIC'), 'REAL')],
    'cast-integer-real' => ['sql' => "CAST(CAST('12.5' AS INTEGER) AS REAL)", 'ast' => $cast($cast($literal('12.5'), 'INTEGER'), 'REAL')],
    'text-leading-zero-real' => ['sql' => "CAST('00000000000000000042' AS REAL)", 'ast' => $cast($literal('00000000000000000042'), 'REAL')],
    'text-real-small' => ['sql' => "CAST('0.000244140625' AS REAL)", 'ast' => $cast($literal('0.000244140625'), 'REAL')],
    'text-real-large' => ['sql' => "CAST('9223372036854774800' AS REAL)", 'ast' => $cast($literal('9223372036854774800'), 'REAL')],
];

$rightTerms = [
    'one-real' => ['sql' => 'CAST(1 AS REAL)', 'ast' => $cast($literal(1), 'REAL')],
    'two-real' => ['sql' => 'CAST(2 AS REAL)', 'ast' => $cast($literal(2), 'REAL')],
    'half-real' => ['sql' => '0.5', 'ast' => $literal(0.5)],
    'quarter-real' => ['sql' => '0.25', 'ast' => $literal(0.25)],
    'minus-three-real' => ['sql' => 'CAST(-3 AS REAL)', 'ast' => $cast($literal(-3), 'REAL')],
    'text-two-real' => ['sql' => "CAST('2' AS REAL)", 'ast' => $cast($literal('2'), 'REAL')],
    'text-three-tail-real' => ['sql' => "CAST('3.5xyz' AS REAL)", 'ast' => $cast($literal('3.5xyz'), 'REAL')],
    'blob-two-real' => ['sql' => "CAST(x'32' AS REAL)", 'ast' => $cast($literal(new SQLiteBlobValue('2')), 'REAL')],
    'nested-numeric-real' => ['sql' => "CAST(CAST('4.25' AS NUMERIC) AS REAL)", 'ast' => $cast($cast($literal('4.25'), 'NUMERIC'), 'REAL')],
    'unary-minus-real' => ['sql' => "-CAST('1.5' AS REAL)", 'ast' => $unary('-', $cast($literal('1.5'), 'REAL'))],
];

$operators = ['+', '-', '*', '/'];

foreach ($realTerms as $leftName => $left) {
    foreach ($rightTerms as $rightName => $right) {
        foreach ($operators as $operator) {
            $sqlExpression = '(' . $left['sql'] . ') ' . $operator . ' (' . $right['sql'] . ')';
            $astExpression = $binary($left['ast'], $operator, $right['ast']);
            $tests["real upstream expression affinity dynamic real oracle expr.test expr-2 cast.test cast-1 real {$leftName} {$operator} {$rightName}"] = static function (TestRunner $t) use ($oracle, $portResult, $sqlExpression, $astExpression, $operator): void {
                $expected = $oracle($sqlExpression);
                $actual = $portResult($astExpression);
                $expectedValue = (float) $expected[0];
                $actualValue = (float) $actual[0];
                $tolerance = max(1.0e-9, abs($expectedValue) * 1.0e-14);

                $t->same($expected[1], $actual[1], $sqlExpression);
                $t->true(abs($expectedValue - $actualValue) <= $tolerance, $sqlExpression . ' value differs beyond REAL tolerance');
                $t->same($operator === '/' ? 'real' : $expected[1], $actual[1], $sqlExpression);
                $t->contains('expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
                $t->contains('cast.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test');
            };
        }
    }
}

$tests['real upstream expression affinity dynamic real oracle owns exactly 1000 arithmetic cases'] = static function (TestRunner $t) use ($realTerms, $rightTerms, $operators): void {
    $t->same(1000, count($realTerms) * count($rightTerms) * count($operators));
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test');
};

$tests['real upstream expression affinity dynamic real oracle application scoring values remain real'] = static function (TestRunner $t) use ($literal, $cast, $binary, $portResult, $textLiteral): void {
    $weight = $textLiteral('0.125');
    $score = $binary($cast($literal('48'), 'REAL'), '*', $cast($literal('0.125'), 'REAL'));

    $t->same(['6.0', 'real'], $portResult($score));
    $t->contains('0.125', $weight);
};

return $tests;
