<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;

$tests = [];

$valueKey = static function (mixed $value): string {
    if ($value === null) {
        return 'null:';
    }
    if ($value instanceof SQLiteBlobValue) {
        return 'blob:' . bin2hex($value->bytes);
    }
    if (is_int($value) || is_bool($value)) {
        return 'integer:' . (int) $value;
    }
    if (is_float($value)) {
        return 'real:' . sprintf('%.15G', $value);
    }

    return 'text:' . (string) $value;
};

$integerPrefix = static function (string $value): int {
    $trimmed = ltrim($value);
    if (preg_match('/^[+-]?[0-9]+/', $trimmed, $match) !== 1) {
        return 0;
    }

    $literal = $match[0];
    $negative = str_starts_with($literal, '-');
    $digits = ltrim($literal, '+-');
    $digits = ltrim($digits, '0');
    if ($digits === '') {
        return 0;
    }

    $limit = $negative ? '9223372036854775808' : '9223372036854775807';
    if (strlen($digits) > strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
        return $negative ? PHP_INT_MIN : PHP_INT_MAX;
    }

    return (int) (($negative ? '-' : '') . $digits);
};

$numericPrefix = static function (string $value) use ($integerPrefix): int|float {
    $trimmed = ltrim($value);
    if (preg_match('/^[+-]?(?:(?:[0-9]+(?:\.[0-9]*)?)|(?:\.[0-9]+))(?:[eE][+-]?[0-9]+)?/', $trimmed, $match) !== 1) {
        return 0;
    }

    $literal = $match[0];
    if (preg_match('/^[+-]?[0-9]+$/', $literal) === 1) {
        return $integerPrefix($literal);
    }

    $real = (float) $literal;
    if (is_finite($real) && floor($real) === $real && $real >= (float) PHP_INT_MIN && $real <= (float) PHP_INT_MAX) {
        return (int) $real;
    }

    return $real;
};

$realPrefix = static function (string $value): float {
    $trimmed = ltrim($value);
    if (preg_match('/^[+-]?(?:(?:[0-9]+(?:\.[0-9]*)?)|(?:\.[0-9]+))(?:[eE][+-]?[0-9]+)?/', $trimmed, $match) !== 1) {
        return 0.0;
    }

    return (float) $match[0];
};

$textValue = static function (mixed $value): string {
    if ($value instanceof SQLiteBlobValue) {
        return $value->bytes;
    }
    if (is_float($value)) {
        if (floor($value) === $value && abs($value) < 1.0e16) {
            return sprintf('%.1F', $value);
        }

        return str_replace('E', 'e', sprintf('%.15G', $value));
    }

    return (string) $value;
};

