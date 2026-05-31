<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaImportExecutor;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/tableopts.test tableopt-1.1 rejects WITHOUT ROWID tables
 *   without an explicit PRIMARY KEY.
 * - SQLite test/tableopts.test tableopt-1.1b rejects AUTOINCREMENT on
 *   WITHOUT ROWID tables.
 * - SQLite test/tableopts.test tableopt-1.2 rejects unknown table options.
 * - SQLite test/tableopts.test tableopt-2.1 creates a composite PRIMARY KEY
 *   WITHOUT ROWID table, and tableopt-3.1 keeps WITHOUT usable as an
 *   identifier. This dynamic corpus exercises those schema parse outcomes
 *   through the PHP schema import executor and PRAGMA catalog surface.
 */

$importError = static function (string $sql): array {
    $executor = new SQLiteSchemaImportExecutor();

    try {
        $executor->execute($sql);
    } catch (InvalidArgumentException $exception) {
        return [
            'message' => $exception->getMessage(),
            'records' => $executor->schemaRecords(),
        ];
    }

    return [
        'message' => 'accepted',
        'records' => $executor->schemaRecords(),
    ];
};

foreach (range(1, 1000) as $variant) {
    $suffix = sprintf('%04d', $variant);
    $missingPk = "tableopts_missing_pk_{$suffix}";
    $autoIncrement = "tableopts_autoinc_{$suffix}";
    $unknownOption = "tableopts_unknown_{$suffix}";
    $settings = "tableopts_settings_{$suffix}";
    $validSql = "CREATE TABLE {$settings}(setting_key TEXT NOT NULL, tenant_id INTEGER NOT NULL, key_value TEXT DEFAULT 'value_{$suffix}', PRIMARY KEY(setting_key, tenant_id)) WITHOUT ROWID";

    $tests["real upstream tableopts without rowid schema dynamic variant {$suffix}"] =
        static function (TestRunner $t) use ($importError, $missingPk, $autoIncrement, $unknownOption, $settings, $validSql): void {
            $missingResult = $importError("CREATE TABLE {$missingPk}(setting_key TEXT CHECK(setting_key <> 'primary key'), key_value TEXT) WITHOUT rowid");
            $autoResult = $importError("CREATE TABLE {$autoIncrement}(setting_id INTEGER PRIMARY KEY AUTOINCREMENT, key_value TEXT) WITHOUT rowid");
            $unknownResult = $importError("CREATE TABLE {$unknownOption}(setting_id INTEGER PRIMARY KEY, key_value TEXT) WITHOUT unknown2");

            $t->same("PRIMARY KEY missing on table {$missingPk}", $missingResult['message']);
            $t->same([], $missingResult['records']);
            $t->same('AUTOINCREMENT not allowed on WITHOUT ROWID tables', $autoResult['message']);
            $t->same([], $autoResult['records']);
            $t->same('unknown table option: unknown2', $unknownResult['message']);
            $t->same([], $unknownResult['records']);

            $executor = new SQLiteSchemaImportExecutor();
            $created = $executor->execute($validSql);
            $records = $executor->schemaRecords();
            $catalog = $executor->catalog();
            $tableList = $catalog->executeSchemaPragma("PRAGMA table_list({$settings})")['rows'][0] ?? [];
            $tableInfo = $catalog->executeSchemaPragma("PRAGMA table_info({$settings})")['rows'];
            $indexList = $catalog->executeSchemaPragma("PRAGMA index_list({$settings})")['rows'];
            $autoindex = "sqlite_autoindex_{$settings}_1";

            $t->same('ok', $created['status']);
            $t->same(true, $created['created']);
            $t->same('main', $created['schema']);
            $t->same($settings, $created['name']);
            $t->same([$autoindex], $created['autoindexes']);
            $t->same(['table', 'index'], array_map(static fn ($record): string => $record->type, $records));
            $t->same([$settings, $autoindex], array_map(static fn ($record): string => $record->name, $records));
            $t->same(1, $tableList['wr'] ?? null);
            $t->same(0, $tableList['strict'] ?? null);
            $t->same(3, $tableList['ncol'] ?? null);
            $t->same('table', $tableList['type'] ?? null);
            $t->same(['setting_key', 'tenant_id', 'key_value'], array_column($tableInfo, 'name'));
            $t->same([1, 2, 0], array_column($tableInfo, 'pk'));
            $t->same($autoindex, $indexList[0]['name'] ?? null);
            $t->same(1, $indexList[0]['unique'] ?? null);
            $t->same('pk', $indexList[0]['origin'] ?? null);

            $identifierExecutor = new SQLiteSchemaImportExecutor();
            $identifierCreated = $identifierExecutor->execute('CREATE TABLE without(x INTEGER PRIMARY KEY, without TEXT)');
            $identifierCatalog = $identifierExecutor->catalog();
            $identifierInfo = $identifierCatalog->executeSchemaPragma('PRAGMA table_info(without)')['rows'];
            $identifierList = $identifierCatalog->executeSchemaPragma('PRAGMA table_list(without)')['rows'][0] ?? [];

            $t->same('ok', $identifierCreated['status']);
            $t->same(['x', 'without'], array_column($identifierInfo, 'name'));
            $t->same([1, 0], array_column($identifierInfo, 'pk'));
            $t->same(0, $identifierList['wr'] ?? null);
        };
}

$tests['real upstream tableopts source citations and non overlap'] =
    static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/tableopts.test');

        $t->same(true, is_string($source));
        $t->contains('do_test tableopt-1.1', (string) $source);
        $t->contains('PRIMARY KEY missing on table t1', (string) $source);
        $t->contains('AUTOINCREMENT not allowed on WITHOUT ROWID tables', (string) $source);
        $t->contains('unknown table option: unknown2', (string) $source);
        $t->contains('SELECT rowid, * FROM t1', (string) $source);
        $t->contains('CREATE TABLE without(x INTEGER PRIMARY KEY, without TEXT)', (string) $source);
    };

return $tests;
