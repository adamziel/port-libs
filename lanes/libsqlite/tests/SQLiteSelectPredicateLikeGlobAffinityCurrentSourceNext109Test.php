<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectPredicate;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'plugin_priority', 'option_value' => 42, 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin_priority_real', 'option_value' => 4.5, 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => 'plugin_enabled', 'option_value' => true, 'autoload' => 'yes'],
    ['option_id' => 5, 'option_name' => 'plugin_disabled', 'option_value' => false, 'autoload' => 'no'],
    ['option_id' => 6, 'option_name' => 'plugin_text_number', 'option_value' => '042', 'autoload' => 'yes'],
    ['option_id' => 7, 'option_name' => 'plugin_blob_slug', 'option_value' => new SQLiteBlobValue('plugin:blob'), 'autoload' => 'yes'],
    ['option_id' => 8, 'option_name' => "plugin_\xc3_malformed", 'option_value' => "plugin:\xc3", 'autoload' => 'yes'],
    ['option_id' => 9, 'option_name' => 'cache_key', 'option_value' => 'cache', 'autoload' => 'yes'],
    ['option_id' => 10, 'option_name' => 'cache_key_space', 'option_value' => 'cache  ', 'autoload' => 'yes'],
    ['option_id' => 11, 'option_name' => 'cache_key_tab', 'option_value' => "cache\t", 'autoload' => 'yes'],
    ['option_id' => 12, 'option_name' => 'Cache_Key_Case', 'option_value' => 'CACHE', 'autoload' => 'yes'],
    ['option_id' => 13, 'option_name' => 'null_value', 'option_value' => null, 'autoload' => 'no'],
];

$filterRowids = static fn (array $predicate): array => array_column(SQLiteSelectPredicate::filter($rows, $predicate), 'option_id');
$like = static fn (mixed $left, mixed $right, mixed $escape = null, bool $caseSensitive = false): array => array_filter([
    'operator' => 'LIKE',
    'left' => $left,
    'right' => $right,
    'escape' => $escape,
    'caseSensitive' => $caseSensitive,
], static fn (mixed $value): bool => $value !== null);
$glob = static fn (mixed $left, mixed $right): array => [
    'operator' => 'GLOB',
    'left' => $left,
    'right' => $right,
];
$compare = static fn (string $operator, mixed $left, mixed $right): array => [
    'operator' => $operator,
    'left' => $left,
    'right' => $right,
];
$column = static fn (string $name): array => ['column' => $name];
$collate = static fn (string $name, string $collation): array => [
    'type' => 'collate',
    'operand' => ['type' => 'column', 'name' => $name],
    'collation' => $collation,
];

