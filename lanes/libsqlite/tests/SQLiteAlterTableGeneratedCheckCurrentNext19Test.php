<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAlterTableColumnCorpus;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

$table = new SQLiteSchemaRecord(
    'table',
    'wp_options',
    'wp_options',
    2,
    'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes")',
    1,
);

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'transient_timeout_feed', 'option_value' => '1700000000', 'autoload' => 'no'],
];

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $part === 'count' ? count($value) : $value[$part];
    }

    return $value;
};

$acceptCases = [
    'virtual lower generated passes check' => [
        "ALTER TABLE wp_options ADD COLUMN option_name_lower TEXT GENERATED ALWAYS AS (lower(option_name)) VIRTUAL CHECK(option_name_lower <> '')",
        'column',
        'option_name_lower',
    ],
    'virtual lower generated scans current rows' => [
        "ALTER TABLE wp_options ADD COLUMN option_name_lower TEXT GENERATED ALWAYS AS (lower(option_name)) VIRTUAL CHECK(option_name_lower <> '')",
        'checked_rows',
        3,
    ],
    'virtual lower generated flag' => [
        "ALTER TABLE wp_options ADD COLUMN option_name_lower TEXT GENERATED ALWAYS AS (lower(option_name)) VIRTUAL CHECK(option_name_lower <> '')",
        'generated',
        true,
    ],
    'virtual length generated passes lower bound' => [
        'ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER AS (length(option_value)) VIRTUAL CHECK(option_value_len >= 5)',
        'column',
        'option_value_len',
    ],
    'virtual length generated rewrite preserved' => [
        'ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER AS (length(option_value)) VIRTUAL CHECK(option_value_len >= 5)',
        'sql',
        'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes", option_value_len INTEGER AS (length(option_value)) VIRTUAL CHECK(option_value_len >= 5))',
    ],
    'ordinary default check scans current rows' => [
        "ALTER TABLE wp_options ADD COLUMN option_source TEXT DEFAULT 'core' CHECK(option_source <> '')",
        'checked_rows',
        3,
    ],
    'ordinary default check is not generated' => [
        "ALTER TABLE wp_options ADD COLUMN option_source TEXT DEFAULT 'core' CHECK(option_source <> '')",
        'generated',
        false,
    ],
    'not null default scans current rows' => [
        "ALTER TABLE wp_options ADD COLUMN option_scope TEXT NOT NULL DEFAULT 'site'",
        'checked_rows',
        3,
    ],
    'not null default rewrite preserved' => [
        "ALTER TABLE wp_options ADD COLUMN option_scope TEXT NOT NULL DEFAULT 'site'",
        'sql',
        "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT \"yes\", option_scope TEXT NOT NULL DEFAULT 'site')",
    ],
    'generated concatenation check passes' => [
        'ALTER TABLE wp_options ADD COLUMN option_route TEXT AS (autoload || ":" || option_name) VIRTUAL CHECK(length(option_route) > 4)',
        'checked_rows',
        3,
    ],
    'generated concatenation column named' => [
        'ALTER TABLE wp_options ADD COLUMN option_route TEXT AS (autoload || ":" || option_name) VIRTUAL CHECK(length(option_route) > 4)',
        'column',
        'option_route',
    ],
    'generated concatenation rewrite preserved' => [
        'ALTER TABLE wp_options ADD COLUMN option_route TEXT AS (autoload || ":" || option_name) VIRTUAL CHECK(length(option_route) > 4)',
        'sql',
        'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes", option_route TEXT AS (autoload || ":" || option_name) VIRTUAL CHECK(length(option_route) > 4))',
    ],
    'generated nullable check permits unknown' => [
        "ALTER TABLE wp_options ADD COLUMN optional_copy TEXT AS (missing_source) VIRTUAL CHECK(optional_copy <> 'blocked')",
        'checked_rows',
        3,
    ],
    'generated nullable check column named' => [
        "ALTER TABLE wp_options ADD COLUMN optional_copy TEXT AS (missing_source) VIRTUAL CHECK(optional_copy <> 'blocked')",
        'column',
        'optional_copy',
    ],
    'ordinary default check column named' => [
        "ALTER TABLE wp_options ADD COLUMN option_source TEXT DEFAULT 'core' CHECK(option_source <> '')",
        'column',
        'option_source',
    ],
    'ordinary default check row count recorded' => [
        "ALTER TABLE wp_options ADD COLUMN option_source TEXT DEFAULT 'core' CHECK(option_source <> '')",
        'current_row_count',
        3,
    ],
    'virtual length generated row count recorded' => [
        'ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER AS (length(option_value)) VIRTUAL CHECK(option_value_len >= 5)',
        'current_row_count',
        3,
    ],
];

