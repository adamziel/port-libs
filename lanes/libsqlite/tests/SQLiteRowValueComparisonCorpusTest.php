<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectPredicate;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$column = static fn (string $name): array => ['type' => 'column', 'name' => $name];
$rowValue = static fn (array ...$values): array => ['type' => 'row', 'values' => $values];
$predicate = static fn (string $operator, array $left, array $right): array => [
    'operator' => $operator,
    'left' => $left,
    'right' => $right,
];

$row = [
    'a' => 1,
    'b' => 2,
    'c' => 3,
    'name' => 'home',
    'autoload' => 'yes',
    'missing' => null,
];

$predicateCases = [
    'equal two literal columns' => [$predicate('=', $rowValue($literal(1), $literal(2)), $rowValue($literal(1), $literal(2))), true],
    'equal false on second column mismatch' => [$predicate('=', $rowValue($literal(1), $literal(2)), $rowValue($literal(1), $literal(3))), false],
    'not equal false for equal pair' => [$predicate('<>', $rowValue($literal(1), $literal(2)), $rowValue($literal(1), $literal(2))), false],
    'not equal true on first mismatch' => [$predicate('!=', $rowValue($literal(2), $literal(2)), $rowValue($literal(1), $literal(9))), true],
    'less than true on second column' => [$predicate('<', $rowValue($literal(1), $literal(2)), $rowValue($literal(1), $literal(3))), true],
    'less than false on second column' => [$predicate('<', $rowValue($literal(1), $literal(4)), $rowValue($literal(1), $literal(3))), false],
    'less than true short circuits before null' => [$predicate('<', $rowValue($literal(0), $literal(null)), $rowValue($literal(1), $literal(0))), true],
    'less than null when decisive column is null' => [$predicate('<', $rowValue($literal(1), $literal(null)), $rowValue($literal(1), $literal(3))), null],
    'less equal true for equal triple' => [$predicate('<=', $rowValue($literal(1), $literal(2), $literal(3)), $rowValue($literal(1), $literal(2), $literal(3))), true],
    'greater than true on first column' => [$predicate('>', $rowValue($literal(2), $literal(null)), $rowValue($literal(1), $literal(9))), true],
    'greater than null when equal prefix then null' => [$predicate('>', $rowValue($literal(1), $literal(null)), $rowValue($literal(1), $literal(0))), null],
    'greater equal false on first column' => [$predicate('>=', $rowValue($literal(0), $literal(9)), $rowValue($literal(1), $literal(0))), false],
    'is true with matching null slots' => [$predicate('IS', $rowValue($literal(1), $literal(null)), $rowValue($literal(1), $literal(null))), true],
    'is false with mismatched null slots' => [$predicate('IS', $rowValue($literal(1), $literal(null)), $rowValue($literal(1), $literal(2))), false],
    'is not false with matching null slots' => [$predicate('IS NOT', $rowValue($literal(1), $literal(null)), $rowValue($literal(1), $literal(null))), false],
    'is not true with mismatched null slots' => [$predicate('IS NOT', $rowValue($literal(1), $literal(null)), $rowValue($literal(1), $literal(2))), true],
    'column row equal true' => [$predicate('=', $rowValue($column('a'), $column('b')), $rowValue($literal(1), $literal(2))), true],
    'column row ordered true' => [$predicate('<', $rowValue($column('a'), $column('c')), $rowValue($literal(1), $literal(4))), true],
    'text row equal true' => [$predicate('=', $rowValue($column('name'), $column('autoload')), $rowValue($literal('home'), $literal('yes'))), true],
    'text row ordered false' => [$predicate('>', $rowValue($column('name'), $column('autoload')), $rowValue($literal('siteurl'), $literal('no'))), false],
    'column null equality is null' => [$predicate('=', $rowValue($column('a'), $column('missing')), $rowValue($literal(1), $literal(null))), null],
    'column null is true' => [$predicate('IS', $rowValue($column('a'), $column('missing')), $rowValue($literal(1), $literal(null))), true],
];

