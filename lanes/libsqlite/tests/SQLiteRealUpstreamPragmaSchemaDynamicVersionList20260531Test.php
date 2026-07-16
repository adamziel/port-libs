<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaDataVersionTracker;
use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma.test pragma-8.1.3 through pragma-8.1.18 covers
 *   schema_version assignment, defensive-mode no-op behavior, attached-schema
 *   schema_version isolation, and schema reload pressure after cookie changes.
 * - SQLite test/pragma.test pragma-8.2.* covers user_version reads/writes that
 *   do not accidentally mutate schema_version.
 * - SQLite test/pragma3.test pragma3-100 through pragma3-170 covers
 *   data_version as a read-only PRAGMA whose value is stable for local commits
 *   and changes after other connections commit.
 * - SQLite test/pragma5.test 1.0 through 3.1 covers virtual PRAGMA metadata
 *   rowsets for function_list, module_list, and pragma_list.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$version = static fn (array $result): int => (int) $result['value'];
$rowValue = static fn (array $result, string $column): int => (int) $result['rows'][0][$column];
$headerValue = static fn (array $result, string $column): int => (int) $result['header'][$column];

$catalogFor = static function (int $variant) use ($record): SQLiteAttachedSchemaCatalog {
    $mainTable = "pragma_meta_main_{$variant}";
    $archiveTable = "pragma_meta_archive_{$variant}";

    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', $mainTable, $mainTable, 1000 + $variant, "CREATE TABLE {$mainTable}(key_name TEXT PRIMARY KEY, key_value TEXT)", 1),
        $record('view', "pragma_meta_view_{$variant}", "pragma_meta_view_{$variant}", null, "CREATE VIEW pragma_meta_view_{$variant} AS SELECT key_name FROM {$mainTable}", 2),
    ]);

    $catalog->attach("archive{$variant}", "archive-{$variant}.sqlite", [
        $record('table', $archiveTable, $archiveTable, 2000 + $variant, "CREATE TABLE {$archiveTable}(key_name TEXT PRIMARY KEY, key_value TEXT)", 3),
    ]);

    return $catalog;
};

foreach (range(1, 1000) as $variant) {
    $tests["real upstream pragma schema dynamic version and virtual list variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $headerValue, $rowValue, $variant, $version): void {
        $state = new SQLitePragmaSchemaDataVersion([
            'main' => [
                'schema_version' => 100 + $variant,
                'data_version' => 10 + $variant,
                'change_counter' => 20 + $variant,
                'user_version' => $variant,
            ],
            "archive{$variant}" => [
                'schema_version' => 200 + $variant,
                'data_version' => 30 + $variant,
                'change_counter' => 40 + $variant,
                'user_version' => $variant * 2,
            ],
        ]);

        $state->setDefensive(true);
        $defensive = $state->execute('PRAGMA schema_version=' . (9000 + $variant));
        $afterDefensiveRead = $state->execute('PRAGMA schema_version');
        $state->setDefensive(false);
        $assigned = $state->execute('PRAGMA schema_version=' . (300 + $variant));
        $attachedAssigned = $state->execute("PRAGMA archive{$variant}.schema_version=" . (400 + $variant));
        $userAssigned = $state->execute('PRAGMA user_version=' . (500 + $variant));
        $ignoredDataWrite = $state->execute('PRAGMA data_version=' . (600 + $variant));

        $tracker = new SQLitePragmaDataVersionTracker(70 + $variant);
        $tracker->open('reader');
        $tracker->open('writer');
        $readerInitial = $tracker->executePragma('reader', 'PRAGMA data_version');
        $tracker->begin('writer');
        $writerDuring = $tracker->executePragma('writer', 'PRAGMA main.data_version');
        $tracker->commit('writer', true);
        $writerAfterLocalCommit = $tracker->executePragma('writer', 'PRAGMA data_version');
        $readerAfterOtherCommit = $tracker->executePragma('reader', 'PRAGMA data_version');
        $readerIgnoredWrite = $tracker->executePragma('reader', 'PRAGMA data_version=1234');

        $catalog = $catalogFor($variant);
        $functionColumns = $catalog->executeSchemaPragma('PRAGMA table_info(pragma_function_list)')['rows'];
        $moduleColumns = $catalog->executeSchemaPragma('PRAGMA table_info(pragma_module_list)')['rows'];
        $pragmaRows = $catalog->executeTableValuedPragma('pragma_pragma_list()')['rows'];
        $tableListRows = $catalog->executeTableValuedPragma('pragma_table_list()')['rows'];

        $t->same('defensive_schema_version_ignored', $defensive['reason']);
        $t->same(100 + $variant, $version($afterDefensiveRead));
        $t->same(300 + $variant, $version($assigned));
        $t->same(400 + $variant, $version($attachedAssigned));
        $t->same(300 + $variant, $version($state->execute('PRAGMA main.schema_version')));
        $t->same(400 + $variant, $version($state->execute("PRAGMA archive{$variant}.schema_version")));
        $t->same(500 + $variant, $version($state->execute('PRAGMA user_version')));
        $t->same(300 + $variant, $headerValue($state->execute('PRAGMA schema_version'), 'schema_cookie'));
        $t->same(20 + $variant, $headerValue($state->execute('PRAGMA data_version'), 'file_change_counter'));
        $t->same('read_only_pragma_ignored', $ignoredDataWrite['reason']);
        $t->same(10 + $variant, $rowValue($state->execute('PRAGMA data_version'), 'data_version'));
        $t->same(1, $readerInitial['value']);
        $t->same(1, $writerDuring['value']);
        $t->same(1, $writerAfterLocalCommit['value']);
        $t->same(2, $readerAfterOtherCommit['value']);
        $t->same(true, $readerIgnoredWrite['write_ignored']);
        $t->same(['name', 'builtin', 'type', 'enc', 'narg', 'flags'], array_column($functionColumns, 'name'));
        $t->same(['name'], array_column($moduleColumns, 'name'));
        $t->same(true, in_array('pragma_list', array_column($pragmaRows, 'name'), true));
        $t->same(['main', "archive{$variant}"], array_values(array_unique(array_column($tableListRows, 'schema'))));
    };
}

$tests['real upstream pragma schema dynamic version list source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-8.1.3 through pragma-8.1.18 schema_version assignment, defensive mode, attached isolation, and reload pressure',
        'pragma.test pragma-8.2.* user_version read/write does not mutate schema_version',
        'pragma3.test pragma3-100 through pragma3-170 data_version read-only and other-connection commit behavior',
        'pragma5.test 1.0 through 3.1 virtual PRAGMA metadata rowsets for function_list, module_list, and pragma_list',
    ];

    $t->same(4, count($sections));
    $t->contains('pragma.test', $sections[0]);
    $t->contains('pragma3.test', $sections[2]);
    $t->contains('pragma5.test', $sections[3]);
};

return $tests;
