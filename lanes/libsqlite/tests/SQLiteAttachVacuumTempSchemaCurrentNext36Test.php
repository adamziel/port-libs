<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachVacuumTempSchemaPlan;
use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePragmaSnapshot;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$makeDatabase = static function (int $pageSize = 1024, int $pageCount = 3, string $autoVacuum = 'none'): SQLiteDatabase {
    $first = str_repeat("\0", $pageSize);
    $first = substr_replace($first, "SQLite format 3\0", 0, 16);
    $first = substr_replace($first, pack('n', $pageSize === 65536 ? 1 : $pageSize), 16, 2);
    $first[18] = "\x01";
    $first[19] = "\x01";
    $first[20] = "\x00";
    $first[21] = "\x40";
    $first[22] = "\x20";
    $first[23] = "\x20";
    $first = substr_replace($first, pack('N', $pageCount), 28, 4);
    $first = substr_replace($first, pack('N', 1), 40, 4);
    $first = substr_replace($first, pack('N', 1), 56, 4);
    if ($autoVacuum !== 'none') {
        $first = substr_replace($first, pack('N', min($pageCount, 3)), 52, 4);
        $first = substr_replace($first, pack('N', $autoVacuum === 'incremental' ? 1 : 0), 64, 4);
    }

    $pages = [$first];
    for ($page = 2; $page <= $pageCount; $page++) {
        $pages[] = str_pad("wp-options-{$autoVacuum}-{$page};", $pageSize, chr(64 + $page));
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$record = static fn (string $type, string $name, string $table, ?int $root): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    'CREATE ' . strtoupper($type) . ' ' . $name,
    1,
);

$makeCatalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog(
        [$record('table', 'wp_options', 'wp_options', 2)],
        [$record('table', 'wp_options', 'wp_options', 4)],
    );
    $catalog->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", static fn (): array => [
        $record('table', 'wp_sitemeta', 'wp_sitemeta', 8),
    ]);
    $catalog->executeAttachDetachSql("ATTACH '/srv/archive.sqlite' AS archive", static fn (): array => [
        $record('table', 'wp_options_archive', 'wp_options_archive', 12),
    ]);

    return $catalog;
};

$makeDatabases = static fn () => [
    'main' => $makeDatabase(1024, 3, 'none'),
    'site' => $makeDatabase(2048, 4, 'full'),
    'archive' => $makeDatabase(1024, 5, 'incremental'),
];

$plan = static fn (string $sql, int|string|null $pageSize = null, int|string|null $autoVacuum = null): array =>
    SQLiteAttachVacuumTempSchemaPlan::planSql($sql, $makeCatalog(), $makeDatabases(), $pageSize, $autoVacuum);

$tests = [];

