<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

$valueAt = static function (array $value, string $path): mixed {
    $current = $value;
    foreach (explode('.', $path) as $part) {
        if (!is_array($current) || !array_key_exists($part, $current)) {
            return null;
        }
        $current = $current[$part];
    }

    return $current;
};

$catalog = static fn (): SQLitePragmaSchemaCatalog => new SQLitePragmaSchemaCatalog([
    new SQLiteSchemaRecord('table', 't2', 't2', 2, 'CREATE TABLE t2(a TYPE_X, b [TYPE_Y], c "TYPE_Z")', 1),
    new SQLiteSchemaRecord('table', 't5', 't5', 3, "CREATE TABLE t5(a TEXT DEFAULT CURRENT_TIMESTAMP, b DEFAULT (5+3), c TEXT, d INTEGER DEFAULT NULL, e TEXT DEFAULT '', UNIQUE(b,c,d), PRIMARY KEY(e,b,c))", 2),
    new SQLiteSchemaRecord('index', 'sqlite_autoindex_t5_1', 't5', 4, null, 3),
    new SQLiteSchemaRecord('index', 'sqlite_autoindex_t5_2', 't5', 5, null, 4),
    new SQLiteSchemaRecord('table', 't2_3', 't2_3', 6, 'CREATE TABLE t2_3(a,b INTEGER PRIMARY KEY,c)', 5),
    new SQLiteSchemaRecord('table', 't3', 't3', 7, 'CREATE TABLE t3(a int references t2(b), b UNIQUE)', 6),
    new SQLiteSchemaRecord('index', 'sqlite_autoindex_t3_1', 't3', 8, null, 7),
    new SQLiteSchemaRecord('index', 't3i1', 't3', 9, 'CREATE INDEX t3i1 ON t3(a,b)', 8),
    new SQLiteSchemaRecord('index', 't3i2', 't3', 10, 'CREATE INDEX t3i2 ON t3(b,a)', 9),
    new SQLiteSchemaRecord('table', 'test_table', 'test_table', 11, "CREATE TABLE test_table(one INT NOT NULL DEFAULT -1, two text, three VARCHAR(45, 65) DEFAULT 'abcde', four REAL DEFAULT X'abcdef', five DEFAULT CURRENT_TIME)", 10),
    new SQLiteSchemaRecord('table', 't68', 't68', 12, 'CREATE TABLE t68(a,b,c,PRIMARY KEY(a,b,a,c))', 11),
    new SQLiteSchemaRecord('table', 'generated_t', 'generated_t', 14, 'CREATE TABLE generated_t(a INT, b INT GENERATED ALWAYS AS (a+1) VIRTUAL, d INT GENERATED ALWAYS AS (a*2) STORED)', 13),
    new SQLiteSchemaRecord('index', 'generated_expr_idx', 'generated_t', 15, 'CREATE INDEX generated_expr_idx ON generated_t((a+1), d DESC COLLATE nocase) WHERE d > 10', 14),
]);

