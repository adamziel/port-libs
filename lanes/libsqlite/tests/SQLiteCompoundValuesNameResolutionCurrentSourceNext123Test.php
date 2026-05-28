<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'priority' => 30],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'priority' => 20],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'priority' => 5],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'priority' => 40],
];
$nextOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'priority' => 30],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'priority' => 20],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'priority' => 40],
    ['option_id' => 5, 'option_name' => 'new_plugin_flag', 'autoload' => 'no', 'priority' => 50],
];

$tests = [];

$rows = static fn (string $sql, array $tables = []): array => SQLiteSelectSql::execute($sql, $tables);
$column = static fn (string $sql, string $name, array $tables = []): array => array_column(SQLiteSelectSql::execute($sql, $tables), $name);

$cases = [
    'left values aliases survive table right arm rename' => [
        "SELECT v.id, v.name FROM (VALUES (1, 'siteurl'), (4, 'active_plugins')) AS v(id, name) UNION ALL SELECT option_id AS option_id, option_name AS option_name FROM wp_options WHERE option_id = 2 ORDER BY id",
        ['id', 'name'],
        [[1, 'siteurl'], [2, 'home'], [4, 'active_plugins']],
        $currentOptions,
    ],
    'right table aliases rename to left values names by position' => [
        "SELECT v.id, v.name FROM (VALUES (1, 'siteurl')) AS v(id, name) UNION ALL SELECT option_id AS current_id, option_name AS current_name FROM wp_options WHERE option_id IN (3, 4) ORDER BY id",
        ['id', 'name'],
        [[1, 'siteurl'], [3, 'rewrite_rules'], [4, 'active_plugins']],
        $currentOptions,
    ],
    'right values aliases rename to left table names by position' => [
        "SELECT option_id, option_name FROM wp_options WHERE option_id = 1 UNION ALL SELECT v.id AS id, v.name AS name FROM (VALUES (5, 'new_plugin_flag'), (6, 'cleanup_marker')) AS v(id, name) ORDER BY option_id",
        ['option_id', 'option_name'],
        [[1, 'siteurl'], [5, 'new_plugin_flag'], [6, 'cleanup_marker']],
        $currentOptions,
    ],
    'qualified values alias resolves through compound intersect' => [
        "SELECT v.id FROM (VALUES (1), (2), (9)) AS v(id) INTERSECT SELECT option_id FROM wp_options ORDER BY id",
        ['id'],
        [[1], [2]],
        $currentOptions,
    ],
    'qualified values alias resolves through compound except' => [
        "SELECT v.id FROM (VALUES (1), (2), (9)) AS v(id) EXCEPT SELECT option_id FROM wp_options ORDER BY id",
        ['id'],
        [[9]],
        $currentOptions,
    ],
    'compound union deduplicates current source against named values' => [
        "SELECT v.name FROM (VALUES ('siteurl'), ('plugin_seed')) AS v(name) UNION SELECT option_name FROM wp_options WHERE option_id IN (1, 2) ORDER BY name",
        ['name'],
        [['home'], ['plugin_seed'], ['siteurl']],
        $currentOptions,
    ],
    'compound union all preserves named values duplicates' => [
        "SELECT v.name FROM (VALUES ('siteurl'), ('siteurl')) AS v(name) UNION ALL SELECT option_name FROM wp_options WHERE option_id = 1 ORDER BY name",
        ['name'],
        [['siteurl'], ['siteurl'], ['siteurl']],
        $currentOptions,
    ],
    'current source values arm differs from next source table arm' => [
        "SELECT v.id, v.name FROM (VALUES (3, 'rewrite_rules')) AS v(id, name) UNION ALL SELECT option_id, option_name FROM wp_options WHERE autoload = 'no' ORDER BY id",
        ['id', 'name'],
        [[3, 'rewrite_rules'], [5, 'new_plugin_flag']],
        $nextOptions,
    ],
    'next source values arm remains positionally renamed to current table names' => [
        "SELECT option_id, option_name FROM wp_options WHERE autoload = 'no' UNION ALL SELECT v.id, v.name FROM (VALUES (8, 'queued_plugin')) AS v(id, name) ORDER BY option_id",
        ['option_id', 'option_name'],
        [[5, 'new_plugin_flag'], [8, 'queued_plugin']],
        $nextOptions,
    ],
    'values aliases are available to where before compound combination' => [
        "SELECT v.id, v.name FROM (VALUES (1, 'siteurl'), (7, 'ignored')) AS v(id, name) WHERE v.id < 3 UNION ALL SELECT option_id, option_name FROM wp_options WHERE option_id = 4 ORDER BY id",
        ['id', 'name'],
        [[1, 'siteurl'], [4, 'active_plugins']],
        $currentOptions,
    ],
    'values aliases are available to unqualified where before compound combination' => [
        "SELECT id, name FROM (VALUES (1, 'siteurl'), (7, 'ignored')) AS v(id, name) WHERE id < 3 UNION ALL SELECT option_id, option_name FROM wp_options WHERE option_id = 4 ORDER BY id",
        ['id', 'name'],
        [[1, 'siteurl'], [4, 'active_plugins']],
        $currentOptions,
    ],
    'values aliases are available to order expression in arm before compound' => [
        "SELECT id, name FROM (VALUES (2, 'home'), (1, 'siteurl')) AS v(id, name) ORDER BY id LIMIT 1 UNION ALL SELECT option_id, option_name FROM wp_options WHERE option_id = 4 ORDER BY id",
        ['id', 'name'],
        [[1, 'siteurl'], [4, 'active_plugins']],
        $currentOptions,
    ],
];

