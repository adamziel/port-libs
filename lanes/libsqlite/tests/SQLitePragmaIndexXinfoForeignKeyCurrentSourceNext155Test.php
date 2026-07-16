<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record155 = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);
$catalog155 = static function () use ($record155): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record155('table', 'wp_option_names', 'wp_option_names', 4, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 1),
        $record155('table', 'wp_options', 'wp_options', 5, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT REFERENCES wp_option_names(name) ON UPDATE CASCADE ON DELETE RESTRICT, option_value TEXT, autoload TEXT, blog_id INTEGER, FOREIGN KEY(blog_id) REFERENCES wp_blogs(blog_id) ON UPDATE NO ACTION ON DELETE CASCADE)', 2),
        $record155('table', 'wp_blogs', 'wp_blogs', 6, 'CREATE TABLE wp_blogs(blog_id INTEGER PRIMARY KEY)', 3),
        $record155('index', 'wp_options_name_autoload', 'wp_options', 7, 'CREATE INDEX wp_options_name_autoload ON wp_options(option_name COLLATE NOCASE DESC, autoload)', 4),
    ]);
    $catalog->attach('archive', '/tmp/archive.sqlite', [
        $record155('table', 'wp_option_names', 'wp_option_names', 8, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 1),
        $record155('table', 'wp_options', 'wp_options', 9, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT REFERENCES wp_option_names(name), option_value TEXT, autoload TEXT)', 2),
        $record155('index', 'wp_options_archive_name', 'wp_options', 10, 'CREATE INDEX wp_options_archive_name ON wp_options(option_name, autoload DESC)', 3),
    ]);

    return $catalog;
};
$schemas155 = static function (int $missingOptions = 2, int $missingBlogs = 1): array {
    $options = [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'siteurl', 'blog_id' => 1, 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'option_name' => null, 'blog_id' => 1, 'autoload' => 'no'],
    ];
    for ($i = 1; $i <= $missingOptions; $i++) {
        $options[] = ['rowid' => 'option-' . $i, 'option_id' => $i + 2, 'option_name' => 'missing_' . $i, 'blog_id' => 1, 'autoload' => 'no'];
    }
    for ($i = 1; $i <= $missingBlogs; $i++) {
        $options[] = ['rowid' => 'blog-' . $i, 'option_id' => $i + 20, 'option_name' => 'siteurl', 'blog_id' => 404 + $i, 'autoload' => 'yes'];
    }

    return [
        'main' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
                'wp_blogs' => [['rowid' => 1, 'blog_id' => 1]],
                'wp_options' => $options,
            ],
            'foreignKeys' => [
                ['id' => 0, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
                ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_blogs', 'columns' => [['child' => 'blog_id', 'parent' => 'blog_id', 'affinity' => 'integer']]],
            ],
        ],
        'archive' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'home']],
                'wp_options' => [
                    ['rowid' => 1, 'option_id' => 1, 'option_name' => 'home', 'autoload' => 'yes'],
                    ['rowid' => 2, 'option_id' => 2, 'option_name' => 'orphaned', 'autoload' => 'no'],
                ],
            ],
            'foreignKeys' => [
                ['id' => 7, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name']]],
            ],
        ],
    ];
};
$page155 = static fn (
    int $offset = 0,
    int $limit = 155,
    ?array $cursor = null,
    ?array $schemas = null,
    string $indexSql = 'PRAGMA main.index_xinfo(wp_options_name_autoload)',
    string $fkListSql = 'PRAGMA main.foreign_key_list(wp_options)',
    string $fkCheckSql = 'PRAGMA main.foreign_key_check(wp_options)',
    bool $tableValuedIndex = false,
    bool $tableValuedFkList = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page155(
    $catalog155(),
    $schemas ?? $schemas155(),
    $indexSql,
    $fkListSql,
    $fkCheckSql,
    $offset,
    $limit,
    $tableValuedIndex,
    $tableValuedFkList,
    $cursor,
);

$valueAt155 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = is_array($value) && array_key_exists($part, $value) ? $value[$part] : $value[(int) $part];
    }

    return $value;
};

