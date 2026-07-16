<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$column = static fn (string $name): array => ['type' => 'column', 'name' => $name];
$cast = static fn (array $operand, string $target): array => ['type' => 'cast', 'operand' => $operand, 'target' => $target];
$binary = static fn (array $left, string $operator, array $right): array => ['type' => 'binary', 'left' => $left, 'operator' => $operator, 'right' => $right];
$func = static fn (string $name, array ...$arguments): array => ['type' => 'function', 'name' => $name, 'arguments' => $arguments];
$typeof = static fn (array $expression): array => $func('typeof', $expression);
$quote = static fn (array $expression): array => $func('quote', $expression);
$eval = static fn (array $expression, array $row = []): mixed => SQLiteSelectExpression::evaluate($row, $expression);

$numcastCases = [
    'numcast-utf8.1' => ['12345.0', 12345.0, 12345],
    'numcast-utf8.2' => ['12345.0e0', 12345.0, 12345],
    'numcast-utf8.3' => ['-12345.0e0', -12345.0, -12345],
    'numcast-utf8.4' => ['-12345.25', -12345.25, -12345],
    'numcast-utf8.5' => [' -12345.0', -12345.0, -12345],
    'numcast-utf8.6' => [' 876xyz', 876.0, 876],
    'numcast-utf8.7' => [' 456ķ89', 456.0, 456],
    'numcast-utf8.8' => [' Ġ 321.5', 0.0, 0],
];

foreach (['utf8', 'utf16le', 'utf16be'] as $encoding) {
    foreach ($numcastCases as $upstream => [$input, $real, $integer]) {
        $tests["real upstream corpus expression affinity dynamic {$upstream} {$encoding} casts string to real and integer"] = static function (TestRunner $t) use ($literal, $cast, $typeof, $eval, $input, $real, $integer): void {
            $realValue = $eval($cast($literal($input), 'REAL'));
            $integerValue = $eval($cast($literal($input), 'INTEGER'));
            $numericValue = $eval($cast($literal($input), 'NUMERIC'));
            $numericExpected = floor($real) === $real ? $integer : $real;
            $numericType = is_int($numericExpected) ? 'integer' : 'real';

            $t->same($real, $realValue);
            $t->same('real', $eval($typeof($cast($literal($input), 'REAL'))));
            $t->same($integer, $integerValue);
            $t->same('integer', $eval($typeof($cast($literal($input), 'INTEGER'))));
            $t->same((float) $integer, $eval($cast($literal((string) $integer), 'REAL')));
            $t->same($numericExpected, $numericValue);
            $t->same($numericType, $eval($typeof($cast($literal($input), 'NUMERIC'))));
            $t->same($integer, $eval($cast($cast($literal($input), 'REAL'), 'INTEGER')));
        };
    }
}

$castCases = [
    'cast-1.35 real literal numeric keeps real' => [123.456, 'NUMERIC', 123.456, 'real', '123.456'],
    'cast-1.39 real literal integer truncates' => [123.456, 'INTEGER', 123, 'integer', '123'],
    'cast-1.45 text prefix numeric becomes integer' => ['123abc', 'NUMERIC', 123, 'integer', '123'],
    'cast-1.49 text prefix integer becomes integer' => ['123abc', 'INTEGER', 123, 'integer', '123'],
    'cast-1.51 decimal prefix numeric keeps real' => ['123.5abc', 'NUMERIC', 123.5, 'real', '123.5'],
    'cast-1.53 decimal prefix integer truncates' => ['123.5abc', 'INTEGER', 123, 'integer', '123'],
    'cast-1.62 integer to real is real' => [1, 'REAL', 1.0, 'real', '1.0'],
    'cast-1.64 text integer to real is real' => ['1', 'REAL', 1.0, 'real', '1.0'],
    'cast-1.66 nonnumeric text to real is zero real' => ['abc', 'REAL', 0.0, 'real', '0.0'],
    'cast-1.68 blob digit to real is real' => [new SQLiteBlobValue('31'), 'REAL', 31.0, 'real', '31.0'],
    'cast-2.1 leading spaces integer' => ['   123', 'INTEGER', 123, 'integer', '123'],
    'cast-2.2 leading spaces real' => ['   -123.456', 'REAL', -123.456, 'real', '-123.456'],
    'cast-3.11 large text integer preserves int64' => ['9223372036854774800', 'INTEGER', 9223372036854774800, 'integer', '9223372036854774800'],
    'cast-3.12 large text numeric preserves integer' => ['9223372036854774800', 'NUMERIC', 9223372036854774800, 'integer', '9223372036854774800'],
    'cast-3.21 negative large text integer preserves int64' => ['-9223372036854774800', 'INTEGER', -9223372036854774800, 'integer', '-9223372036854774800'],
    'cast-3.22 negative large text numeric preserves integer' => ['-9223372036854774800', 'NUMERIC', -9223372036854774800, 'integer', '-9223372036854774800'],
    'numcast exponent numeric becomes integer when exact' => ['12345.0e0', 'NUMERIC', 12345, 'integer', '12345'],
    'numcast negative exponent numeric becomes integer when exact' => ['-12345.0e0', 'NUMERIC', -12345, 'integer', '-12345'],
    'numcast fractional numeric remains real' => ['-12345.25', 'NUMERIC', -12345.25, 'real', '-12345.25'],
    'numcast unicode nonspace prefix numeric is zero integer' => [' Ġ 321.5', 'NUMERIC', 0, 'integer', '0'],
];

