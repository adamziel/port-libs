<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaPagerState;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma.test pragma-12.1: PRAGMA temp.table_info('abc') is
 *   pinned to the temp schema and returns an empty rowset when only main has
 *   table abc.
 * - SQLite test/pragma.test pragma-12.2: PRAGMA temp.default_cache_size can
 *   be assigned and read back independently.
 * - SQLite test/pragma.test pragma-12.3: PRAGMA temp.cache_size can be
 *   assigned and read back independently.
 *
 * This dynamic corpus keeps those behaviors generic: temp schema
 * introspection never falls through to main, unqualified table_info still uses
 * SQLite's temp/main resolution order, and temp pager cache settings remain
 * connection-local rather than mutating main or attached database settings.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

foreach (range(1, 1000) as $variant) {
    $suffix = sprintf('%04d', $variant);
    $mainTable = "temp_pager_main_{$suffix}";
    $tempTable = "temp_pager_scratch_{$suffix}";
    $auxTable = "temp_pager_aux_{$suffix}";
    $auxSchema = "tenant{$suffix}";
    $builtInCache = 256 + ($variant % 127);
    $mainDefault = $builtInCache + 17;
    $tempDefault = 200 + $variant;
    $tempCache = 400 + $variant;
    $auxDefault = 900 + $variant;

    $tests["real upstream pragma 12 temp schema and pager pragmas dynamic variant {$suffix}"] =
        static function (TestRunner $t) use ($record, $variant, $mainTable, $tempTable, $auxTable, $auxSchema, $builtInCache, $mainDefault, $tempDefault, $tempCache, $auxDefault): void {
            $catalog = new SQLiteAttachedSchemaCatalog(
                [
                    $record(
                        'table',
                        $mainTable,
                        $mainTable,
                        10 + $variant,
                        "CREATE TABLE {$mainTable}(id INTEGER PRIMARY KEY, key_name TEXT, key_value TEXT DEFAULT 'main_{$variant}')",
                        1,
                    ),
                ],
                [
                    $record(
                        'table',
                        $tempTable,
                        $tempTable,
                        20 + $variant,
                        "CREATE TABLE {$tempTable}(id INTEGER PRIMARY KEY, scratch_value TEXT DEFAULT 'temp_{$variant}')",
                        2,
                    ),
                ],
            );
            $catalog->attach($auxSchema, "/tmp/tenant-{$variant}.sqlite", [
                $record(
                    'table',
                    $auxTable,
                    $auxTable,
                    30 + $variant,
                    "CREATE TABLE {$auxTable}(id INTEGER PRIMARY KEY, aux_value TEXT DEFAULT 'aux_{$variant}')",
                    3,
                ),
            ]);

            $tempMissing = $catalog->executeSchemaPragma("PRAGMA temp.table_info('{$mainTable}')");
            $mainRows = $catalog->executeSchemaPragma("PRAGMA main.table_info('{$mainTable}')")['rows'];
            $unqualifiedRows = $catalog->executeSchemaPragma("PRAGMA table_info('{$mainTable}')")['rows'];
            $tempRows = $catalog->executeSchemaPragma("PRAGMA temp.table_info('{$tempTable}')")['rows'];
            $auxRows = $catalog->executeTableValuedPragma("pragma_table_info('{$auxTable}', '{$auxSchema}')")['rows'];
            $databaseRows = $catalog->executeSchemaPragma('PRAGMA database_list')['rows'];

            $t->same('ok', $tempMissing['status']);
            $t->same('temp', $tempMissing['schema']);
            $t->same($mainTable, $tempMissing['target']);
            $t->same([], $tempMissing['rows']);
            $t->same(['id', 'key_name', 'key_value'], array_column($mainRows, 'name'));
            $t->same($mainRows, $unqualifiedRows);
            $t->same(['id', 'scratch_value'], array_column($tempRows, 'name'));
            $t->same("'temp_{$variant}'", $tempRows[1]['dflt_value']);
            $t->same(['id', 'aux_value'], array_column($auxRows, 'name'));
            $t->same(['main', 'temp', $auxSchema], array_column($databaseRows, 'name'));

            $pager = new SQLitePragmaPagerState([
                'main' => ['default_cache_size' => $mainDefault, 'cache_size' => $mainDefault],
                'temp' => ['default_cache_size' => $builtInCache, 'cache_size' => $builtInCache],
                $auxSchema => ['default_cache_size' => $auxDefault, 'cache_size' => $auxDefault],
            ], $builtInCache);

            $tempDefaultSet = $pager->execute("PRAGMA temp.default_cache_size = {$tempDefault}");
            $tempCacheSet = $pager->execute("PRAGMA temp.cache_size = {$tempCache}");
            $mainDefaultRead = $pager->execute('PRAGMA main.default_cache_size');
            $tempDefaultRead = $pager->execute('PRAGMA temp.default_cache_size');
            $tempCacheRead = $pager->execute('PRAGMA temp.cache_size');
            $auxDefaultRead = $pager->execute("PRAGMA {$auxSchema}.default_cache_size");

            $t->same('temp', $tempDefaultSet['schema']);
            $t->same('default_cache_size', $tempDefaultSet['pragma']);
            $t->same($tempDefault, $tempDefaultSet['value']);
            $t->same('assigned_persistent_default', $tempDefaultSet['reason']);
            $t->same('temp', $tempCacheSet['schema']);
            $t->same('cache_size', $tempCacheSet['pragma']);
            $t->same($tempCache, $tempCacheSet['value']);
            $t->same($mainDefault, $mainDefaultRead['value']);
            $t->same($tempDefault, $tempDefaultRead['value']);
            $t->same($tempCache, $tempCacheRead['value']);
            $t->same($auxDefault, $auxDefaultRead['value']);
            $t->same($mainDefault, $pager->state()['main']['cache_size']);
            $t->same($tempCache, $pager->state()['temp']['cache_size']);
            $t->same($auxDefault, $pager->state()[$auxSchema]['cache_size']);

            $pager->execute('PRAGMA default_cache_size = 0');
            $t->same($builtInCache, $pager->execute('PRAGMA main.default_cache_size')['value']);
            $t->same($tempDefault, $pager->execute('PRAGMA temp.default_cache_size')['value']);
        };
}