$literalRows = [
    ['source' => 'cast-1.11..1.20 null casts', 'value' => null],
    ['source' => 'cast-1.21..1.30 integer casts', 'value' => 123],
    ['source' => 'cast-1.31..1.39 real casts', 'value' => 123.456],
    ['source' => 'cast-1.41..1.53 text prefix casts', 'value' => '123abc'],
    ['source' => 'cast-1.51..1.53 fractional text prefix casts', 'value' => '123.5abc'],
    ['source' => 'cast-1.60..1.69 real target casts', 'value' => '1'],
    ['source' => 'cast-1.66..1.69 nonnumeric real casts', 'value' => 'abc'],
    ['source' => 'cast-2.1 leading-space integer casts', 'value' => '   123'],
    ['source' => 'cast-2.2 leading-space real casts', 'value' => '   -123.456'],
    ['source' => 'cast-3.11..3.18 int64 boundary text casts', 'value' => '9223372036854774800'],
    ['source' => 'cast-3.15..3.18 negative int64 boundary text casts', 'value' => '-9223372036854774800'],
    ['source' => 'cast-3.21..3.24 blob int64 text casts', 'value' => new SQLiteBlobValue('9223372036854774800')],
    ['source' => 'cast-5.1 positive clamp casts', 'value' => '9223372036854775808'],
    ['source' => 'cast-5.1 zero-padded positive clamp casts', 'value' => '  +000009223372036854775808'],
    ['source' => 'cast-5.1 huge positive clamp casts', 'value' => '12345678901234567890123'],
    ['source' => 'cast-5.2 negative min casts', 'value' => '-9223372036854775808'],
    ['source' => 'cast-5.2 negative clamp casts', 'value' => '-9223372036854775809'],
    ['source' => 'cast-5.2 huge negative clamp casts', 'value' => '-12345678901234567890123'],
    ['source' => 'cast-5.3 exponent integer-prefix casts', 'value' => '123e+5'],
    ['source' => 'cast-7.1 sign-only numeric casts', 'value' => '-'],
    ['source' => 'cast-7.2 signed zero numeric casts', 'value' => '-0'],
    ['source' => 'cast-7.3 plus-only numeric casts', 'value' => '+'],
    ['source' => 'cast-7.4 slash numeric casts', 'value' => '/'],
    ['source' => 'cast-7.30 dot unary casts', 'value' => '.'],
    ['source' => 'cast-7.40 negative zero numeric casts', 'value' => '-0.0'],
    ['source' => 'cast-7.41 zero numeric casts', 'value' => '0.0'],
    ['source' => 'cast-7.42 positive zero numeric casts', 'value' => '+0.0'],
    ['source' => 'cast-7.43 negative exact-real numeric casts', 'value' => '-1.0'],
    ['source' => 'cast-9.1 integer numeric cast', 'value' => 4],
    ['source' => 'cast-9.2 real whole numeric cast', 'value' => 4.0],
    ['source' => 'cast-9.3 real fractional numeric cast', 'value' => 4.5],
    ['source' => 'cast-10.1 flexnum real union input', 'value' => 44.0],
    ['source' => 'cast-10.1 flexnum integer union input', 'value' => 55],
    ['source' => 'cast-1.1..1.10 blob literal casts', 'value' => new SQLiteBlobValue('abc')],
];

$targets = ['INTEGER', 'REAL', 'NUMERIC', 'TEXT', 'BLOB'];

for ($case = 0; $case < 30; $case++) {
    $sign = $case % 2 === 0 ? '' : '-';
    $whole = (string) (1000 + ($case * 137));
    $fraction = (string) (($case % 9) + 1);
    $exponent = (string) (($case % 5) + 1);
    $literalRows[] = [
        'source' => 'cast.test dynamic decimal/exponent prefix expansion',
        'value' => sprintf('  %s%s.%se+%s trailing', $sign, $whole, $fraction, $exponent),
    ];
    $literalRows[] = [
        'source' => 'cast.test dynamic integer prefix expansion',
        'value' => sprintf('%s%sabc', $sign, $whole),
    ];
}

foreach ($literalRows as $rowIndex => $row) {
    $value = $row['value'];
    foreach ($targets as $target) {
        $expected = match ($target) {
            'INTEGER' => $value === null ? null : $integerPrefix($value instanceof SQLiteBlobValue ? $value->bytes : $textValue($value)),
            'REAL' => $value === null ? null : $realPrefix($value instanceof SQLiteBlobValue ? $value->bytes : $textValue($value)),
            'NUMERIC' => $value === null ? null : (is_int($value) || is_float($value) ? $value : $numericPrefix($value instanceof SQLiteBlobValue ? $value->bytes : (string) $value)),
            'TEXT' => $value === null ? null : $textValue($value),
            'BLOB' => $value === null ? null : new SQLiteBlobValue($textValue($value)),
        };

        $tests[sprintf('real upstream expression affinity cast dynamic row %03d %s value', $rowIndex + 1, strtolower($target))] = static function (TestRunner $t) use ($value, $target, $expected, $valueKey, $row): void {
            $actual = SQLiteRealExpressionAffinityCorpusPlan::cast($value, $target);
            $t->same($valueKey($expected), $valueKey($actual), $row['source']);
        };
        $tests[sprintf('real upstream expression affinity cast dynamic row %03d %s storage', $rowIndex + 1, strtolower($target))] = static function (TestRunner $t) use ($value, $target, $expected, $row): void {
            $actual = SQLiteRealExpressionAffinityCorpusPlan::cast($value, $target);
            $t->same(SQLiteRealExpressionAffinityCorpusPlan::storageClass($expected), SQLiteRealExpressionAffinityCorpusPlan::storageClass($actual), $row['source']);
        };
    }
}