foreach ($cases as $name => [$sql, $columns, $expected, $options]) {
    $tests['compound values name resolution current source next123 ' . $name] = static function (TestRunner $t) use ($rows, $sql, $columns, $expected, $options): void {
        $actual = [];
        foreach ($rows($sql, ['wp_options' => $options]) as $row) {
            $actual[] = array_map(static fn (string $column): mixed => $row[$column], $columns);
        }
        $t->same($expected, $actual);
    };
}

$tests['compound values name resolution current source next123 output keys remain left values aliases'] = static function (TestRunner $t) use ($rows, $currentOptions): void {
    $actual = $rows("SELECT v.id, v.name FROM (VALUES (1, 'siteurl')) AS v(id, name) UNION ALL SELECT option_id, option_name FROM wp_options WHERE option_id = 2", ['wp_options' => $currentOptions]);
    $t->same([['id', 'name'], ['id', 'name']], array_map('array_keys', $actual));
};

$tests['compound values name resolution current source next123 output keys remain left table names'] = static function (TestRunner $t) use ($rows, $currentOptions): void {
    $actual = $rows("SELECT option_id, option_name FROM wp_options WHERE option_id = 1 UNION ALL SELECT v.id, v.name FROM (VALUES (2, 'home')) AS v(id, name)", ['wp_options' => $currentOptions]);
    $t->same([['option_id', 'option_name'], ['option_id', 'option_name']], array_map('array_keys', $actual));
};

$tests['compound values name resolution current source next123 plan exposes values alias columns'] = static function (TestRunner $t): void {
    $plan = SQLiteSelectSql::plan("SELECT v.id, v.name FROM (VALUES (1, 'siteurl')) AS v(id, name)", []);
    $t->same(['v.id' => 1, 'v.name' => 'siteurl'], $plan['from'][0]);
};

$tests['compound values name resolution current source next123 qualified plan exposes values alias when correlated'] = static function (TestRunner $t): void {
    $plan = SQLiteSelectSql::plan("SELECT v.id FROM (VALUES (1)) AS v(id)", [], [], ['outer_id' => 9]);
    $t->same('v', $plan['sourceAlias']);
    $t->same(['v.id' => 1], $plan['from'][0]);
};

$tests['compound values name resolution current source next123 rejects too few aliases'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT v.id FROM (VALUES (1, 'siteurl')) AS v(id)", []));
};

$tests['compound values name resolution current source next123 rejects too many aliases'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT v.id FROM (VALUES (1)) AS v(id, name)", []));
};

$tests['compound values name resolution current source next123 rejects empty alias list'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT * FROM (VALUES (1)) AS v()", []));
};

$tests['compound values name resolution current source next123 rejects malformed alias tail'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT * FROM (VALUES (1)) AS v(id) extra", []));
};

$tests['compound values name resolution current source next123 rejects malformed column alias'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT * FROM (VALUES (1)) AS v(1bad)", []));
};

foreach (range(1, 24) as $id) {
    $tests['compound values name resolution current source next123 generated union all row ' . $id] = static function (TestRunner $t) use ($column, $id): void {
        $sql = "SELECT v.id FROM (VALUES ({$id}), (" . ($id + 100) . ")) AS v(id) WHERE v.id = {$id} UNION ALL SELECT v.id FROM (VALUES (" . ($id + 200) . ")) AS v(id) ORDER BY id";
        $t->same([$id, $id + 200], $column($sql, 'id'));
    };
}

foreach (range(1, 12) as $id) {
    $tests['compound values name resolution current source next123 generated except row ' . $id] = static function (TestRunner $t) use ($column, $id): void {
        $sql = "SELECT v.id FROM (VALUES ({$id}), (" . ($id + 1) . "), (" . ($id + 2) . ")) AS v(id) EXCEPT SELECT r.id FROM (VALUES (" . ($id + 1) . ")) AS r(id) ORDER BY id";
        $t->same([$id, $id + 2], $column($sql, 'id'));
    };
}

return $tests;