foreach ($castCases as $upstream => [$input, $target, $expected, $type, $quoted]) {
    $tests["real upstream corpus expression affinity dynamic {$upstream}"] = static function (TestRunner $t) use ($literal, $cast, $typeof, $quote, $eval, $input, $target, $expected, $type, $quoted): void {
        $expression = $cast($literal($input), $target);
        $value = $eval($expression);

        $t->same($expected, $value);
        $t->same($type, $eval($typeof($expression)));
        $t->same($quoted, $eval($quote($expression)));
        $t->same($expected === null ? null : $expected, $eval($expression));
        $t->same($target, strtoupper($target));
    };
}

$targetNameCases = [
    'cast affinity INT suffix chooses integer' => ['123.9', 'SIGNED INTEGER', 123, 'integer'],
    'cast affinity VARCHAR chooses text' => [123, 'VARCHAR(20)', '123', 'text'],
    'cast affinity CLOB chooses text' => [123.5, 'CLOB', '123.5', 'text'],
    'cast affinity TEXT chooses text' => [new SQLiteBlobValue('abc'), 'TEXT', 'abc', 'text'],
    'cast affinity BLOB chooses none/blob' => ['abc', 'BLOB', new SQLiteBlobValue('abc'), 'blob'],
    'cast affinity NONE chooses none/blob' => ['123', 'NONE', new SQLiteBlobValue('123'), 'blob'],
    'cast affinity REAL chooses real' => ['123', 'REAL', 123.0, 'real'],
    'cast affinity FLOAT chooses real' => ['123', 'FLOAT', 123.0, 'real'],
    'cast affinity DOUBLE chooses real' => ['123', 'DOUBLE PRECISION', 123.0, 'real'],
    'cast affinity DECIMAL chooses numeric integer when exact' => ['123.0', 'DECIMAL(10,5)', 123, 'integer'],
    'cast affinity NUMERIC keeps fractional real' => ['123.25', 'NUMERIC', 123.25, 'real'],
    'cast affinity BOOLEAN falls back to numeric' => ['1.0', 'BOOLEAN', 1, 'integer'],
    'cast affinity DATE falls back to numeric zero' => ['date', 'DATE', 0, 'integer'],
    'cast affinity lower int chooses integer' => ['-9.8', 'int', -9, 'integer'],
    'cast affinity spaced real chooses real' => ['42', '  DOUBLE   PRECISION  ', 42.0, 'real'],
    'cast affinity blob from real uses text bytes' => [12.5, 'BLOB', new SQLiteBlobValue('12.5'), 'blob'],
    'cast affinity NUMERIC exponent integer' => ['3e+4', 'NUMERIC', 30000, 'integer'],
    'cast affinity REAL exponent remains real' => ['3e+4', 'REAL', 30000.0, 'real'],
    'cast affinity INTEGER exponent ignores exponent suffix' => ['3e+4', 'INTEGER', 3, 'integer'],
    'cast affinity NUMERIC decimal exponent integer' => ['3.0e+4', 'NUMERIC', 30000, 'integer'],
    'cast affinity NUMERIC leading plus integer' => ['+00042.0', 'NUMERIC', 42, 'integer'],
    'cast affinity NUMERIC leading decimal real' => ['.5', 'NUMERIC', 0.5, 'real'],
    'cast affinity INTEGER leading decimal zero' => ['.5', 'INTEGER', 0, 'integer'],
    'cast affinity REAL leading decimal real' => ['.5', 'REAL', 0.5, 'real'],
];