$default155 = static fn (): array => $page155();
$cases155 = [
    'status ok' => [$default155, 'status', 'ok'],
    'default limit' => [$default155, 'limit', 155],
    'total rows' => [$default155, 'total', 8],
    'count rows' => [$default155, 'count', 8],
    'complete true' => [$default155, 'complete', true],
    'next null' => [$default155, 'next', null],
    'current xinfo count' => [$default155, 'current.index_xinfo', 3],
    'current fk list count' => [$default155, 'current.foreign_key_list', 2],
    'current fk check count' => [$default155, 'current.foreign_key_check', 3],
    'current key column option name' => [$default155, 'current.index_key_columns.0', 'option_name'],
    'current key column autoload' => [$default155, 'current.index_key_columns.1', 'autoload'],
    'current aux rowid name null' => [$default155, 'rows.2.name', null],
    'current fk table' => [$default155, 'current.foreign_key_tables.0', 'wp_options'],
    'current fk parent names' => [$default155, 'current.foreign_key_parents.0', 'wp_option_names'],
    'current fk parent blogs' => [$default155, 'current.foreign_key_parents.1', 'wp_blogs'],
    'source id length' => [static fn (): array => ['len' => strlen($page155()['source_id'])], 'len', 64],
    'source catalog length' => [static fn (): array => ['len' => strlen($page155()['current_source']['catalog'])], 'len', 64],
    'source schema length' => [static fn (): array => ['len' => strlen($page155()['current_source']['schemas'])], 'len', 64],
    'source index sql normalized' => [$default155, 'current_source.index_xinfo_sql', 'pragma main.index_xinfo(wp_options_name_autoload)'],
    'source fk list sql normalized' => [$default155, 'current_source.foreign_key_list_sql', 'pragma main.foreign_key_list(wp_options)'],
    'source fk check sql normalized' => [$default155, 'current_source.foreign_key_check_sql', 'pragma main.foreign_key_check(wp_options)'],
    'source table valued index false' => [$default155, 'current_source.table_valued_index_xinfo', false],
    'source table valued fk list false' => [$default155, 'current_source.table_valued_foreign_key_list', false],
    'row0 phase index' => [$default155, 'rows.0.phase', 'index_xinfo'],
    'row0 schema main' => [$default155, 'rows.0.schema', 'main'],
    'row0 target index' => [$default155, 'rows.0.target', 'wp_options_name_autoload'],
    'row0 name option_name' => [$default155, 'rows.0.name', 'option_name'],
    'row0 coll nocase' => [$default155, 'rows.0.coll', 'NOCASE'],
    'row0 desc true' => [$default155, 'rows.0.desc', 1],
    'row1 name autoload' => [$default155, 'rows.1.name', 'autoload'],
    'row2 aux rowid key zero' => [$default155, 'rows.2.key', 0],
    'row3 fk list phase' => [$default155, 'rows.3.phase', 'foreign_key_list'],
    'row3 fk list parent' => [$default155, 'rows.3.parent', 'wp_option_names'],
    'row3 fk list from' => [$default155, 'rows.3.from', 'option_name'],
    'row3 on update' => [$default155, 'rows.3.on_update', 'CASCADE'],
    'row3 on delete' => [$default155, 'rows.3.on_delete', 'RESTRICT'],
    'row4 fk list parent' => [$default155, 'rows.4.parent', 'wp_blogs'],
    'row4 from blog id' => [$default155, 'rows.4.from', 'blog_id'],
    'row5 fk check phase' => [$default155, 'rows.5.phase', 'foreign_key_check'],
    'row5 fk check table' => [$default155, 'rows.5.table', 'wp_options'],
    'row5 fk check rowid' => [$default155, 'rows.5.rowid', 'option-1'],
    'row5 fk check parent' => [$default155, 'rows.5.parent', 'wp_option_names'],
    'row7 blog rowid' => [$default155, 'rows.7.rowid', 'blog-1'],
    'row7 blog fkid' => [$default155, 'rows.7.fkid', 1],
    'offset starts fk check' => [static fn (): array => $page155(5, 2), 'rows.0.kind', 'foreign_key_check'],
    'offset next offset' => [static fn (): array => $page155(5, 2), 'next_offset', 7],
    'tail complete' => [static fn (): array => $page155(10, 5), 'complete', true],
    'past tail count zero' => [static fn (): array => $page155(30, 5), 'count', 0],
    'archive table valued schema' => [static fn (): array => $page155(indexSql: "pragma_index_xinfo('wp_options_archive_name','archive')", fkListSql: "pragma_foreign_key_list('wp_options','archive')", fkCheckSql: "SELECT * FROM pragma_foreign_key_check('archive.wp_options')", tableValuedIndex: true, tableValuedFkList: true), 'rows.0.schema', 'archive'],
    'archive table valued total' => [static fn (): array => $page155(indexSql: "pragma_index_xinfo('wp_options_archive_name','archive')", fkListSql: "pragma_foreign_key_list('wp_options','archive')", fkCheckSql: "SELECT * FROM pragma_foreign_key_check('archive.wp_options')", tableValuedIndex: true, tableValuedFkList: true), 'total', 5],
    'archive fk check rowid' => [static fn (): array => $page155(indexSql: "pragma_index_xinfo('wp_options_archive_name','archive')", fkListSql: "pragma_foreign_key_list('wp_options','archive')", fkCheckSql: "SELECT * FROM pragma_foreign_key_check('archive.wp_options')", tableValuedIndex: true, tableValuedFkList: true), 'rows.4.rowid', 2],
];

