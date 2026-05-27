<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectPredicate;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'expected_autoload' => 'yes', 'bytes' => 24, 'expected_bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'expected_autoload' => 'no', 'bytes' => 24, 'expected_bytes' => 24],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'expected_autoload' => 'yes', 'bytes' => 9, 'expected_bytes' => 12],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'expected_autoload' => 'no', 'bytes' => 12, 'expected_bytes' => null],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'expected_autoload' => null, 'bytes' => 110, 'expected_bytes' => 110],
    ['option_id' => 6, 'option_name' => 'orphaned', 'autoload' => null, 'expected_autoload' => null, 'bytes' => null, 'expected_bytes' => null],
    ['option_id' => 7, 'option_name' => 'template', 'autoload' => 'auto', 'expected_autoload' => 'auto', 'bytes' => 0, 'expected_bytes' => 0],
];

$meta = [
    ['meta_id' => 11, 'option_id' => 1, 'meta_key' => 'autoload', 'meta_value' => 'yes', 'expected_meta' => 'yes'],
    ['meta_id' => 12, 'option_id' => 2, 'meta_key' => 'autoload', 'meta_value' => 'no', 'expected_meta' => 'yes'],
    ['meta_id' => 13, 'option_id' => 3, 'meta_key' => 'autoload', 'meta_value' => 'yes', 'expected_meta' => 'yes'],
    ['meta_id' => 14, 'option_id' => 4, 'meta_key' => 'autoload', 'meta_value' => 'no', 'expected_meta' => 'no'],
    ['meta_id' => 15, 'option_id' => 5, 'meta_key' => 'autoload', 'meta_value' => null, 'expected_meta' => 'no'],
    ['meta_id' => 16, 'option_id' => 6, 'meta_key' => 'autoload', 'meta_value' => null, 'expected_meta' => null],
    ['meta_id' => 17, 'option_id' => 7, 'meta_key' => 'autoload', 'meta_value' => 'auto', 'expected_meta' => 'auto'],
];