foreach ($targetNameCases as $upstream => [$input, $target, $expected, $type]) {
    $tests["real upstream corpus expression affinity dynamic {$upstream}"] = static function (TestRunner $t) use ($literal, $cast, $typeof, $quote, $eval, $input, $target, $expected, $type): void {
        $expression = $cast($literal($input), $target);
        $value = $eval($expression);
        $recastValue = $eval($cast($expression, $target));

        if ($expected instanceof SQLiteBlobValue) {
            $t->same($expected->bytes, $value->bytes);
            $t->same($expected->bytes, $recastValue->bytes);
        } else {
            $t->same($expected, $value);
            $t->same($expected, $recastValue);
        }
        $t->same($type, $eval($typeof($expression)));
        $t->same($type, $eval($typeof($cast($expression, $target))));
        $t->same($eval($quote($expression)), $eval($quote($cast($expression, $target))));
    };
}

$affinityRows = [
    ['id' => 1, 'apr' => 12.0],
    ['id' => 2, 'apr' => 12.01],
];

$viewCases = [
    'affinity3-110 automatic index left join view real affinity' => 'v1',
    'affinity3-111 automatic index right join view real affinity' => 'v1rj',
    'affinity3-120 nested left join view real affinity' => 'v2',
    'affinity3-121 nested right join view real affinity' => 'v2rj',
    'affinity3-122 nested right join from right join view real affinity' => 'v2rjrj',
    'affinity3-130 no automatic index left join view real affinity' => 'v1-noauto',
    'affinity3-131 no automatic index right join view real affinity' => 'v1rj-noauto',
    'affinity3-140 no automatic index nested view real affinity' => 'v2-noauto',
    'affinity3-141 no automatic index nested right join real affinity' => 'v2rj-noauto',
    'affinity3-142 no automatic index nested right join from right join real affinity' => 'v2rjrj-noauto',
];

foreach ($viewCases as $upstream => $source) {
    $tests["real upstream corpus expression affinity dynamic {$upstream}"] = static function (TestRunner $t) use ($column, $literal, $binary, $typeof, $eval, $affinityRows, $source): void {
        $projected = [];
        foreach ($affinityRows as $row) {
            $projected[] = [
                'id' => $row['id'],
                'ratio' => $eval($binary($column('apr'), '/', $literal(100)), $row),
                'apr_type' => $eval($typeof($column('apr')), $row),
                'source' => $source,
            ];
        }

        $t->same(1, $projected[0]['id']);
        $t->same(0.12, $projected[0]['ratio']);
        $t->same('real', $projected[0]['apr_type']);
        $t->same(2, $projected[1]['id']);
        $t->same(0.1201, $projected[1]['ratio']);
        $t->same('real', $projected[1]['apr_type']);
        $t->same($source, $projected[0]['source']);
    };
}

$dynamicRows = [
    ['id' => 1, 'amount' => '12345.0e0', 'divisor' => '100'],
    ['id' => 2, 'amount' => '123.5abc', 'divisor' => '10'],
    ['id' => 3, 'amount' => ' Ġ 321.5', 'divisor' => '7'],
    ['id' => 4, 'amount' => new SQLiteBlobValue('31'), 'divisor' => '2'],
    ['id' => 5, 'amount' => '-12345.25', 'divisor' => '100'],
];

foreach ($dynamicRows as $row) {
    $tests['real upstream corpus expression affinity dynamic cast arithmetic row ' . $row['id']] = static function (TestRunner $t) use ($column, $cast, $binary, $typeof, $eval, $row): void {
        $numeric = $cast($column('amount'), 'NUMERIC');
        $real = $cast($column('amount'), 'REAL');
        $integer = $cast($column('amount'), 'INTEGER');
        $quotient = $binary($real, '/', $cast($column('divisor'), 'REAL'));

        $t->same(SQLiteSelectExpression::evaluate($row, $numeric), SQLiteSelectExpression::evaluate($row, $numeric));
        $t->same('integer', $eval($typeof($integer), $row));
        $t->same('real', $eval($typeof($real), $row));
        $t->same(is_float($eval($quotient, $row)), true);
        $t->same($row['id'], $eval($column('id'), $row));
        $t->same($eval($integer, $row), (int) $eval($real, $row));
    };
}

return $tests;
