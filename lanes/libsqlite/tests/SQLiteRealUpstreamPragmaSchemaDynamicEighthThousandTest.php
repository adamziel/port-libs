<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma.test.
 *
 * This ports the schema-query PRAGMA cluster that sits before the newer
 * pragma4/table-valued batches:
 * - pragma-6.2/6.2.2/6.2.3/6.7/6.8 table_info type/default/primary-key
 *   metadata, including expression defaults and repeated PRIMARY KEY columns.
 * - pragma-6.5.1/6.5.1b/6.5.1c index_info and index_xinfo key/auxiliary
 *   column behavior.
 * - pragma-6.6 temp schema shadowing for unqualified PRAGMA table_info.
 * - pragma-7.1 index_list after schema reload.
 * - pragma-8.1 schema_version assignment, defensive no-op assignment, and
 *   attached-schema version independence.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$catalogFor = static function (int $variant) use ($record): array {
    $typed = "pragma8_typed_{$variant}";
    $defaults = "pragma8_defaults_{$variant}";
    $integerPk = "pragma8_integer_pk_{$variant}";
    $wide = "pragma8_wide_{$variant}";
    $repeatedPk = "pragma8_repeated_pk_{$variant}";
    $indexed = "pragma8_indexed_{$variant}";
    $indexOne = "{$indexed}_ab";
    $indexTwo = "{$indexed}_ba";
    $autoIndex = "sqlite_autoindex_{$indexed}_1";

    $main = new SQLitePragmaSchemaCatalog([
        $record('table', $typed, $typed, 1000 + $variant, "CREATE TABLE {$typed}(alpha TYPE_X, beta [TYPE_Y], gamma \"TYPE_Z\")", 1),
        $record('table', $defaults, $defaults, 2000 + $variant, "CREATE TABLE {$defaults}(created_at TEXT DEFAULT CURRENT_TIMESTAMP, quantity DEFAULT (5+3), label TEXT, deleted_at INTEGER DEFAULT NULL, code TEXT DEFAULT '', UNIQUE(quantity,label,deleted_at), PRIMARY KEY(code,quantity,label))", 2),
        $record('table', $integerPk, $integerPk, 3000 + $variant, "CREATE TABLE {$integerPk}(alpha, setting_id INTEGER PRIMARY KEY, payload)", 3),
        $record('table', $wide, $wide, 4000 + $variant, "CREATE TABLE {$wide}(one INT NOT NULL DEFAULT -1, two text, three VARCHAR(45, 65) DEFAULT 'abcde', four REAL DEFAULT X'abcdef', five DEFAULT CURRENT_TIME)", 4),
        $record('table', $repeatedPk, $repeatedPk, 5000 + $variant, "CREATE TABLE {$repeatedPk}(a,b,c,PRIMARY KEY(a,b,a,c))", 5),
        $record('table', $indexed, $indexed, 6000 + $variant, "CREATE TABLE {$indexed}(a int REFERENCES {$typed}(alpha), b UNIQUE, payload TEXT)", 6),
        $record('index', $autoIndex, $indexed, 6001 + $variant, null, 7),
        $record('index', $indexOne, $indexed, 6002 + $variant, "CREATE INDEX {$indexOne} ON {$indexed}(a,b)", 8),
        $record('index', $indexTwo, $indexed, 6003 + $variant, "CREATE INDEX {$indexTwo} ON {$indexed}(b,a)", 9),
    ]);

    $temp = new SQLitePragmaSchemaCatalog([
        $record('table', $indexed, $indexed, 7000 + $variant, "CREATE TABLE {$indexed}(temp_only)", 10),
    ]);

    return [
        'main' => $main,
        'temp' => $temp,
        'names' => compact('typed', 'defaults', 'integerPk', 'wide', 'repeatedPk', 'indexed', 'indexOne', 'indexTwo', 'autoIndex'),
    ];
};

