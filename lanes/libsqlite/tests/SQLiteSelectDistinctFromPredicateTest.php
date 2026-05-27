<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectPredicate;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$column = static fn (string $name): array => ['type' => 'column', 'name' => $name];
$rowValue = static fn (array ...$values): array => ['type' => 'row', 'values' => $values];

$predicateRows = [
    ['id' => 1, 'name' => 'siteurl', 'autoload' => 'yes', 'expected' => 'yes', 'bytes' => 24, 'other_bytes' => 24, 'payload' => new SQLiteBlobValue('A')],
    ['id' => 2, 'name' => 'home', 'autoload' => 'yes', 'expected' => 'no', 'bytes' => 24, 'other_bytes' => 12, 'payload' => new SQLiteBlobValue('A')],
    ['id' => 3, 'name' => 'blogname', 'autoload' => null, 'expected' => null, 'bytes' => null, 'other_bytes' => null, 'payload' => new SQLiteBlobValue('B')],
    ['id' => 4, 'name' => 'cache_plugin', 'autoload' => 'no', 'expected' => null, 'bytes' => 0, 'other_bytes' => null, 'payload' => null],
];

$predicateCases = [
    'null is not distinct from null' => [['operator' => 'IS NOT DISTINCT FROM', 'left' => $literal(null), 'right' => $literal(null)], true],
    'null is distinct from integer' => [['operator' => 'IS DISTINCT FROM', 'left' => $literal(null), 'right' => $literal(1)], true],
    'integer is not distinct from same integer' => [['operator' => 'IS NOT DISTINCT FROM', 'left' => $literal(7), 'right' => $literal(7)], true],
    'integer is distinct from different integer' => [['operator' => 'IS DISTINCT FROM', 'left' => $literal(7), 'right' => $literal(8)], true],
    'integer is not distinct from equal real' => [['operator' => 'IS NOT DISTINCT FROM', 'left' => $literal(7), 'right' => $literal(7.0)], true],
    'integer is distinct from text storage class' => [['operator' => 'IS DISTINCT FROM', 'left' => $literal(7), 'right' => $literal('7')], true],
    'text is not distinct from same text' => [['operator' => 'IS NOT DISTINCT FROM', 'left' => $literal('yes'), 'right' => $literal('yes')], true],
    'text is distinct from case difference' => [['operator' => 'IS DISTINCT FROM', 'left' => $literal('yes'), 'right' => $literal('YES')], true],
    'blob is not distinct from same bytes' => [['operator' => 'IS NOT DISTINCT FROM', 'left' => $literal(new SQLiteBlobValue('AB')), 'right' => $literal(new SQLiteBlobValue('AB'))], true],
    'blob is distinct from different bytes' => [['operator' => 'IS DISTINCT FROM', 'left' => $literal(new SQLiteBlobValue('AB')), 'right' => $literal(new SQLiteBlobValue('AC'))], true],
    'blob is distinct from text storage class' => [['operator' => 'IS DISTINCT FROM', 'left' => $literal(new SQLiteBlobValue('AB')), 'right' => $literal('AB')], true],
    'row value is not distinct from same values' => [['operator' => 'IS NOT DISTINCT FROM', 'left' => $rowValue($literal('yes'), $literal(24)), 'right' => $rowValue($literal('yes'), $literal(24))], true],
    'row value is distinct from later value' => [['operator' => 'IS DISTINCT FROM', 'left' => $rowValue($literal('yes'), $literal(24)), 'right' => $rowValue($literal('yes'), $literal(25))], true],
    'row value nulls are not distinct when aligned' => [['operator' => 'IS NOT DISTINCT FROM', 'left' => $rowValue($literal(null), $literal(24)), 'right' => $rowValue($literal(null), $literal(24))], true],
    'row value is distinct when one null differs' => [['operator' => 'IS DISTINCT FROM', 'left' => $rowValue($literal(null), $literal(24)), 'right' => $rowValue($literal('yes'), $literal(24))], true],
    'column values not distinct' => [['operator' => 'IS NOT DISTINCT FROM', 'left' => $column('autoload'), 'right' => $column('expected')], [1, 3]],
    'column values distinct' => [['operator' => 'IS DISTINCT FROM', 'left' => $column('autoload'), 'right' => $column('expected')], [2, 4]],
    'column null distinct from non null' => [['operator' => 'IS DISTINCT FROM', 'left' => $column('autoload'), 'right' => $literal('yes')], [3, 4]],
    'column null not distinct from null' => [['operator' => 'IS NOT DISTINCT FROM', 'left' => $column('autoload'), 'right' => $literal(null)], [3]],
    'blob column distinct from different blob' => [['operator' => 'IS DISTINCT FROM', 'left' => $column('payload'), 'right' => $literal(new SQLiteBlobValue('A'))], [3, 4]],
];

