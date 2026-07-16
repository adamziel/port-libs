<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaQuickcheckStatForeignKeyCurrentSourceYield;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$database = str_repeat("\0", 512);
$database = substr_replace($database, "SQLite format 3\0", 0, 16);
$database = substr_replace($database, pack('n', 512), 16, 2);
$database[18] = "\x01";
$database[19] = "\x01";
$database = substr_replace($database, pack('N', 1), 28, 4);
$database = substr_replace($database, pack('N', 1), 56, 4);

$badDatabase = substr_replace($database, pack('N', 7), 36, 4);

$records = [
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 3, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 2),
    $record('table', 'wp_posts', 'wp_posts', 4, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_parent INTEGER)', 3),
    $record('table', 'sqlite_stat1', 'sqlite_stat1', 5, 'CREATE TABLE sqlite_stat1(tbl,idx,stat)', 4),
    $record('table', 'sqlite_stat4', 'sqlite_stat4', 6, 'CREATE TABLE sqlite_stat4(tbl,idx,neq,nlt,ndlt,sample)', 5),
];

$malformedRecords = [
    $records[0],
    $record('table', 'sqlite_stat1', 'sqlite_stat1', 5, 'CREATE TABLE sqlite_stat1(tbl,idx)', 4),
];

$schemas = [
    'main' => [
        'tables' => [
            'wp_option_names' => [
                ['rowid' => 1, 'name' => 'siteurl'],
                ['rowid' => 2, 'name' => 'home'],
            ],
            'wp_options' => [
                ['rowid' => 1, 'option_id' => 1, 'option_name' => 'siteurl'],
                ['rowid' => 2, 'option_id' => 2, 'option_name' => 'missing_autoload'],
                ['rowid' => 3, 'option_id' => 3, 'option_name' => 'home'],
            ],
            'wp_posts' => [
                ['rowid' => 1, 'ID' => 1, 'post_parent' => 0],
                ['rowid' => 2, 'ID' => 2, 'post_parent' => 99],
            ],
        ],
        'foreignKeys' => [
            ['id' => 11, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
            ]],
            ['id' => 12, 'table' => 'wp_posts', 'parent' => 'wp_posts', 'columns' => [
                ['child' => 'post_parent', 'parent' => 'ID', 'affinity' => 'integer'],
            ]],
        ],
    ],
];

$cleanSchemas = [
    'main' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [['rowid' => 1, 'option_id' => 1, 'option_name' => 'siteurl']],
        ],
        'foreignKeys' => [
            ['id' => 11, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
            ]],
        ],
    ],
];