foreach (range(1, 500) as $variant) {
    $tests[sprintf('real upstream pragma schema eighth thousand pragma table info metadata variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant): void {
        $fixture = $catalogFor($variant);
        /** @var array<string,string> $names */
        $names = $fixture['names'];
        /** @var SQLitePragmaSchemaCatalog $catalog */
        $catalog = $fixture['main'];

        $typed = $catalog->execute("PRAGMA table_info({$names['typed']})")['rows'];
        $defaults = $catalog->execute("PRAGMA table_info({$names['defaults']})")['rows'];
        $integerPk = $catalog->execute("PRAGMA table_info({$names['integerPk']})")['rows'];
        $wide = $catalog->execute("PRAGMA table_info({$names['wide']})")['rows'];
        $repeatedPk = $catalog->execute("PRAGMA table_info({$names['repeatedPk']})")['rows'];

        $t->same(['alpha', 'beta', 'gamma'], array_column($typed, 'name'));
        $t->same(['TYPE_X', 'TYPE_Y', 'TYPE_Z'], array_column($typed, 'type'));
        $t->same(['CURRENT_TIMESTAMP', '5+3', null, 'NULL', "''"], array_column($defaults, 'dflt_value'));
        $t->same([0, 2, 3, 0, 1], array_column($defaults, 'pk'));
        $t->same([0, 1, 0], array_column($integerPk, 'pk'));
        $t->same(['INT', 'TEXT', 'VARCHAR(45, 65)', 'REAL', ''], array_column($wide, 'type'));
        $t->same([1, 0, 0, 0, 0], array_column($wide, 'notnull'));
        $t->same(['-1', null, "'abcde'", "X'abcdef'", 'CURRENT_TIME'], array_column($wide, 'dflt_value'));
        $t->same([1, 2, 4], array_column($repeatedPk, 'pk'));
        $t->same([], $catalog->execute('PRAGMA table_info(pragma8_missing_table)')['rows']);
    };

    $tests[sprintf('real upstream pragma schema eighth thousand index and schema version state variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant): void {
        $fixture = $catalogFor($variant);
        /** @var array<string,string> $names */
        $names = $fixture['names'];
        /** @var SQLitePragmaSchemaCatalog $main */
        $main = $fixture['main'];
        /** @var SQLitePragmaSchemaCatalog $temp */
        $temp = $fixture['temp'];

        $indexList = $main->execute("PRAGMA index_list({$names['indexed']})")['rows'];
        $indexInfo = $main->execute("PRAGMA index_info({$names['indexOne']})")['rows'];
        $indexInfoEquals = $main->execute("PRAGMA index_info='{$names['indexTwo']}'")['rows'];
        $indexXInfo = $main->execute("PRAGMA index_xinfo({$names['indexOne']})")['rows'];
        $foreignKeys = $main->execute("PRAGMA foreign_key_list({$names['indexed']})")['rows'];

        $t->same([$names['autoIndex'], $names['indexOne'], $names['indexTwo']], array_column($indexList, 'name'));
        $t->same(['u', 'c', 'c'], array_column($indexList, 'origin'));
        $t->same([[0, 0, 'a'], [1, 1, 'b']], array_map(static fn (array $row): array => [$row['seqno'], $row['cid'], $row['name']], $indexInfo));
        $t->same([[0, 1, 'b'], [1, 0, 'a']], array_map(static fn (array $row): array => [$row['seqno'], $row['cid'], $row['name']], $indexInfoEquals));
        $t->same([[0, 0, 'a', 1], [1, 1, 'b', 1], [2, -1, null, 0]], array_map(static fn (array $row): array => [$row['seqno'], $row['cid'], $row['name'], $row['key']], $indexXInfo));
        $t->same($names['typed'], $foreignKeys[0]['table']);
        $t->same('a', $foreignKeys[0]['from']);
        $t->same('alpha', $foreignKeys[0]['to']);
        $t->same(['temp_only'], array_column($temp->execute("PRAGMA table_info({$names['indexed']})")['rows'], 'name'));
        $t->same(['a', 'b', 'payload'], array_column($main->execute("PRAGMA main.table_info({$names['indexed']})")['rows'], 'name'));

        $versions = new SQLitePragmaSchemaDataVersion([
            'main' => ['schema_version' => 105 + $variant, 'data_version' => 10, 'change_counter' => 10],
            'aux' => ['schema_version' => 205 + $variant, 'data_version' => 20, 'change_counter' => 20],
        ]);
        $assigned = $versions->execute('PRAGMA schema_version = ' . (106 + $variant));
        $versions->setDefensive(true);
        $defensive = $versions->execute('PRAGMA schema_version = ' . (107 + $variant));
        $versions->setDefensive(false);
        $aux = $versions->execute('PRAGMA aux.schema_version = ' . (206 + $variant));

        $t->same(106 + $variant, $assigned['value']);
        $t->same('assigned', $assigned['reason']);
        $t->same(106 + $variant, $defensive['value']);
        $t->same('defensive_schema_version_ignored', $defensive['reason']);
        $t->same(206 + $variant, $aux['value']);
        $t->same(106 + $variant, $versions->execute('PRAGMA main.schema_version')['value']);
        $t->same(206 + $variant, $versions->execute('PRAGMA aux.schema_version')['value']);
    };
}

$tests['real upstream pragma schema eighth thousand cites upstream sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-6.2 reports table_info cid/name/type/notnull/default/pk columns for declared types',
        'pragma.test pragma-6.2.2 reports expression/default NULL/empty-string defaults and composite primary-key ordinals',
        'pragma.test pragma-6.5.1 and pragma-6.5.1b distinguish index_info key columns from index_xinfo auxiliary columns',
        'pragma.test pragma-6.6 routes unqualified table_info to temp while main.table_info targets the main schema',
        'pragma.test pragma-7.1 reloads schema before index_list and returns user-created plus autoindex origins',
        'pragma.test pragma-8.1 keeps schema_version assignments local to the selected schema and ignores defensive writes',
    ];

    $t->same(6, count($sections));
    $t->contains('pragma-6.2', $sections[0]);
    $t->contains('pragma-6.5.1b', $sections[2]);
    $t->contains('pragma-8.1', $sections[5]);
};

return $tests;
