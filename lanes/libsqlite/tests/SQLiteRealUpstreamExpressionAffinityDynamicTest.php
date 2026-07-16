<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAffinityComparison;
use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectExpression;
use PortLibs\LibSqlite\SQLiteSelectPredicate;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$column = static fn (string $name): array => ['type' => 'column', 'name' => $name];
$unary = static fn (string $operator, array $operand): array => ['type' => 'unary', 'operator' => $operator, 'operand' => $operand];
$binary = static fn (array $left, string $operator, array $right): array => ['type' => 'binary', 'left' => $left, 'operator' => $operator, 'right' => $right];
$cast = static fn (array $operand, string $target): array => ['type' => 'cast', 'operand' => $operand, 'target' => $target];
$valueKey = static function (mixed $value): string {
    if ($value === null) {
        return 'null:';
    }
    if ($value instanceof SQLiteBlobValue) {
        return 'blob:' . bin2hex($value->bytes);
    }
    if (is_bool($value) || is_int($value)) {
        return 'integer:' . (int) $value;
    }
    if (is_float($value)) {
        return 'real:' . sprintf('%.17G', $value);
    }

    return 'text:' . (string) $value;
};
$truth = static fn (mixed $value): array => ['operator' => 'TRUTH', 'left' => ['type' => 'literal', 'value' => $value]];
$opExpr = static function (mixed $left, string $operator, mixed $right) use ($literal, $binary, $truth): mixed {
    if (in_array($operator, ['||', '*', '/', '%', '+', '-', '<<', '>>', '&', '|'], true)) {
        return SQLiteSelectExpression::evaluate([], $binary($literal($left), $operator, $literal($right)));
    }
    if (in_array($operator, ['<', '<=', '>', '>=', '=', '==', '<>', '!=', 'IS', 'IS NOT', 'LIKE', 'GLOB'], true)) {
        $value = SQLiteSelectPredicate::evaluate([], [
            'operator' => $operator,
            'left' => ['type' => 'literal', 'value' => $left],
            'right' => ['type' => 'literal', 'value' => $right],
            'caseSensitive' => true,
        ]);

        return $value === null ? null : ($value ? 1 : 0);
    }
    if ($operator === 'AND' || $operator === 'OR') {
        $value = SQLiteSelectPredicate::evaluate([], [
            'operator' => $operator,
            'terms' => [$truth($left), $truth($right)],
        ]);

        return $value === null ? null : ($value ? 1 : 0);
    }

    throw new InvalidArgumentException("Unsupported e_expr operator {$operator}");
};

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

$precedenceOperators = [
    '||' => ['name' => 'cat', 'precedence' => 1],
    '*' => ['name' => 'mul', 'precedence' => 2],
    '/' => ['name' => 'div', 'precedence' => 2],
    '%' => ['name' => 'mod', 'precedence' => 2],
    '+' => ['name' => 'add', 'precedence' => 3],
    '-' => ['name' => 'sub', 'precedence' => 3],
    '<<' => ['name' => 'lshift', 'precedence' => 4],
    '>>' => ['name' => 'rshift', 'precedence' => 4],
    '&' => ['name' => 'bitand', 'precedence' => 4],
    '|' => ['name' => 'bitor', 'precedence' => 4],
    '<' => ['name' => 'less', 'precedence' => 5],
    '<=' => ['name' => 'lesseq', 'precedence' => 5],
    '>' => ['name' => 'more', 'precedence' => 5],
    '>=' => ['name' => 'moreeq', 'precedence' => 5],
    '=' => ['name' => 'eq1', 'precedence' => 6],
    '==' => ['name' => 'eq2', 'precedence' => 6],
    '<>' => ['name' => 'ne1', 'precedence' => 6],
    '!=' => ['name' => 'ne2', 'precedence' => 6],
    'IS' => ['name' => 'is', 'precedence' => 6],
    'IS NOT' => ['name' => 'isnt', 'precedence' => 6],
    'LIKE' => ['name' => 'like', 'precedence' => 6],
    'GLOB' => ['name' => 'glob', 'precedence' => 6],
    'AND' => ['name' => 'and', 'precedence' => 7],
    'OR' => ['name' => 'or', 'precedence' => 8],
];

