<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachUriSchemaCache;
use PortLibs\LibSqlite\SQLiteAttachWalTempCachePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    1,
);

$makeCatalog = static fn (): SQLiteAttachedSchemaCatalog => new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT)'),
        $record('index', 'wp_options_name', 'wp_options', 3, 'CREATE INDEX wp_options_name ON wp_options(option_name)'),
        $record('table', 'wp_posts', 'wp_posts', 4, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_title TEXT)'),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 20, 'CREATE TEMP TABLE wp_options(option_name TEXT, option_value TEXT)'),
        $record('index', 'wp_options_temp_name', 'wp_options', 21, 'CREATE INDEX wp_options_temp_name ON wp_options(option_name)'),
        $record('table', 'wp_import_stage', 'wp_import_stage', 22, 'CREATE TEMP TABLE wp_import_stage(option_name TEXT, option_value TEXT)'),
    ],
);

$currentArchiveRecords = static fn (): array => [
    $record('table', 'wp_options', 'wp_options', 40, 'CREATE TABLE wp_options(option_name TEXT, archived_at TEXT)'),
    $record('index', 'wp_options_archive_name', 'wp_options', 41, 'CREATE INDEX wp_options_archive_name ON wp_options(option_name)'),
    $record('table', 'wp_comments', 'wp_comments', 42, 'CREATE TABLE wp_comments(comment_ID INTEGER PRIMARY KEY, comment_post_ID INTEGER)'),
];

$nextArchiveRecords = static fn (): array => [
    $record('table', 'wp_options', 'wp_options', 70, 'CREATE TABLE wp_options(option_name TEXT, archived_at TEXT, wal_commit INTEGER)'),
    $record('index', 'wp_options_archive_name', 'wp_options', 71, 'CREATE INDEX wp_options_archive_name ON wp_options(option_name, archived_at)'),
    $record('table', 'wp_comments', 'wp_comments', 72, 'CREATE TABLE wp_comments(comment_ID INTEGER PRIMARY KEY, comment_post_ID INTEGER, wal_commit INTEGER)'),
    $record('table', 'wp_terms', 'wp_terms', 73, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT)'),
    $record('index', 'wp_terms_slug', 'wp_terms', 74, 'CREATE INDEX wp_terms_slug ON wp_terms(slug)'),
];

$plan = static function () use ($makeCatalog, $currentArchiveRecords, $nextArchiveRecords): array {
    $loads = 0;
    $loader = static function (string $file, string $schema) use (&$loads, $currentArchiveRecords): array {
        ++$loads;

        return $currentArchiveRecords();
    };

    $result = SQLiteAttachWalTempCachePlan::currentNext(
        $makeCatalog(),
        new SQLiteAttachUriSchemaCache(),
        "ATTACH 'file:/srv/wp/archive.sqlite?mode=rw&cache=shared&vfs=unix-dotfile' AS archive",
        $loader,
        $nextArchiveRecords(),
        12,
        13,
        ['wp_options', 'main.wp_options', 'archive.wp_options', 'wp_comments', 'wp_terms', 'wp_import_stage', 'sqlite_schema'],
        ['wp_options_name', 'wp_options_archive_name', 'wp_terms_slug', 'wp_options_temp_name'],
    );
    $result['loader_count'] = $loads;

    return $result;
};

$valueAt = static function (array $value, string $path): mixed {
    $current = $value;
    $parts = explode('.', $path);
    for ($i = 0; $i < count($parts); $i++) {
        $part = $parts[$i];
        if (is_array($current) && array_key_exists($part, $current)) {
            $current = $current[$part];
            continue;
        }
        if (is_array($current) && isset($parts[$i + 1]) && array_key_exists($part . '.' . $parts[$i + 1], $current)) {
            $current = $current[$part . '.' . $parts[++$i]];
            continue;
        }

        return null;
    }

    return $current;
};

