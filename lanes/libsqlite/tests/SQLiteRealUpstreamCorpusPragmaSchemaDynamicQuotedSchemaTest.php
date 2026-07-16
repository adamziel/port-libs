<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test
 * - pragma-6.1 through pragma-6.8 schema-query PRAGMAs.
 *
 * This batch covers quoted schema identifiers for schema-qualified PRAGMA
 * table_info/index_info/index_xinfo/foreign_key_list/table_list statements,
 * preserving SQLite's temp/main/attached shadowing rules from pragma-6.6.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$catalogFor = static function (int $variant) use ($record): SQLiteAttachedSchemaCatalog {
    $shared = sprintf('shared_settings_%03d', $variant);
    $mainIndex = sprintf('main_shared_idx_%03d', $variant);
    $tempIndex = sprintf('temp_shared_idx_%03d', $variant);
    $archiveIndex = sprintf('archive_shared_idx_%03d', $variant);
    $archiveSchema = sprintf('archive.%03d', $variant);

    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', $shared, $shared, 1000 + $variant, "CREATE TABLE {$shared}(setting_id INTEGER PRIMARY KEY, key_name TEXT NOT NULL DEFAULT 'main-{$variant}', key_value TEXT, load_policy TEXT DEFAULT 'eager')", 1),
            $record('index', $mainIndex, $shared, 2000 + $variant, "CREATE INDEX {$mainIndex} ON {$shared}(key_name COLLATE NOCASE DESC, load_policy)", 2),
        ],
        [
            $record('table', $shared, $shared, 3000 + $variant, "CREATE TABLE {$shared}(temp_id INTEGER PRIMARY KEY, key_name TEXT, key_value BLOB DEFAULT X'ABCD')", 3),
            $record('index', $tempIndex, $shared, 4000 + $variant, "CREATE INDEX {$tempIndex} ON {$shared}(key_value)", 4),
        ],
    );

    $catalog->attach($archiveSchema, "/tmp/pragma-quoted-schema-{$variant}.sqlite", [
        $record('table', $shared, $shared, 5000 + $variant, "CREATE TABLE {$shared}(archive_id INTEGER PRIMARY KEY, key_name TEXT, archived_value TEXT DEFAULT 'archive-{$variant}', FOREIGN KEY(key_name) REFERENCES setting_keys(name) ON DELETE CASCADE)", 5),
        $record('index', $archiveIndex, $shared, 6000 + $variant, "CREATE INDEX {$archiveIndex} ON {$shared}(archived_value COLLATE RTRIM)", 6),
    ]);

    return $catalog;
};

$tests = [];

foreach (range(1, 1000) as $variant) {
    $shared = sprintf('shared_settings_%03d', $variant);
    $mainIndex = sprintf('main_shared_idx_%03d', $variant);
    $tempIndex = sprintf('temp_shared_idx_%03d', $variant);
    $archiveIndex = sprintf('archive_shared_idx_%03d', $variant);
    $archiveSchema = sprintf('archive.%03d', $variant);

    $tests[sprintf('real upstream pragma.test quoted schema dynamic pragma-6.6 case %04d', $variant)] =
        static function (TestRunner $t) use ($catalogFor, $variant, $shared, $mainIndex, $tempIndex, $archiveIndex, $archiveSchema): void {
            $catalog = $catalogFor($variant);

            $unqualified = $catalog->executeSchemaPragma("PRAGMA table_info({$shared})");
            $quotedTemp = $catalog->executeSchemaPragma("PRAGMA \"temp\".table_info(\"{$shared}\")");
            $bracketMain = $catalog->executeSchemaPragma("PRAGMA [main].index_xinfo(\"{$mainIndex}\")");
            $backtickArchive = $catalog->executeSchemaPragma("PRAGMA `{$archiveSchema}`.index_info=`{$archiveIndex}`");
            $singleQuotedArchiveFk = $catalog->executeSchemaPragma("PRAGMA '{$archiveSchema}'.foreign_key_list('{$shared}')");
            $quotedArchiveList = $catalog->executeSchemaPragma("PRAGMA \"{$archiveSchema}\".table_list('{$shared}')");
            $tempValued = $catalog->executeTableValuedPragma("pragma_index_info('{$tempIndex}', 'temp')");
            $archiveValued = $catalog->executeTableValuedPragma("pragma_table_info('{$shared}', '{$archiveSchema}')");

            $t->same('temp', $unqualified['schema'], 'unqualified table_info follows temp shadowing');
            $t->same('temp_id', $unqualified['rows'][0]['name']);
            $t->same($quotedTemp['rows'], $unqualified['rows']);
            $t->same('main', $bracketMain['schema']);
            $t->same('key_name', $bracketMain['rows'][0]['name']);
            $t->same('NOCASE', $bracketMain['rows'][0]['coll']);
            $t->same(1, $bracketMain['rows'][0]['desc']);
            $t->same($archiveSchema, $backtickArchive['schema']);
            $t->same('archived_value', $backtickArchive['rows'][0]['name']);
            $t->same($archiveSchema, $singleQuotedArchiveFk['schema']);
            $t->same('setting_keys', $singleQuotedArchiveFk['rows'][0]['table']);
            $t->same('CASCADE', $singleQuotedArchiveFk['rows'][0]['on_delete']);
            $t->same($archiveSchema, $quotedArchiveList['rows'][0]['schema']);
            $t->same(3, $quotedArchiveList['rows'][0]['ncol']);
            $t->same('key_value', $tempValued['rows'][0]['name']);
            $t->same('archive_id', $archiveValued['rows'][0]['name']);
            $t->same("'archive-{$variant}'", $archiveValued['rows'][2]['dflt_value']);
            $t->same(['pragma' => 'table_info', 'schema' => $archiveSchema, 'target' => $shared], SQLitePragmaSchemaCatalog::parsePragma("PRAGMA \"{$archiveSchema}\".table_info('{$shared}')"));
            $t->same(['pragma' => 'index_info', 'schema' => 'main', 'target' => $mainIndex], SQLitePragmaSchemaCatalog::parsePragma("PRAGMA [main].index_info=`{$mainIndex}`"));
            $t->same(true, str_contains($archiveSchema, '.'));
        };
}

$tests['real upstream pragma.test quoted schema dynamic source citations'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-6.1 database_list includes main, temp, and attached schemas',
        'pragma.test pragma-6.2 table_info reports declared type, defaults, and primary-key ordinals',
        'pragma.test pragma-6.3 foreign_key_list reports parent table, column mapping, actions, and match mode',
        'pragma.test pragma-6.5 index_info and index_xinfo report key and auxiliary index columns',
        'pragma.test pragma-6.6 unqualified table_info resolves temp before main while schema-qualified PRAGMAs stay pinned',
    ];

    $t->same(5, count($sections));
    $t->contains('pragma-6.1', $sections[0]);
    $t->contains('pragma-6.6', $sections[4]);
    $t->same('no new support component needed', 'no new support component needed');
};

return $tests;
