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

$currentSource = 'cff6baf6d405b3aa7ae1f6fca752e50506b70f0e';
$nextSource = 'pragma-integrity-autoindex-fk-current-source-next92';

$recordsBySchema = static function (string $variant = 'clean') use ($record): array {
    $tempParentRoot = $variant === 'temp-parent-root-missing' ? null : 6;
    $archiveChildRoot = $variant === 'archive-child-index-missing' ? null : 13;
    $networkParentSql = $variant === 'network-collation-mismatch'
        ? 'CREATE TABLE wp_blogmeta(blog_id INTEGER, meta_key TEXT COLLATE NOCASE, PRIMARY KEY(blog_id, meta_key)) WITHOUT ROWID'
        : 'CREATE TABLE wp_blogmeta(blog_id INTEGER, meta_key TEXT, PRIMARY KEY(blog_id, meta_key)) WITHOUT ROWID';
    $networkParentIndexSql = $variant === 'network-collation-mismatch'
        ? 'CREATE UNIQUE INDEX sqlite_autoindex_wp_blogmeta_1 ON wp_blogmeta(blog_id, meta_key COLLATE BINARY)'
        : null;

    return [
        'temp' => [
            $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT UNIQUE)', 1),
            $record('index', 'sqlite_autoindex_wp_option_names_1', 'wp_option_names', $tempParentRoot, null, 2),
            $record('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT, UNIQUE(option_name, autoload), FOREIGN KEY(option_name) REFERENCES wp_option_names(name))', 3),
            $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 8, null, 4),
        ],
        'main' => [
            $record('table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_title TEXT)', 1),
            $record('table', 'wp_postmeta', 'wp_postmeta', 3, 'CREATE TABLE wp_postmeta(meta_id INTEGER PRIMARY KEY, post_id INTEGER REFERENCES wp_posts(ID), meta_key TEXT)', 2),
            $record('table', 'wp_terms', 'wp_terms', 4, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT UNIQUE)', 3),
            $record('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 9, null, 4),
        ],
        'archive' => [
            $record('table', 'wp_option_names', 'wp_option_names', 10, 'CREATE TABLE wp_option_names(name TEXT UNIQUE)', 1),
            $record('index', 'sqlite_autoindex_wp_option_names_1', 'wp_option_names', 11, null, 2),
            $record('table', 'wp_options', 'wp_options', 12, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, locale TEXT, UNIQUE(option_name, locale), FOREIGN KEY(option_name) REFERENCES wp_option_names(name))', 3),
            $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', $archiveChildRoot, null, 4),
        ],
        'network' => [
            $record('table', 'wp_blogmeta', 'wp_blogmeta', 14, $networkParentSql, 1),
            $record('index', 'sqlite_autoindex_wp_blogmeta_1', 'wp_blogmeta', 15, $networkParentIndexSql, 2),
            $record('table', 'wp_sitemeta', 'wp_sitemeta', 16, 'CREATE TABLE wp_sitemeta(meta_id INTEGER PRIMARY KEY, blog_id INTEGER, meta_key TEXT, FOREIGN KEY(blog_id, meta_key) REFERENCES wp_blogmeta(blog_id, meta_key))', 3),
        ],
    ];
};