$sqlCases = [
    'unqualified unique lhs not distinct after join' => ["SELECT w.option_name AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE autoload IS NOT DISTINCT FROM m.meta_value ORDER BY w.option_id", ['siteurl', 'blogname', '_transient_feed', 'orphaned', 'template']],
    'unqualified unique lhs distinct after join' => ["SELECT w.option_name AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE autoload IS DISTINCT FROM m.meta_value ORDER BY w.option_id", ['home', '_site_transient_update_plugins']],
    'unqualified unique rhs not distinct after join' => ["SELECT w.option_name AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE w.autoload IS NOT DISTINCT FROM meta_value ORDER BY w.option_id", ['siteurl', 'blogname', '_transient_feed', 'orphaned', 'template']],
    'unqualified unique rhs distinct after join' => ["SELECT w.option_name AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE w.autoload IS DISTINCT FROM meta_value ORDER BY w.option_id", ['home', '_site_transient_update_plugins']],
    'unqualified expected column not distinct' => ["SELECT w.option_name AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE meta_value IS NOT DISTINCT FROM expected_meta ORDER BY w.option_id", ['siteurl', 'blogname', '_transient_feed', 'orphaned', 'template']],
    'unqualified expected column distinct' => ["SELECT w.option_name AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE meta_value IS DISTINCT FROM expected_meta ORDER BY w.option_id", ['home', '_site_transient_update_plugins']],
    'unqualified null literal is not' => ["SELECT w.option_name AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE meta_value IS NOT NULL ORDER BY w.option_id", ['siteurl', 'home', 'blogname', '_transient_feed', 'template']],
    'unqualified null literal is' => ["SELECT w.option_name AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE meta_value IS NULL ORDER BY w.option_id", ['_site_transient_update_plugins', 'orphaned']],
    'unqualified is not literal' => ["SELECT w.option_name AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE meta_value IS NOT 'yes' ORDER BY w.option_id", ['home', '_transient_feed', '_site_transient_update_plugins', 'orphaned', 'template']],
    'unqualified is literal' => ["SELECT w.option_name AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE meta_value IS 'yes' ORDER BY w.option_id", ['siteurl', 'blogname']],
    'unqualified numeric lhs not distinct' => ["SELECT w.option_name AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE bytes IS NOT DISTINCT FROM expected_bytes ORDER BY w.option_id", ['siteurl', 'home', '_site_transient_update_plugins', 'orphaned', 'template']],
    'unqualified numeric lhs distinct' => ["SELECT w.option_name AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE bytes IS DISTINCT FROM expected_bytes ORDER BY w.option_id", ['blogname', '_transient_feed']],
    'unqualified projection from current source' => ["SELECT option_name AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE meta_value IS NOT DISTINCT FROM expected_meta ORDER BY w.option_id LIMIT 3", ['siteurl', 'blogname', '_transient_feed']],
    'unqualified order expression from current source' => ["SELECT w.option_name AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE meta_value IS NOT NULL ORDER BY bytes + 1 DESC, name LIMIT 3", ['home', 'siteurl', '_transient_feed']],
    'unqualified scalar function argument' => ["SELECT upper(option_name) AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE meta_value IS NOT DISTINCT FROM expected_meta ORDER BY w.option_id LIMIT 2", ['SITEURL', 'BLOGNAME']],
    'unqualified cast operand' => ["SELECT w.option_name AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE cast(bytes AS integer) IS NOT DISTINCT FROM expected_bytes ORDER BY w.option_id", ['siteurl', 'home', '_site_transient_update_plugins', 'orphaned', 'template']],
    'unqualified binary operand' => ["SELECT w.option_name AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE bytes + 0 IS DISTINCT FROM expected_bytes ORDER BY w.option_id", ['blogname', '_transient_feed']],
    'unqualified row value not distinct' => ["SELECT w.option_name AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE (autoload, bytes) IS NOT DISTINCT FROM (expected_autoload, expected_bytes) ORDER BY w.option_id", ['siteurl', 'orphaned', 'template']],
    'unqualified row value distinct' => ["SELECT w.option_name AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE (autoload, bytes) IS DISTINCT FROM (expected_autoload, expected_bytes) ORDER BY w.option_id", ['home', 'blogname', '_transient_feed', '_site_transient_update_plugins']],
    'unqualified current source after left join null extended' => ["SELECT w.option_name AS name FROM wp_options AS w LEFT JOIN option_meta AS m ON w.option_id = m.option_id WHERE meta_value IS NULL ORDER BY w.option_id", ['_site_transient_update_plugins', 'orphaned']],
    'unqualified left source after left join' => ["SELECT w.option_name AS name FROM wp_options AS w LEFT JOIN option_meta AS m ON w.option_id = m.option_id WHERE expected_autoload IS NOT DISTINCT FROM autoload ORDER BY w.option_id", ['siteurl', 'blogname', '_transient_feed', 'orphaned', 'template']],
    'unqualified join predicate rhs unique column' => ["SELECT w.option_name AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id IS NOT DISTINCT FROM meta_id - 10 WHERE m.meta_key IS 'autoload' ORDER BY w.option_id", ['siteurl', 'home', 'blogname', '_transient_feed', '_site_transient_update_plugins', 'orphaned', 'template']],
    'unqualified join predicate lhs unique column' => ["SELECT w.option_name AS name FROM wp_options AS w JOIN option_meta AS m ON meta_id - 10 IS NOT DISTINCT FROM w.option_id WHERE meta_value IS NOT NULL ORDER BY w.option_id", ['siteurl', 'home', 'blogname', '_transient_feed', 'template']],
    'qualified grouped input distinct filter with unique predicate columns' => ["SELECT w.autoload AS autoload, count(w.bytes) AS rows FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE meta_value IS NOT DISTINCT FROM expected_meta GROUP BY w.autoload ORDER BY autoload", [':0', 'auto:1', 'no:1', 'yes:2']],
    'qualified grouped having is not distinct with unique predicate columns' => ["SELECT w.autoload AS autoload, count(w.bytes) AS rows FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE meta_value IS NOT DISTINCT FROM expected_meta GROUP BY w.autoload HAVING count(w.bytes) IS NOT DISTINCT FROM 2 ORDER BY autoload", ['yes:2']],
    'qualified grouped having is distinct with unique predicate columns' => ["SELECT w.autoload AS autoload, count(w.bytes) AS rows FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE meta_value IS NOT DISTINCT FROM expected_meta GROUP BY w.autoload HAVING count(w.bytes) IS DISTINCT FROM 2 ORDER BY autoload", [':0', 'auto:1', 'no:1']],
    'unqualified not distinct with and' => ["SELECT w.option_name AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE autoload IS NOT DISTINCT FROM meta_value AND bytes IS NOT NULL ORDER BY w.option_id", ['siteurl', 'blogname', '_transient_feed', 'template']],
    'unqualified distinct with or null arm' => ["SELECT w.option_name AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE autoload IS DISTINCT FROM meta_value OR meta_value IS NULL ORDER BY w.option_id", ['home', '_site_transient_update_plugins', 'orphaned']],
    'unqualified scalar function on both sides' => ["SELECT w.option_name AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE upper(meta_value) IS NOT DISTINCT FROM upper(expected_meta) ORDER BY w.option_id", ['siteurl', 'blogname', '_transient_feed', 'orphaned', 'template']],
    'unqualified coalesce expression on both sides' => ["SELECT w.option_name AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE coalesce(meta_value, '') IS DISTINCT FROM coalesce(expected_meta, '') ORDER BY w.option_id", ['home', '_site_transient_update_plugins']],
    'unqualified arithmetic join predicate with null-safe comparison' => ["SELECT w.option_name AS name FROM wp_options AS w JOIN option_meta AS m ON meta_id - 10 IS NOT DISTINCT FROM w.option_id WHERE meta_value IS NOT NULL ORDER BY w.option_id", ['siteurl', 'home', 'blogname', '_transient_feed', 'template']],
    'unqualified scalar expression projection' => ["SELECT option_name || ':' || meta_key AS label FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE meta_value IS NOT DISTINCT FROM expected_meta ORDER BY w.option_id LIMIT 2", ['siteurl:autoload', 'blogname:autoload']],
    'unqualified null-safe filter with limit offset' => ["SELECT option_name AS name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE meta_value IS DISTINCT FROM expected_meta ORDER BY w.option_id LIMIT 1 OFFSET 1", ['_site_transient_update_plugins']],
    'unqualified not distinct plan operator' => ["PLAN:SELECT w.option_name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE autoload IS NOT DISTINCT FROM meta_value", 'IS NOT DISTINCT FROM'],
    'unqualified distinct plan operator' => ["PLAN:SELECT w.option_name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE autoload IS DISTINCT FROM meta_value", 'IS DISTINCT FROM'],
    'unqualified is not plan operator' => ["PLAN:SELECT w.option_name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE meta_value IS NOT NULL", 'IS NOT'],
    'unqualified is plan operator' => ["PLAN:SELECT w.option_name FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id WHERE meta_value IS NULL", 'IS'],
];

