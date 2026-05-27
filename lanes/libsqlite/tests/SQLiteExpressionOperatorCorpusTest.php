<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectExpression;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'flags' => 5, 'mask' => 3, 'bytes' => 24, 'value' => '12plugins'],
    ['option_id' => 2, 'option_name' => 'home', 'flags' => 6, 'mask' => 2, 'bytes' => 24, 'value' => '8home'],
    ['option_id' => 3, 'option_name' => 'blogname', 'flags' => 1, 'mask' => 4, 'bytes' => 9, 'value' => 'abc'],
    ['option_id' => 4, 'option_name' => 'orphaned', 'flags' => null, 'mask' => 7, 'bytes' => 0, 'value' => null],
];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$column = static fn (string $name): array => ['type' => 'column', 'name' => $name];
$unary = static fn (string $operator, array $operand): array => ['type' => 'unary', 'operator' => $operator, 'operand' => $operand];
$binary = static fn (string $operator, array $left, array $right): array => [
    'type' => 'binary',
    'operator' => $operator,
    'left' => $left,
    'right' => $right,
];

$expressionCases = [
    'unary plus integer literal' => [$unary('+', $literal(7)), [], 7],
    'unary plus numeric text' => [$unary('+', $literal(' 12plugins')), [], 12],
    'unary plus blob numeric prefix' => [$unary('+', $literal(new SQLiteBlobValue('15blob'))), [], 15],
    'unary plus non numeric text' => [$unary('+', $literal('plugins')), [], 0],
    'unary plus null propagates null' => [$unary('+', $literal(null)), [], null],
    'unary minus integer literal' => [$unary('-', $literal(7)), [], -7],
    'unary minus real literal' => [$unary('-', $literal(2.5)), [], -2.5],
    'unary minus numeric text' => [$unary('-', $literal(' 12plugins')), [], -12],
    'unary minus blob numeric prefix' => [$unary('-', $literal(new SQLiteBlobValue('15blob'))), [], -15],
    'unary minus null propagates null' => [$unary('-', $literal(null)), [], null],
    'unary bitwise not integer' => [$unary('~', $literal(5)), [], -6],
    'unary bitwise not numeric text' => [$unary('~', $literal('5plugins')), [], -6],
    'unary bitwise not real truncates' => [$unary('~', $literal(5.75)), [], -6],
    'unary bitwise not null propagates null' => [$unary('~', $literal(null)), [], null],
    'bitwise and literals' => [$binary('&', $literal(6), $literal(3)), [], 2],
    'bitwise or literals' => [$binary('|', $literal(4), $literal(1)), [], 5],
    'left shift literals' => [$binary('<<', $literal(3), $literal(2)), [], 12],
    'right shift literals' => [$binary('>>', $literal(16), $literal(2)), [], 4],
    'bitwise and truncates real operands' => [$binary('&', $literal(6.9), $literal(3.2)), [], 2],
    'bitwise or numeric text operands' => [$binary('|', $literal('4cache'), $literal('1')), [], 5],
    'left shift numeric text count' => [$binary('<<', $literal('3'), $literal('2rows')), [], 12],
    'right shift blob numeric prefix' => [$binary('>>', $literal(new SQLiteBlobValue('16blob')), $literal(2)), [], 4],
    'bitwise null left propagates null' => [$binary('&', $literal(null), $literal(3)), [], null],
    'bitwise null right propagates null' => [$binary('|', $literal(4), $literal(null)), [], null],
    'unary column bitwise not' => [$unary('~', $column('flags')), $rows[0], -6],
    'column bitwise and mask' => [$binary('&', $column('flags'), $column('mask')), $rows[0], 1],
    'column bitwise or mask' => [$binary('|', $column('flags'), $column('mask')), $rows[0], 7],
    'column left shift mask' => [$binary('<<', $column('mask'), $literal(2)), $rows[0], 12],
    'column right shift bytes' => [$binary('>>', $column('bytes'), $literal(3)), $rows[0], 3],
    'nested bitwise after arithmetic' => [$binary('&', $binary('+', $column('flags'), $literal(2)), $literal(7)), $rows[0], 7],
    'nested shift count after arithmetic' => [$binary('<<', $literal(1), $binary('+', $literal(1), $literal(2))), [], 8],
    'nested unary bitwise mask' => [$binary('&', $unary('~', $column('mask')), $literal(7)), $rows[0], 4],
    'nested unary minus shift' => [$binary('<<', $unary('-', $literal(-2)), $literal(1)), [], 4],
    'concatenation sees unary numeric text' => [$binary('||', $literal('flag:'), $unary('+', $column('value'))), $rows[0], 'flag:12'],
    'modulo after bitwise expression' => [$binary('%', $binary('|', $column('flags'), $literal(8)), $literal(5)), $rows[0], 3],
];

foreach ($expressionCases as $name => [$expression, $row, $expected]) {
    $tests['upstream expression operator corpus ' . $name] = static function (TestRunner $t) use ($expression, $row, $expected): void {
        $t->same($expected, SQLiteSelectExpression::evaluate($row, $expression));
    };
}

