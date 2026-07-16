<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaIntegritySourceCursor;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $name, int $root, string $sql): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    'table',
    $name,
    $name,
    $root,
    $sql,
    $root,
);

$catalog = static fn (bool $tempOptions = false): SQLiteAttachedSchemaCatalog => new SQLiteAttachedSchemaCatalog(
    [
        $record('wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)'),
        $record('wp_option_names', 3, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)'),
    ],
    $tempOptions ? [
        $record('wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)'),
        $record('wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)'),
    ] : [],
);

$database = str_repeat("\0", 512);
$database = substr_replace($database, "SQLite format 3\0", 0, 16);
$database = substr_replace($database, pack('n', 512), 16, 2);
$database[18] = "\x01";
$database[19] = "\x01";
$database = substr_replace($database, pack('N', 1), 28, 4);
$database = substr_replace($database, pack('N', 1), 56, 4);

$schemas = [
    'main' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 11, 'option_id' => 11, 'option_name' => 'siteurl'],
                ['rowid' => 12, 'option_id' => 12, 'option_name' => 'main_missing'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
        ],
    ],
    'temp' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 'temp-1', 'option_id' => 1, 'option_name' => 'siteurl'],
                ['rowid' => 'temp-2', 'option_id' => 2, 'option_name' => 'temp_missing'],
                ['rowid' => 'temp-3', 'option_id' => 3, 'option_name' => 'temp_missing_again'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 2, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
        ],
    ],
];

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$statementSql = "PRAGMA foreign_key_check('wp_options')";
$tableValuedSql = "SELECT * FROM pragma_foreign_key_check('wp_options')";

$firstStatement = static fn (): array => SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma($database, $schemas, $statementSql, 0, 1, 'PRAGMA quick_check', null, $catalog(false));
$firstTableValued = static fn (): array => SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($database, $schemas, $tableValuedSql, 0, 1, 'PRAGMA quick_check', null, $catalog(false));
$secondStatementSame = static fn (): array => SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma(
    $database,
    $schemas,
    $statementSql,
    1,
    1,
    'PRAGMA quick_check',
    ['source_id' => $firstStatement()['source_id'], 'next_offset' => $firstStatement()['next_offset']],
    $catalog(false),
);
$shadowedStatement = static fn (): array => SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma($database, $schemas, $statementSql, 0, 2, 'PRAGMA quick_check', null, $catalog(true));
$shadowedTableValued = static fn (): array => SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($database, $schemas, $tableValuedSql, 0, 2, 'PRAGMA quick_check', null, $catalog(true));