$cases = [
    'integer column LIKE coerces to decimal text' => [$like($column('option_value'), '4%', null, true), [2, 3]],
    'integer literal LIKE coerces on left side' => [$like(42, '4%', null, true), array_column($rows, 'option_id')],
    'integer pattern LIKE coerces on right side' => [$like('42', 42, null, true), array_column($rows, 'option_id')],
    'float column LIKE uses compact SQLite-style text' => [$like($column('option_value'), '4._', null, true), [3]],
    'float literal pattern LIKE coerces pattern' => [$like('4.5', 4.5, null, true), array_column($rows, 'option_id')],
    'true column LIKE coerces to one' => [$like($column('option_value'), '1', null, true), [4]],
    'false column LIKE coerces to zero' => [$like($column('option_value'), '0', null, true), [5]],
    'blob column LIKE uses byte text' => [$like($column('option_value'), 'plugin:%', null, true), [7, 8]],
    'malformed byte string LIKE remains byte-aware' => [$like($column('option_value'), "plugin:\xc3%", null, true), [8]],
    'escaped integer LIKE still coerces numeric text' => [$like($column('option_value'), '4.%', '!', true), [3]],
    'numeric column GLOB coerces integer and float' => [$glob($column('option_value'), '4*'), [2, 3]],
    'true column GLOB coerces to one' => [$glob($column('option_value'), '1'), [4]],
    'false column GLOB coerces to zero' => [$glob($column('option_value'), '0'), [5]],
    'blob column GLOB uses byte text' => [$glob($column('option_value'), 'plugin:*'), [7, 8]],
    'malformed byte string GLOB remains byte-aware' => [$glob($column('option_value'), "plugin:\xc3*"), [8]],
    'NOT LIKE with numeric coercion excludes numeric matches' => [['operator' => 'NOT LIKE', 'left' => $column('option_value'), 'right' => '4%', 'caseSensitive' => true], [1, 4, 5, 6, 7, 8, 9, 10, 11, 12]],
    'NOT GLOB with numeric coercion excludes numeric matches' => [['operator' => 'NOT GLOB', 'left' => $column('option_value'), 'right' => '4*'], [1, 4, 5, 6, 7, 8, 9, 10, 11, 12]],
    'NULL LIKE remains unknown and filtered out' => [$like($column('option_value'), '%'), [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]],
    'column LIKE boolean pattern coerces pattern true' => [$like($column('option_value'), true, null, true), [4]],
    'column GLOB boolean pattern coerces pattern false' => [$glob($column('option_value'), false), [5]],
    'column LIKE blob pattern uses byte text' => [$like($column('option_value'), new SQLiteBlobValue('plugin:%'), null, true), [7, 8]],
    'RTRIM equality ignores trailing spaces' => [$compare('=', $collate('option_value', 'RTRIM'), 'cache'), [9, 10]],
    'RTRIM equality does not trim tab' => [$compare('=', $collate('option_value', 'RTRIM'), "cache\t"), [11]],
    'RTRIM inequality keeps tab distinct from space-trimmed value' => [$compare('!=', $collate('option_value', 'RTRIM'), 'cache'), [1, 2, 3, 4, 5, 6, 7, 8, 11, 12]],
    'RTRIM greater-than treats tab after cache' => [$compare('>', $collate('option_value', 'RTRIM'), 'cache'), [1, 7, 8, 11]],
    'NOCASE equality still folds ASCII only' => [$compare('=', $collate('option_value', 'NOCASE'), 'cache'), [9, 12]],
];

foreach ($cases as $name => [$predicate, $expected]) {
    $tests['select predicate like glob affinity current source next109 ' . $name] = static function (TestRunner $t) use ($filterRowids, $predicate, $expected): void {
        $t->same($expected, $filterRowids($predicate));
    };
}

$sqlCases = [
    'SQL LIKE integer column coercion' => [
        "SELECT option_id FROM wp_options WHERE option_value LIKE '4%' ORDER BY option_id",
        [2, 3],
    ],
    'SQL GLOB integer column coercion' => [
        "SELECT option_id FROM wp_options WHERE option_value GLOB '4*' ORDER BY option_id",
        [2, 3],
    ],
    'SQL NOT LIKE numeric coercion' => [
        "SELECT option_id FROM wp_options WHERE option_value NOT LIKE '4%' ORDER BY option_id",
        [1, 4, 5, 6, 7, 8, 9, 10, 11, 12],
    ],
    'SQL RTRIM trims spaces only for equality' => [
        "SELECT option_id FROM wp_options WHERE option_value COLLATE RTRIM = 'cache' ORDER BY option_id",
        [9, 10],
    ],
    'SQL RTRIM preserves tab as distinct' => [
        "SELECT option_id FROM wp_options WHERE option_value COLLATE RTRIM = 'cache\t' ORDER BY option_id",
        [11],
    ],
    'SQL NOCASE still folds cache case' => [
        "SELECT option_id FROM wp_options WHERE option_value COLLATE NOCASE = 'cache' ORDER BY option_id",
        [9, 12],
    ],
    'SQL LIKE escaped numeric text' => [
        "SELECT option_id FROM wp_options WHERE option_value LIKE '4.%' ESCAPE '!' ORDER BY option_id",
        [3],
    ],
    'SQL GLOB malformed byte prefix' => [
        "SELECT option_id FROM wp_options WHERE option_value GLOB 'plugin:\xc3*' ORDER BY option_id",
        [8],
    ],
    'SQL LIKE boolean text coercion' => [
        "SELECT option_id FROM wp_options WHERE option_value LIKE '1' ORDER BY option_id",
        [4],
    ],
    'SQL GLOB boolean text coercion' => [
        "SELECT option_id FROM wp_options WHERE option_value GLOB '0' ORDER BY option_id",
        [5],
    ],
];

