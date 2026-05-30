<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAffinityComparison;
use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectExpression;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$column = static fn (string $name): array => ['type' => 'column', 'name' => $name];
$unary = static fn (string $operator, array $operand): array => ['type' => 'unary', 'operator' => $operator, 'operand' => $operand];
$binary = static fn (array $left, string $operator, array $right): array => ['type' => 'binary', 'left' => $left, 'operator' => $operator, 'right' => $right];
$cast = static fn (array $operand, string $target): array => ['type' => 'cast', 'operand' => $operand, 'target' => $target];

$affinityRows = [
    ['rowid' => 1, 'xi' => 1, 'xr' => 1.0, 'xb' => 1, 'xn' => 1, 'xt' => '1'],
    ['rowid' => 2, 'xi' => 2, 'xr' => 2.0, 'xb' => '2', 'xn' => 2, 'xt' => '2'],
    ['rowid' => 3, 'xi' => 3, 'xr' => 3.0, 'xb' => '03', 'xn' => 3, 'xt' => '03'],
];

$equalityMatrix = [
    // Upstream affinity2.test affinity2-200.
    'affinity2-200 row1 xi equals xt' => [$affinityRows[0]['xi'], $affinityRows[0]['xt'], 'INTEGER', 'TEXT', true],
    'affinity2-200 row1 xi equals xb' => [$affinityRows[0]['xi'], $affinityRows[0]['xb'], 'INTEGER', 'BLOB', true],
    'affinity2-200 row1 xi equals unary plus xt' => [$affinityRows[0]['xi'], SQLiteSelectExpression::evaluate($affinityRows[0], $unary('+', $column('xt'))), 'INTEGER', 'NONE', true],
    'affinity2-200 row2 xi equals xt' => [$affinityRows[1]['xi'], $affinityRows[1]['xt'], 'INTEGER', 'TEXT', true],
    'affinity2-200 row2 xi equals xb' => [$affinityRows[1]['xi'], $affinityRows[1]['xb'], 'INTEGER', 'BLOB', true],
    'affinity2-200 row2 xi equals unary plus xt' => [$affinityRows[1]['xi'], SQLiteSelectExpression::evaluate($affinityRows[1], $unary('+', $column('xt'))), 'INTEGER', 'NONE', true],
    'affinity2-200 row3 xi equals xt' => [$affinityRows[2]['xi'], $affinityRows[2]['xt'], 'INTEGER', 'TEXT', true],
    'affinity2-200 row3 xi equals xb' => [$affinityRows[2]['xi'], $affinityRows[2]['xb'], 'INTEGER', 'BLOB', true],
    'affinity2-200 row3 xi equals unary plus xt' => [$affinityRows[2]['xi'], SQLiteSelectExpression::evaluate($affinityRows[2], $unary('+', $column('xt'))), 'INTEGER', 'NONE', true],
    // Upstream affinity2.test affinity2-210.
    'affinity2-210 row1 xr equals xt' => [$affinityRows[0]['xr'], $affinityRows[0]['xt'], 'REAL', 'TEXT', true],
    'affinity2-210 row1 xr equals xb' => [$affinityRows[0]['xr'], $affinityRows[0]['xb'], 'REAL', 'BLOB', true],
    'affinity2-210 row1 xr equals unary plus xt' => [$affinityRows[0]['xr'], SQLiteSelectExpression::evaluate($affinityRows[0], $unary('+', $column('xt'))), 'REAL', 'NONE', true],
    'affinity2-210 row2 xr equals xt' => [$affinityRows[1]['xr'], $affinityRows[1]['xt'], 'REAL', 'TEXT', true],
    'affinity2-210 row2 xr equals xb' => [$affinityRows[1]['xr'], $affinityRows[1]['xb'], 'REAL', 'BLOB', true],
    'affinity2-210 row2 xr equals unary plus xt' => [$affinityRows[1]['xr'], SQLiteSelectExpression::evaluate($affinityRows[1], $unary('+', $column('xt'))), 'REAL', 'NONE', true],
    'affinity2-210 row3 xr equals xt' => [$affinityRows[2]['xr'], $affinityRows[2]['xt'], 'REAL', 'TEXT', true],
    'affinity2-210 row3 xr equals xb' => [$affinityRows[2]['xr'], $affinityRows[2]['xb'], 'REAL', 'BLOB', true],
    'affinity2-210 row3 xr equals unary plus xt' => [$affinityRows[2]['xr'], SQLiteSelectExpression::evaluate($affinityRows[2], $unary('+', $column('xt'))), 'REAL', 'NONE', true],
    // Upstream affinity2.test affinity2-220.
    'affinity2-220 row1 xn equals xt' => [$affinityRows[0]['xn'], $affinityRows[0]['xt'], 'NUMERIC', 'TEXT', true],
    'affinity2-220 row1 xn equals xb' => [$affinityRows[0]['xn'], $affinityRows[0]['xb'], 'NUMERIC', 'BLOB', true],
    'affinity2-220 row1 xn equals unary plus xt' => [$affinityRows[0]['xn'], SQLiteSelectExpression::evaluate($affinityRows[0], $unary('+', $column('xt'))), 'NUMERIC', 'NONE', true],
    'affinity2-220 row2 xn equals xt' => [$affinityRows[1]['xn'], $affinityRows[1]['xt'], 'NUMERIC', 'TEXT', true],
    'affinity2-220 row2 xn equals xb' => [$affinityRows[1]['xn'], $affinityRows[1]['xb'], 'NUMERIC', 'BLOB', true],
    'affinity2-220 row2 xn equals unary plus xt' => [$affinityRows[1]['xn'], SQLiteSelectExpression::evaluate($affinityRows[1], $unary('+', $column('xt'))), 'NUMERIC', 'NONE', true],
    'affinity2-220 row3 xn equals xt' => [$affinityRows[2]['xn'], $affinityRows[2]['xt'], 'NUMERIC', 'TEXT', true],
    'affinity2-220 row3 xn equals xb' => [$affinityRows[2]['xn'], $affinityRows[2]['xb'], 'NUMERIC', 'BLOB', true],
    'affinity2-220 row3 xn equals unary plus xt' => [$affinityRows[2]['xn'], SQLiteSelectExpression::evaluate($affinityRows[2], $unary('+', $column('xt'))), 'NUMERIC', 'NONE', true],
    // Upstream affinity2.test affinity2-300.
    'affinity2-300 row1 xt equals unary plus xi' => [$affinityRows[0]['xt'], SQLiteSelectExpression::evaluate($affinityRows[0], $unary('+', $column('xi'))), 'TEXT', 'NONE', true],
    'affinity2-300 row1 xt equals xi' => [$affinityRows[0]['xt'], $affinityRows[0]['xi'], 'TEXT', 'INTEGER', true],
    'affinity2-300 row1 xt equals xb' => [$affinityRows[0]['xt'], $affinityRows[0]['xb'], 'NONE', 'NONE', false],
    'affinity2-300 row2 xt equals unary plus xi' => [$affinityRows[1]['xt'], SQLiteSelectExpression::evaluate($affinityRows[1], $unary('+', $column('xi'))), 'TEXT', 'NONE', true],
    'affinity2-300 row2 xt equals xi' => [$affinityRows[1]['xt'], $affinityRows[1]['xi'], 'TEXT', 'INTEGER', true],
    'affinity2-300 row2 xt equals xb' => [$affinityRows[1]['xt'], $affinityRows[1]['xb'], 'NONE', 'NONE', true],
    'affinity2-300 row3 xt equals unary plus xi' => [$affinityRows[2]['xt'], SQLiteSelectExpression::evaluate($affinityRows[2], $unary('+', $column('xi'))), 'TEXT', 'NONE', false],
    'affinity2-300 row3 xt equals xi' => [$affinityRows[2]['xt'], $affinityRows[2]['xi'], 'TEXT', 'INTEGER', true],
    'affinity2-300 row3 xt equals xb' => [$affinityRows[2]['xt'], $affinityRows[2]['xb'], 'NONE', 'NONE', true],
    // Upstream affinity2.test affinity2-500 through affinity2-507.
    'affinity2-500 negative blob coerces before text comparison' => [SQLiteSelectExpression::evaluate([], $unary('-', $literal(new SQLiteBlobValue("\xce")))), '-1', 'NONE', 'TEXT', true],
    'affinity2-502 nested signed blob coerces before text comparison' => [SQLiteSelectExpression::evaluate([], $unary('+', $unary('-', $unary('+', $literal(new SQLiteBlobValue("\xce")))))), '-1', 'NONE', 'TEXT', true],
    'affinity2-504 negative text coerces before text comparison' => [SQLiteSelectExpression::evaluate([], $unary('-', $literal('ce'))), '-1', 'NONE', 'TEXT', true],
    'affinity2-506 nested signed text coerces before text comparison' => [SQLiteSelectExpression::evaluate([], $unary('+', $unary('-', $unary('+', $literal('ce'))))), '-1', 'NONE', 'TEXT', true],
];