// Source truth: upstream pragma.test pragma-6.2 through 7.1.2.
$schemaCases = [
    'pragma-6.2 type x' => ['PRAGMA table_info(t2)', 'rows.0.type', 'TYPE_X'],
    'pragma-6.2 bracket type unquoted' => ['PRAGMA table_info(t2)', 'rows.1.type', 'TYPE_Y'],
    'pragma-6.2 double quoted type unquoted' => ['PRAGMA table_info(t2)', 'rows.2.type', 'TYPE_Z'],
    'pragma-6.2.1 missing target returns empty rows' => ['PRAGMA table_info', 'rows', []],
    'pragma-6.2.2 current timestamp default' => ['PRAGMA table_info(t5)', 'rows.0.dflt_value', 'CURRENT_TIMESTAMP'],
    'pragma-6.2.2 parenthesized default expression stripped' => ['PRAGMA table_info(t5)', 'rows.1.dflt_value', '5+3'],
    'pragma-6.2.2 composite pk b' => ['PRAGMA table_info(t5)', 'rows.1.pk', 2],
    'pragma-6.2.2 composite pk c' => ['PRAGMA table_info(t5)', 'rows.2.pk', 3],
    'pragma-6.2.2 null default literal' => ['PRAGMA table_info(t5)', 'rows.3.dflt_value', 'NULL'],
    'pragma-6.2.2 empty string default' => ['PRAGMA table_info(t5)', 'rows.4.dflt_value', "''"],
    'pragma-6.2.2 composite pk e' => ['PRAGMA table_info(t5)', 'rows.4.pk', 1],
    'pragma-6.2.3 implicit type' => ['PRAGMA table_info(t2_3)', 'rows.0.type', ''],
    'pragma-6.2.3 integer pk' => ['PRAGMA table_info(t2_3)', 'rows.1.pk', 1],
    'pragma-6.3.1 foreign key table' => ['PRAGMA foreign_key_list(t3)', 'rows.0.table', 't2'],
    'pragma-6.3.1 foreign key from' => ['PRAGMA foreign_key_list(t3)', 'rows.0.from', 'a'],
    'pragma-6.3.1 foreign key to' => ['PRAGMA foreign_key_list(t3)', 'rows.0.to', 'b'],
    'pragma-6.3.1 foreign key update action' => ['PRAGMA foreign_key_list(t3)', 'rows.0.on_update', 'NO ACTION'],
    'pragma-6.3.1 foreign key delete action' => ['PRAGMA foreign_key_list(t3)', 'rows.0.on_delete', 'NO ACTION'],
    'pragma-6.3.1 foreign key match' => ['PRAGMA foreign_key_list(t3)', 'rows.0.match', 'NONE'],
    'pragma-6.3.2 missing foreign-key target returns empty rows' => ['PRAGMA foreign_key_list', 'rows', []],
    'pragma-6.3.3 bogus foreign key empty' => ['PRAGMA foreign_key_list(t3_bogus)', 'rows', []],
    'pragma-6.4 index list autoindex name' => ['PRAGMA index_list(t3)', 'rows.0.name', 'sqlite_autoindex_t3_1'],
    'pragma-6.4 index list autoindex unique' => ['PRAGMA index_list(t3)', 'rows.0.unique', 1],
    'pragma-6.4 index list autoindex origin' => ['PRAGMA index_list(t3)', 'rows.0.origin', 'u'],
    'pragma-6.4 index list explicit name' => ['PRAGMA index_list(t3)', 'rows.1.name', 't3i1'],
    'pragma-6.5.1 index info first cid' => ['PRAGMA index_info(t3i1)', 'rows.0.cid', 0],
    'pragma-6.5.1 index info first name' => ['PRAGMA index_info(t3i1)', 'rows.0.name', 'a'],
    'pragma-6.5.1 index info second cid' => ['PRAGMA index_info(t3i1)', 'rows.1.cid', 1],
    'pragma-6.5.1 index info second name' => ['PRAGMA index_info(t3i1)', 'rows.1.name', 'b'],
    'pragma-6.5.1b index xinfo rowid cid' => ['PRAGMA index_xinfo(t3i1)', 'rows.2.cid', -1],
    'pragma-6.5.1b index xinfo rowid name' => ['PRAGMA index_xinfo(t3i1)', 'rows.2.name', null],
    'pragma-6.5.1b index xinfo rowid key flag' => ['PRAGMA index_xinfo(t3i1)', 'rows.2.key', 0],
    'pragma-6.5.1c index info equals first name' => ["PRAGMA index_info='t3i2'", 'rows.0.name', 'b'],
    'pragma-6.5.1c index info equals second name' => ["PRAGMA index_info='t3i2'", 'rows.1.name', 'a'],
    'pragma-6.5.2 bogus index info empty' => ['PRAGMA index_info(t3i1_bogus)', 'rows', []],
    'pragma-6.7 one not null' => ['PRAGMA table_info(test_table)', 'rows.0.notnull', 1],
    'pragma-6.7 one default' => ['PRAGMA table_info(test_table)', 'rows.0.dflt_value', '-1'],
    'pragma-6.7 text type uppercased' => ['PRAGMA table_info(test_table)', 'rows.1.type', 'TEXT'],
    'pragma-6.7 varchar type keeps comma' => ['PRAGMA table_info(test_table)', 'rows.2.type', 'VARCHAR(45, 65)'],
    'pragma-6.7 blob default' => ['PRAGMA table_info(test_table)', 'rows.3.dflt_value', "X'abcdef'"],
    'pragma-6.7 current time default' => ['PRAGMA table_info(test_table)', 'rows.4.dflt_value', 'CURRENT_TIME'],
    'pragma-6.8 duplicate pk first ordinal' => ['PRAGMA table_info(t68)', 'rows.0.pk', 1],
    'pragma-6.8 duplicate pk second ordinal' => ['PRAGMA table_info(t68)', 'rows.1.pk', 2],
    'pragma-6.8 duplicate pk later ordinal' => ['PRAGMA table_info(t68)', 'rows.2.pk', 4],
    'pragma-7.1.2 bogus index list empty' => ['PRAGMA index_list(t3_bogus)', 'rows', []],
    'generated table_info keeps visible column only' => ['PRAGMA table_info(generated_t)', 'rows.0.name', 'a'],
    'generated table_xinfo virtual hidden code' => ['PRAGMA table_xinfo(generated_t)', 'rows.1.hidden', 2],
    'generated table_xinfo stored hidden code' => ['PRAGMA table_xinfo(generated_t)', 'rows.2.hidden', 3],
    'generated index list partial' => ['PRAGMA index_list(generated_t)', 'rows.0.partial', 1],
    'generated index xinfo expression cid' => ['PRAGMA index_xinfo(generated_expr_idx)', 'rows.0.cid', -2],
    'generated index xinfo desc term' => ['PRAGMA index_xinfo(generated_expr_idx)', 'rows.1.desc', 1],
    'generated index xinfo collation term' => ['PRAGMA index_xinfo(generated_expr_idx)', 'rows.1.coll', 'NOCASE'],
];

