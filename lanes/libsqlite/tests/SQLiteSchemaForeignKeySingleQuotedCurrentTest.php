<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    1,
);

$makeCatalog = static fn (): SQLitePragmaSchemaCatalog => new SQLitePragmaSchemaCatalog([
    $record('table', 'wp option names', 'wp option names', 2, "CREATE TABLE 'wp option names'('name key' TEXT PRIMARY KEY, 'blog id' INTEGER NOT NULL)"),
    $record('table', 'wp legacy meta', 'wp legacy meta', 3, "CREATE TABLE 'wp legacy meta'(
        'meta id' INTEGER PRIMARY KEY,
        'option key' TEXT REFERENCES 'wp option names'('name key') ON UPDATE CASCADE ON DELETE SET DEFAULT MATCH legacy,
        'blog id' INTEGER,
        'autoload key' TEXT,
        CONSTRAINT 'legacy composite fk' FOREIGN KEY('blog id', 'autoload key') REFERENCES 'wp option names'('blog id', 'name key') ON UPDATE SET NULL ON DELETE CASCADE
    )"),
    $record('table', 'wp plain legacy', 'wp plain legacy', 4, "CREATE TABLE 'wp plain legacy'('option key' TEXT REFERENCES 'wp option names')"),
]);

$valueAt = static function (mixed $value, string $path): mixed {
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
    'pragma status ok' => ["PRAGMA foreign_key_list('wp legacy meta')", 'status', 'ok'],
    'pragma name retained' => ["PRAGMA foreign_key_list('wp legacy meta')", 'pragma', 'foreign_key_list'],
    'target preserves single quoted table name' => ["PRAGMA foreign_key_list('wp legacy meta')", 'target', 'wp legacy meta'],
    'three rows extracted' => ["PRAGMA foreign_key_list('wp legacy meta')", 'rows.count', 3],
    'column fk id starts at zero' => ["PRAGMA foreign_key_list('wp legacy meta')", 'rows.0.id', 0],
    'column fk seq starts at zero' => ["PRAGMA foreign_key_list('wp legacy meta')", 'rows.0.seq', 0],
    'column fk parent table unquoted' => ["PRAGMA foreign_key_list('wp legacy meta')", 'rows.0.table', 'wp option names'],
    'column fk child column unquoted' => ["PRAGMA foreign_key_list('wp legacy meta')", 'rows.0.from', 'option key'],
    'column fk parent column unquoted' => ["PRAGMA foreign_key_list('wp legacy meta')", 'rows.0.to', 'name key'],
    'column fk update action retained' => ["PRAGMA foreign_key_list('wp legacy meta')", 'rows.0.on_update', 'CASCADE'],
    'column fk delete action retained' => ["PRAGMA foreign_key_list('wp legacy meta')", 'rows.0.on_delete', 'SET DEFAULT'],
    'column fk match retained' => ["PRAGMA foreign_key_list('wp legacy meta')", 'rows.0.match', 'LEGACY'],
    'table fk first row id increments' => ["PRAGMA foreign_key_list('wp legacy meta')", 'rows.1.id', 1],
    'table fk first seq zero' => ["PRAGMA foreign_key_list('wp legacy meta')", 'rows.1.seq', 0],
    'table fk first child unquoted' => ["PRAGMA foreign_key_list('wp legacy meta')", 'rows.1.from', 'blog id'],
    'table fk first parent unquoted' => ["PRAGMA foreign_key_list('wp legacy meta')", 'rows.1.to', 'blog id'],
    'table fk second row shares id' => ["PRAGMA foreign_key_list('wp legacy meta')", 'rows.2.id', 1],
    'table fk second seq one' => ["PRAGMA foreign_key_list('wp legacy meta')", 'rows.2.seq', 1],
    'table fk second child unquoted' => ["PRAGMA foreign_key_list('wp legacy meta')", 'rows.2.from', 'autoload key'],
    'table fk second parent unquoted' => ["PRAGMA foreign_key_list('wp legacy meta')", 'rows.2.to', 'name key'],
    'table fk update action retained' => ["PRAGMA foreign_key_list('wp legacy meta')", 'rows.1.on_update', 'SET NULL'],
    'table fk delete action retained' => ["PRAGMA foreign_key_list('wp legacy meta')", 'rows.1.on_delete', 'CASCADE'],
    'table fk second update action retained' => ["PRAGMA foreign_key_list('wp legacy meta')", 'rows.2.on_update', 'SET NULL'],
    'table fk second delete action retained' => ["PRAGMA foreign_key_list('wp legacy meta')", 'rows.2.on_delete', 'CASCADE'],
    'table fk default match none' => ["PRAGMA foreign_key_list('wp legacy meta')", 'rows.1.match', 'NONE'],
    'table fk second default match none' => ["PRAGMA foreign_key_list('wp legacy meta')", 'rows.2.match', 'NONE'],
    'table valued rows extracted' => ["pragma_foreign_key_list('wp legacy meta')", 'rows.count', 3],
    'table valued second target table' => ["pragma_foreign_key_list('wp legacy meta')", 'rows.1.table', 'wp option names'],
    'table valued third child column' => ["pragma_foreign_key_list('wp legacy meta')", 'rows.2.from', 'autoload key'],
    'table valued third parent column' => ["pragma_foreign_key_list('wp legacy meta')", 'rows.2.to', 'name key'],
    'implicit parent column remains null' => ["PRAGMA foreign_key_list('wp plain legacy')", 'rows.0.to', null],
    'implicit parent table unquoted' => ["PRAGMA foreign_key_list('wp plain legacy')", 'rows.0.table', 'wp option names'],
    'implicit child column unquoted' => ["PRAGMA foreign_key_list('wp plain legacy')", 'rows.0.from', 'option key'],
    'implicit default update action' => ["PRAGMA foreign_key_list('wp plain legacy')", 'rows.0.on_update', 'NO ACTION'],
    'implicit default delete action' => ["PRAGMA foreign_key_list('wp plain legacy')", 'rows.0.on_delete', 'NO ACTION'],
];