foreach ($sqlCases as $name => [$sql, $expected]) {
    $tests['null distinct is-not current source ' . $name] = static function (TestRunner $t) use ($sql, $expected, $options, $meta): void {
        if (str_starts_with($sql, 'PLAN:')) {
            $plan = SQLiteSelectSql::plan(substr($sql, 5), ['wp_options' => $options, 'option_meta' => $meta]);
            $t->same($expected, $plan['where']['operator'] ?? null);
            return;
        }

        $rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options, 'option_meta' => $meta]);
        $actual = array_map(static fn (array $row): string => implode(':', array_map(static fn (mixed $value): string => $value === null ? '' : (string) $value, array_values($row))), $rows);
        $t->same($expected, $actual);
    };
}

$predicateRows = [
    ['w.autoload' => 'yes', 'm.meta_value' => 'yes', 'w.bytes' => 24, 'w.expected_bytes' => 24],
    ['w.autoload' => 'yes', 'm.meta_value' => 'no', 'w.bytes' => 24, 'w.expected_bytes' => 24],
    ['w.autoload' => null, 'm.meta_value' => null, 'w.bytes' => null, 'w.expected_bytes' => null],
];

$predicateCases = [
    'predicate resolves unique lhs suffix' => [['operator' => 'IS NOT DISTINCT FROM', 'left' => ['type' => 'column', 'name' => 'autoload'], 'right' => ['type' => 'column', 'name' => 'm.meta_value']], [['yes', 'yes'], [null, null]]],
    'predicate resolves unique rhs suffix' => [['operator' => 'IS DISTINCT FROM', 'left' => ['type' => 'column', 'name' => 'w.autoload'], 'right' => ['type' => 'column', 'name' => 'meta_value']], [['yes', 'no']]],
    'predicate resolves unique row-value suffixes' => [['operator' => 'IS NOT DISTINCT FROM', 'left' => ['type' => 'row', 'values' => [['type' => 'column', 'name' => 'autoload'], ['type' => 'column', 'name' => 'bytes']]], 'right' => ['type' => 'row', 'values' => [['type' => 'column', 'name' => 'm.meta_value'], ['type' => 'column', 'name' => 'expected_bytes']]]], [['yes', 'yes'], [null, null]]],
    'predicate resolves unique is not null suffix' => [['operator' => 'IS NOT NULL', 'left' => ['type' => 'column', 'name' => 'meta_value']], [['yes', 'yes'], ['yes', 'no']]],
];

foreach ($predicateCases as $name => [$predicate, $expected]) {
    $tests['null distinct is-not current source ' . $name] = static function (TestRunner $t) use ($predicateRows, $predicate, $expected): void {
        $matched = SQLiteSelectPredicate::filter($predicateRows, $predicate);
        $t->same($expected, array_map(static fn (array $row): array => [$row['w.autoload'], $row['m.meta_value']], $matched));
    };
}

$tests['null distinct is-not current source rejects ambiguous unqualified predicate column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectPredicate::evaluate(
        ['w.option_id' => 1, 'm.option_id' => 1],
        ['operator' => 'IS NOT NULL', 'left' => ['type' => 'column', 'name' => 'option_id']]
    ));
};

$tests['null distinct is-not current source rejects ambiguous unqualified projection column'] = static function (TestRunner $t) use ($options, $meta): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        'SELECT option_id FROM wp_options AS w JOIN option_meta AS m ON w.option_id = m.option_id',
        ['wp_options' => $options, 'option_meta' => $meta]
    ));
};

return $tests;