$tests = [];
foreach ($cases155 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey current source next155 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt155): void {
        $t->same($expected, $valueAt155($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey current source next155 resumes stable cursor'] = static function (TestRunner $t) use ($page155): void {
    $first = $page155(0, 5);
    $second = $page155(5, 4, $first['next']);

    $t->same(5, $second['offset']);
    $t->same($first['source_id'], $second['source_id']);
    $t->same('foreign_key_check', $second['rows'][0]['kind']);
};

$tests['pragma index xinfo foreignkey current source next155 accepts source only cursor'] = static function (TestRunner $t) use ($page155): void {
    $first = $page155(0, 5);
    $second = $page155(5, 4, ['source_id' => $first['source_id']]);

    $t->same(5, $second['offset']);
    $t->same('foreign_key_check', $second['rows'][0]['kind']);
};

$tests['pragma index xinfo foreignkey current source next155 source changes with schemas'] = static function (TestRunner $t) use ($page155, $schemas155): void {
    $first = $page155(schemas: $schemas155(2, 1));
    $second = $page155(schemas: $schemas155(3, 1));

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(8, $first['total']);
    $t->same(9, $second['total']);
};

$tests['pragma index xinfo foreignkey current source next155 rejects stale source cursor'] = static function (TestRunner $t) use ($page155, $schemas155): void {
    $first = $page155(0, 5);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page155(5, 4, $first['next'], $schemas155(3, 1)));
};

$tests['pragma index xinfo foreignkey current source next155 rejects stale offset cursor'] = static function (TestRunner $t) use ($page155): void {
    $first = $page155(0, 5);
};

$tests['pragma index xinfo foreignkey current source next155 rejects invalid limit'] = static function (TestRunner $t) use ($page155): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page155(0, 0));
};

$tests['pragma index xinfo foreignkey current source next155 rejects non index pragma'] = static function (TestRunner $t) use ($page155): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page155(indexSql: 'PRAGMA main.table_info(wp_options)'));
};

$tests['pragma index xinfo foreignkey current source next155 rejects non fk list pragma'] = static function (TestRunner $t) use ($page155): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page155(fkListSql: 'PRAGMA main.index_list(wp_options)'));
};

return $tests;