foreach ($equalityMatrix as $name => [$left, $right, $leftAffinity, $rightAffinity, $expected]) {
    $tests['real upstream expression affinity dynamic ' . $name] = static function (TestRunner $t) use ($left, $right, $leftAffinity, $rightAffinity, $expected): void {
        $t->same($expected, SQLiteAffinityComparison::equals($left, $right, $leftAffinity, $rightAffinity));
    };
}

$comparisonMatrix = [
    'affinity2-500 negative blob coerces before text comparison' => [SQLiteSelectExpression::evaluate([], $unary('-', $literal(new SQLiteBlobValue("\xce")))), '-1', 'NONE', 'TEXT'],
    'affinity2-502 nested signed blob coerces before text comparison' => [SQLiteSelectExpression::evaluate([], $unary('+', $unary('-', $unary('+', $literal(new SQLiteBlobValue("\xce")))))), '-1', 'NONE', 'TEXT'],
    'affinity2-504 negative text coerces before text comparison' => [SQLiteSelectExpression::evaluate([], $unary('-', $literal('ce'))), '-1', 'NONE', 'TEXT'],
    'affinity2-506 nested signed text coerces before text comparison' => [SQLiteSelectExpression::evaluate([], $unary('+', $unary('-', $unary('+', $literal('ce'))))), '-1', 'NONE', 'TEXT'],
];