$tests = [];
foreach ($cases as $name => [$sql, $path, $expected]) {
    $tests['schema foreign key current single quoted identifiers ' . $name] = static function (TestRunner $t) use ($makeCatalog, $valueAt, $sql, $path, $expected): void {
        $catalog = $makeCatalog();
        $result = str_starts_with(strtolower($sql), 'pragma_')
            ? $catalog->executeTableValuedPragma($sql)
            : $catalog->execute($sql);
        $t->same($expected, $valueAt($result, $path));
    };
}

$tests['schema foreign key current single quoted identifiers resolve through attached current schema'] = static function (TestRunner $t) use ($record, $valueAt): void {
    $catalog = new SQLiteAttachedSchemaCatalog([], []);
    $catalog->attach('legacy', '/srv/wp/legacy.sqlite', [
        $record('table', 'wp option names', 'wp option names', 2, "CREATE TABLE 'wp option names'('name key' TEXT PRIMARY KEY)"),
        $record('table', 'wp legacy meta', 'wp legacy meta', 3, "CREATE TABLE 'wp legacy meta'('option key' TEXT REFERENCES 'wp option names'('name key') ON DELETE CASCADE)"),
    ]);

    $result = $catalog->executeTableValuedPragma("pragma_foreign_key_list('wp legacy meta')");
    $t->same('legacy', $result['schema']);
    $t->same(1, count($result['rows']));
    $t->same('wp option names', $valueAt($result, 'rows.0.table'));
    $t->same('option key', $valueAt($result, 'rows.0.from'));
    $t->same('name key', $valueAt($result, 'rows.0.to'));
    $t->same('NO ACTION', $valueAt($result, 'rows.0.on_update'));
    $t->same('CASCADE', $valueAt($result, 'rows.0.on_delete'));
    $t->same('NONE', $valueAt($result, 'rows.0.match'));
};

return $tests;