$cases = [
    'bare vacuum targets main' => [static fn () => $plan('VACUUM')['schema'], 'main'],
    'bare vacuum source file is main null' => [static fn () => $plan('VACUUM')['source_file'], null],
    'bare vacuum preserves temp schema flag' => [static fn () => $plan('VACUUM')['temp_schema_preserved'], true],
    'bare vacuum does not invalidate schema cache' => [static fn () => $plan('VACUUM')['cache_invalidated'], false],
    'bare vacuum keeps attach generation' => [static fn () => $plan('VACUUM')['schema_generation'], 2],
    'bare vacuum reports database list heads' => [static fn () => array_column(array_slice($plan('VACUUM')['database_list'], 0, 2), 'name'), ['main', 'temp']],
    'bare vacuum keeps attached database list tail' => [static fn () => array_column(array_slice($plan('VACUUM')['database_list'], 2), 'name'), ['site', 'archive']],
    'explicit main targets main' => [static fn () => $plan('VACUUM main')['schema'], 'main'],
    'double quoted main targets main' => [static fn () => $plan('VACUUM "main"')['schema'], 'main'],
    'bracket quoted main targets main' => [static fn () => $plan('VACUUM [main]')['schema'], 'main'],
    'attached site targets site' => [static fn () => $plan('VACUUM site')['schema'], 'site'],
    'attached site source file comes from database list' => [static fn () => $plan('VACUUM site')['source_file'], '/srv/site.sqlite'],
    'quoted attached site targets site' => [static fn () => $plan('VACUUM "Site"')['schema'], 'site'],
    'backtick attached archive targets archive' => [static fn () => $plan('VACUUM `Archive`')['schema'], 'archive'],
    'attached archive source file comes from database list' => [static fn () => $plan('VACUUM archive')['source_file'], '/srv/archive.sqlite'],
    'attached site source page size is isolated' => [static fn () => $plan('VACUUM site')['source_page_size'], 2048],
    'attached archive source page count is isolated' => [static fn () => $plan('VACUUM archive')['source_page_count'], 5],
    'main source page count is isolated from attachments' => [static fn () => $plan('VACUUM main')['source_page_count'], 3],
    'attached site preserves full auto vacuum' => [static fn () => $plan('VACUUM site')['source_auto_vacuum'], 'full'],
    'attached archive preserves incremental auto vacuum' => [static fn () => $plan('VACUUM archive')['source_auto_vacuum'], 'incremental'],
    'main preserves none auto vacuum' => [static fn () => $plan('VACUUM main')['source_auto_vacuum'], 'none'],
    'page size rewrite applies to attached site only' => [static fn () => $plan('VACUUM site', 4096)['target_page_size'], 4096],
    'auto vacuum rewrite applies to attached archive' => [static fn () => $plan('VACUUM archive', null, 'none')['target_auto_vacuum'], 'none'],
    'in place vacuum has no target path' => [static fn () => $plan('VACUUM site')['target_path'], null],
    'in place vacuum first operation replaces schema image' => [static fn () => $plan('VACUUM site')['operations'][0]['op'], 'replace_schema_database_image'],
    'in place vacuum operation names attached schema' => [static fn () => $plan('VACUUM site')['operations'][0]['schema'], 'site'],
    'in place vacuum operation names attached file' => [static fn () => $plan('VACUUM site')['operations'][0]['file'], '/srv/site.sqlite'],
    'in place vacuum operation tracks byte length' => [static fn () => $plan('VACUUM archive')['operations'][0]['bytes'], 5120],
    'in place vacuum includes header rewrite operation' => [static fn () => $plan('VACUUM site')['operations'][1]['op'], 'rewrite_header'],
    'in place vacuum includes image rewrite operation' => [static fn () => $plan('VACUUM site')['operations'][2]['op'], 'rewrite_database_image'],
    'page size change appends vacuum dependency operation' => [static fn () => $plan('VACUUM site', 4096)['operations'][3]['op'], 'page_size_change_requires_vacuum'],
    'dependencies include attached schema vacuum' => [static fn () => in_array('sqlite-vacuum-attached-schema', $plan('VACUUM site')['dependencies'], true), true],
    'dependencies include temp preservation' => [static fn () => in_array('sqlite-temp-schema-preservation', $plan('VACUUM site')['dependencies'], true), true],
    'vacuum into bare targets main' => [static fn () => $plan("VACUUM INTO '/tmp/main-vac.sqlite'")['schema'], 'main'],
    'vacuum into records target path' => [static fn () => $plan("VACUUM INTO '/tmp/main-vac.sqlite'")['target_path'], '/tmp/main-vac.sqlite'],
    'vacuum attached into records target path' => [static fn () => $plan("VACUUM site INTO '/tmp/site-vac.sqlite'")['target_path'], '/tmp/site-vac.sqlite'],
    'vacuum attached into keeps source file' => [static fn () => $plan("VACUUM site INTO '/tmp/site-vac.sqlite'")['source_file'], '/srv/site.sqlite'],
    'vacuum into first operation writes target image' => [static fn () => $plan("VACUUM site INTO '/tmp/site-vac.sqlite'")['operations'][0]['op'], 'write'],
    'vacuum into second operation syncs target image' => [static fn () => $plan("VACUUM site INTO '/tmp/site-vac.sqlite'")['operations'][1]['op'], 'sync'],
    'vacuum into third operation syncs directory' => [static fn () => $plan("VACUUM site INTO '/tmp/site-vac.sqlite'")['operations'][2]['op'], 'sync_directory'],
    'vacuum into fourth operation records rewrite' => [static fn () => $plan("VACUUM site INTO '/tmp/site-vac.sqlite'")['operations'][3]['op'], 'vacuum_rewrite'],
    'vacuum into double quoted target unquotes' => [static fn () => $plan('VACUUM site INTO "/tmp/site""copy.sqlite"')['target_path'], '/tmp/site"copy.sqlite'],
    'vacuum into bare path target is accepted' => [static fn () => $plan('VACUUM archive INTO /tmp/archive-copy.sqlite')['target_path'], '/tmp/archive-copy.sqlite'],
    'vacuum into can rewrite page size' => [static fn () => $plan("VACUUM archive INTO '/tmp/archive.sqlite'", 2048)['target_page_size'], 2048],
    'vacuum into can rewrite auto vacuum' => [static fn () => $plan("VACUUM main INTO '/tmp/main.sqlite'", null, 'full')['target_auto_vacuum'], 'full'],
    'vacuum into preserves schema generation' => [static fn () => $plan("VACUUM site INTO '/tmp/site.sqlite'")['schema_generation'], 2],
    'vacuum into does not invalidate schema cache' => [static fn () => $plan("VACUUM site INTO '/tmp/site.sqlite'")['cache_invalidated'], false],
    'vacuum into keeps temp schema preservation flag' => [static fn () => $plan("VACUUM site INTO '/tmp/site.sqlite'")['temp_schema_preserved'], true],
    'vacuum into target page count follows rewrite' => [static fn () => $plan("VACUUM main INTO '/tmp/main.sqlite'", 2048)['target_page_count'], 2],
    'vacuum in place target page count follows rewrite' => [static fn () => $plan('VACUUM main', 2048)['target_page_count'], 2],
    'vacuum in place rewritten snapshot sees page size' => [static function () use ($makeCatalog, $makeDatabases): int {
        $plan = SQLiteAttachVacuumTempSchemaPlan::planSql('VACUUM site', $makeCatalog(), $makeDatabases(), 4096);
        $bytes = str_repeat("\0", $plan['target_page_size'] * $plan['target_page_count']);
        $bytes = substr_replace($bytes, "SQLite format 3\0", 0, 16);
        $bytes = substr_replace($bytes, pack('n', 4096), 16, 2);
        $bytes = substr_replace($bytes, pack('N', $plan['target_page_count']), 28, 4);

        return SQLitePragmaSnapshot::fromDatabase(SQLiteDatabase::fromBytes($bytes))->value('page_size');
    }, 4096],
    'database list still exposes temp after vacuum' => [static fn () => $plan('VACUUM site')['database_list'][1]['name'], 'temp'],
    'database list temp file remains empty string' => [static fn () => $plan('VACUUM site')['database_list'][1]['file'], ''],
    'attached order remains stable after vacuum' => [static fn () => array_column($plan('VACUUM site')['database_list'], 'name'), ['main', 'temp', 'site', 'archive']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['attach vacuum temp schema current next36 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$errorCases = [
    'rejects temp schema' => static fn () => $plan('VACUUM temp'),
    'rejects quoted temp schema' => static fn () => $plan('VACUUM "temp"'),
    'rejects missing attached schema' => static fn () => $plan('VACUUM network'),
    'rejects missing database image for attached schema' => static fn () => SQLiteAttachVacuumTempSchemaPlan::planSql('VACUUM site', $makeCatalog(), ['main' => $makeDatabase()]),
    'rejects empty vacuum into target' => static fn () => $plan("VACUUM site INTO ''"),
    'rejects unbounded vacuum into expression' => static fn () => $plan('VACUUM site INTO concat("/tmp/", "x")'),
    'rejects non vacuum SQL' => static fn () => $plan('ANALYZE site'),
    'rejects malformed schema into split' => static fn () => $plan('VACUUM site into-name'),
];

foreach ($errorCases as $name => $callback) {
    $tests['attach vacuum temp schema current next36 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

return $tests;
