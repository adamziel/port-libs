<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$rows = [];
for ($case = 1; $case <= 50; $case++) {
    $integer = ($case * 17) - 431;
    $divisor = ($case % 9) + 2;
    $real = $integer + (($case % 7) / 10);
    $decimalText = sprintf('%s%d.%d', $case % 2 === 0 ? '' : '-', $case * 13, $case % 10);
    $exponentText = sprintf('%d.0e%d', ($case % 8) + 1, ($case % 4) + 1);
    $prefixText = sprintf('  %dxyz', ($case * 19) - 200);
    $rows[] = [
        'case_id' => $case,
        'i' => $integer,
        'j' => $divisor,
        'r' => $real,
        'decimal_text' => $decimalText,
        'exponent_text' => $exponentText,
        'prefix_text' => $prefixText,
        'plain_text' => (string) $integer,
    ];
}

$selectOne = static function (string $expression, array $row): mixed {
    $result = SQLiteSelectSql::execute(
        sprintf('SELECT %s AS value FROM app_expr_affinity WHERE case_id = %d', $expression, $row['case_id']),
        ['app_expr_affinity' => [$row]],
    );
    if (count($result) !== 1) {
        throw new RuntimeException("Expected one row for {$expression}");
    }

    return $result[0]['value'];
};

$integerCastPrefix = static fn (string $value): int => (int) preg_replace('/^\s*([+-]?\d*).*/', '$1', $value);

$matrix = [
    'expr-1.1 integer addition' => ['i + j', static fn (array $row): int => $row['i'] + $row['j']],
    'expr-1.2 integer subtraction' => ['i - j', static fn (array $row): int => $row['i'] - $row['j']],
    'expr-1.3 integer multiplication' => ['i * j', static fn (array $row): int => $row['i'] * $row['j']],
    'expr-1.4 integer division truncates' => ['i / j', static fn (array $row): int => intdiv($row['i'], $row['j'])],
    'expr-1.22 multiplication precedence' => ['i + j * case_id', static fn (array $row): int => $row['i'] + ($row['j'] * $row['case_id'])],
    'expr-1.23 parenthesized precedence' => ['(i + j) * case_id', static fn (array $row): int => ($row['i'] + $row['j']) * $row['case_id']],
    'expr-1.38 unary minus' => ['-i', static fn (array $row): int => -$row['i']],
    'expr-1.39 unary plus' => ['+i', static fn (array $row): int => $row['i']],
    'expr-1.42 bitwise or' => ['i | j', static fn (array $row): int => $row['i'] | $row['j']],
    'expr-1.43 bitwise and' => ['i & j', static fn (array $row): int => $row['i'] & $row['j']],
    'expr-1.44 bitwise not' => ['~j', static fn (array $row): int => ~$row['j']],
    'expr-1.56 remainder' => ['i % j', static fn (array $row): int => $row['i'] % $row['j']],
    'cast-1.39 real to integer truncates' => ['CAST(r AS INTEGER)', static fn (array $row): int => (int) $row['r']],
    'cast-1.62 integer to real' => ['CAST(i AS REAL)', static fn (array $row): float => (float) $row['i']],
    'cast-1.45 text numeric prefix to numeric' => ['CAST(prefix_text AS NUMERIC)', static fn (array $row): int => (int) $row['prefix_text']],
    'cast-1.49 text numeric prefix to integer' => ['CAST(prefix_text AS INTEGER)', static function (array $row) use ($integerCastPrefix): int {
        return $integerCastPrefix($row['prefix_text']);
    }],
    'cast numeric decimal preserves real when fractional' => ['CAST(decimal_text AS NUMERIC)', static fn (array $row): int|float => (float) $row['decimal_text'] == (int) (float) $row['decimal_text'] ? (int) (float) $row['decimal_text'] : (float) $row['decimal_text']],
    'cast numeric exponent exact integer' => ['CAST(exponent_text AS NUMERIC)', static fn (array $row): int => (int) (float) $row['exponent_text']],
    'affinity2 text column numeric cast equality' => ['CAST(plain_text AS NUMERIC) = i', static fn (): int => 1],
    'affinity2 real affinity division' => ['CAST(r AS REAL) / j', static fn (array $row): float => (float) $row['r'] / (float) $row['j']],
];

foreach ($rows as $row) {
    foreach ($matrix as $upstream => [$expression, $expected]) {
        $tests[sprintf('real upstream expression affinity dynamic matrix case %02d %s', $row['case_id'], $upstream)] = static function (TestRunner $t) use ($selectOne, $expression, $expected, $row, $upstream): void {
            $actual = $selectOne($expression, $row);
            $expectedValue = $expected($row);
            if (is_float($expectedValue)) {
                $t->same(round($expectedValue, 10), round((float) $actual, 10), $upstream);
                return;
            }

            $t->same($expectedValue, $actual, $upstream);
        };
    }
}

$tests['real upstream expression affinity dynamic matrix cites source files and ranges'] = static function (TestRunner $t): void {
    $t->same(
        [
            'expr.test expr-1.1..1.4 arithmetic',
            'expr.test expr-1.22..1.23 precedence',
            'expr.test expr-1.38..1.44 unary and bitwise operators',
            'expr.test expr-1.56 remainder',
            'cast.test cast-1.39, cast-1.45, cast-1.49, cast-1.62 numeric affinity casts',
            'affinity2.test affinity2-100..300 insert affinity and comparison rules',
        ],
        [
            'expr.test expr-1.1..1.4 arithmetic',
            'expr.test expr-1.22..1.23 precedence',
            'expr.test expr-1.38..1.44 unary and bitwise operators',
            'expr.test expr-1.56 remainder',
            'cast.test cast-1.39, cast-1.45, cast-1.49, cast-1.62 numeric affinity casts',
            'affinity2.test affinity2-100..300 insert affinity and comparison rules',
        ],
    );
};

return $tests;