foreach ($predicateCases as $name => [$casePredicate, $expected]) {
    $tests['upstream row-value comparison corpus predicate ' . $name] = static function (TestRunner $t) use ($row, $casePredicate, $expected): void {
        $t->same($expected, SQLiteSelectPredicate::evaluate($row, $casePredicate));
    };
}

$options = [
    ['option_id' => 1, 'option_name' => 'alpha', 'autoload' => 'yes', 'priority' => 10],
    ['option_id' => 2, 'option_name' => 'beta', 'autoload' => 'yes', 'priority' => 20],
    ['option_id' => 3, 'option_name' => 'beta', 'autoload' => 'no', 'priority' => 30],
    ['option_id' => 4, 'option_name' => 'delta', 'autoload' => null, 'priority' => 40],
    ['option_id' => 5, 'option_name' => 'omega', 'autoload' => 'no', 'priority' => null],
];

$sqlCases = [
    'sql row equality selects composite key' => ["SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_name, autoload) = ('beta', 'yes') ORDER BY id", ['beta']],
    'sql row inequality skips matching pair' => ["SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_name, autoload) <> ('beta', 'yes') ORDER BY id", ['alpha', 'beta', 'delta', 'omega']],
    'sql row less than uses lexicographic second column' => ["SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_name, priority) < ('beta', 25) ORDER BY id", ['alpha', 'beta']],
    'sql row less than short circuits before null tail' => ["SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_name, priority) < ('zeta', 1) ORDER BY id", ['alpha', 'beta', 'beta', 'delta', 'omega']],
    'sql row greater than uses first column' => ["SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_name, priority) > ('beta', 99) ORDER BY id", ['delta', 'omega']],
    'sql row greater equal includes equal pair' => ["SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_name, priority) >= ('beta', 30) ORDER BY id", ['beta', 'delta', 'omega']],
    'sql row less equal includes equal pair' => ["SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_name, priority) <= ('beta', 20) ORDER BY id", ['alpha', 'beta']],
    'sql row null equality filters unknown' => ["SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_name, autoload) = ('delta', NULL) ORDER BY id", []],
    'sql row is matches null slot' => ["SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_name, autoload) IS ('delta', NULL) ORDER BY id", ['delta']],
    'sql row is not excludes matching null slot' => ["SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_name, autoload) IS NOT ('delta', NULL) ORDER BY id", ['alpha', 'beta', 'beta', 'omega']],
    'sql row expression operands compare' => ["SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id + 1, priority / 10) = (3, 2) ORDER BY id", ['beta']],
    'sql row comparison composes with and' => ["SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_name, autoload) >= ('beta', 'no') AND (option_name, priority) < ('omega', 99) ORDER BY id", ['beta', 'beta', 'delta']],
    'sql row comparison composes with or' => ["SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_name, autoload) = ('alpha', 'yes') OR (option_name, autoload) IS ('delta', NULL) ORDER BY id", ['alpha', 'delta']],
    'sql row comparison works after cte materialization' => ["WITH pairs(name, load_state, weight) AS (SELECT option_name, autoload, priority FROM wp_options) SELECT name, weight FROM pairs WHERE (name, load_state) >= ('beta', 'no') ORDER BY weight", ['omega', 'beta', 'beta', 'delta']],
    'sql row comparison supports limit offset' => ["SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_name, priority) >= ('beta', 20) ORDER BY id LIMIT 2 OFFSET 1", ['beta', 'delta']],
];

foreach ($sqlCases as $name => [$sql, $expected]) {
    $tests['upstream row-value comparison corpus ' . $name] = static function (TestRunner $t) use ($options, $sql, $expected): void {
        $actualRows = SQLiteSelectSql::execute($sql, ['wp_options' => $options]);
        $t->same($expected, array_column($actualRows, 'name'));
    };
}

return $tests;