$cases = [
    'statement first status' => [$firstStatement, 'status', 'ok'],
    'statement first source id length' => [static fn (): array => ['length' => strlen($firstStatement()['source_id'])], 'length', 64],
    'statement first catalog hash length' => [static fn (): array => ['length' => strlen((string) $firstStatement()['current_source']['catalog_hash'])], 'length', 64],
    'statement first schema hash length' => [static fn (): array => ['length' => strlen($firstStatement()['current_source']['schema_hash'])], 'length', 64],
    'statement first normalized pragma sql' => [$firstStatement, 'current_source.foreign_key_sql', "pragma foreign_key_check('wp_options')"],
    'statement first normalized integrity sql' => [$firstStatement, 'current_source.integrity_sql', 'pragma quick_check'],
    'statement first count' => [$firstStatement, 'count', 1],
    'statement first total main only' => [$firstStatement, 'total', 1],
    'statement first next offset null' => [$firstStatement, 'next_offset', null],
    'statement first complete' => [$firstStatement, 'complete', true],
    'statement first current foreign key count' => [$firstStatement, 'current.foreign_key', 1],
    'statement first row schema' => [$firstStatement, 'rows.0.schema', 'main'],
    'statement first row table' => [$firstStatement, 'rows.0.table', 'wp_options'],
    'statement first rowid' => [$firstStatement, 'rows.0.rowid', 12],
    'statement first parent' => [$firstStatement, 'rows.0.parent', 'wp_option_names'],
    'statement first fkid' => [$firstStatement, 'rows.0.fkid', 1],
    'statement first message' => [$firstStatement, 'rows.0.message', 'foreign key mismatch in main.wp_options rowid 12 references wp_option_names fkid 1'],
    'table valued first status' => [$firstTableValued, 'status', 'ok'],
    'table valued source id length' => [static fn (): array => ['length' => strlen($firstTableValued()['source_id'])], 'length', 64],
    'table valued catalog hash length' => [static fn (): array => ['length' => strlen((string) $firstTableValued()['current_source']['catalog_hash'])], 'length', 64],
    'table valued normalized sql' => [$firstTableValued, 'current_source.foreign_key_sql', "select * from pragma_foreign_key_check('wp_options')"],
    'table valued row schema' => [$firstTableValued, 'rows.0.schema', 'main'],
    'table valued rowid' => [$firstTableValued, 'rows.0.rowid', 12],
    'same cursor second status' => [$secondStatementSame, 'status', 'ok'],
    'same cursor second offset' => [$secondStatementSame, 'offset', 1],
    'same cursor second empty count' => [$secondStatementSame, 'count', 0],
    'same cursor second complete' => [$secondStatementSame, 'complete', true],
    'shadowed statement source changes' => [static fn (): array => ['changed' => $firstStatement()['source_id'] !== $shadowedStatement()['source_id']], 'changed', true],
    'shadowed statement catalog hash changes' => [static fn (): array => ['changed' => $firstStatement()['current_source']['catalog_hash'] !== $shadowedStatement()['current_source']['catalog_hash']], 'changed', true],
    'shadowed statement schema hash stable' => [static fn (): array => ['same' => $firstStatement()['current_source']['schema_hash'] === $shadowedStatement()['current_source']['schema_hash']], 'same', true],
    'shadowed statement total temp rows' => [$shadowedStatement, 'total', 2],
    'shadowed statement count' => [$shadowedStatement, 'count', 2],
    'shadowed statement first schema' => [$shadowedStatement, 'rows.0.schema', 'temp'],
    'shadowed statement first rowid' => [$shadowedStatement, 'rows.0.rowid', 'temp-2'],
    'shadowed statement second rowid' => [$shadowedStatement, 'rows.1.rowid', 'temp-3'],
    'shadowed statement first fkid' => [$shadowedStatement, 'rows.0.fkid', 2],
    'shadowed table valued source changes' => [static fn (): array => ['changed' => $firstTableValued()['source_id'] !== $shadowedTableValued()['source_id']], 'changed', true],
    'shadowed table valued first schema' => [$shadowedTableValued, 'rows.0.schema', 'temp'],
    'shadowed table valued second rowid' => [$shadowedTableValued, 'rows.1.rowid', 'temp-3'],
    'shadowed table valued current foreign key count' => [$shadowedTableValued, 'current.foreign_key', 2],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma foreign key integrity pagination current source next97 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma foreign key integrity pagination current source next97 rejects stale statement cursor after temp shadowing'] = static function (TestRunner $t) use ($database, $schemas, $statementSql, $catalog, $firstStatement): void {
    $first = $firstStatement();
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma(
        $database,
        $schemas,
        $statementSql,
        1,
        1,
        'PRAGMA quick_check',
        ['source_id' => $first['source_id'], 'next_offset' => 1],
        $catalog(true),
    ));
};

$tests['pragma foreign key integrity pagination current source next97 rejects stale table valued cursor after temp shadowing'] = static function (TestRunner $t) use ($database, $schemas, $tableValuedSql, $catalog, $firstTableValued): void {
    $first = $firstTableValued();
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma(
        $database,
        $schemas,
        $tableValuedSql,
        1,
        1,
        'PRAGMA quick_check',
        ['source_id' => $first['source_id'], 'next_offset' => 1],
        $catalog(true),
    ));
};

$tests['pragma foreign key integrity pagination current source next97 rejects mismatched next offset'] = static function (TestRunner $t) use ($database, $schemas, $statementSql, $catalog, $firstStatement): void {
    $first = $firstStatement();
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma(
        $database,
        $schemas,
        $statementSql,
        2,
        1,
        'PRAGMA quick_check',
        ['source_id' => $first['source_id'], 'next_offset' => 1],
        $catalog(false),
    ));
};

$tests['pragma foreign key integrity pagination current source next97 rejects negative offset'] = static function (TestRunner $t) use ($database, $schemas, $statementSql, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma($database, $schemas, $statementSql, -1, 1, 'PRAGMA quick_check', null, $catalog(false)));
};

$tests['pragma foreign key integrity pagination current source next97 rejects zero limit'] = static function (TestRunner $t) use ($database, $schemas, $tableValuedSql, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($database, $schemas, $tableValuedSql, 0, 0, 'PRAGMA quick_check', null, $catalog(false)));
};

return $tests;