foreach ($schemaCases as $name => [$sql, $path, $expected]) {
    $tests['real upstream pragma schema dynamic followup ' . $name] = static function (TestRunner $t) use ($catalog, $valueAt, $sql, $path, $expected): void {
        if ($path === 'throws') {
            $t->throws($expected, static fn () => $catalog()->execute($sql));
            return;
        }
        $t->same($expected, $valueAt($catalog()->execute($sql), $path));
    };
}

$tests['real upstream pragma schema dynamic followup pragma-7.1.1 index list reflects dropped t3i2'] = static function (TestRunner $t) use ($catalog): void {
    $records = array_values(array_filter($catalog()->records(), static fn (SQLiteSchemaRecord $record): bool => $record->name !== 't3i2'));
    $result = (new SQLitePragmaSchemaCatalog($records))->execute('PRAGMA index_list(t3)');
    $t->same(2, count($result['rows']));
    $t->same('t3i1', $result['rows'][1]['name']);
};

// Source truth: upstream pragma.test pragma-8.1/8.2 and pragma3.test
// pragma3-100 through 190.
$versionCases = [
    'pragma-8.1.1 schema assignment' => [static fn (): mixed => (new SQLitePragmaSchemaDataVersion())->execute('PRAGMA schema_version = 105')['value'], 105],
    'pragma-8.1.2 schema query rows' => [static function (): mixed {
        $state = new SQLitePragmaSchemaDataVersion();
        $state->execute('PRAGMA schema_version = 105');
        return $state->execute('PRAGMA schema_version')['rows'];
    }, [['schema_version' => 105]]],
    'pragma-8.1.4 repeated assignment unchanged' => [static fn (): mixed => (new SQLitePragmaSchemaDataVersion(['main' => ['schema_version' => 106]]))->execute('PRAGMA schema_version = 106')['changed'], false],
    'pragma-8.1.11 aux schema isolated' => [static function (): mixed {
        $state = new SQLitePragmaSchemaDataVersion(['main' => ['schema_version' => 106], 'aux' => ['schema_version' => 0]]);
        $state->execute('PRAGMA aux.schema_version = 205');
        return [$state->execute('PRAGMA main.schema_version')['value'], $state->execute('PRAGMA aux.schema_version')['value']];
    }, [106, 205]],
    'pragma-8.2 user version signed' => [static function (): mixed {
        $state = new SQLitePragmaSchemaDataVersion();
        $state->execute('PRAGMA user_version=-450');
        return $state->execute('PRAGMA user_version')['value'];
    }, -450],
    'pragma3-100 data version initial main' => [static fn (): mixed => (new SQLitePragmaSchemaDataVersion())->execute('PRAGMA data_version')['rows'], [['data_version' => 1]]],
    'pragma3-101 data version initial temp' => [static fn (): mixed => (new SQLitePragmaSchemaDataVersion())->execute('PRAGMA temp.data_version')['rows'], [['data_version' => 1]]],
    'pragma3-102 data version assignment noop' => [static function (): mixed {
        $state = new SQLitePragmaSchemaDataVersion();
        $assigned = $state->execute('PRAGMA main.data_version=1234');
        return [$assigned['value'], $state->execute('PRAGMA main.data_version')['value'], $assigned['changed'], $assigned['reason']];
    }, [1, 1, false, 'read_only_pragma_ignored']],
    'pragma3-110 local commit stable' => [static function (): mixed {
        $state = new SQLitePragmaSchemaDataVersion();
        $before = $state->execute('PRAGMA data_version')['value'];
        $during = $state->recordLocalCommit('main', 3, 'local_insert')['value'];
        return [$before, $during, $state->execute('PRAGMA data_version')['value'], $state->headerUpdate('main')['file_change_counter']];
    }, [1, 1, 1, 4]],
    'pragma3-140 external commit changes observer' => [static function (): mixed {
        $state = new SQLitePragmaSchemaDataVersion();
        $before = $state->execute('PRAGMA data_version')['value'];
        $state->recordExternalCommit('main', 1, 'other_connection_commit');
        return [$before, $state->execute('PRAGMA data_version')['value']];
    }, [1, 2]],
    'pragma3-160 rollback restores state' => [static function (): mixed {
        $state = new SQLitePragmaSchemaDataVersion(['main' => ['schema_version' => 7, 'data_version' => 2, 'change_counter' => 2]]);
        $state->beginTransaction();
        $state->recordLocalCommit('main', 1, 'local_update');
        $state->recordSchemaChange('main', 1, 'local_schema');
        $state->rollbackTransaction();
        return [$state->execute('PRAGMA schema_version')['value'], $state->execute('PRAGMA data_version')['value']];
    }, [7, 2]],
    'pragma3-190 external post commit changes other connection' => [static function (): mixed {
        $reader = new SQLitePragmaSchemaDataVersion(['main' => ['data_version' => 2, 'change_counter' => 2]]);
        $writer = new SQLitePragmaSchemaDataVersion(['main' => ['data_version' => 2, 'change_counter' => 2]]);
        $writer->recordLocalCommit('main', 1, 'writer_local_commit');
        $reader->recordExternalCommit('main', 1, 'writer_commit_observed');
        return [$writer->execute('PRAGMA data_version')['value'], $reader->execute('PRAGMA data_version')['value']];
    }, [2, 3]],
];

foreach ($versionCases as $name => [$callback, $expected]) {
    $tests['real upstream pragma schema dynamic followup ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
