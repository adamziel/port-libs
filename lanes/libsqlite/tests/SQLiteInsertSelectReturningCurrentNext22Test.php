<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteInsertSelectSql;

$fixtures = static function (): array {
    return [
        'wp_options' => [
            ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'bytes' => 24],
            ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'bytes' => 24],
            ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Example Site', 'autoload' => 'yes', 'bytes' => 9],
            ['option_id' => 4, 'option_name' => '_transient_feed', 'option_value' => 'cached', 'autoload' => 'no', 'bytes' => 12],
            ['option_id' => 5, 'option_name' => 'home', 'option_value' => 'duplicate-home', 'autoload' => 'no', 'bytes' => 14],
            ['option_id' => 6, 'option_name' => null, 'option_value' => 'anonymous', 'autoload' => 'no', 'bytes' => 9],
        ],
        'archived_options' => [
            ['archive_id' => 10, 'option_name' => 'siteurl', 'option_value' => 'old-site', 'autoload' => 'no', 'source_id' => 100],
            ['archive_id' => 11, 'option_name' => 'legacy', 'option_value' => 'old-legacy', 'autoload' => 'no', 'source_id' => 101],
            ['archive_id' => 12, 'option_name' => null, 'option_value' => 'old-null', 'autoload' => 'no', 'source_id' => 102],
        ],
    ];
};

$insertSql = static function (string $action = 'IGNORE', int $offset = 20, string $returning = 'archive_id, option_name, source_id'): string {
    return "INSERT OR {$action} INTO archived_options (archive_id, option_name, option_value, autoload, source_id) "
        . "SELECT option_id + {$offset}, option_name, option_value, 'no', option_id FROM wp_options ORDER BY option_id "
        . "RETURNING {$returning}";
};

