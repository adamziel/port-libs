<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext154;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record154 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog154 = static function (bool $stable = true) use ($record154): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record154('table', 'wp_option_names', 'wp_option_names', 2, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY, blog_id INTEGER DEFAULT 1)', 1),
        $record154(
            'table',
            'wp_options',
            'wp_options',
            3,
            $stable
                ? 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT COLLATE NOCASE, autoload TEXT, blog_id INTEGER DEFAULT 1, FOREIGN KEY(option_name) REFERENCES wp_option_names(name) ON UPDATE CASCADE ON DELETE RESTRICT)'
                : 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT COLLATE NOCASE, autoload TEXT, blog_id INTEGER DEFAULT 1, FOREIGN KEY(option_name) REFERENCES wp_option_names(name) ON UPDATE NO ACTION ON DELETE SET NULL)',
            2,
        ),
        $record154(
            'index',
            'wp_options_name_expr154',
            'wp_options',
            4,
            $stable
                ? 'CREATE INDEX wp_options_name_expr154 ON wp_options(lower(option_name) COLLATE NOCASE DESC, autoload)'
                : 'CREATE INDEX wp_options_name_expr154 ON wp_options(lower(option_name) COLLATE BINARY, autoload DESC)',
            3,
        ),
    ]);
    $catalog->attach('archive', '/tmp/wp-archive.sqlite', [
        $record154('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 1),
        $record154('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, FOREIGN KEY(option_name) REFERENCES wp_option_names(name) ON UPDATE CASCADE ON DELETE RESTRICT)', 2),
        $record154('index', 'wp_options_name_expr154', 'wp_options', 7, 'CREATE INDEX wp_options_name_expr154 ON wp_options(lower(option_name) COLLATE NOCASE DESC)', 3),
    ]);

    return $catalog;
};