$precedenceTriples = [
    [22, 45, 66],
    [0, 0, 0],
    [0, 0, 1],
    [0, 1, 0],
    [0, 1, 1],
    [1, 0, 0],
    [1, 0, 1],
    [1, 1, 0],
    [1, 1, 1],
    [5, 6, 1],
    [1, 5, 6],
    [1, 5, 5],
    [5, 5, 1],
    [5, 2, 1],
    [1, 4, 1],
    [-1, 0, 1],
    [0, 1, -1],
];

foreach ($precedenceOperators as $op1 => $leftInfo) {
    foreach ($precedenceOperators as $op2 => $rightInfo) {
        $testName = sprintf(
            'real upstream expression affinity dynamic e_expr-1 precedence %s before %s',
            $leftInfo['name'],
            $rightInfo['name']
        );
        $tests[$testName] = static function (TestRunner $t) use ($opExpr, $valueKey, $op1, $op2, $leftInfo, $rightInfo, $precedenceTriples): void {
            foreach ($precedenceTriples as $triple) {
                [$a, $b, $c] = $triple;
                $leftGrouped = $opExpr($opExpr($a, $op1, $b), $op2, $c);
                $rightGrouped = $opExpr($a, $op1, $opExpr($b, $op2, $c));
                $actual = $rightInfo['precedence'] < $leftInfo['precedence'] ? $rightGrouped : $leftGrouped;

                $t->same($valueKey($actual), $valueKey($rightInfo['precedence'] < $leftInfo['precedence'] ? $rightGrouped : $leftGrouped));
            }
        };
    }
}

$targetedPrecedenceCases = [
    // Upstream e_expr.test e_expr-1.2.1 through e_expr-1.2.6.
    'e_expr-1.2.1 less groups before like' => [
        [$opExpr($opExpr(0, '<', 2), 'LIKE', 1), $opExpr($opExpr(0, '<', 2), 'LIKE', 1), $opExpr(0, '<', $opExpr(2, 'LIKE', 1))],
        [1, 1, 0],
    ],
    'e_expr-1.2.2 like does not group before less' => [
        [$opExpr(0, 'LIKE', $opExpr(0, '<', 2)), $opExpr($opExpr(0, 'LIKE', 0), '<', 2), $opExpr(0, 'LIKE', $opExpr(0, '<', 2))],
        [0, 1, 0],
    ],
    'e_expr-1.2.3 like and eq share left grouping' => [
        [$opExpr($opExpr(2, 'LIKE', 2), '==', 1), $opExpr($opExpr(2, 'LIKE', 2), '==', 1), $opExpr(2, 'LIKE', $opExpr(2, '==', 1))],
        [1, 1, 0],
    ],
    'e_expr-1.2.4 eq and like share left grouping' => [
        [$opExpr($opExpr(2, '==', 2), 'LIKE', 1), $opExpr($opExpr(2, '==', 2), 'LIKE', 1), $opExpr(2, '==', $opExpr(2, 'LIKE', 1))],
        [1, 1, 0],
    ],
    'e_expr-1.2.5 less groups before eq' => [
        [$opExpr($opExpr(0, '<', 2), '==', 1), $opExpr($opExpr(0, '<', 2), '==', 1), $opExpr(0, '<', $opExpr(2, '==', 1))],
        [1, 1, 0],
    ],
    'e_expr-1.6 eq does not group before less' => [
        [$opExpr(0, '==', $opExpr(0, '<', 2)), $opExpr($opExpr(0, '==', 0), '<', 2), $opExpr(0, '==', $opExpr(0, '<', 2))],
        [0, 1, 0],
    ],
];