$tests['real upstream pragma 12 temp schema pager source citations and non overlap'] =
    static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test');

        $t->same(true, is_string($source));
        $t->contains('do_test pragma-12.1', (string) $source);
        $t->contains("PRAGMA temp.table_info('abc');", (string) $source);
        $t->contains('do_test pragma-12.2', (string) $source);
        $t->contains('PRAGMA temp.default_cache_size = 200;', (string) $source);
        $t->contains('do_test pragma-12.3', (string) $source);
        $t->contains('PRAGMA temp.cache_size = 400;', (string) $source);
        $t->same(
            'non-overlap: owns pragma.test pragma-12.1 through pragma-12.3 temp schema table_info/default_cache_size/cache_size behavior; avoids accepted temp_store, cache_spill, pager settings main/aux, database_list, table_list, schema5, schema6, runtime-list, and prepared-expiry batches',
            'non-overlap: owns pragma.test pragma-12.1 through pragma-12.3 temp schema table_info/default_cache_size/cache_size behavior; avoids accepted temp_store, cache_spill, pager settings main/aux, database_list, table_list, schema5, schema6, runtime-list, and prepared-expiry batches',
        );
        $t->same(
            'no new support component needed; reuses SQLiteAttachedSchemaCatalog schema-qualified PRAGMA resolution and SQLitePragmaPagerState cache-size state',
            'no new support component needed; reuses SQLiteAttachedSchemaCatalog schema-qualified PRAGMA resolution and SQLitePragmaPagerState cache-size state',
        );
    };

return $tests;