foreach ($acceptCases as $name => [$sql, $path, $expected]) {
    $tests['alter table generated check current next19 ' . $name] = static function (TestRunner $t) use ($table, $rows, $valueAt, $sql, $path, $expected): void {
        $t->same($expected, $valueAt(SQLiteAlterTableColumnCorpus::addColumn($table, $sql, $rows), $path));
    };
}

$rejectCases = [
    'generated lower check rejects blank option name' => [
        "ALTER TABLE wp_options ADD COLUMN option_name_lower TEXT AS (lower(option_name)) VIRTUAL CHECK(option_name_lower <> '')",
        [
            ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
            ['option_id' => 2, 'option_name' => '', 'option_value' => 'bad', 'autoload' => 'yes'],
        ],
    ],
    'generated length check rejects short current value' => [
        'ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER AS (length(option_value)) VIRTUAL CHECK(option_value_len > 3)',
        [
            ['option_id' => 1, 'option_name' => 'short', 'option_value' => 'abc', 'autoload' => 'no'],
        ],
    ],
    'ordinary check rejects default against current rows' => [
        "ALTER TABLE wp_options ADD COLUMN option_source TEXT DEFAULT '' CHECK(option_source <> '')",
        $rows,
    ],
    'not null default null rejects current rows' => [
        'ALTER TABLE wp_options ADD COLUMN option_scope TEXT NOT NULL DEFAULT NULL',
        $rows,
    ],
    'generated not null rejects null expression' => [
        'ALTER TABLE wp_options ADD COLUMN copied_missing TEXT AS (missing_source) VIRTUAL NOT NULL',
        $rows,
    ],
    'second current row failure reports row scan' => [
        'ALTER TABLE wp_options ADD COLUMN option_name_len INTEGER AS (length(option_name)) VIRTUAL CHECK(option_name_len > 0)',
        [
            ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'ok', 'autoload' => 'yes'],
            ['option_id' => 2, 'option_name' => '', 'option_value' => 'bad', 'autoload' => 'yes'],
        ],
    ],
];

foreach ($rejectCases as $name => [$sql, $currentRows]) {
    $tests['alter table generated check current next19 rejects ' . $name] = static function (TestRunner $t) use ($table, $sql, $currentRows): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableColumnCorpus::addColumn($table, $sql, $currentRows));
    };
}

$tests['alter table generated check current next19 empty table does not scan'] = static function (TestRunner $t) use ($table): void {
    $plan = SQLiteAlterTableColumnCorpus::addColumn($table, "ALTER TABLE wp_options ADD COLUMN option_name_lower TEXT AS (lower(option_name)) VIRTUAL CHECK(option_name_lower <> '')", []);
    $t->same(0, $plan['checked_rows']);
};

$tests['alter table generated check current next19 existing no-check add remains unscanned'] = static function (TestRunner $t) use ($table, $rows): void {
    $plan = SQLiteAlterTableColumnCorpus::addColumn($table, 'ALTER TABLE wp_options ADD COLUMN option_note TEXT', $rows);
    $t->same(0, $plan['checked_rows']);
};

$tests['alter table generated check current next19 still rejects stored generated'] = static function (TestRunner $t) use ($table, $rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableColumnCorpus::addColumn($table, "ALTER TABLE wp_options ADD COLUMN stored_name TEXT AS (lower(option_name)) STORED CHECK(stored_name <> '')", $rows));
};

return $tests;
