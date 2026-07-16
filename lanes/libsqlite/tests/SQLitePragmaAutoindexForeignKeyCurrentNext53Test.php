<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaAutoindexForeignKeyPreflight;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$records = static function (string $variant = 'clean') use ($record): array {
    $optionNamesIndexRoot = $variant === 'missing-parent-root' ? null : 4;
    $optionNamesSql = $variant === 'collation-mismatch'
        ? 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE UNIQUE, source TEXT)'
        : 'CREATE TABLE wp_option_names(name TEXT UNIQUE, source TEXT)';
    $optionNamesIndexSql = $variant === 'collation-mismatch'
        ? 'CREATE UNIQUE INDEX sqlite_autoindex_wp_option_names_1 ON wp_option_names(name COLLATE BINARY)'
        : null;

    $records = [
        $record('table', 'wp_sites', 'wp_sites', 2, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT UNIQUE)', 1),
        $record('index', 'sqlite_autoindex_wp_sites_1', 'wp_sites', 3, null, 2),
        $record('table', 'wp_option_names', 'wp_option_names', 5, $optionNamesSql, 3),
        $record('index', 'sqlite_autoindex_wp_option_names_1', 'wp_option_names', $optionNamesIndexRoot, $optionNamesIndexSql, 4),
        $record('table', 'wp_options', 'wp_options', 7, "CREATE TABLE wp_options(
            option_id INTEGER PRIMARY KEY,
            blog_id INTEGER REFERENCES wp_sites(blog_id),
            option_name TEXT NOT NULL,
            locale TEXT NOT NULL DEFAULT 'en_US',
            option_value TEXT,
            UNIQUE(option_name, locale),
            FOREIGN KEY(option_name) REFERENCES wp_option_names(name)
        )", 5),
        $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 8, null, 6),
        $record('table', 'wp_postmeta', 'wp_postmeta', 9, 'CREATE TABLE wp_postmeta(meta_id INTEGER PRIMARY KEY, post_id INTEGER REFERENCES wp_posts(ID), meta_key TEXT)', 7),
        $record('table', 'wp_posts', 'wp_posts', 10, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_title TEXT)', 8),
    ];

    if ($variant === 'missing-child-index') {
        return array_values(array_filter(
            $records,
            static fn (SQLiteSchemaRecord $candidate): bool => $candidate->name !== 'sqlite_autoindex_wp_options_1'
        ));
    }

    return $records;
};