$page = static fn (int $offset = 0, int $limit = 123, ?array $cursor = null): array => SQLitePragmaQuickcheckStatForeignKeyCurrentSourceYield::page($database, $schemas, $records, 'PRAGMA foreign_key_check', $offset, $limit, 'PRAGMA quick_check', $cursor);
$badPage = static fn (): array => SQLitePragmaQuickcheckStatForeignKeyCurrentSourceYield::page($badDatabase, $schemas, $malformedRecords, 'PRAGMA foreign_key_check', 0, 123, 'PRAGMA quick_check');
$cleanPage = static fn (): array => SQLitePragmaQuickcheckStatForeignKeyCurrentSourceYield::page($database, $cleanSchemas, [$records[0], $records[1], $records[3]], 'PRAGMA foreign_key_check', 0, 123, 'PRAGMA quick_check');

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$cases = [
    'status blocked' => [$page, 'status', 'blocked'],
    'default limit next123' => [$page, 'limit', 123],
    'total combines stat and fk rows' => [$page, 'total', 5],
    'count combines stat and fk rows' => [$page, 'count', 5],
    'complete true' => [$page, 'complete', true],
    'next null' => [$page, 'next', null],
    'source id length' => [static fn (): array => ['len' => strlen($page()['source_id'])], 'len', 64],
    'database source hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['database'])], 'len', 64],
    'schemas source hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['schemas'])], 'len', 64],
    'records source hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['records'])], 'len', 64],
    'fk sql normalized' => [$page, 'current_source.foreign_key_sql', 'pragma foreign_key_check'],
    'quick sql normalized' => [$page, 'current_source.quickcheck_sql', 'pragma quick_check'],
    'current quick errors zero' => [$page, 'current.quickcheck_errors', 0],
    'current stat tables two' => [$page, 'current.stat_tables', 2],
    'current stat rows two' => [$page, 'current.stat_rows', 2],
    'current stat blockers zero' => [$page, 'current.stat_blockers', 0],
    'current fk violations three' => [$page, 'current.foreign_key_violations', 3],
    'current schemas main' => [$page, 'current.schemas.0', 'main'],
    'ready false' => [$page, 'next_state.ready', false],
    'blocker fk only' => [$page, 'next_state.blocking.0', 'foreign_key_check'],
    'row0 stat1 kind' => [$page, 'rows.0.kind', 'sqlite_stat1'],
    'row0 stat source' => [$page, 'rows.0.source', 'stat_catalog'],
    'row0 table' => [$page, 'rows.0.table', 'sqlite_stat1'],
    'row0 status' => [$page, 'rows.0.status', 'ok'],
    'row0 tracked tables' => [$page, 'rows.0.tracked_tables', 3],
    'row0 message' => [$page, 'rows.0.message', 'sqlite_stat1 catalog ready for 3 planner tables'],
    'row1 stat4 kind' => [$page, 'rows.1.kind', 'sqlite_stat4'],
    'row1 rowid' => [$page, 'rows.1.rowid', 5],
    'row1 rootpage' => [$page, 'rows.1.rootpage', 6],
    'row2 fk kind' => [$page, 'rows.2.kind', 'foreign_key_check'],
    'row2 source' => [$page, 'rows.2.source', 'foreign_key'],
    'row2 table' => [$page, 'rows.2.table', 'wp_options'],
    'row2 rowid' => [$page, 'rows.2.rowid', 2],
    'row2 parent' => [$page, 'rows.2.parent', 'wp_option_names'],
    'row2 fkid' => [$page, 'rows.2.fkid', 11],
    'row2 message' => [$page, 'rows.2.message', 'foreign key mismatch in main.wp_options rowid 2 references wp_option_names fkid 11'],
    'row3 table' => [$page, 'rows.3.table', 'wp_posts'],
    'row3 rowid' => [$page, 'rows.3.rowid', 1],
    'row3 parent' => [$page, 'rows.3.parent', 'wp_posts'],
    'bad status blocked' => [$badPage, 'status', 'blocked'],
    'bad quick errors one' => [$badPage, 'current.quickcheck_errors', 1],
    'bad stat blockers one' => [$badPage, 'current.stat_blockers', 1],
    'bad blocker quick' => [$badPage, 'next_state.blocking.0', 'quick_check'],
    'bad blocker stat' => [$badPage, 'next_state.blocking.1', 'sqlite_stat_catalog'],
    'bad blocker fk' => [$badPage, 'next_state.blocking.2', 'foreign_key_check'],
    'bad quick row kind' => [$badPage, 'rows.0.kind', 'quick_check'],
    'bad quick message' => [$badPage, 'rows.0.message', 'freelist page count is nonzero but first trunk page is zero'],
    'bad stat row status' => [$badPage, 'rows.1.status', 'malformed'],
    'bad stat row message' => [$badPage, 'rows.1.message', 'sqlite_stat1 catalog is missing stat columns'],
    'clean status ok' => [$cleanPage, 'status', 'ok'],
    'clean ready true' => [$cleanPage, 'next_state.ready', true],
    'clean total stat only' => [$cleanPage, 'total', 1],
    'clean row kind stat1' => [$cleanPage, 'rows.0.kind', 'sqlite_stat1'],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma quickcheck stat foreignkey current source next123 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma quickcheck stat foreignkey current source next123 paginates with cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 2);
    $second = $page(2, 2, ['source_id' => $first['source_id'], 'next_offset' => 2]);

    $t->same(2, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 2], $first['next']);
    $t->same('sqlite_stat1', $first['rows'][0]['kind']);
    $t->same('foreign_key_check', $second['rows'][0]['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 4], $second['next']);
};

$tests['pragma quickcheck stat foreignkey current source next123 rejects stale schema cursor'] = static function (TestRunner $t) use ($page, $database, $cleanSchemas, $records): void {
    $first = $page(0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaQuickcheckStatForeignKeyCurrentSourceYield::page($database, $cleanSchemas, $records, 'PRAGMA foreign_key_check', 2, 2, 'PRAGMA quick_check', ['source_id' => $first['source_id'], 'next_offset' => 2]));
};

$tests['pragma quickcheck stat foreignkey current source next123 rejects stale records cursor'] = static function (TestRunner $t) use ($page, $database, $schemas, $malformedRecords): void {
    $first = $page(0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaQuickcheckStatForeignKeyCurrentSourceYield::page($database, $schemas, $malformedRecords, 'PRAGMA foreign_key_check', 2, 2, 'PRAGMA quick_check', ['source_id' => $first['source_id'], 'next_offset' => 2]));
};

$tests['pragma quickcheck stat foreignkey current source next123 rejects stale offset cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $page(3, 2, ['source_id' => $first['source_id'], 'next_offset' => 2]));
};

$tests['pragma quickcheck stat foreignkey current source next123 rejects integrity pragma'] = static function (TestRunner $t) use ($database, $schemas, $records): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaQuickcheckStatForeignKeyCurrentSourceYield::page($database, $schemas, $records, 'PRAGMA foreign_key_check', 0, 123, 'PRAGMA integrity_check'));
};

$tests['pragma quickcheck stat foreignkey current source next123 rejects negative offset'] = static function (TestRunner $t) use ($database, $schemas, $records): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaQuickcheckStatForeignKeyCurrentSourceYield::page($database, $schemas, $records, 'PRAGMA foreign_key_check', -1, 1));
};

$tests['pragma quickcheck stat foreignkey current source next123 rejects zero limit'] = static function (TestRunner $t) use ($database, $schemas, $records): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaQuickcheckStatForeignKeyCurrentSourceYield::page($database, $schemas, $records, 'PRAGMA foreign_key_check', 0, 0));
};

return $tests;