foreach ($predicateCases as $name => [$predicate, $expected]) {
    $tests['select predicate distinct-from ' . $name] = static function (TestRunner $t) use ($predicateRows, $predicate, $expected): void {
        if (is_bool($expected)) {
            $t->same($expected, SQLiteSelectPredicate::evaluate($predicateRows[0], $predicate));
            return;
        }

        $t->same($expected, array_column(SQLiteSelectPredicate::filter($predicateRows, $predicate), 'id'));
    };
}

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'expected_autoload' => 'yes', 'bytes' => 24, 'expected_bytes' => 24, 'option_value' => 'https://example.test'],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'expected_autoload' => 'no', 'bytes' => 24, 'expected_bytes' => 24, 'option_value' => 'https://example.test'],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'expected_autoload' => 'yes', 'bytes' => 9, 'expected_bytes' => 12, 'option_value' => 'Example Site'],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'expected_autoload' => 'no', 'bytes' => 12, 'expected_bytes' => null, 'option_value' => 'cached'],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'expected_autoload' => null, 'bytes' => 110, 'expected_bytes' => 110, 'option_value' => new SQLiteBlobValue('plugin-cache')],
    ['option_id' => 6, 'option_name' => 'orphaned', 'autoload' => null, 'expected_autoload' => null, 'bytes' => null, 'expected_bytes' => null, 'option_value' => null],
    ['option_id' => 7, 'option_name' => 'blob_copy', 'autoload' => 'no', 'expected_autoload' => 'no', 'bytes' => 12, 'expected_bytes' => 12, 'option_value' => new SQLiteBlobValue('plugin-cache')],
];

$meta = [
    ['option_id' => 1, 'meta_option_id' => 1, 'meta_key' => 'autoload', 'meta_value' => 'yes'],
    ['option_id' => 2, 'meta_option_id' => 2, 'meta_key' => 'autoload', 'meta_value' => 'no'],
    ['option_id' => 3, 'meta_option_id' => 3, 'meta_key' => 'autoload', 'meta_value' => 'yes'],
    ['option_id' => 4, 'meta_option_id' => 4, 'meta_key' => 'autoload', 'meta_value' => 'no'],
    ['option_id' => 5, 'meta_option_id' => 5, 'meta_key' => 'autoload', 'meta_value' => null],
    ['option_id' => 6, 'meta_option_id' => 6, 'meta_key' => 'autoload', 'meta_value' => null],
];