$foreignKeys = [
    ['table' => 'wp_options', 'parent' => 'wp_sites', 'columns' => ['blog_id' => 'blog_id']],
    ['table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => ['option_name' => 'name']],
    ['table' => 'wp_postmeta', 'parent' => 'wp_posts', 'columns' => ['post_id' => 'ID']],
];

$clean = static fn (): array => SQLitePragmaAutoindexForeignKeyPreflight::plan($records(), $foreignKeys);
$missingRoot = static fn (): array => SQLitePragmaAutoindexForeignKeyPreflight::plan($records('missing-parent-root'), $foreignKeys);
$missingChild = static fn (): array => SQLitePragmaAutoindexForeignKeyPreflight::plan($records('missing-child-index'), $foreignKeys);
$collationMismatch = static fn (): array => SQLitePragmaAutoindexForeignKeyPreflight::plan($records('collation-mismatch'), $foreignKeys);
$missingParentCoverage = static fn (): array => SQLitePragmaAutoindexForeignKeyPreflight::plan($records(), [
    ...$foreignKeys,
    ['table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => ['locale' => 'source']],
]);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$cases = [
    'clean status ready' => [$clean, 'status', 'ready'],
    'clean next ready' => [$clean, 'next.ready', true],
    'clean blocking empty' => [$clean, 'next.blocking.count', 0],
    'clean table count' => [$clean, 'tables.count', 5],
    'clean first table' => [$clean, 'tables.0', 'wp_sites'],
    'clean autoindex count' => [$clean, 'autoindexes.count', 3],
    'clean sites autoindex name' => [$clean, 'autoindexes.0.name', 'sqlite_autoindex_wp_sites_1'],
    'clean sites autoindex root' => [$clean, 'autoindexes.0.rootpage', 3],
    'clean sites autoindex column' => [$clean, 'autoindexes.0.columns.0', 'domain'],
    'clean sites autoindex collation' => [$clean, 'autoindexes.0.collations.0', 'BINARY'],
    'clean sites autoindex expected count' => [$clean, 'autoindexes.0.expected', 1],
    'clean sites autoindex actual count' => [$clean, 'autoindexes.0.actual', 1],
    'clean sites autoindex origin' => [$clean, 'autoindexes.0.origin', 'u'],
    'clean sites autoindex unique' => [$clean, 'autoindexes.0.unique', 1],
    'clean sites autoindex status' => [$clean, 'autoindexes.0.status', 'ok'],
    'clean parent autoindex name' => [$clean, 'autoindexes.1.name', 'sqlite_autoindex_wp_option_names_1'],
    'clean parent autoindex column' => [$clean, 'autoindexes.1.columns.0', 'name'],
    'clean parent autoindex status' => [$clean, 'autoindexes.1.status', 'ok'],
    'clean child composite autoindex name' => [$clean, 'autoindexes.2.name', 'sqlite_autoindex_wp_options_1'],
    'clean child composite expected count' => [$clean, 'autoindexes.2.expected', 2],
    'clean child composite actual count' => [$clean, 'autoindexes.2.actual', 2],
    'clean child composite first column' => [$clean, 'autoindexes.2.columns.0', 'option_name'],
    'clean child composite second column' => [$clean, 'autoindexes.2.columns.1', 'locale'],
    'clean child composite second collation' => [$clean, 'autoindexes.2.collations.1', 'BINARY'],
    'clean current autoindex errors' => [$clean, 'current.autoindex_errors', 0],
    'clean current fk parent errors' => [$clean, 'current.foreign_key_parent_errors', 0],
    'clean foreign key count' => [$clean, 'foreign_keys.count', 3],
    'clean blog foreign key table' => [$clean, 'foreign_keys.0.table', 'wp_options'],
    'clean blog foreign key parent' => [$clean, 'foreign_keys.0.parent', 'wp_sites'],
    'clean blog foreign key child column' => [$clean, 'foreign_keys.0.columns.0', 'blog_id'],
    'clean blog foreign key parent column' => [$clean, 'foreign_keys.0.parent_columns.0', 'blog_id'],
    'clean blog foreign key rowid parent has no required autoindex' => [$clean, 'foreign_keys.0.required_autoindex', null],
    'clean blog foreign key status' => [$clean, 'foreign_keys.0.status', 'ok'],
    'clean option-name foreign key autoindex' => [$clean, 'foreign_keys.1.required_autoindex', 'sqlite_autoindex_wp_option_names_1'],
    'clean option-name foreign key status' => [$clean, 'foreign_keys.1.status', 'ok'],
    'clean post rowid foreign key status' => [$clean, 'foreign_keys.2.status', 'ok'],
    'missing root status blocked' => [$missingRoot, 'status', 'blocked'],
    'missing root next not ready' => [$missingRoot, 'next.ready', false],
    'missing root blocker count' => [$missingRoot, 'next.blocking.count', 2],
    'missing root first blocker' => [$missingRoot, 'next.blocking.0', 'autoindex_catalog'],
    'missing root second blocker' => [$missingRoot, 'next.blocking.1', 'foreign_key_parent_autoindex'],
    'missing root autoindex status' => [$missingRoot, 'autoindexes.1.status', 'blocked'],
    'missing root autoindex root null' => [$missingRoot, 'autoindexes.1.rootpage', null],
    'missing root current autoindex error count' => [$missingRoot, 'current.autoindex_errors', 1],
    'missing root current fk parent error count' => [$missingRoot, 'current.foreign_key_parent_errors', 1],
    'missing root fk status blocked' => [$missingRoot, 'foreign_keys.1.status', 'blocked'],
    'missing child status blocked' => [$missingChild, 'status', 'blocked'],
    'missing child only autoindex blocker count' => [$missingChild, 'next.blocking.count', 1],
    'missing child blocker' => [$missingChild, 'next.blocking.0', 'autoindex_catalog'],
    'missing child actual column count zero' => [$missingChild, 'autoindexes.2.actual', 0],
    'missing child status field blocked' => [$missingChild, 'autoindexes.2.status', 'blocked'],
    'missing child root null' => [$missingChild, 'autoindexes.2.rootpage', null],
    'missing child fk parents still ok' => [$missingChild, 'current.foreign_key_parent_errors', 0],
    'collation mismatch status blocked' => [$collationMismatch, 'status', 'blocked'],
    'collation mismatch autoindex collation from catalog binary' => [$collationMismatch, 'autoindexes.1.collations.0', 'BINARY'],
    'collation mismatch autoindex blocked' => [$collationMismatch, 'autoindexes.1.status', 'blocked'],
    'collation mismatch autoindex error count' => [$collationMismatch, 'current.autoindex_errors', 1],
    'missing parent coverage status blocked' => [$missingParentCoverage, 'status', 'blocked'],
    'missing parent coverage fk error count' => [$missingParentCoverage, 'current.foreign_key_parent_errors', 1],
    'missing parent coverage final fk parent column' => [$missingParentCoverage, 'foreign_keys.3.parent_columns.0', 'source'],
    'missing parent coverage final fk status' => [$missingParentCoverage, 'foreign_keys.3.status', 'blocked'],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma autoindex foreignkey current next53 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma autoindex foreignkey current next53 supports tuple column form'] = static function (TestRunner $t) use ($records): void {
    $plan = SQLitePragmaAutoindexForeignKeyPreflight::plan($records(), [
        ['table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
            ['child' => 'option_name', 'parent' => 'name'],
        ]],
    ]);

    $t->same('ready', $plan['status']);
    $t->same('sqlite_autoindex_wp_option_names_1', $plan['foreign_keys'][0]['required_autoindex']);
};

$tests['pragma autoindex foreignkey current next53 rejects non schema records'] = static function (TestRunner $t) use ($foreignKeys): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaAutoindexForeignKeyPreflight::plan([['type' => 'table']], $foreignKeys));
};

$tests['pragma autoindex foreignkey current next53 rejects malformed foreign key columns'] = static function (TestRunner $t) use ($records): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaAutoindexForeignKeyPreflight::plan($records(), [
        ['table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => []],
    ]));
};

$tests['pragma autoindex foreignkey current next53 rejects malformed child table'] = static function (TestRunner $t) use ($records): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaAutoindexForeignKeyPreflight::plan($records(), [
        ['table' => 'bad-name', 'parent' => 'wp_option_names', 'columns' => ['option_name' => 'name']],
    ]));
};

return $tests;
