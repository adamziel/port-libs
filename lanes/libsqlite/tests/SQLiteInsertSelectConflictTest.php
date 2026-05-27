<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteInsertSelectSql;

$fixtures = static function (): array {
    return [
        'wp_options' => [
            ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'bytes' => 24],
            ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'bytes' => 24],
            ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Example Site', 'autoload' => 'yes', 'bytes' => 9],
            ['option_id' => 4, 'option_name' => 'home', 'option_value' => 'duplicate-home', 'autoload' => 'no', 'bytes' => 14],
            ['option_id' => 5, 'option_name' => null, 'option_value' => 'anonymous', 'autoload' => 'no', 'bytes' => 9],
        ],
        'archived_options' => [
            ['archive_id' => 10, 'option_name' => 'siteurl', 'option_value' => 'old-site', 'source_id' => 100],
            ['archive_id' => 11, 'option_name' => 'legacy', 'option_value' => 'old-legacy', 'source_id' => 101],
            ['archive_id' => 12, 'option_name' => null, 'option_value' => 'old-null', 'source_id' => 102],
        ],
    ];
};

$insertSql = static function (string $action = 'IGNORE', int $offset = 20, ?string $where = null): string {
    $sql = "INSERT OR {$action} INTO archived_options (archive_id, option_name, option_value, source_id) SELECT option_id + {$offset}, option_name, option_value, option_id FROM wp_options";
    if ($where !== null) {
        $sql .= " WHERE {$where}";
    }

    return $sql . ' ORDER BY option_id';
};