$sqlCases = [
    'filters matching autoload with is not distinct' => ["SELECT option_id AS id, option_name FROM wp_options WHERE autoload IS NOT DISTINCT FROM expected_autoload ORDER BY id", ['siteurl', 'blogname', '_transient_feed', 'orphaned', 'blob_copy']],
    'filters drifted autoload with is distinct' => ["SELECT option_id AS id, option_name FROM wp_options WHERE autoload IS DISTINCT FROM expected_autoload ORDER BY id", ['home', '_site_transient_update_plugins']],
    'keeps null equality with is not distinct' => ["SELECT option_id AS id, option_name FROM wp_options WHERE autoload IS NOT DISTINCT FROM NULL ORDER BY id", ['orphaned']],
    'keeps null differences with is distinct' => ["SELECT option_id AS id, option_name FROM wp_options WHERE expected_autoload IS DISTINCT FROM NULL ORDER BY id", ['siteurl', 'home', 'blogname', '_transient_feed', 'blob_copy']],
    'matches numeric equality across integer and real' => ["SELECT option_id AS id, option_name FROM wp_options WHERE bytes IS NOT DISTINCT FROM expected_bytes ORDER BY id", ['siteurl', 'home', '_site_transient_update_plugins', 'orphaned', 'blob_copy']],
    'finds numeric and null byte drift' => ["SELECT option_id AS id, option_name FROM wp_options WHERE bytes IS DISTINCT FROM expected_bytes ORDER BY id", ['blogname', '_transient_feed']],
    'composes distinct predicate with and' => ["SELECT option_id AS id, option_name FROM wp_options WHERE autoload IS DISTINCT FROM expected_autoload AND bytes IS NOT DISTINCT FROM expected_bytes ORDER BY id", ['home', '_site_transient_update_plugins']],
    'composes not distinct predicate with or' => ["SELECT option_id AS id, option_name FROM wp_options WHERE autoload IS NOT DISTINCT FROM NULL OR expected_bytes IS DISTINCT FROM bytes ORDER BY id", ['blogname', '_transient_feed', 'orphaned']],
    'uses distinct predicate after join' => ["SELECT wp_options.option_id AS id, wp_options.option_name AS name FROM wp_options JOIN option_meta AS m ON wp_options.option_id = m.option_id WHERE wp_options.autoload IS DISTINCT FROM m.meta_value ORDER BY id", ['home', '_site_transient_update_plugins']],
    'uses not distinct predicate after join' => ["SELECT wp_options.option_id AS id, wp_options.option_name AS name FROM wp_options JOIN option_meta AS m ON wp_options.option_id = m.option_id WHERE wp_options.autoload IS NOT DISTINCT FROM m.meta_value ORDER BY id", ['siteurl', 'blogname', '_transient_feed', 'orphaned']],
    'orders rows filtered by distinct predicate' => ["SELECT option_name, bytes FROM wp_options WHERE expected_bytes IS DISTINCT FROM bytes ORDER BY bytes DESC, option_name", ['_transient_feed:12', 'blogname:9']],
    'limits rows filtered by not distinct predicate' => ["SELECT option_name FROM wp_options WHERE autoload IS NOT DISTINCT FROM expected_autoload ORDER BY option_name LIMIT 3", ['_transient_feed', 'blob_copy', 'blogname']],
    'groups rows after distinct filtering' => ["SELECT autoload, count(*) AS rows, sum(bytes) AS byte_sum FROM wp_options WHERE expected_autoload IS DISTINCT FROM NULL GROUP BY autoload ORDER BY autoload", ['no:2:24', 'yes:3:57']],
    'applies having with distinct predicate input' => ["SELECT autoload, count(*) AS rows, sum(bytes) AS byte_sum FROM wp_options WHERE autoload IS NOT DISTINCT FROM expected_autoload GROUP BY autoload HAVING count(*) IS DISTINCT FROM 2 ORDER BY autoload", [':1:']],
    'supports parameter RHS for not distinct' => ["SELECT option_id AS id, option_name FROM wp_options WHERE autoload IS NOT DISTINCT FROM :autoload ORDER BY id", ['siteurl', 'home', 'blogname'], [':autoload' => 'yes']],
    'supports parameter RHS for distinct null' => ["SELECT option_id AS id, option_name FROM wp_options WHERE expected_autoload IS DISTINCT FROM ? ORDER BY id", ['siteurl', 'home', 'blogname', '_transient_feed', 'blob_copy'], [0 => null]],
    'supports literal blob not distinct comparison' => ["SELECT option_id AS id, option_name FROM wp_options WHERE option_value IS NOT DISTINCT FROM X'706c7567696e2d6361636865' ORDER BY id", ['_site_transient_update_plugins', 'blob_copy']],
    'supports literal blob distinct comparison' => ["SELECT option_id AS id, option_name FROM wp_options WHERE option_value IS DISTINCT FROM X'706c7567696e2d6361636865' ORDER BY id LIMIT 3", ['siteurl', 'home', 'blogname']],
    'keeps storage-class distinction for text and integer' => ["SELECT option_id AS id, option_name FROM wp_options WHERE option_id IS DISTINCT FROM '1' ORDER BY id LIMIT 2", ['siteurl', 'home']],
    'keeps numeric not distinct for integer and real' => ["SELECT option_name FROM wp_options WHERE option_id IS NOT DISTINCT FROM 1.0", ['siteurl']],
    'combines distinct predicate with subquery result' => ["SELECT option_id AS id, option_name FROM wp_options WHERE autoload IS DISTINCT FROM (SELECT meta_value FROM option_meta WHERE meta_option_id = option_id AND meta_key = 'autoload') ORDER BY id", ['_transient_feed', '_site_transient_update_plugins', 'orphaned', 'blob_copy']],
    'combines not distinct predicate with subquery result' => ["SELECT option_id AS id, option_name FROM wp_options WHERE autoload IS NOT DISTINCT FROM (SELECT meta_value FROM option_meta WHERE meta_option_id = option_id AND meta_key = 'autoload') ORDER BY id", ['siteurl', 'home', 'blogname']],
    'uses distinct predicate in joined grouped filter' => ["SELECT m.meta_value AS state, count(*) AS rows, sum(wp_options.bytes) AS byte_sum FROM wp_options JOIN option_meta AS m ON wp_options.option_id = m.option_id WHERE wp_options.autoload IS NOT DISTINCT FROM m.meta_value GROUP BY m.meta_value ORDER BY rows DESC, state", ['yes:2:33', ':1:', 'no:1:12']],
    'plans distinct-from predicate operator' => ["PLAN:SELECT option_name FROM wp_options WHERE autoload IS DISTINCT FROM expected_autoload", 'IS DISTINCT FROM'],
    'plans not-distinct predicate operator' => ["PLAN:SELECT option_name FROM wp_options WHERE autoload IS NOT DISTINCT FROM expected_autoload", 'IS NOT DISTINCT FROM'],
];

