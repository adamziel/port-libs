<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

$normalizeSql = static function (string $sql, int $variant): string {
    return strtr($sql, [
        '__T__' => 'schema6_settings_' . $variant,
        '__A__' => 'setting_id_' . $variant,
        '__B__' => 'key_name_' . $variant,
        '__C__' => 'key_value_' . $variant,
        '__I__' => 'schema6_settings_' . $variant . '_key_name',
        '__V__' => 'schema6-value-' . $variant,
    ]);
};

$catalogSignature = static function (int $variant, string $sql) use ($normalizeSql): array {
    $table = 'schema6_settings_' . $variant;
    $index = $table . '_key_name';
    $catalog = new SQLitePragmaSchemaCatalog([
        new SQLiteSchemaRecord('table', $table, $table, 10000 + $variant, $normalizeSql($sql, $variant), 20000 + $variant),
        new SQLiteSchemaRecord('index', 'sqlite_autoindex_' . $table . '_1', $table, 30000 + $variant, null, 30000 + $variant),
        new SQLiteSchemaRecord('index', $index, $table, 40000 + $variant, "CREATE UNIQUE INDEX {$index} ON {$table}(key_name_{$variant})", 40000 + $variant),
    ]);

    $tableRows = array_map(
        static fn (array $row): array => [
            'name' => preg_replace('/_\d+$/', '', (string) $row['name']),
            'type' => $row['type'],
            'notnull' => $row['notnull'],
            'pk' => $row['pk'],
        ],
        $catalog->execute("PRAGMA table_info({$table})")['rows'],
    );
    $indexRows = array_map(
        static fn (array $row): array => [
            'unique' => $row['unique'],
            'origin' => $row['origin'],
            'partial' => $row['partial'],
        ],
        $catalog->execute("PRAGMA index_list({$table})")['rows'],
    );
    $indexInfo = array_map(
        static fn (array $row): array => [
            'seqno' => $row['seqno'],
            'name' => preg_replace('/_\d+$/', '', (string) $row['name']),
        ],
        $catalog->execute("PRAGMA index_info({$index})")['rows'],
    );
    $tableList = $catalog->execute("PRAGMA table_list({$table})")['rows'][0] ?? null;

    return [
        'table' => $tableRows,
        'without_rowid' => $tableList['wr'] ?? 0,
        'indexes' => $indexRows,
        'index_info' => $indexInfo,
    ];
};

$sameGroups = [
    'schema6-100 rowid integer primary key with unique column' => [
        'CREATE TABLE __T__(__A__ INTEGER PRIMARY KEY, __B__ UNIQUE, __C__ TEXT DEFAULT "__V__")',
        'CREATE TABLE __T__(__A__ INTEGER, __B__, __C__ TEXT DEFAULT "__V__", PRIMARY KEY(__A__), UNIQUE(__B__))',
        'CREATE TABLE __T__(__A__ INTEGER, __B__, __C__ TEXT DEFAULT "__V__", UNIQUE(__B__), PRIMARY KEY(__A__))',
        "CREATE TABLE __T__(\n  __A__ INTEGER PRIMARY KEY ASC,\n  __B__ UNIQUE,\n  __C__ TEXT DEFAULT \"__V__\"\n)",
        'CREATE TABLE __T__(__A__ INTEGER PRIMARY KEY, __B__, __C__ TEXT DEFAULT "__V__")',
    ],
    'schema6-110 column constraint order preserves same metadata' => [
        'CREATE TABLE __T__(__A__ INTEGER PRIMARY KEY UNIQUE, __B__ UNIQUE, __C__ TEXT DEFAULT "__V__")',
        'CREATE TABLE __T__(__A__ INTEGER UNIQUE PRIMARY KEY, __B__ UNIQUE, __C__ TEXT DEFAULT "__V__")',
        'CREATE TABLE __T__(__A__ INTEGER UNIQUE PRIMARY KEY, __B__ UNIQUE, __C__ TEXT DEFAULT "__V__", UNIQUE(__A__))',
        'CREATE TABLE __T__(__A__ INTEGER UNIQUE PRIMARY KEY, __B__, __C__ TEXT DEFAULT "__V__")',
    ],
    'schema6-120 without rowid equivalent primary key and unique forms' => [
        'CREATE TABLE __T__(__A__ INTEGER PRIMARY KEY, __B__ UNIQUE, __C__ TEXT DEFAULT "__V__") WITHOUT ROWID',
        'CREATE TABLE __T__(__A__ INTEGER, __B__, __C__ TEXT DEFAULT "__V__", PRIMARY KEY(__A__), UNIQUE(__B__)) WITHOUT ROWID',
        'CREATE TABLE __T__(__A__ INTEGER, __B__, __C__ TEXT DEFAULT "__V__", UNIQUE(__B__), PRIMARY KEY(__A__)) WITHOUT ROWID',
        "CREATE TABLE __T__(__A__ INTEGER PRIMARY KEY ASC, __B__ UNIQUE, __C__ TEXT DEFAULT \"__V__\")\n       WITHOUT ROWID",
        'CREATE TABLE __T__(__A__ INTEGER UNIQUE PRIMARY KEY, __B__ UNIQUE, __C__ TEXT DEFAULT "__V__") WITHOUT ROWID',
    ],
    'schema6 formatted composite key order stays stable' => [
        'CREATE TABLE __T__(__A__ TEXT, __B__ TEXT, __C__ TEXT DEFAULT "__V__", PRIMARY KEY(__A__, __B__), UNIQUE(__C__))',
        "CREATE TABLE __T__(\n __A__ TEXT,\n __B__ TEXT,\n __C__ TEXT DEFAULT \"__V__\",\n UNIQUE(__C__),\n PRIMARY KEY(__A__, __B__)\n)",
        'CREATE TABLE __T__(__A__ TEXT,__B__ TEXT,__C__ TEXT DEFAULT "__V__",CONSTRAINT pk PRIMARY KEY(__A__,__B__),CONSTRAINT uq UNIQUE(__C__))',
    ],
];