$cases = [
    'status planned' => ['status', 'planned'],
    'operation named' => ['operation', 'attach-wal-temp-cache-current-next'],
    'schema archive' => ['schema', 'archive'],
    'file decoded' => ['file', '/srv/wp/archive.sqlite'],
    'current cookie captured' => ['current_schema_cookie', 12],
    'next cookie captured' => ['next_schema_cookie', 13],
    'cookie changed' => ['cookie_changed', true],
    'attach event stores shared cache' => ['attach.cache_event', 'shared_schema_cache_store'],
    'attach is cacheable' => ['attach.cacheable', true],
    'attach loader called' => ['attach.loader_called', true],
    'loader called once for current attach' => ['loader_count', 1],
    'attach record count current' => ['attach.record_count', 3],
    'attach URI mode rw' => ['attach.uri.mode', 'rw'],
    'attach URI cache shared' => ['attach.uri.cache', 'shared'],
    'attach URI vfs preserved' => ['attach.uri.vfs', 'unix-dotfile'],
    'attach next requires reload' => ['attach.next.requires_reload', true],
    'attach next does not reuse current cookie' => ['attach.next.reuse_current', false],
    'current source archive' => ['current_snapshot.source', 'archive'],
    'current search order temp first' => ['current_snapshot.search_order.0', 'temp'],
    'current search order archive third' => ['current_snapshot.search_order.2', 'archive'],
    'current temp shadows wp_options' => ['current_snapshot.tables.wp_options.schema', 'temp'],
    'current temp wp_options root' => ['current_snapshot.tables.wp_options.rootpage', 20],
    'current explicit main root' => ['current_snapshot.tables.main.wp_options.rootpage', 2],
    'current explicit archive root' => ['current_snapshot.tables.archive.wp_options.rootpage', 40],
    'current comments root' => ['current_snapshot.tables.wp_comments.rootpage', 42],
    'current missing terms before WAL schema commit' => ['current_snapshot.tables.wp_terms.schema', null],
    'current temp import stage visible' => ['current_snapshot.tables.wp_import_stage.schema', 'temp'],
    'current sqlite_schema remains main' => ['current_snapshot.tables.sqlite_schema.schema', 'main'],
    'current archive index root' => ['current_snapshot.indexes.wp_options_archive_name.rootpage', 41],
    'current missing terms slug index' => ['current_snapshot.indexes.wp_terms_slug.schema', null],
    'invalidation stale' => ['invalidation.stale', true],
    'invalidation current false after replace' => ['invalidation.current', false],
    'invalidation before generation one' => ['invalidation.before_generation', 1],
    'invalidation after generation two' => ['invalidation.after_generation', 2],
    'invalidation database count unchanged' => ['invalidation.after_database_count', 3],
    'invalidation sequence unchanged' => ['invalidation.sequence_changed', false],
    'temp shadow table preserved' => ['invalidation.table_changes.wp_options.changed', false],
    'temp shadow before temp' => ['invalidation.table_changes.wp_options.before.schema', 'temp'],
    'temp shadow after temp' => ['invalidation.table_changes.wp_options.after.schema', 'temp'],
    'main explicit table unchanged' => ['invalidation.table_changes.main.wp_options.changed', false],
    'archive explicit table changed' => ['invalidation.table_changes.archive.wp_options.changed', true],
    'archive explicit before root' => ['invalidation.table_changes.archive.wp_options.before.rootpage', 40],
    'archive explicit after root' => ['invalidation.table_changes.archive.wp_options.after.rootpage', 70],
    'comments table changed' => ['invalidation.table_changes.wp_comments.changed', true],
    'comments after root' => ['invalidation.table_changes.wp_comments.after.rootpage', 72],
    'terms table appears after WAL schema commit' => ['invalidation.table_changes.wp_terms.after.schema', 'archive'],
    'terms table after root' => ['invalidation.table_changes.wp_terms.after.rootpage', 73],
    'temp stage unchanged' => ['invalidation.table_changes.wp_import_stage.changed', false],
    'sqlite schema unchanged' => ['invalidation.table_changes.sqlite_schema.changed', false],
    'archive index changed' => ['invalidation.index_changes.wp_options_archive_name.changed', true],
    'archive index after root' => ['invalidation.index_changes.wp_options_archive_name.after.rootpage', 71],
    'terms slug index appears' => ['invalidation.index_changes.wp_terms_slug.after.schema', 'archive'],
    'terms slug index root' => ['invalidation.index_changes.wp_terms_slug.after.rootpage', 74],
    'temp index unchanged' => ['invalidation.index_changes.wp_options_temp_name.changed', false],
    'changed tables list' => ['invalidation.changed_tables', ['archive.wp_options', 'wp_comments', 'wp_terms']],
    'unchanged tables list' => ['invalidation.unchanged_tables', ['wp_options', 'main.wp_options', 'wp_import_stage', 'sqlite_schema']],
    'changed indexes list' => ['invalidation.changed_indexes', ['wp_options_archive_name', 'wp_terms_slug']],
    'unchanged indexes list' => ['invalidation.unchanged_indexes', ['wp_options_name', 'wp_options_temp_name']],
    'next snapshot archive table root' => ['next_snapshot.tables.archive.wp_options.rootpage', 70],
    'next snapshot terms schema' => ['next_snapshot.tables.wp_terms.schema', 'archive'],
    'next snapshot temp shadow still wins' => ['next_snapshot.tables.wp_options.schema', 'temp'],
    'next attach reloads shared cache' => ['next_attach.cache_event', 'shared_schema_cache_store'],
    'next attach loader called' => ['next_attach.loader_called', true],
    'next attach record count' => ['next_attach.record_count', 5],
    'next attach schema is renamed probe' => ['next_attach.schema', 'archive_next'],
    'next attach reuses next cookie' => ['next_attach.next.reuse_current', true],
    'temp shadow table summary' => ['temp_shadow_tables', ['wp_options', 'wp_import_stage']],
    'attached changed tables summary' => ['attached_changed_tables', ['archive.wp_options', 'wp_comments', 'wp_terms']],
    'attached changed indexes summary' => ['attached_changed_indexes', ['wp_options_archive_name', 'wp_terms_slug']],
    'dependency marker first' => ['dependencies.0', 'sqlite-attach-wal-temp-cache-current-next44'],
    'dependency includes shared cookie' => ['dependencies.2', 'shared-cache-schema-cookie'],
];