return [
    'insert select conflict plan records ignore action' => static function (TestRunner $t) use ($fixtures, $insertSql): void {
        $plan = SQLiteInsertSelectSql::plan($insertSql(), $fixtures());
        $t->same('ignore', $plan['conflict_action']);
        $t->same('archived_options', $plan['target']);
    },
    'insert select conflict plan records replace action' => static function (TestRunner $t) use ($fixtures, $insertSql): void {
        $plan = SQLiteInsertSelectSql::plan($insertSql('REPLACE', 30), $fixtures());
        $t->same('replace', $plan['conflict_action']);
        $t->same([31, 32, 33, 34, 35], array_column($plan['inserted_rows'], 'archive_id'));
    },
    'insert select conflict plan preserves abort action' => static function (TestRunner $t) use ($fixtures): void {
        $plan = SQLiteInsertSelectSql::plan('INSERT OR ABORT INTO archived_options (archive_id, option_name) SELECT option_id, option_name FROM wp_options', $fixtures());
        $t->same('abort', $plan['conflict_action']);
    },
    'insert select conflict default action is abort' => static function (TestRunner $t) use ($fixtures): void {
        $plan = SQLiteInsertSelectSql::plan('INSERT INTO archived_options (archive_id, option_name) SELECT option_id, option_name FROM wp_options', $fixtures());
        $t->same('abort', $plan['conflict_action']);
    },
    'insert select ignore skips existing unique option name' => static function (TestRunner $t) use ($fixtures, $insertSql): void {
        $result = SQLiteInsertSelectSql::execute($insertSql(), $fixtures(), [], [['option_name']]);
        $t->same(['siteurl', 'home'], array_column($result['ignored_rows'], 'option_name'));
    },
    'insert select ignore inserts non-conflicting source rows' => static function (TestRunner $t) use ($fixtures, $insertSql): void {
        $result = SQLiteInsertSelectSql::execute($insertSql(), $fixtures(), [], [['option_name']]);
        $t->same([22, 23, 25], array_column($result['inserted_rows'], 'archive_id'));
    },
    'insert select ignore preserves existing archive rows' => static function (TestRunner $t) use ($fixtures, $insertSql): void {
        $result = SQLiteInsertSelectSql::execute($insertSql(), $fixtures(), [], [['option_name']]);
        $t->same([10, 11, 12], array_slice(array_column($result['after'], 'archive_id'), 0, 3));
    },
    'insert select ignore counts only inserted rows as changes' => static function (TestRunner $t) use ($fixtures, $insertSql): void {
        $result = SQLiteInsertSelectSql::execute($insertSql(), $fixtures(), [], [['option_name']]);
        $t->same(3, $result['changes']);
    },
    'insert select ignore treats null unique values as non-conflicting' => static function (TestRunner $t) use ($fixtures, $insertSql): void {
        $result = SQLiteInsertSelectSql::execute($insertSql(), $fixtures(), [], [['option_name']]);
        $t->same([null, null], array_values(array_filter(array_column($result['after'], 'option_name'), static fn (mixed $name): bool => $name === null)));
    },
    'insert select ignore detects conflicts against prior inserted rows' => static function (TestRunner $t) use ($fixtures, $insertSql): void {
        $result = SQLiteInsertSelectSql::execute($insertSql('IGNORE', 60, "option_name = 'home'"), $fixtures(), [], [['option_name']]);
        $t->same([62], array_column($result['inserted_rows'], 'archive_id'));
        $t->same([64], array_column($result['ignored_rows'], 'archive_id'));
    },
    'insert select replace deletes existing conflicting row' => static function (TestRunner $t) use ($fixtures, $insertSql): void {
        $result = SQLiteInsertSelectSql::execute($insertSql('REPLACE', 30), $fixtures(), [], [['option_name']]);
        $t->same(['siteurl', 'home'], array_column($result['deleted_rows'], 'option_name'));
    },
    'insert select replace deletes prior inserted conflict' => static function (TestRunner $t) use ($fixtures, $insertSql): void {
        $result = SQLiteInsertSelectSql::execute($insertSql('REPLACE', 30), $fixtures(), [], [['option_name']]);
        $t->same([10, 32], array_column($result['deleted_rows'], 'archive_id'));
    },
    'insert select replace retains last conflicting source row' => static function (TestRunner $t) use ($fixtures, $insertSql): void {
        $result = SQLiteInsertSelectSql::execute($insertSql('REPLACE', 30), $fixtures(), [], [['option_name']]);
        $t->same('duplicate-home', $result['after'][4]['option_value']);
    },
    'insert select replace counts every attempted insert as change' => static function (TestRunner $t) use ($fixtures, $insertSql): void {
        $result = SQLiteInsertSelectSql::execute($insertSql('REPLACE', 30), $fixtures(), [], [['option_name']]);
        $t->same(5, $result['changes']);
    },
    'insert select replace keeps null unique values distinct' => static function (TestRunner $t) use ($fixtures, $insertSql): void {
        $result = SQLiteInsertSelectSql::execute($insertSql('REPLACE', 30), $fixtures(), [], [['option_name']]);
        $t->same([12, 35], array_values(array_map(
            static fn (array $row): int => $row['archive_id'],
            array_filter($result['after'], static fn (array $row): bool => $row['option_name'] === null),
        )));
    },
    'insert select composite unique allows same name with different value' => static function (TestRunner $t) use ($fixtures, $insertSql): void {
        $result = SQLiteInsertSelectSql::execute($insertSql('IGNORE', 40), $fixtures(), [], [['option_name', 'option_value']]);
        $t->same(5, $result['changes']);
    },
    'insert select composite unique compares all columns' => static function (TestRunner $t) use ($fixtures, $insertSql): void {
        $rows = $fixtures();
        $rows['archived_options'][] = ['archive_id' => 13, 'option_name' => 'blogname', 'option_value' => 'Example Site', 'source_id' => 0];
        $result = SQLiteInsertSelectSql::execute($insertSql('IGNORE', 40), $rows, [], [['option_name', 'option_value']]);
        $t->same(['blogname'], array_column($result['ignored_rows'], 'option_name'));
    },
    'insert select parameterized conflict source works' => static function (TestRunner $t) use ($fixtures): void {
        $result = SQLiteInsertSelectSql::execute(
            'INSERT OR IGNORE INTO archived_options (archive_id, option_name, option_value, source_id) SELECT option_id + ?, option_name || :suffix, option_value, option_id FROM wp_options WHERE autoload = :autoload ORDER BY option_id',
            ['wp_options' => $fixtures()['wp_options'], 'archived_options' => [['archive_id' => 70, 'option_name' => 'home:copy', 'option_value' => 'old', 'source_id' => 0]]],
            [0 => 50, ':suffix' => ':copy', ':autoload' => 'yes'],
            [['option_name']],
        );
        $t->same(['home:copy'], array_column($result['ignored_rows'], 'option_name'));
    },
    'insert select abort throws on unique conflict' => static function (TestRunner $t) use ($fixtures): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteInsertSelectSql::execute('INSERT INTO archived_options (archive_id, option_name) SELECT option_id, option_name FROM wp_options', $fixtures(), [], [['option_name']]));
    },
    'insert select or abort throws on unique conflict' => static function (TestRunner $t) use ($fixtures): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteInsertSelectSql::execute('INSERT OR ABORT INTO archived_options (archive_id, option_name) SELECT option_id, option_name FROM wp_options', $fixtures(), [], [['option_name']]));
    },
    'insert select or fail throws on unique conflict' => static function (TestRunner $t) use ($fixtures): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteInsertSelectSql::execute('INSERT OR FAIL INTO archived_options (archive_id, option_name) SELECT option_id, option_name FROM wp_options', $fixtures(), [], [['option_name']]));
    },
    'insert select or rollback throws on unique conflict' => static function (TestRunner $t) use ($fixtures): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteInsertSelectSql::execute('INSERT OR ROLLBACK INTO archived_options (archive_id, option_name) SELECT option_id, option_name FROM wp_options', $fixtures(), [], [['option_name']]));
    },
    'insert select conflict rejects empty unique column list' => static function (TestRunner $t) use ($fixtures): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteInsertSelectSql::execute('INSERT OR IGNORE INTO archived_options (archive_id, option_name) SELECT option_id, option_name FROM wp_options', $fixtures(), [], [[]]));
    },
    'insert select conflict rejects malformed unique column name' => static function (TestRunner $t) use ($fixtures): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteInsertSelectSql::execute('INSERT OR IGNORE INTO archived_options (archive_id, option_name) SELECT option_id, option_name FROM wp_options', $fixtures(), [], [['1bad']]));
    },
    'insert select conflict rejects missing unique column data' => static function (TestRunner $t) use ($fixtures): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteInsertSelectSql::execute('INSERT OR IGNORE INTO archived_options (archive_id, option_name) SELECT option_id, option_name FROM wp_options', $fixtures(), [], [['missing']]));
    },
    'insert select conflict rejects unsupported conflict keyword' => static function (TestRunner $t) use ($fixtures): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteInsertSelectSql::execute('INSERT OR UPSERT INTO archived_options (archive_id, option_name) SELECT option_id, option_name FROM wp_options', $fixtures()));
    },
];