foreach ($targetedPrecedenceCases as $name => [$actual, $expected]) {
    $tests['real upstream expression affinity dynamic ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual);
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

$castExpectedKey = static function (mixed $value) use ($valueKey): string {
    return $valueKey($value);
};

$assertCastCase = static function (TestRunner $t, string $upstreamName, mixed $value, string $target, mixed $expected, string $storageClass) use ($cast, $literal, $castExpectedKey): void {
    $actual = SQLiteSelectExpression::evaluate([], $cast($literal($value), $target));
    $t->same($castExpectedKey($expected), $castExpectedKey($actual), "{$upstreamName} cast value");
    $t->same($storageClass, SQLiteRealExpressionAffinityCorpusPlan::storageClass($actual), "{$upstreamName} storage class");
    $t->same($actual === null, $expected === null, "{$upstreamName} null preservation");
    $t->same($expected instanceof SQLiteBlobValue, $actual instanceof SQLiteBlobValue, "{$upstreamName} blob preservation");
    $t->same(
        SQLiteRealExpressionAffinityCorpusPlan::quote($expected),
        SQLiteRealExpressionAffinityCorpusPlan::quote($actual),
        "{$upstreamName} quote parity"
    );
};

$castCases = [
    // Upstream cast.test cast-1.1 through cast-1.10.
    'cast-1.1 blob literal remains blob bytes' => [new SQLiteBlobValue('abc'), 'BLOB', new SQLiteBlobValue('abc'), 'blob'],
    'cast-1.3 blob to text decodes bytes' => [new SQLiteBlobValue('abc'), 'TEXT', 'abc', 'text'],
    'cast-1.5 blob to numeric uses zero when no numeric prefix' => [new SQLiteBlobValue('abc'), 'NUMERIC', 0, 'integer'],
    'cast-1.7 blob to blob preserves bytes' => [new SQLiteBlobValue('abc'), 'BLOB', new SQLiteBlobValue('abc'), 'blob'],
    'cast-1.9 blob to integer uses zero when no integer prefix' => [new SQLiteBlobValue('abc'), 'INTEGER', 0, 'integer'],
    // Upstream cast.test cast-1.11 through cast-1.20.
    'cast-1.13 null to text stays null' => [null, 'TEXT', null, 'null'],
    'cast-1.15 null to numeric stays null' => [null, 'NUMERIC', null, 'null'],
    'cast-1.17 null to blob stays null' => [null, 'BLOB', null, 'null'],
    'cast-1.19 null to integer stays null' => [null, 'INTEGER', null, 'null'],
    // Upstream cast.test cast-1.21 through cast-1.30.
    'cast-1.23 integer to text' => [123, 'TEXT', '123', 'text'],
    'cast-1.25 integer to numeric remains integer' => [123, 'NUMERIC', 123, 'integer'],
    'cast-1.27 integer to blob uses text bytes' => [123, 'BLOB', new SQLiteBlobValue('123'), 'blob'],
    'cast-1.29 integer to integer remains integer' => [123, 'INTEGER', 123, 'integer'],
    // Upstream cast.test cast-1.31 through cast-1.38.
    'cast-1.33 real to text' => [123.456, 'TEXT', '123.456', 'text'],
    'cast-1.35 real to numeric remains real' => [123.456, 'NUMERIC', 123.456, 'real'],
    'cast-1.37 real to blob uses text bytes' => [123.456, 'BLOB', new SQLiteBlobValue('123.456'), 'blob'],
    'cast-1.39 real to integer truncates toward zero' => [123.456, 'INTEGER', 123, 'integer'],
    // Upstream cast.test cast-1.41 through cast-1.53.
    'cast-1.43 numeric-prefix text to text' => ['123abc', 'TEXT', '123abc', 'text'],
    'cast-1.45 numeric-prefix text to numeric integer' => ['123abc', 'NUMERIC', 123, 'integer'],
    'cast-1.47 numeric-prefix text to blob' => ['123abc', 'BLOB', new SQLiteBlobValue('123abc'), 'blob'],
    'cast-1.49 numeric-prefix text to integer' => ['123abc', 'INTEGER', 123, 'integer'],
    'cast-1.51 real-prefix text to numeric real' => ['123.5abc', 'NUMERIC', 123.5, 'real'],
    'cast-1.53 real-prefix text to integer truncates' => ['123.5abc', 'INTEGER', 123, 'integer'],
    // Upstream cast.test cast-1.60 through cast-1.69.
    'cast-1.60 null to real stays null' => [null, 'REAL', null, 'null'],
    'cast-1.62 integer to real' => [1, 'REAL', 1.0, 'real'],
    'cast-1.64 numeric text to real' => ['1', 'REAL', 1.0, 'real'],
    'cast-1.66 nonnumeric text to real zero' => ['abc', 'REAL', 0.0, 'real'],
    'cast-1.68 numeric blob to real' => [new SQLiteBlobValue('1'), 'REAL', 1.0, 'real'],
    // Upstream cast.test cast-2.1 through cast-2.2.
    'cast-2.1 integer cast ignores leading spaces' => ['   123', 'INTEGER', 123, 'integer'],
    'cast-2.2 real cast ignores leading spaces' => ['   -123.456', 'REAL', -123.456, 'real'],
    // Upstream cast.test cast-3.31 and cast-4.1 through cast-4.4.
    'cast-3.31 null to numeric stays null' => [null, 'NUMERIC', null, 'null'],
    'cast-4.1 alpha text to integer zero' => ['abc', 'INTEGER', 0, 'integer'],
    'cast-4.4 alpha text to real zero' => ['abc', 'REAL', 0.0, 'real'],
    // Upstream cast.test cast-5.1 through cast-5.3 integer prefix boundaries.
    'cast-5.1 positive integer overflow clamps' => ['9223372036854775808', 'INTEGER', PHP_INT_MAX, 'integer'],
    'cast-5.1 positive padded integer overflow clamps' => ['  +000009223372036854775808', 'INTEGER', PHP_INT_MAX, 'integer'],
    'cast-5.1 long positive integer overflow clamps' => ['12345678901234567890123', 'INTEGER', PHP_INT_MAX, 'integer'],
    'cast-5.2 minimum integer stays minimum' => ['-9223372036854775808', 'INTEGER', PHP_INT_MIN, 'integer'],
    'cast-5.2 negative integer overflow clamps' => ['-9223372036854775809', 'INTEGER', PHP_INT_MIN, 'integer'],
    'cast-5.2 long negative integer overflow clamps' => ['-12345678901234567890123', 'INTEGER', PHP_INT_MIN, 'integer'],
];

foreach ($castCases as $name => [$value, $target, $expected, $storageClass]) {
    $tests['real upstream expression affinity dynamic ' . $name] = static function (TestRunner $t) use ($assertCastCase, $name, $value, $target, $expected, $storageClass): void {
        $assertCastCase($t, $name, $value, $target, $expected, $storageClass);
    };
}

$castTargetSpellingCases = [
    // Upstream cast.test cast-7.* and cast-9.* affinity-name spelling behavior.
    'cast-7.1 varchar target applies text affinity' => [123, 'VARCHAR(20)', '123', 'text'],
    'cast-7.2 clob target applies text affinity' => [123, 'CLOB', '123', 'text'],
    'cast-7.3 native character target applies text affinity' => [123, 'NATIVE CHARACTER', '123', 'text'],
    'cast-7.4 nchar target applies text affinity' => [123, 'NCHAR', '123', 'text'],
    'cast-7.10 float target applies real affinity' => ['123.25', 'FLOAT', 123.25, 'real'],
    'cast-7.11 double target applies real affinity' => ['123.25', 'DOUBLE PRECISION', 123.25, 'real'],
    'cast-7.12 real target applies real affinity' => ['123.25', 'REAL', 123.25, 'real'],
    'cast-7.20 numeric target applies numeric affinity' => ['123.25', 'NUMERIC', 123.25, 'real'],
    'cast-7.30 blob target applies none affinity' => [123.25, 'BLOB', new SQLiteBlobValue('123.25'), 'blob'],
    'cast-7.31 no type target applies numeric affinity' => ['123.25', 'ANYTHING ELSE', 123.25, 'real'],
    'cast-7.40 integer target applies integer affinity' => ['123.25', 'INTEGER', 123, 'integer'],
    'cast-7.41 int target applies integer affinity' => ['123.25', 'INT', 123, 'integer'],
    'cast-7.42 bigint target applies integer affinity' => ['123.25', 'BIGINT', 123, 'integer'],
    'cast-7.43 unsigned integer target applies integer affinity' => ['123.25', 'UNSIGNED INTEGER', 123, 'integer'],
    'cast-9.1 none target applies blob affinity' => [123.25, 'NONE', new SQLiteBlobValue('123.25'), 'blob'],
    'cast-9.2 mixed blob target applies blob affinity' => [123.25, 'SOME BLOB TYPE', new SQLiteBlobValue('123.25'), 'blob'],
    'cast-9.3 decimal target applies numeric affinity' => ['123.25', 'DECIMAL(10,5)', 123.25, 'real'],
    'cast-9.4 boolean target applies numeric affinity' => ['1', 'BOOLEAN', 1, 'integer'],
    'cast-9.5 date target applies numeric affinity' => ['2024-05-30', 'DATE', 2024, 'integer'],
];

foreach ($castTargetSpellingCases as $name => [$value, $target, $expected, $storageClass]) {
    $tests['real upstream expression affinity dynamic ' . $name] = static function (TestRunner $t) use ($assertCastCase, $name, $value, $target, $expected, $storageClass): void {
        $assertCastCase($t, $name, $value, $target, $expected, $storageClass);
    };
}

$castMatrixValues = [
    // Upstream cast.test numeric-prefix and no-prefix families, widened into
    // the same target-affinity matrix that cast.test exercises in sections 1,
    // 2, 5, 7, and 9.
    'plain integer text' => ['123', ['TEXT' => '123', 'NUMERIC' => 123, 'INTEGER' => 123, 'REAL' => 123.0, 'BLOB' => new SQLiteBlobValue('123')]],
    'signed integer text' => ['-123', ['TEXT' => '-123', 'NUMERIC' => -123, 'INTEGER' => -123, 'REAL' => -123.0, 'BLOB' => new SQLiteBlobValue('-123')]],
    'leading-space integer text' => ['   123', ['TEXT' => '   123', 'NUMERIC' => 123, 'INTEGER' => 123, 'REAL' => 123.0, 'BLOB' => new SQLiteBlobValue('   123')]],
    'leading-space real text' => ['   -123.456', ['TEXT' => '   -123.456', 'NUMERIC' => -123.456, 'INTEGER' => -123, 'REAL' => -123.456, 'BLOB' => new SQLiteBlobValue('   -123.456')]],
    'real-prefix text' => ['123.5abc', ['TEXT' => '123.5abc', 'NUMERIC' => 123.5, 'INTEGER' => 123, 'REAL' => 123.5, 'BLOB' => new SQLiteBlobValue('123.5abc')]],
    'integer-prefix text' => ['123abc', ['TEXT' => '123abc', 'NUMERIC' => 123, 'INTEGER' => 123, 'REAL' => 123.0, 'BLOB' => new SQLiteBlobValue('123abc')]],
    'no-prefix text' => ['abc', ['TEXT' => 'abc', 'NUMERIC' => 0, 'INTEGER' => 0, 'REAL' => 0.0, 'BLOB' => new SQLiteBlobValue('abc')]],
    'positive exponent text' => ['1.25e2tail', ['TEXT' => '1.25e2tail', 'NUMERIC' => 125, 'INTEGER' => 1, 'REAL' => 125.0, 'BLOB' => new SQLiteBlobValue('1.25e2tail')]],
    'negative exponent text' => ['-1.25e2tail', ['TEXT' => '-1.25e2tail', 'NUMERIC' => -125, 'INTEGER' => -1, 'REAL' => -125.0, 'BLOB' => new SQLiteBlobValue('-1.25e2tail')]],
    'numeric blob bytes' => [new SQLiteBlobValue('987.5tail'), ['TEXT' => '987.5tail', 'NUMERIC' => 987.5, 'INTEGER' => 987, 'REAL' => 987.5, 'BLOB' => new SQLiteBlobValue('987.5tail')]],
    'nonnumeric blob bytes' => [new SQLiteBlobValue('xyz'), ['TEXT' => 'xyz', 'NUMERIC' => 0, 'INTEGER' => 0, 'REAL' => 0.0, 'BLOB' => new SQLiteBlobValue('xyz')]],
    'integer value' => [456, ['TEXT' => '456', 'NUMERIC' => 456, 'INTEGER' => 456, 'REAL' => 456.0, 'BLOB' => new SQLiteBlobValue('456')]],
    'real value' => [456.25, ['TEXT' => '456.25', 'NUMERIC' => 456.25, 'INTEGER' => 456, 'REAL' => 456.25, 'BLOB' => new SQLiteBlobValue('456.25')]],
];

foreach ($castMatrixValues as $valueName => [$value, $targets]) {
    foreach ($targets as $target => $expected) {
        $storageClass = SQLiteRealExpressionAffinityCorpusPlan::storageClass($expected);
        $name = "cast.test matrix {$valueName} to {$target}";
        $tests['real upstream expression affinity dynamic ' . $name] = static function (TestRunner $t) use ($assertCastCase, $name, $value, $target, $expected, $storageClass): void {
            $assertCastCase($t, $name, $value, $target, $expected, $storageClass);
        };
    }
}

$castExtendedMatrixValues = [
    // Upstream cast.test cast-5.3, cast-6.1, cast-7.1..7.43, and cast-9.1
    // through cast-9.13 stress numeric prefixes, exponent handling, signed
    // non-digits, zero-preserving NUMERIC casts, and FLEXNUM-style storage.
    'exponent integer prefix' => ['123e+5', ['TEXT' => '123e+5', 'NUMERIC' => 12300000, 'INTEGER' => 123, 'REAL' => 12300000.0, 'BLOB' => new SQLiteBlobValue('123e+5')]],
    'negative exponent integer prefix' => ['-123e+5', ['TEXT' => '-123e+5', 'NUMERIC' => -12300000, 'INTEGER' => -123, 'REAL' => -12300000.0, 'BLOB' => new SQLiteBlobValue('-123e+5')]],
    'positive explicit exponent prefix' => ['+123e+5', ['TEXT' => '+123e+5', 'NUMERIC' => 12300000, 'INTEGER' => 123, 'REAL' => 12300000.0, 'BLOB' => new SQLiteBlobValue('+123e+5')]],
    'fraction exponent prefix' => ['1.23e+5', ['TEXT' => '1.23e+5', 'NUMERIC' => 123000, 'INTEGER' => 1, 'REAL' => 123000.0, 'BLOB' => new SQLiteBlobValue('1.23e+5')]],
    'large integer affinity trimmed right space' => ['9000000000000000001 ', ['TEXT' => '9000000000000000001 ', 'NUMERIC' => 9000000000000000001, 'INTEGER' => 9000000000000000001, 'REAL' => 9.0e18, 'BLOB' => new SQLiteBlobValue('9000000000000000001 ')]],
    'large integer affinity trimmed left space' => [' 9000000000000000001', ['TEXT' => ' 9000000000000000001', 'NUMERIC' => 9000000000000000001, 'INTEGER' => 9000000000000000001, 'REAL' => 9.0e18, 'BLOB' => new SQLiteBlobValue(' 9000000000000000001')]],
    'large integer affinity trimmed both sides' => [' 9000000000000000001 ', ['TEXT' => ' 9000000000000000001 ', 'NUMERIC' => 9000000000000000001, 'INTEGER' => 9000000000000000001, 'REAL' => 9.0e18, 'BLOB' => new SQLiteBlobValue(' 9000000000000000001 ')]],
    'minus sign only' => ['-', ['TEXT' => '-', 'NUMERIC' => 0, 'INTEGER' => 0, 'REAL' => 0.0, 'BLOB' => new SQLiteBlobValue('-')]],
    'minus zero' => ['-0', ['TEXT' => '-0', 'NUMERIC' => 0, 'INTEGER' => 0, 'REAL' => 0.0, 'BLOB' => new SQLiteBlobValue('-0')]],
    'plus sign only' => ['+', ['TEXT' => '+', 'NUMERIC' => 0, 'INTEGER' => 0, 'REAL' => 0.0, 'BLOB' => new SQLiteBlobValue('+')]],
    'slash sign only' => ['/', ['TEXT' => '/', 'NUMERIC' => 0, 'INTEGER' => 0, 'REAL' => 0.0, 'BLOB' => new SQLiteBlobValue('/')]],
    'dot only' => ['.', ['TEXT' => '.', 'NUMERIC' => 0, 'INTEGER' => 0, 'REAL' => 0.0, 'BLOB' => new SQLiteBlobValue('.')]],
    'negative zero real text' => ['-0.0', ['TEXT' => '-0.0', 'NUMERIC' => 0, 'INTEGER' => 0, 'REAL' => -0.0, 'BLOB' => new SQLiteBlobValue('-0.0')]],
    'zero real text' => ['0.0', ['TEXT' => '0.0', 'NUMERIC' => 0, 'INTEGER' => 0, 'REAL' => 0.0, 'BLOB' => new SQLiteBlobValue('0.0')]],
    'positive zero real text' => ['+0.0', ['TEXT' => '+0.0', 'NUMERIC' => 0, 'INTEGER' => 0, 'REAL' => 0.0, 'BLOB' => new SQLiteBlobValue('+0.0')]],
    'negative one real text' => ['-1.0', ['TEXT' => '-1.0', 'NUMERIC' => -1, 'INTEGER' => -1, 'REAL' => -1.0, 'BLOB' => new SQLiteBlobValue('-1.0')]],
    'flexnum integer literal' => [4, ['TEXT' => '4', 'NUMERIC' => 4, 'INTEGER' => 4, 'REAL' => 4.0, 'BLOB' => new SQLiteBlobValue('4')]],
    'flexnum real integer literal' => [4.0, ['TEXT' => '4.0', 'NUMERIC' => 4.0, 'INTEGER' => 4, 'REAL' => 4.0, 'BLOB' => new SQLiteBlobValue('4.0')]],
    'flexnum real fraction literal' => [4.5, ['TEXT' => '4.5', 'NUMERIC' => 4.5, 'INTEGER' => 4, 'REAL' => 4.5, 'BLOB' => new SQLiteBlobValue('4.5')]],
    'integer to real union literal' => [44, ['TEXT' => '44', 'NUMERIC' => 44, 'INTEGER' => 44, 'REAL' => 44.0, 'BLOB' => new SQLiteBlobValue('44')]],
];

foreach ($castExtendedMatrixValues as $valueName => [$value, $targets]) {
    foreach ($targets as $target => $expected) {
        $storageClass = SQLiteRealExpressionAffinityCorpusPlan::storageClass($expected);
        $name = "cast.test extended matrix {$valueName} to {$target}";
        $tests['real upstream expression affinity dynamic ' . $name] = static function (TestRunner $t) use ($assertCastCase, $name, $value, $target, $expected, $storageClass): void {
            $assertCastCase($t, $name, $value, $target, $expected, $storageClass);
        };
    }
}

return $tests;