$tests = [];

foreach ($cases as $name => [$path, $expected]) {
    $tests['attach wal temp cache current next44 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['attach wal temp cache current next44 same cookie keeps attach reusable'] = static function (TestRunner $t) use ($makeCatalog, $currentArchiveRecords, $nextArchiveRecords): void {
    $result = SQLiteAttachWalTempCachePlan::currentNext(
        $makeCatalog(),
        new SQLiteAttachUriSchemaCache(),
        "ATTACH 'file:/srv/wp/reuse.sqlite?mode=rw&cache=shared' AS archive",
        static fn (): array => $currentArchiveRecords(),
        $nextArchiveRecords(),
        15,
        15,
        ['archive.wp_options'],
    );

    $t->same(false, $result['cookie_changed']);
    $t->same(false, $result['attach']['next']['requires_reload']);
    $t->same(true, $result['attach']['next']['reuse_current']);
    $t->same(true, $result['invalidation']['stale']);
};

$tests['attach wal temp cache current next44 rejects invalid inputs'] = static function (TestRunner $t) use ($makeCatalog, $currentArchiveRecords, $nextArchiveRecords): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteAttachWalTempCachePlan::currentNext($makeCatalog(), new SQLiteAttachUriSchemaCache(), "ATTACH 'file:/srv/wp/private.sqlite?cache=private' AS archive", static fn (): array => $currentArchiveRecords(), $nextArchiveRecords(), 1, 2, ['archive.wp_options']));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteAttachWalTempCachePlan::currentNext($makeCatalog(), new SQLiteAttachUriSchemaCache(), "ATTACH 'file:/srv/wp/empty.sqlite?cache=shared' AS archive", static fn (): array => $currentArchiveRecords(), [], 1, 2, ['archive.wp_options']));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteAttachWalTempCachePlan::currentNext($makeCatalog(), new SQLiteAttachUriSchemaCache(), "ATTACH 'file:/srv/wp/lookups.sqlite?cache=shared' AS archive", static fn (): array => $currentArchiveRecords(), $nextArchiveRecords(), 1, 2, []));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteAttachWalTempCachePlan::currentNext($makeCatalog(), new SQLiteAttachUriSchemaCache(), "ATTACH 'file:/srv/wp/bad.sqlite?cache=shared' AS archive", static fn (): array => $currentArchiveRecords(), ['bad'], 1, 2, ['archive.wp_options']));
};

return $tests;