$page154 = static fn (
    int $offset = 0,
    int $limit = 154,
    ?array $cursor = null,
    ?SQLiteAttachedSchemaCatalog $current = null,
    ?SQLiteAttachedSchemaCatalog $next = null,
    string $indexSql = 'PRAGMA main.index_xinfo(wp_options_name_expr154)',
    string $fkSql = 'PRAGMA main.foreign_key_list(wp_options)',
    bool $tableValuedIndex = false,
    bool $tableValuedFk = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext154::currentNextPage(
    $current ?? $catalog154(),
    $next ?? $catalog154(),
    $indexSql,
    $fkSql,
    $offset,
    $limit,
    $tableValuedIndex,
    $tableValuedFk,
    $cursor,
);

$valueAt154 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$stable154 = static fn (): array => $page154();
$drift154 = static fn (): array => $page154(next: $catalog154(false));
$archive154 = static fn (): array => $page154(
    indexSql: "pragma_index_xinfo('wp_options_name_expr154','archive')",
    fkSql: "pragma_foreign_key_list('wp_options','archive')",
    tableValuedIndex: true,
    tableValuedFk: true,
);

$cases154 = [
    'stable status ok' => [$stable154, 'status', 'ok'],
    'stable total rows' => [$stable154, 'total', 8],
    'stable count rows' => [$stable154, 'count', 8],
    'stable complete' => [$stable154, 'complete', true],
    'stable next null' => [$stable154, 'next', null],
    'stable ready true' => [$stable154, 'next_state.ready', true],
    'stable current index rows' => [$stable154, 'current.index_xinfo', 3],
    'stable current fk rows' => [$stable154, 'current.foreign_key_list', 1],
    'stable key columns' => [$stable154, 'current.index_key_columns', 2],
    'stable aux columns' => [$stable154, 'current.index_aux_columns', 1],
    'stable expression columns' => [$stable154, 'current.index_expression_columns', 1],
    'stable collation nocase' => [$stable154, 'current.index_collations.0', 'NOCASE'],
    'stable fk action' => [$stable154, 'current.foreign_key_actions.0', 'CASCADE/RESTRICT'],
    'stable fk parent' => [$stable154, 'current.foreign_key_parents.0', 'wp_option_names'],
    'stable schema main' => [$stable154, 'current.schemas.0', 'main'],
    'stable targets index first' => [$stable154, 'current.targets.0', 'wp_options_name_expr154'],
    'stable targets table second' => [$stable154, 'current.targets.1', 'wp_options'],
    'stable next index rows' => [$stable154, 'next_counts.index_xinfo', 3],
    'stable next fk rows' => [$stable154, 'next_counts.foreign_key_list', 1],
    'stable delta index zero' => [$stable154, 'delta.index_xinfo', 0],
    'stable delta fk zero' => [$stable154, 'delta.foreign_key_list', 0],
    'stable delta index unchanged' => [$stable154, 'delta.index_changed', false],
    'stable delta fk unchanged' => [$stable154, 'delta.foreign_key_changed', false],
    'stable delta stable true' => [$stable154, 'delta.stable', true],
    'stable source index sql' => [$stable154, 'current_source.index_xinfo_sql', 'pragma main.index_xinfo(wp_options_name_expr154)'],
    'stable source fk sql' => [$stable154, 'current_source.foreign_key_list_sql', 'pragma main.foreign_key_list(wp_options)'],
    'stable source table valued index false' => [$stable154, 'current_source.table_valued_index_xinfo', false],
    'stable source table valued fk false' => [$stable154, 'current_source.table_valued_foreign_key_list', false],
    'row0 current index phase' => [$stable154, 'rows.0.phase', 'index_xinfo'],
    'row0 expression name null' => [$stable154, 'rows.0.name', null],
    'row0 expression cid' => [$stable154, 'rows.0.cid', -2],
    'row0 desc' => [$stable154, 'rows.0.desc', 1],
    'row0 collation' => [$stable154, 'rows.0.coll', 'NOCASE'],
    'row1 autoload name' => [$stable154, 'rows.1.name', 'autoload'],
    'row2 aux expression rowid' => [$stable154, 'rows.2.name', null],
    'row3 fk phase' => [$stable154, 'rows.3.phase', 'foreign_key_list'],
    'row3 fk from' => [$stable154, 'rows.3.from', 'option_name'],
    'row3 fk to' => [$stable154, 'rows.3.to', 'name'],
    'row3 fk update' => [$stable154, 'rows.3.on_update', 'CASCADE'],
    'row3 fk delete' => [$stable154, 'rows.3.on_delete', 'RESTRICT'],
    'row4 next side' => [$stable154, 'rows.4.side', 'next'],
    'row7 next fk action' => [$stable154, 'rows.7.on_delete', 'RESTRICT'],
    'drift status blocked' => [$drift154, 'status', 'blocked'],
    'drift ready false' => [$drift154, 'next_state.ready', false],
    'drift index changed' => [$drift154, 'delta.index_changed', true],
    'drift fk changed' => [$drift154, 'delta.foreign_key_changed', true],
    'drift stable false' => [$drift154, 'delta.stable', false],
    'drift index removed count' => [$drift154, 'delta.index_removed.0', $drift154()['delta']['index_removed'][0]],
    'drift fk added count' => [$drift154, 'delta.foreign_key_added.0', $drift154()['delta']['foreign_key_added'][0]],
    'drift blocker index' => [$drift154, 'next_state.blocking.0', 'index_xinfo_drift'],
    'drift blocker fk' => [$drift154, 'next_state.blocking.1', 'foreign_key_list_drift'],
    'drift next row collation binary' => [$drift154, 'rows.4.coll', 'BINARY'],
    'drift next fk update no action' => [$drift154, 'rows.7.on_update', 'NO ACTION'],
    'drift next fk delete set null' => [$drift154, 'rows.7.on_delete', 'SET NULL'],
    'archive status ok' => [$archive154, 'status', 'ok'],
    'archive source table valued index' => [$archive154, 'current_source.table_valued_index_xinfo', true],
    'archive source table valued fk' => [$archive154, 'current_source.table_valued_foreign_key_list', true],
    'archive source index sql' => [$archive154, 'current_source.index_xinfo_sql', "pragma_index_xinfo('wp_options_name_expr154','archive')"],
    'archive schema' => [$archive154, 'current.schemas.0', 'archive'],
    'archive index rows' => [$archive154, 'current.index_xinfo', 2],
    'archive fk rows' => [$archive154, 'current.foreign_key_list', 1],
    'archive row0 target' => [$archive154, 'rows.0.target', 'wp_options_name_expr154'],
    'archive row2 fk schema' => [$archive154, 'rows.2.schema', 'archive'],
    'archive row2 fk table' => [$archive154, 'rows.2.table', 'wp_option_names'],
];

$tests = [];
foreach ($cases154 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey current source next154 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt154): void {
        $t->same($expected, $valueAt154($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey current source next154 paginates stable source'] = static function (TestRunner $t) use ($page154): void {
    $first = $page154(0, 3);
    $second = $page154(3, 3, $first['next']);
    $third = $page154(6, 3, $second['next']);

    $t->same(3, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 3], $first['next']);
    $t->same('foreign_key_list', $second['rows'][0]['phase']);
    $t->same('next', $second['rows'][1]['side']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey current source next154 source changes with next catalog'] = static function (TestRunner $t) use ($page154, $catalog154): void {
    $stable = $page154();
    $drift = $page154(next: $catalog154(false));

    $t->same(true, $stable['source_id'] !== $drift['source_id']);
    $t->same(true, $stable['next_source']['catalog'] !== $drift['next_source']['catalog']);
};

$tests['pragma index xinfo foreignkey current source next154 rejects stale cursor'] = static function (TestRunner $t) use ($page154, $catalog154): void {
    $first = $page154(0, 3);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page154(3, 3, $first['next'], next: $catalog154(false)));
};

$tests['pragma index xinfo foreignkey current source next154 rejects stale offset'] = static function (TestRunner $t) use ($page154): void {
    $first = $page154(0, 3);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page154(4, 3, $first['next']));
};

$tests['pragma index xinfo foreignkey current source next154 rejects missing index'] = static function (TestRunner $t) use ($page154): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page154(indexSql: 'PRAGMA main.index_xinfo(missing_index)'));
};

$tests['pragma index xinfo foreignkey current source next154 rejects missing fk list'] = static function (TestRunner $t) use ($page154): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page154(fkSql: 'PRAGMA main.foreign_key_list(missing_table)'));
};

return $tests;