return [
    'insert select returning plan separates select and returning SQL' => static function (TestRunner $t) use ($fixtures, $insertSql): void {
        $plan = SQLiteInsertSelectSql::plan($insertSql(), $fixtures());
        $t->same('archived_options', $plan['target']);
        $t->same('ignore', $plan['conflict_action']);
        $t->same(['archive_id', 'option_name', 'option_value', 'autoload', 'source_id'], $plan['columns']);
        $t->same("SELECT option_id + 20, option_name, option_value, 'no', option_id FROM wp_options ORDER BY option_id", $plan['select_sql']);
        $t->same('archive_id, option_name, source_id', $plan['returning_sql']);
        $t->same(6, count($plan['source_rows']));
        $t->same([21, 22, 23, 24, 25, 26], array_column($plan['inserted_rows'], 'archive_id'));
    },
    'insert select returning ignore exposes only inserted rows' => static function (TestRunner $t) use ($fixtures, $insertSql): void {
        $result = SQLiteInsertSelectSql::execute($insertSql(), $fixtures(), [], [['option_name']]);
        $t->same('ignore', $result['conflict_action']);
        $t->same(4, $result['changes']);
        $t->same(4, count($result['inserted_rows']));
        $t->same(2, count($result['ignored_rows']));
        $t->same([], $result['deleted_rows']);
        $t->same('archive_id, option_name, source_id', $result['returning_sql']);
        $t->same(4, count($result['returning_rows']));
        $t->same([22, 23, 24, 26], array_column($result['inserted_rows'], 'archive_id'));
        $t->same([22, 23, 24, 26], array_column($result['returning_rows'], 'archive_id'));
        $t->same(['home', 'blogname', '_transient_feed', null], array_column($result['returning_rows'], 'option_name'));
        $t->same([2, 3, 4, 6], array_column($result['returning_rows'], 'source_id'));
        $t->same(['siteurl', 'home'], array_column($result['ignored_rows'], 'option_name'));
        $t->same(['siteurl', 'legacy', null, 'home', 'blogname', '_transient_feed', null], array_column($result['after'], 'option_name'));
    },
    'insert select returning replace exposes replacement inserts not deletes' => static function (TestRunner $t) use ($fixtures, $insertSql): void {
        $result = SQLiteInsertSelectSql::execute($insertSql('REPLACE', 30), $fixtures(), [], [['option_name']]);
        $t->same('replace', $result['conflict_action']);
        $t->same(6, $result['changes']);
        $t->same(6, count($result['inserted_rows']));
        $t->same(2, count($result['deleted_rows']));
        $t->same([], $result['ignored_rows']);
        $t->same([10, 32], array_column($result['deleted_rows'], 'archive_id'));
        $t->same([31, 32, 33, 34, 35, 36], array_column($result['inserted_rows'], 'archive_id'));
        $t->same([31, 32, 33, 34, 35, 36], array_column($result['returning_rows'], 'archive_id'));
        $t->same(['siteurl', 'home', 'blogname', '_transient_feed', 'home', null], array_column($result['returning_rows'], 'option_name'));
        $t->same([1, 2, 3, 4, 5, 6], array_column($result['returning_rows'], 'source_id'));
        $t->same([11, 12, 31, 33, 34, 35, 36], array_column($result['after'], 'archive_id'));
        $t->same(['legacy', null, 'siteurl', 'blogname', '_transient_feed', 'home', null], array_column($result['after'], 'option_name'));
    },
    'insert select returning projects wildcard rows' => static function (TestRunner $t) use ($fixtures, $insertSql): void {
        $result = SQLiteInsertSelectSql::execute($insertSql('IGNORE', 40, '*'), $fixtures(), [], [['option_name']]);
        $t->same('*', $result['returning_sql']);
        $t->same(['archive_id', 'option_name', 'option_value', 'autoload', 'source_id'], array_keys($result['returning_rows'][0]));
        $t->same([42, 43, 44, 46], array_column($result['returning_rows'], 'archive_id'));
        $t->same(['https://example.test', 'Example Site', 'cached', 'anonymous'], array_column($result['returning_rows'], 'option_value'));
        $t->same(['no', 'no', 'no', 'no'], array_column($result['returning_rows'], 'autoload'));
        $t->same([2, 3, 4, 6], array_column($result['returning_rows'], 'source_id'));
    },
    'insert select returning projects expressions and aliases' => static function (TestRunner $t) use ($fixtures, $insertSql): void {
        $result = SQLiteInsertSelectSql::execute(
            $insertSql('IGNORE', 50, "archive_id AS copied_id, option_name || ':' || source_id AS label, source_id + 1000 AS imported_from"),
            $fixtures(),
            [],
            [['option_name']],
        );
        $t->same(['copied_id', 'label', 'imported_from'], array_keys($result['returning_rows'][0]));
        $t->same([52, 53, 54, 56], array_column($result['returning_rows'], 'copied_id'));
        $t->same(['home:2', 'blogname:3', '_transient_feed:4', null], array_column($result['returning_rows'], 'label'));
        $t->same([1002, 1003, 1004, 1006], array_column($result['returning_rows'], 'imported_from'));
        $t->same(4, $result['changes']);
    },
    'insert select returning keeps null unique values returnable' => static function (TestRunner $t) use ($fixtures, $insertSql): void {
        $result = SQLiteInsertSelectSql::execute($insertSql('IGNORE', 60), $fixtures(), [], [['option_name']]);
        $nullReturned = array_values(array_filter($result['returning_rows'], static fn (array $row): bool => $row['option_name'] === null));
        $t->same(1, count($nullReturned));
        $t->same(66, $nullReturned[0]['archive_id']);
        $t->same(6, $nullReturned[0]['source_id']);
        $t->same([12, 66], array_values(array_map(
            static fn (array $row): int => $row['archive_id'],
            array_filter($result['after'], static fn (array $row): bool => $row['option_name'] === null),
        )));
    },
    'insert select returning empty insert has empty returning rows' => static function (TestRunner $t) use ($fixtures): void {
        $result = SQLiteInsertSelectSql::execute(
            "INSERT INTO archived_options (archive_id, option_name) SELECT option_id, option_name FROM wp_options WHERE option_name = 'missing' RETURNING archive_id, option_name",
            $fixtures(),
        );
        $t->same(0, $result['changes']);
        $t->same([], $result['inserted_rows']);
        $t->same([], $result['returning_rows']);
        $t->same('archive_id, option_name', $result['returning_sql']);
        $t->same($fixtures()['archived_options'], $result['after']);
    },
    'insert select returning preserves absent returning metadata without clause' => static function (TestRunner $t) use ($fixtures): void {
        $result = SQLiteInsertSelectSql::execute(
            "INSERT INTO archived_options (archive_id, option_name) SELECT option_id + 80, option_name FROM wp_options WHERE option_name = 'blogname'",
            $fixtures(),
        );
        $t->same(1, $result['changes']);
        $t->same(null, $result['returning_sql']);
        $t->same([], $result['returning_rows']);
        $t->same([83], array_column($result['inserted_rows'], 'archive_id'));
    },
    'insert select returning rejects empty projection' => static function (TestRunner $t) use ($fixtures): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteInsertSelectSql::execute(
            "INSERT INTO archived_options (archive_id, option_name) SELECT option_id, option_name FROM wp_options RETURNING",
            $fixtures(),
        ));
    },
    'insert select returning rejects unsupported returning expression' => static function (TestRunner $t) use ($fixtures): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteInsertSelectSql::execute(
            "INSERT INTO archived_options (archive_id, option_name) SELECT option_id + 90, option_name FROM wp_options WHERE option_name = 'blogname' RETURNING missing_column",
            $fixtures(),
        ));
    },
];