foreach ($comparisonMatrix as $name => [$left, $right, $leftAffinity, $rightAffinity]) {
    $tests['real upstream expression affinity dynamic ' . $name] = static function (TestRunner $t) use ($left, $right, $leftAffinity, $rightAffinity): void {
        $comparison = SQLiteAffinityComparison::compare($left, $right, $leftAffinity, $rightAffinity);
        $t->true($comparison !== null && $comparison >= 0);
    };
}

$expressionCases = [
    // Upstream expr.test expr-1.1 through expr-1.5.
    'expr-1.1 integer addition' => [$binary($literal(10), '+', $literal(20)), 30],
    'expr-1.2 integer subtraction' => [$binary($literal(10), '-', $literal(20)), -10],
    'expr-1.3 integer multiplication' => [$binary($literal(10), '*', $literal(20)), 200],
    'expr-1.4 integer division truncates' => [$binary($literal(10), '/', $literal(20)), 0],
    'expr-1.5 reverse integer division' => [$binary($literal(20), '/', $literal(10)), 2],
    // Upstream expr.test expr-1.38 through expr-1.46e.
    'expr-1.38 unary minus' => [$unary('-', $literal(1)), -1],
    'expr-1.39 unary plus' => [$unary('+', $literal(1)), 1],
    'expr-1.40 unary plus parenthesized addition' => [$unary('+', $binary($literal(2), '+', $literal(1))), 3],
    'expr-1.41 unary minus parenthesized addition' => [$unary('-', $binary($literal(2), '+', $literal(1))), -3],
    'expr-1.42 bitwise or' => [$binary($literal(1), '|', $literal(2)), 3],
    'expr-1.42b literal bitwise or' => [$binary($literal(4), '|', $literal(2)), 6],
    'expr-1.43 bitwise and' => [$binary($literal(1), '&', $literal(2)), 0],
    'expr-1.43b literal bitwise and' => [$binary($literal(4), '&', $literal(5)), 4],
    'expr-1.44 bitwise not' => [$unary('~', $literal(1)), -2],
    'expr-1.44b bitwise not null propagates null' => [$unary('~', $literal(null)), null],
    'expr-1.45a shift left' => [$binary($literal(1), '<<', $literal(3)), 8],
    'expr-1.45b negative left shift reverses direction' => [$binary($literal(1), '>>', $literal(-3)), 8],
    'expr-1.45c zero shift' => [$binary($literal(1), '<<', $literal(0)), 1],
    'expr-1.45f oversize left shift returns zero' => [$binary($literal(1), '<<', $literal(64)), 0],
    'expr-1.46a shift right' => [$binary($literal(32), '>>', $literal(3)), 4],
    'expr-1.46b shift right to zero' => [$binary($literal(32), '>>', $literal(6)), 0],
    'expr-1.46c negative shift right sign extends' => [$binary($literal(-32), '>>', $literal(3)), -4],
    'expr-1.46d oversize negative shift right sign extends' => [$binary($literal(-32), '>>', $literal(100)), -1],
    'expr-1.46e negative right shift reverses direction' => [$binary($literal(32), '>>', $literal(-3)), 256],
    // Upstream expr.test expr-1.56, expr-1.58 through expr-1.69, expr-1.96 through expr-1.99, expr-1.108, expr-1.109.
    'expr-1.56 modulo' => [$binary($literal(25), '%', $literal(11)), 3],
    'expr-1.58 null addition propagates null' => [$binary($literal(null), '+', $literal(1)), null],
    'expr-1.61 null subtraction propagates null' => [$binary($literal(null), '-', $literal(1)), null],
    'expr-1.64 null multiplication propagates null' => [$binary($literal(null), '*', $literal(1)), null],
    'expr-1.67 null division propagates null' => [$binary($literal(null), '/', $literal(1)), null],
    'expr-1.96 null left shift propagates null' => [$binary($literal(null), '<<', $literal(3)), null],
    'expr-1.97 null right shift propagates null' => [$binary($literal(32), '>>', $literal(null)), null],
    'expr-1.98 null bitwise or propagates null' => [$binary($literal(null), '|', $literal(null)), null],
    'expr-1.99 null bitwise and propagates null' => [$binary($literal(32), '&', $literal(null)), null],
    'expr-1.108 modulo by zero returns null' => [$binary($literal(1), '%', $literal(0)), null],
    'expr-1.109 division by zero returns null' => [$binary($literal(1), '/', $literal(0)), null],
];