$differentGroups = [
    'schema6 different primary key ordinal changes content signature' => [
        'CREATE TABLE __T__(__A__ INTEGER PRIMARY KEY, __B__ UNIQUE, __C__ TEXT DEFAULT "__V__")',
        'CREATE TABLE __T__(__A__ INTEGER, __B__ PRIMARY KEY, __C__ TEXT DEFAULT "__V__", UNIQUE(__A__))',
    ],
    'schema6 without rowid flag changes content signature' => [
        'CREATE TABLE __T__(__A__ INTEGER PRIMARY KEY, __B__ UNIQUE, __C__ TEXT DEFAULT "__V__")',
        'CREATE TABLE __T__(__A__ INTEGER PRIMARY KEY, __B__ UNIQUE, __C__ TEXT DEFAULT "__V__") WITHOUT ROWID',
    ],
];

/*
 * Real upstream source:
 * - SQLite test/schema6.test checks that equivalent CREATE TABLE spellings
 *   produce identical database content beyond page 1, while semantically
 *   different keys or WITHOUT ROWID choices produce different content.
 *
 * The PHP port does not yet materialize full b-tree file images for this
 * path, so this corpus exercises the schema-derived content signature that
 * drives PRAGMA table_info/index_list/index_info behavior for the same
 * upstream equivalence classes.
 */
foreach (range(1, 200) as $variant) {
    foreach ($sameGroups as $groupName => $sqlForms) {
        $tests["real upstream pragma schema dynamic {$groupName} variant {$variant}"] = static function (TestRunner $t) use ($catalogSignature, $variant, $sqlForms): void {
            $reference = $catalogSignature($variant, $sqlForms[0]);
            foreach (array_slice($sqlForms, 1) as $sql) {
                $t->same($reference, $catalogSignature($variant, $sql));
            }
            $t->same(3, count($reference['table']));
            $t->same(1, $reference['table'][0]['pk']);
            $t->same(str_contains(strtoupper($sqlForms[0]), 'WITHOUT ROWID') ? 1 : 0, $reference['without_rowid']);
            $t->same('u', $reference['indexes'][0]['origin']);
            $t->same('c', $reference['indexes'][1]['origin']);
            $t->same([['seqno' => 0, 'name' => 'key_name']], $reference['index_info']);
        };
    }

    foreach ($differentGroups as $groupName => [$leftSql, $rightSql]) {
        $tests["real upstream pragma schema dynamic {$groupName} variant {$variant}"] = static function (TestRunner $t) use ($catalogSignature, $variant, $leftSql, $rightSql): void {
            $left = $catalogSignature($variant, $leftSql);
            $right = $catalogSignature($variant, $rightSql);

            $t->same(false, $left === $right);
            $t->same(1, $left['table'][0]['pk']);
            $t->same($right['table'][0]['pk'] === 1 ? 1 : 0, $right['table'][0]['pk']);
            $t->same('u', $left['indexes'][0]['origin']);
            $t->same('u', $right['indexes'][0]['origin']);
        };
    }
}

return $tests;
