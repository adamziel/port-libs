<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectExpression;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$cast = static fn (mixed $value, string $target): array => ['type' => 'cast', 'operand' => $literal($value), 'target' => $target];
$unary = static fn (string $operator, mixed $value): array => ['type' => 'unary', 'operator' => $operator, 'operand' => $literal($value)];
$binary = static fn (string $operator, mixed $left, mixed $right): array => ['type' => 'binary', 'operator' => $operator, 'left' => $literal($left), 'right' => $literal($right)];

$expressionCases = [
    'integer cast clamps positive overflow' => [$cast('9223372036854775808', 'INTEGER'), PHP_INT_MAX],
    'integer cast clamps positive all nines overflow' => [$cast('999999999999999999999', 'INTEGER'), PHP_INT_MAX],
    'integer cast clamps unsigned 64 max text' => [$cast('18446744073709551615', 'INTEGER'), PHP_INT_MAX],
    'integer cast clamps negative overflow' => [$cast('-9223372036854775809', 'INTEGER'), PHP_INT_MIN],
    'integer cast preserves signed int64 minimum' => [$cast('-9223372036854775808', 'INTEGER'), PHP_INT_MIN],
    'integer cast preserves signed int64 maximum' => [$cast('9223372036854775807', 'INTEGER'), PHP_INT_MAX],
    'integer cast overflow ignores decimal tail' => [$cast('9223372036854775808.5', 'INTEGER'), PHP_INT_MAX],
    'integer cast overflow ignores exponent tail' => [$cast('9223372036854775808e2', 'INTEGER'), PHP_INT_MAX],
    'integer cast overflow from blob bytes' => [$cast(new SQLiteBlobValue('9223372036854775808blob'), 'INTEGER'), PHP_INT_MAX],
    'numeric cast keeps int64 maximum as integer' => [$cast('9223372036854775807', 'NUMERIC'), PHP_INT_MAX],
    'numeric cast keeps int64 minimum as integer' => [$cast('-9223372036854775808', 'NUMERIC'), PHP_INT_MIN],
    'numeric cast promotes positive overflow to real' => [$cast('9223372036854775808', 'NUMERIC'), 9.223372036854776E+18],
    'numeric cast promotes negative overflow to real' => [$cast('-9223372036854775809', 'NUMERIC'), -9.223372036854776E+18],
    'numeric cast promotes leading-zero overflow to real' => [$cast('0009223372036854775808tail', 'NUMERIC'), 9.223372036854776E+18],
    'numeric unary plus promotes overflow to real' => [$unary('+', '18446744073709551615'), 1.8446744073709552E+19],
    'numeric unary minus promotes overflow to real' => [$unary('-', '18446744073709551615'), -1.8446744073709552E+19],
    'numeric addition uses real when left integer text overflows' => [$binary('+', '9223372036854775808', '1'), 9.223372036854776E+18],
    'numeric multiplication uses real when right integer text overflows' => [$binary('*', '2', '9223372036854775808'), 1.8446744073709552E+19],
];

foreach ($expressionCases as $name => [$expression, $expected]) {
    $tests['upstream vdbe numeric cast overflow expression ' . $name] = static function (TestRunner $t) use ($expression, $expected): void {
        $actual = SQLiteSelectExpression::evaluate([], $expression);
        if (is_float($expected)) {
            $t->true(is_float($actual));
            $t->same(sprintf('%.17g', $expected), sprintf('%.17g', $actual));
            return;
        }

        $t->same($expected, $actual);
    };
}

$rows = [
    ['option_id' => 1, 'option_name' => 'autoload_threshold', 'option_value' => '9223372036854775807'],
    ['option_id' => 2, 'option_name' => 'overflow_threshold', 'option_value' => '9223372036854775808'],
    ['option_id' => 3, 'option_name' => 'negative_overflow_threshold', 'option_value' => '-9223372036854775809'],
    ['option_id' => 4, 'option_name' => 'unsigned_threshold', 'option_value' => '18446744073709551615'],
    ['option_id' => 5, 'option_name' => 'zero_threshold', 'option_value' => '0'],
];

$sqlCases = [
    'integer cast clamps overflow rows for ordering' => ["SELECT option_name, CAST(option_value AS INTEGER) AS threshold FROM wp_options ORDER BY threshold DESC, option_id LIMIT 3", ['autoload_threshold:9223372036854775807', 'overflow_threshold:9223372036854775807', 'unsigned_threshold:9223372036854775807']],
    'numeric cast orders overflow real above int64 maximum' => ["SELECT option_name, CAST(option_value AS NUMERIC) AS threshold FROM wp_options ORDER BY threshold DESC LIMIT 3", ['unsigned_threshold:1.8446744073710E+19', 'overflow_threshold:9.2233720368548E+18', 'autoload_threshold:9223372036854775807']],
    'integer cast lower clamp filters negative overflow' => ["SELECT option_name, CAST(option_value AS INTEGER) AS threshold FROM wp_options WHERE CAST(option_value AS INTEGER) = CAST('-9223372036854775808' AS INTEGER) ORDER BY option_id", ['negative_overflow_threshold:-9223372036854775808']],
    'numeric cast comparison separates int64 maximum from overflow real' => ["SELECT option_name FROM wp_options WHERE CAST(option_value AS NUMERIC) > CAST('9223372036854775807' AS NUMERIC) ORDER BY option_id", ['overflow_threshold', 'unsigned_threshold']],
    'numeric cast in arithmetic keeps overflow real storage' => ["SELECT option_name, CAST(option_value AS NUMERIC) + 1 AS threshold FROM wp_options WHERE option_id IN (1, 2) ORDER BY option_id", ['autoload_threshold:9.2233720368548E+18', 'overflow_threshold:9.2233720368548E+18']],
    'integer cast in arithmetic uses clamped int64 operand' => ["SELECT option_name, CAST(option_value AS INTEGER) + 1 AS threshold FROM wp_options WHERE option_id IN (1, 2) ORDER BY option_id", ['autoload_threshold:9.2233720368548E+18', 'overflow_threshold:9.2233720368548E+18']],
    'numeric cast treats lower clamp boundary as integer' => ["SELECT option_name FROM wp_options WHERE CAST('-9223372036854775808' AS NUMERIC) = CAST(option_value AS INTEGER) ORDER BY option_id", ['negative_overflow_threshold']],
];

foreach ($sqlCases as $name => [$sql, $expected]) {
    $tests['upstream select sql vdbe numeric cast overflow ' . $name] = static function (TestRunner $t) use ($sql, $rows, $expected): void {
        $actualRows = SQLiteSelectSql::execute($sql, ['wp_options' => $rows]);
        $actual = array_map(static function (array $row): string {
            return implode(':', array_map(static fn (mixed $value): string => is_float($value) ? sprintf('%.13E', $value) : (string) $value, array_values($row)));
        }, $actualRows);

        $t->same($expected, $actual);
    };
}

return $tests;