foreach ($sqlCases as $name => $case) {
    $tests['select sql distinct-from predicate ' . $name] = static function (TestRunner $t) use ($case, $options, $meta): void {
        [$sql, $expected, $parameters] = $case + [2 => []];
        if (str_starts_with($sql, 'PLAN:')) {
            $plan = SQLiteSelectSql::plan(substr($sql, 5), ['wp_options' => $options, 'option_meta' => $meta], $parameters);
            $t->same($expected, $plan['where']['operator'] ?? null);
            return;
        }

        $rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options, 'option_meta' => $meta], $parameters);
        $actual = array_map(static function (array $row): string {
            unset($row['id']);
            $values = array_values($row);

            return implode(':', array_map(static fn (mixed $value): string => $value === null ? '' : (string) $value, $values));
        }, $rows);
        $t->same($expected, $actual);
    };
}

$tests['select sql distinct-from predicate rejects missing RHS'] = static function (TestRunner $t) use ($options): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT option_name FROM wp_options WHERE autoload IS DISTINCT FROM', ['wp_options' => $options]));
};

$tests['select sql distinct-from predicate rejects missing LHS'] = static function (TestRunner $t) use ($options): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT option_name FROM wp_options WHERE IS NOT DISTINCT FROM autoload', ['wp_options' => $options]));
};

$tests['select predicate distinct-from rejects mismatched row width'] = static function (TestRunner $t) use ($rowValue, $literal): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectPredicate::evaluate([], [
        'operator' => 'IS DISTINCT FROM',
        'left' => $rowValue($literal(1), $literal(2)),
        'right' => $rowValue($literal(1), $literal(2), $literal(3)),
    ]));
};

return $tests;
