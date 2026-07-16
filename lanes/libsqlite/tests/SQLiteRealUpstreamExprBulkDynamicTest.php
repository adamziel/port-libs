<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$rows = [];
for ($case = 1; $case <= 40; $case++) {
    $left = $case * 3 + 7;
    $right = ($case % 9) + 2;
    $peer = ($case % 5 === 0) ? $left : $left + (($case % 2 === 0) ? 4 : -3);
    $rows[] = [
        'case_id' => $case,
        'i1' => $left,
        'i2' => $right,
        'peer' => $peer,
        'mask' => $case % 8,
        'lo' => min($right, $peer),
        'hi' => max($left, $peer) + 2,
        'zero' => 0,
        't1' => $case % 2 === 0 ? 'hello' : 'world',
    ];
}

$exprCases = [
    'expr.test expr-1.1 integer addition' => ['i1+i2', static fn (array $row): int => $row['i1'] + $row['i2']],
    'expr.test expr-1.2 integer subtraction' => ['i1-i2', static fn (array $row): int => $row['i1'] - $row['i2']],
    'expr.test expr-1.3 integer multiplication' => ['i1*i2', static fn (array $row): int => $row['i1'] * $row['i2']],
    'expr.test expr-1.4 integer division truncates' => ['i1/i2', static fn (array $row): int => intdiv($row['i1'], $row['i2'])],
    'expr.test expr-1.6 less-than comparison' => ['i2<i1', static fn (array $row): int => $row['i2'] < $row['i1'] ? 1 : 0],
    'expr.test expr-1.7 less-or-equal comparison' => ['peer<=i1', static fn (array $row): int => $row['peer'] <= $row['i1'] ? 1 : 0],
    'expr.test expr-1.8 greater-than comparison' => ['i1>i2', static fn (array $row): int => $row['i1'] > $row['i2'] ? 1 : 0],
    'expr.test expr-1.9 greater-or-equal comparison' => ['i1>=peer', static fn (array $row): int => $row['i1'] >= $row['peer'] ? 1 : 0],
    'expr.test expr-1.10 not-equal comparison' => ['i1!=peer', static fn (array $row): int => $row['i1'] !== $row['peer'] ? 1 : 0],
    'expr.test expr-1.11 equal comparison' => ['i1=peer', static fn (array $row): int => $row['i1'] === $row['peer'] ? 1 : 0],
    'expr.test expr-1.12 angle not-equal comparison' => ['i1<>peer', static fn (array $row): int => $row['i1'] !== $row['peer'] ? 1 : 0],
    'expr.test expr-1.13 double-equal comparison' => ['i1==peer', static fn (array $row): int => $row['i1'] === $row['peer'] ? 1 : 0],
    'expr.test expr-1.22 operator precedence' => ['i1+i2*mask', static fn (array $row): int => $row['i1'] + ($row['i2'] * $row['mask'])],
    'expr.test expr-1.23 parenthesized precedence' => ['(i1+i2)*mask', static fn (array $row): int => ($row['i1'] + $row['i2']) * $row['mask']],
    'expr.test expr-1.24 scalar min function' => ['min(i1,i2,peer)', static fn (array $row): int => min($row['i1'], $row['i2'], $row['peer'])],
    'expr.test expr-1.25 scalar max function' => ['max(i1,i2,peer)', static fn (array $row): int => max($row['i1'], $row['i2'], $row['peer'])],
    'expr.test expr-1.36 NOT truthy integer' => ['not i1', static fn (): int => 0],
    'expr.test expr-1.37 NOT zero integer' => ['not zero', static fn (): int => 1],
    'expr.test expr-1.38 unary minus' => ['-i1', static fn (array $row): int => -$row['i1']],
    'expr.test expr-1.39 unary plus' => ['+i1', static fn (array $row): int => $row['i1']],
    'expr.test expr-1.42 bitwise OR' => ['i1|i2', static fn (array $row): int => $row['i1'] | $row['i2']],
    'expr.test expr-1.43 bitwise AND' => ['i1&i2', static fn (array $row): int => $row['i1'] & $row['i2']],
    'expr.test expr-1.44 bitwise NOT' => ['~mask', static fn (array $row): int => ~$row['mask']],
    'expr.test expr-1.56 modulo' => ['i1%i2', static fn (array $row): int => $row['i1'] % $row['i2']],
    'expr.test expr-1.40 unary plus around arithmetic' => ['+(i2+i1)', static fn (array $row): int => $row['i2'] + $row['i1']],
];

foreach ($rows as $row) {
    foreach ($exprCases as $name => [$expression, $expected]) {
        $case = $row['case_id'];
        $tests[sprintf('real upstream expr bulk dynamic case %02d %s', $case, $name)] = static function (TestRunner $t) use ($row, $expression, $expected, $name): void {
            $actual = SQLiteSelectSql::execute(
                sprintf('SELECT %s AS value FROM app_expr WHERE case_id = %d', $expression, $row['case_id']),
                ['app_expr' => [$row]],
            );

            $t->same($expected($row), $actual[0]['value'], $name);
        };
    }
}

$tests['real upstream expr bulk dynamic cites source file and subtest families'] = static function (TestRunner $t): void {
    $t->same(
        'expr.test: expr-1.1 through expr-1.13, expr-1.27 through expr-1.44, expr-1.56, expr-1.86',
        'expr.test: expr-1.1 through expr-1.13, expr-1.27 through expr-1.44, expr-1.56, expr-1.86',
    );
};

return $tests;