foreach ($sqlCases as $name => [$sql, $expected]) {
    $tests['select predicate like glob affinity current source next109 ' . $name] = static function (TestRunner $t) use ($rows, $sql, $expected): void {
        $result = SQLiteSelectSql::execute($sql, ['wp_options' => $rows]);
        $t->same($expected, array_column($result, 'option_id'));
    };
}

$scalarCases = [
    'direct LIKE integer true' => [$like(42, '4%', null, true), true],
    'direct LIKE float true' => [$like(4.5, '4._', null, true), true],
    'direct LIKE bool true' => [$like(true, '1', null, true), true],
    'direct LIKE bool false' => [$like(false, '0', null, true), true],
    'direct LIKE blob true' => [$like(new SQLiteBlobValue('plugin:blob'), 'plugin:%', null, true), true],
    'direct GLOB integer true' => [$glob(42, '4*'), true],
    'direct GLOB float true' => [$glob(4.5, '4.?'), true],
    'direct GLOB bool true' => [$glob(true, '1'), true],
    'direct GLOB bool false' => [$glob(false, '0'), true],
    'direct GLOB blob true' => [$glob(new SQLiteBlobValue('plugin:blob'), 'plugin:*'), true],
    'direct RTRIM space equality true' => [$compare('=', ['type' => 'collate', 'operand' => ['type' => 'literal', 'value' => 'cache  '], 'collation' => 'RTRIM'], 'cache'), true],
    'direct RTRIM tab equality false' => [$compare('=', ['type' => 'collate', 'operand' => ['type' => 'literal', 'value' => "cache\t"], 'collation' => 'RTRIM'], 'cache'), false],
];

foreach ($scalarCases as $name => [$predicate, $expected]) {
    $tests['select predicate like glob affinity current source next109 ' . $name] = static function (TestRunner $t) use ($predicate, $expected): void {
        $t->same($expected, SQLiteSelectPredicate::evaluate([], $predicate));
    };
}

$tests['select predicate like glob affinity current source next109 LIKE null escape stays unknown'] = static function (TestRunner $t) use ($like): void {
    $predicate = $like('plugin:blob', 'plugin:%', null, true);
    $predicate['escape'] = ['column' => 'escape_value'];
    $t->same(null, SQLiteSelectPredicate::evaluate(['escape_value' => null], $predicate));
};

$tests['select predicate like glob affinity current source next109 rejects array LIKE operand'] = static function (TestRunner $t) use ($like): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectPredicate::evaluate([], $like(['x'], '%')));
};

$tests['select predicate like glob affinity current source next109 rejects array GLOB operand'] = static function (TestRunner $t) use ($glob): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectPredicate::evaluate([], $glob(['x'], '*')));
};

$tests['select predicate like glob affinity current source next109 rejects array escape operand'] = static function (TestRunner $t) use ($like): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectPredicate::evaluate([], $like('plugin', 'plugin', ['!'])));
};

$tests['select predicate like glob affinity current source next109 rejects multi-character escape after coercion'] = static function (TestRunner $t) use ($like): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectPredicate::evaluate([], $like('plugin', 'plugin', 42)));
};

return $tests;