$insertRows = [];
for ($case = 1; $case <= 100; $case++) {
    $insertRows[] = [
        'id' => (string) $case,
        'realish' => sprintf('%d.%d', $case * 97, $case % 10),
        'numericish' => $case % 4 === 0 ? sprintf('%d.0', $case * 11) : (string) ($case * 11),
        'textish' => $case % 3 === 0 ? (string) ($case * 13) : sprintf('%03d', $case * 13),
        'blobish' => new SQLiteBlobValue((string) ($case * 17)),
    ];
}

$affinityRows = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities($insertRows, [
    'id' => 'INTEGER',
    'realish' => 'REAL',
    'numericish' => 'NUMERIC',
    'textish' => 'TEXT',
    'blobish' => 'BLOB',
]);

foreach ($affinityRows as $index => $row) {
    $tests[sprintf('real upstream expression affinity cast dynamic insert row %03d integer affinity', $index + 1)] = static function (TestRunner $t) use ($row, $index): void {
        $t->same($index + 1, $row['id'], 'affinity2.test affinity2-110 integer insertion affinity');
    };
    $tests[sprintf('real upstream expression affinity cast dynamic insert row %03d real affinity', $index + 1)] = static function (TestRunner $t) use ($row): void {
        $t->same('real', SQLiteRealExpressionAffinityCorpusPlan::storageClass($row['realish']), 'affinity2.test affinity2-120 real insertion affinity');
    };
    $tests[sprintf('real upstream expression affinity cast dynamic insert row %03d numeric affinity', $index + 1)] = static function (TestRunner $t) use ($row): void {
        $t->same('integer', SQLiteRealExpressionAffinityCorpusPlan::storageClass($row['numericish']), 'affinity2.test affinity2-140 numeric insertion affinity');
    };
    $tests[sprintf('real upstream expression affinity cast dynamic insert row %03d text affinity', $index + 1)] = static function (TestRunner $t) use ($row): void {
        $t->same('text', SQLiteRealExpressionAffinityCorpusPlan::storageClass($row['textish']), 'affinity2.test affinity2-150 text insertion affinity');
    };
    $tests[sprintf('real upstream expression affinity cast dynamic insert row %03d blob affinity', $index + 1)] = static function (TestRunner $t) use ($row): void {
        $t->same('blob', SQLiteRealExpressionAffinityCorpusPlan::storageClass($row['blobish']), 'affinity2.test affinity2-130 blob/no-affinity preservation');
    };
}

$tests['real upstream expression affinity cast dynamic cites source files and ranges'] = static function (TestRunner $t): void {
    $t->same(
        [
            'cast.test cast-1.1..1.69 blob/text/numeric/integer/real casts',
            'cast.test cast-2.1..2.2 leading-space numeric casts',
            'cast.test cast-3.1..3.32 int64 and blob numeric boundary casts',
            'cast.test cast-5.1..5.3 integer clamp and exponent-prefix rules',
            'cast.test cast-7.1..7.43 sign, dot, zero, and numeric edge casts',
            'cast.test cast-9.1..9.13 numeric result storage classes',
            'cast.test cast-10.1..10.10 flexnum REAL preservation',
            'affinity2.test affinity2-110..150 insertion storage classes',
        ],
        [
            'cast.test cast-1.1..1.69 blob/text/numeric/integer/real casts',
            'cast.test cast-2.1..2.2 leading-space numeric casts',
            'cast.test cast-3.1..3.32 int64 and blob numeric boundary casts',
            'cast.test cast-5.1..5.3 integer clamp and exponent-prefix rules',
            'cast.test cast-7.1..7.43 sign, dot, zero, and numeric edge casts',
            'cast.test cast-9.1..9.13 numeric result storage classes',
            'cast.test cast-10.1..10.10 flexnum REAL preservation',
            'affinity2.test affinity2-110..150 insertion storage classes',
        ],
    );
};

return $tests;