$sqlCases = [
    'select bitwise and columns' => ["SELECT option_id AS id, option_name AS name, flags & mask AS both FROM wp_options ORDER BY id", ['siteurl:1', 'home:2', 'blogname:0', 'orphaned:']],
    'select bitwise or columns' => ["SELECT option_id AS id, option_name AS name, flags | mask AS either FROM wp_options ORDER BY id", ['siteurl:7', 'home:6', 'blogname:5', 'orphaned:']],
    'select left shift column' => ["SELECT option_id AS id, option_name AS name, flags << 1 AS shifted FROM wp_options ORDER BY id", ['siteurl:10', 'home:12', 'blogname:2', 'orphaned:']],
    'select right shift column' => ["SELECT option_id AS id, option_name AS name, bytes >> 3 AS pages FROM wp_options ORDER BY id", ['siteurl:3', 'home:3', 'blogname:1', 'orphaned:0']],
    'select unary bitwise not column' => ["SELECT option_id AS id, option_name AS name, ~flags AS inverse FROM wp_options ORDER BY id", ['siteurl:-6', 'home:-7', 'blogname:-2', 'orphaned:']],
    'select unary minus column' => ["SELECT option_id AS id, option_name AS name, -bytes AS neg_bytes FROM wp_options ORDER BY id", ['siteurl:-24', 'home:-24', 'blogname:-9', 'orphaned:0']],
    'select unary plus numeric text' => ["SELECT option_id AS id, option_name AS name, +value AS numeric_value FROM wp_options ORDER BY id", ['siteurl:12', 'home:8', 'blogname:0', 'orphaned:']],
    'where bitwise and equals' => ["SELECT option_id AS id, option_name AS name, flags FROM wp_options WHERE flags & 1 = 1 ORDER BY id", ['siteurl:5', 'blogname:1']],
    'where bitwise or equals' => ["SELECT option_id AS id, option_name AS name, flags | mask AS combined FROM wp_options WHERE flags | mask = 7 ORDER BY id", ['siteurl:7']],
    'where left shift comparison' => ["SELECT option_id AS id, option_name AS name, flags << 1 AS shifted FROM wp_options WHERE flags << 1 >= 10 ORDER BY id", ['siteurl:10', 'home:12']],
    'where right shift comparison' => ["SELECT option_id AS id, option_name AS name, bytes >> 3 AS bucket FROM wp_options WHERE bytes >> 3 = 3 ORDER BY id", ['siteurl:3', 'home:3']],
    'where unary bitwise not comparison' => ["SELECT option_id AS id, option_name AS name, ~flags AS inverse FROM wp_options WHERE ~flags < -5 ORDER BY id", ['siteurl:-6', 'home:-7']],
    'where unary plus numeric text comparison' => ["SELECT option_id AS id, option_name AS name, +value AS numeric_value FROM wp_options WHERE +value >= 8 ORDER BY numeric_value DESC, id", ['siteurl:12', 'home:8']],
    'order by bitwise expression' => ["SELECT option_id AS id, option_name AS name FROM wp_options WHERE flags IS NOT NULL ORDER BY flags & 3 DESC, id ASC", ['home', 'siteurl', 'blogname']],
    'order by unary expression' => ["SELECT option_id AS id, option_name AS name FROM wp_options WHERE flags IS NOT NULL ORDER BY ~flags ASC", ['home', 'siteurl', 'blogname']],
    'precedence arithmetic before bitwise and' => ["SELECT option_id AS id, option_name AS name, flags + 2 & 7 AS masked FROM wp_options WHERE option_id = 1", ['siteurl:7']],
    'precedence arithmetic shift count' => ["SELECT option_id AS id, option_name AS name, 1 << option_id + 1 AS shifted FROM wp_options WHERE option_id <= 3 ORDER BY id", ['siteurl:4', 'home:8', 'blogname:16']],
    'precedence multiplication before bitwise or' => ["SELECT option_id AS id, option_name AS name, flags | mask * 2 AS combined FROM wp_options WHERE option_id = 1", ['siteurl:7']],
    'parenthesized bitwise before arithmetic' => ["SELECT option_id AS id, option_name AS name, (flags | mask) * 2 AS weight FROM wp_options WHERE option_id = 1", ['siteurl:14']],
    'parenthesized arithmetic before shift' => ["SELECT option_id AS id, option_name AS name, (flags + 1) << 1 AS shifted FROM wp_options WHERE option_id = 1", ['siteurl:12']],
    'unary bitwise not with mask' => ["SELECT option_id AS id, option_name AS name, ~flags & 7 AS inverted_low FROM wp_options WHERE flags IS NOT NULL ORDER BY id", ['siteurl:2', 'home:1', 'blogname:6']],
    'null bitwise filtered out by comparison' => ["SELECT option_id AS id, option_name AS name FROM wp_options WHERE flags & 1 = 1 ORDER BY id", ['siteurl', 'blogname']],
    'null bitwise projection remains null' => ["SELECT option_id AS id, option_name AS name, flags & mask AS both FROM wp_options WHERE option_name = 'orphaned'", ['orphaned:']],
    'bitwise in grouped aggregate having' => ["SELECT flags & 1 AS odd_flag, sum(bytes) AS total FROM wp_options WHERE flags IS NOT NULL AND flags & 1 = 1 GROUP BY flags HAVING sum(bytes) >= 9 ORDER BY total DESC", ['1:24', '1:9']],
    'bitwise expression before limit offset' => ["SELECT option_id AS id, option_name AS name FROM wp_options WHERE option_id <= (1 << 2) ORDER BY flags | mask DESC, id LIMIT 2 OFFSET 1", ['home', 'blogname']],
];

foreach ($sqlCases as $name => [$sql, $expected]) {
    $tests['upstream select sql operator corpus ' . $name] = static function (TestRunner $t) use ($sql, $rows, $expected): void {
        $actualRows = SQLiteSelectSql::execute($sql, ['wp_options' => $rows]);
        $actual = array_map(static function (array $row): string {
            unset($row['id']);
            $values = array_values($row);

            return implode(':', array_map(static fn (mixed $value): string => $value === null ? '' : (string) $value, $values));
        }, $actualRows);
        $t->same($expected, $actual);
    };
}

return $tests;