foreach ($expressionCases as $name => [$expression, $expected]) {
    $tests['real upstream expression affinity dynamic ' . $name] = static function (TestRunner $t) use ($expression, $expected): void {
        $t->same($expected, SQLiteSelectExpression::evaluate([], $expression));
    };
}

$settingsRows = [
    ['setting_id' => 1, 'key_name' => 'alpha', 'left_value' => 3, 'right_value' => 8],
    ['setting_id' => 2, 'key_name' => 'beta', 'left_value' => 3, 'right_value' => null],
    ['setting_id' => 3, 'key_name' => 'gamma', 'left_value' => null, 'right_value' => 8],
    ['setting_id' => 4, 'key_name' => 'delta', 'left_value' => null, 'right_value' => 3],
];

$sqlCases = [
    // Upstream expr.test expr-1.86 through expr-1.95.
    'expr-1.86 between lower and upper' => ["SELECT key_name FROM app_settings WHERE 5 BETWEEN left_value AND right_value ORDER BY setting_id", ['alpha']],
    'expr-1.87 not between lower and upper' => ["SELECT key_name FROM app_settings WHERE 5 NOT BETWEEN left_value AND right_value ORDER BY setting_id", ['delta']],
    'expr-1.88 outside between lower and upper' => ["SELECT key_name FROM app_settings WHERE 55 BETWEEN left_value AND right_value ORDER BY setting_id", []],
    'expr-1.89 outside not between lower and upper' => ["SELECT key_name FROM app_settings WHERE 55 NOT BETWEEN left_value AND right_value ORDER BY setting_id", ['alpha', 'gamma', 'delta']],
    'expr-1.90 null upper between stays unknown' => ["SELECT key_name FROM app_settings WHERE key_name = 'beta' AND 5 BETWEEN left_value AND right_value", []],
    'expr-1.91 null upper not between stays unknown' => ["SELECT key_name FROM app_settings WHERE key_name = 'beta' AND 5 NOT BETWEEN left_value AND right_value", []],
    'expr-1.92 null upper false between' => ["SELECT key_name FROM app_settings WHERE key_name = 'beta' AND 2 BETWEEN left_value AND right_value", []],
    'expr-1.93 null upper true not between' => ["SELECT key_name FROM app_settings WHERE key_name = 'beta' AND 2 NOT BETWEEN left_value AND right_value", ['beta']],
    'expr-1.94 null lower false between' => ["SELECT key_name FROM app_settings WHERE key_name = 'gamma' AND 55 BETWEEN left_value AND right_value", []],
    'expr-1.95 null lower true not between' => ["SELECT key_name FROM app_settings WHERE key_name = 'gamma' AND 55 NOT BETWEEN left_value AND right_value", ['gamma']],
];

foreach ($sqlCases as $name => [$sql, $expected]) {
    $tests['real upstream expression affinity dynamic ' . $name] = static function (TestRunner $t) use ($sql, $settingsRows, $expected): void {
        $rows = SQLiteSelectSql::execute($sql, ['app_settings' => $settingsRows]);
        $t->same($expected, array_map(static fn (array $row): string => (string) $row['key_name'], $rows));
    };
}

return $tests;
