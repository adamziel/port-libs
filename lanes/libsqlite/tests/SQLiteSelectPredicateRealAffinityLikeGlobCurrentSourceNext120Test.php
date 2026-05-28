<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectPredicate;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$rows = [
    ['option_id' => 1, 'option_name' => 'plugin_real_one', 'option_value' => 1.0, 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'plugin_real_one_half', 'option_value' => 1.5, 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin_real_four', 'option_value' => 4.0, 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => 'plugin_real_four_half', 'option_value' => 4.5, 'autoload' => 'yes'],
    ['option_id' => 5, 'option_name' => 'plugin_real_micro', 'option_value' => 0.000001, 'autoload' => 'yes'],
    ['option_id' => 6, 'option_name' => 'plugin_real_tiny', 'option_value' => 0.0000001, 'autoload' => 'no'],
    ['option_id' => 7, 'option_name' => 'plugin_real_large', 'option_value' => 1000000000000000.0, 'autoload' => 'yes'],
    ['option_id' => 8, 'option_name' => 'plugin_real_huge', 'option_value' => 1.0e20, 'autoload' => 'no'],
    ['option_id' => 9, 'option_name' => 'plugin_text_one', 'option_value' => '1', 'autoload' => 'yes'],
    ['option_id' => 10, 'option_name' => 'plugin_text_one_real', 'option_value' => '1.0', 'autoload' => 'yes'],
    ['option_id' => 11, 'option_name' => 'plugin_null', 'option_value' => null, 'autoload' => 'no'],
];

$column = static fn (string $name): array => ['column' => $name];
$like = static fn (mixed $left, mixed $right, bool $caseSensitive = true): array => [
    'operator' => 'LIKE',
    'left' => $left,
    'right' => $right,
    'caseSensitive' => $caseSensitive,
];
$glob = static fn (mixed $left, mixed $right): array => [
    'operator' => 'GLOB',
    'left' => $left,
    'right' => $right,
];
$ids = static fn (array $predicate): array => array_column(SQLiteSelectPredicate::filter($rows, $predicate), 'option_id');

$predicateCases = [
    'real one LIKE keeps decimal point' => [$like($column('option_value'), '1.0'), [1, 10]],
    'real one no longer matches integer text pattern' => [$like($column('option_value'), '1'), [9]],
    'real four LIKE keeps decimal point' => [$like($column('option_value'), '4.0'), [3]],
    'real four does not match integer four pattern' => [$like($column('option_value'), '4'), []],
    'real one half LIKE keeps compact decimal' => [$like($column('option_value'), '1.5'), [2]],
    'real four half LIKE keeps compact decimal' => [$like($column('option_value'), '4.5'), [4]],
    'micro real LIKE uses lowercase two digit negative exponent' => [$like($column('option_value'), '1.0e-06'), [5]],
    'tiny real LIKE uses lowercase two digit negative exponent' => [$like($column('option_value'), '1.0e-07'), [6]],
    'large real LIKE uses lowercase positive exponent' => [$like($column('option_value'), '1.0e+15'), [7]],
    'huge real LIKE uses lowercase positive exponent' => [$like($column('option_value'), '1.0e+20'), [8]],
    'uppercase exponent pattern does not match case sensitive LIKE' => [$like($column('option_value'), '1.0E+20'), []],
    'uppercase exponent pattern can match default case folded LIKE' => [$like($column('option_value'), '1.0E+20', false), [8]],
    'real one GLOB keeps decimal point' => [$glob($column('option_value'), '1.0'), [1, 10]],
    'real one GLOB does not match integer pattern' => [$glob($column('option_value'), '1'), [9]],
    'real four GLOB keeps decimal point' => [$glob($column('option_value'), '4.0'), [3]],
    'micro real GLOB uses lowercase two digit negative exponent' => [$glob($column('option_value'), '1.0e-0[6]'), [5]],
    'tiny real GLOB uses lowercase two digit negative exponent' => [$glob($column('option_value'), '1.0e-0[7]'), [6]],
    'large real GLOB uses lowercase positive exponent' => [$glob($column('option_value'), '1.0e+[1][5]'), [7]],
    'huge real GLOB uses lowercase positive exponent' => [$glob($column('option_value'), '1.0e+[2][0]'), [8]],
    'uppercase exponent GLOB remains case sensitive' => [$glob($column('option_value'), '1.0E+20'), []],
    'NOT LIKE decimal one excludes real and text one point zero' => [['operator' => 'NOT LIKE', 'left' => $column('option_value'), 'right' => '1.0', 'caseSensitive' => true], [2, 3, 4, 5, 6, 7, 8, 9]],
    'NOT GLOB exponent excludes huge real only' => [['operator' => 'NOT GLOB', 'left' => $column('option_value'), 'right' => '1.0e+20'], [1, 2, 3, 4, 5, 6, 7, 9, 10]],
];

foreach ($predicateCases as $name => [$predicate, $expected]) {
    $tests['select predicate real affinity like glob current source next120 ' . $name] = static function (TestRunner $t) use ($ids, $predicate, $expected): void {
        $t->same($expected, $ids($predicate));
    };
}

$scalarCases = [
    'direct LIKE real one keeps decimal point' => [$like(1.0, '1.0'), true],
    'direct LIKE real one rejects integer pattern' => [$like(1.0, '1'), false],
    'direct LIKE real four keeps decimal point' => [$like(4.0, '4.0'), true],
    'direct LIKE real four rejects integer pattern' => [$like(4.0, '4'), false],
    'direct LIKE micro uses sqlite exponent spelling' => [$like(0.000001, '1.0e-06'), true],
    'direct LIKE tiny uses sqlite exponent spelling' => [$like(0.0000001, '1.0e-07'), true],
    'direct LIKE large uses sqlite positive exponent spelling' => [$like(1000000000000000.0, '1.0e+15'), true],
    'direct LIKE huge uses sqlite positive exponent spelling' => [$like(1.0e20, '1.0e+20'), true],
    'direct GLOB real one keeps decimal point' => [$glob(1.0, '1.0'), true],
    'direct GLOB real one rejects integer pattern' => [$glob(1.0, '1'), false],
    'direct GLOB micro uses sqlite exponent spelling' => [$glob(0.000001, '1.0e-0?'), true],
    'direct GLOB huge rejects uppercase exponent' => [$glob(1.0e20, '1.0E+20'), false],
];

foreach ($scalarCases as $name => [$predicate, $expected]) {
    $tests['select predicate real affinity like glob current source next120 ' . $name] = static function (TestRunner $t) use ($predicate, $expected): void {
        $t->same($expected, SQLiteSelectPredicate::evaluate([], $predicate));
    };
}

$sqlCases = [
    'SQL LIKE real one keeps decimal point' => ["SELECT option_id FROM wp_options WHERE option_value LIKE '1.0' ORDER BY option_id", [1, 10]],
    'SQL LIKE real one rejects integer pattern' => ["SELECT option_id FROM wp_options WHERE option_value LIKE '1' ORDER BY option_id", [9]],
    'SQL LIKE real four keeps decimal point' => ["SELECT option_id FROM wp_options WHERE option_value LIKE '4.0' ORDER BY option_id", [3]],
    'SQL LIKE micro uses sqlite exponent spelling' => ["SELECT option_id FROM wp_options WHERE option_value LIKE '1.0e-06' ORDER BY option_id", [5]],
    'SQL LIKE tiny uses sqlite exponent spelling' => ["SELECT option_id FROM wp_options WHERE option_value LIKE '1.0e-07' ORDER BY option_id", [6]],
    'SQL LIKE large uses sqlite exponent spelling' => ["SELECT option_id FROM wp_options WHERE option_value LIKE '1.0e+15' ORDER BY option_id", [7]],
    'SQL LIKE huge uses sqlite exponent spelling' => ["SELECT option_id FROM wp_options WHERE option_value LIKE '1.0e+20' ORDER BY option_id", [8]],
    'SQL GLOB real one keeps decimal point' => ["SELECT option_id FROM wp_options WHERE option_value GLOB '1.0' ORDER BY option_id", [1, 10]],
    'SQL GLOB real one rejects integer pattern' => ["SELECT option_id FROM wp_options WHERE option_value GLOB '1' ORDER BY option_id", [9]],
    'SQL GLOB micro uses sqlite exponent spelling' => ["SELECT option_id FROM wp_options WHERE option_value GLOB '1.0e-0[6]' ORDER BY option_id", [5]],
    'SQL GLOB huge uses sqlite exponent spelling' => ["SELECT option_id FROM wp_options WHERE option_value GLOB '1.0e+[2][0]' ORDER BY option_id", [8]],
    'SQL NOT LIKE decimal one excludes real and text one point zero' => ["SELECT option_id FROM wp_options WHERE option_value NOT LIKE '1.0' ORDER BY option_id", [2, 3, 4, 5, 6, 7, 8, 9]],
    'SQL NOT GLOB exponent excludes huge real only' => ["SELECT option_id FROM wp_options WHERE option_value NOT GLOB '1.0e+20' ORDER BY option_id", [1, 2, 3, 4, 5, 6, 7, 9, 10]],
];

foreach ($sqlCases as $name => [$sql, $expected]) {
    $tests['select predicate real affinity like glob current source next120 ' . $name] = static function (TestRunner $t) use ($rows, $sql, $expected): void {
        $result = SQLiteSelectSql::execute($sql, ['wp_options' => $rows]);
        $t->same($expected, array_column($result, 'option_id'));
    };
}

$tests['select predicate real affinity like glob current source next120 null real predicate remains unknown'] = static function (TestRunner $t) use ($like): void {
    $t->same(null, SQLiteSelectPredicate::evaluate(['option_value' => null], $like(['column' => 'option_value'], '1.0')));
};

$tests['select predicate real affinity like glob current source next120 oracle spelling evidence'] = static function (TestRunner $t): void {
    $t->same(true, SQLiteSelectPredicate::evaluate([], ['operator' => 'LIKE', 'left' => 1.0e20, 'right' => '1.0e+20', 'caseSensitive' => true]));
    $t->same(false, SQLiteSelectPredicate::evaluate([], ['operator' => 'LIKE', 'left' => 1.0e20, 'right' => '1.0E+20', 'caseSensitive' => true]));
};

return $tests;