$foreignKeysBySchema = [
    'temp' => [
        ['table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => ['option_name' => 'name']],
    ],
    'main' => [
        ['table' => 'wp_postmeta', 'parent' => 'wp_posts', 'columns' => ['post_id' => 'ID']],
    ],
    'archive' => [
        ['table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => ['option_name' => 'name']],
    ],
    'network' => [
        ['table' => 'wp_sitemeta', 'parent' => 'wp_blogmeta', 'columns' => [
            ['child' => 'blog_id', 'parent' => 'blog_id'],
            ['child' => 'meta_key', 'parent' => 'meta_key'],
        ]],
    ],
];

$plan = static fn (string $variant = 'clean'): array => SQLitePragmaAutoindexForeignKeyPreflight::planCurrentSource(
    $recordsBySchema($variant),
    $foreignKeysBySchema,
    $currentSource,
    $nextSource,
);
$clean = static fn (): array => $plan();
$tempMissing = static fn (): array => $plan('temp-parent-root-missing');
$archiveMissing = static fn (): array => $plan('archive-child-index-missing');
$networkMismatch = static fn (): array => $plan('network-collation-mismatch');

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
    'clean schema count' => [$clean, 'schemas.count', 4],
    'clean temp first' => [$clean, 'schemas.0', 'temp'],
    'clean main second' => [$clean, 'schemas.1', 'main'],
    'clean archive third' => [$clean, 'schemas.2', 'archive'],
    'clean network fourth' => [$clean, 'schemas.3', 'network'],
    'clean current source' => [$clean, 'current.source', $currentSource],
    'clean next source' => [$clean, 'next.source', $nextSource],
    'clean next ready' => [$clean, 'next.ready', true],
    'clean blockers empty' => [$clean, 'next.blocking.count', 0],
    'clean autoindex count' => [$clean, 'autoindexes.count', 6],
    'clean temp parent schema' => [$clean, 'autoindexes.0.schema', 'temp'],
    'clean temp parent name' => [$clean, 'autoindexes.0.name', 'sqlite_autoindex_wp_option_names_1'],
    'clean temp parent root' => [$clean, 'autoindexes.0.rootpage', 6],
    'clean temp parent column' => [$clean, 'autoindexes.0.columns.0', 'name'],
    'clean temp child schema' => [$clean, 'autoindexes.1.schema', 'temp'],
    'clean temp child composite first column' => [$clean, 'autoindexes.1.columns.0', 'option_name'],
    'clean temp child composite second column' => [$clean, 'autoindexes.1.columns.1', 'autoload'],
    'clean main terms schema' => [$clean, 'autoindexes.2.schema', 'main'],
    'clean main terms name' => [$clean, 'autoindexes.2.name', 'sqlite_autoindex_wp_terms_1'],
    'clean archive parent schema' => [$clean, 'autoindexes.3.schema', 'archive'],
    'clean archive child schema' => [$clean, 'autoindexes.4.schema', 'archive'],
    'clean network parent schema' => [$clean, 'autoindexes.5.schema', 'network'],
    'clean network parent first column' => [$clean, 'autoindexes.5.columns.0', 'blog_id'],
    'clean network parent second column' => [$clean, 'autoindexes.5.columns.1', 'meta_key'],
    'clean all autoindexes ok' => [$clean, 'current.autoindex_errors', 0],
    'clean all fk parents ok' => [$clean, 'current.foreign_key_parent_errors', 0],
    'clean fk count' => [$clean, 'foreign_keys.count', 4],
    'clean temp fk schema' => [$clean, 'foreign_keys.0.schema', 'temp'],
    'clean temp fk required autoindex' => [$clean, 'foreign_keys.0.required_autoindex', 'sqlite_autoindex_wp_option_names_1'],
    'clean temp fk status' => [$clean, 'foreign_keys.0.status', 'ok'],
    'clean main rowid alias schema' => [$clean, 'foreign_keys.1.schema', 'main'],
    'clean main rowid alias required autoindex null' => [$clean, 'foreign_keys.1.required_autoindex', null],
    'clean main rowid alias status' => [$clean, 'foreign_keys.1.status', 'ok'],
    'clean archive fk schema' => [$clean, 'foreign_keys.2.schema', 'archive'],
    'clean archive fk required autoindex' => [$clean, 'foreign_keys.2.required_autoindex', 'sqlite_autoindex_wp_option_names_1'],
    'clean network fk schema' => [$clean, 'foreign_keys.3.schema', 'network'],
    'clean network fk required autoindex' => [$clean, 'foreign_keys.3.required_autoindex', 'sqlite_autoindex_wp_blogmeta_1'],
    'temp missing status blocked' => [$tempMissing, 'status', 'blocked'],
    'temp missing autoindex errors' => [$tempMissing, 'current.autoindex_errors', 1],
    'temp missing fk parent errors' => [$tempMissing, 'current.foreign_key_parent_errors', 1],
    'temp missing next not ready' => [$tempMissing, 'next.ready', false],
    'temp missing first blocker current source' => [$tempMissing, 'next.blocking.0', 'autoindex_catalog_current_source'],
    'temp missing second blocker current source' => [$tempMissing, 'next.blocking.1', 'foreign_key_parent_autoindex_current_source'],
    'temp missing autoindex schema retained' => [$tempMissing, 'autoindexes.0.schema', 'temp'],
    'temp missing autoindex blocked' => [$tempMissing, 'autoindexes.0.status', 'blocked'],
    'temp missing fk blocked' => [$tempMissing, 'foreign_keys.0.status', 'blocked'],
    'archive child missing status blocked' => [$archiveMissing, 'status', 'blocked'],
    'archive child missing autoindex errors one' => [$archiveMissing, 'current.autoindex_errors', 1],
    'archive child missing fk parent errors zero' => [$archiveMissing, 'current.foreign_key_parent_errors', 0],
    'archive child missing only one blocker' => [$archiveMissing, 'next.blocking.count', 1],
    'archive child missing blocker' => [$archiveMissing, 'next.blocking.0', 'autoindex_catalog_current_source'],
    'archive child missing schema retained' => [$archiveMissing, 'autoindexes.4.schema', 'archive'],
    'archive child missing root null' => [$archiveMissing, 'autoindexes.4.rootpage', null],
    'network mismatch status blocked' => [$networkMismatch, 'status', 'blocked'],
    'network mismatch autoindex errors one' => [$networkMismatch, 'current.autoindex_errors', 1],
    'network mismatch fk parent errors one' => [$networkMismatch, 'current.foreign_key_parent_errors', 1],
    'network mismatch autoindex schema retained' => [$networkMismatch, 'autoindexes.5.schema', 'network'],
    'network mismatch actual collation binary' => [$networkMismatch, 'autoindexes.5.collations.1', 'BINARY'],
    'network mismatch status field blocked' => [$networkMismatch, 'autoindexes.5.status', 'blocked'],
    'network mismatch fk blocked' => [$networkMismatch, 'foreign_keys.3.status', 'blocked'],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma integrity autoindex fk current source next92 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma integrity autoindex fk current source next92 skips absent empty schema'] = static function (TestRunner $t) use ($recordsBySchema, $foreignKeysBySchema, $currentSource, $nextSource): void {
    $records = $recordsBySchema();
    $records['scratch'] = [];
    $foreignKeys = $foreignKeysBySchema;
    $foreignKeys['scratch'] = [];

    $plan = SQLitePragmaAutoindexForeignKeyPreflight::planCurrentSource($records, $foreignKeys, $currentSource, $nextSource);

    $t->same(false, in_array('scratch', $plan['schemas'], true));
};

$tests['pragma integrity autoindex fk current source next92 includes foreign key only schema'] = static function (TestRunner $t) use ($recordsBySchema, $foreignKeysBySchema, $currentSource, $nextSource): void {
    $foreignKeys = $foreignKeysBySchema;
    $foreignKeys['broken'] = [
        ['table' => 'wp_orphan', 'parent' => 'wp_missing_parent', 'columns' => ['parent_id' => 'id']],
    ];

    $plan = SQLitePragmaAutoindexForeignKeyPreflight::planCurrentSource($recordsBySchema(), $foreignKeys, $currentSource, $nextSource);

    $t->same('broken', $plan['schemas'][4]);
    $t->same('broken', $plan['foreign_keys'][4]['schema']);
    $t->same('blocked', $plan['foreign_keys'][4]['status']);
};

$tests['pragma integrity autoindex fk current source next92 rejects missing current source'] = static function (TestRunner $t) use ($recordsBySchema, $foreignKeysBySchema, $nextSource): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaAutoindexForeignKeyPreflight::planCurrentSource($recordsBySchema(), $foreignKeysBySchema, '', $nextSource));
};

$tests['pragma integrity autoindex fk current source next92 rejects missing next source'] = static function (TestRunner $t) use ($recordsBySchema, $foreignKeysBySchema, $currentSource): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaAutoindexForeignKeyPreflight::planCurrentSource($recordsBySchema(), $foreignKeysBySchema, $currentSource, ''));
};

return $tests;
