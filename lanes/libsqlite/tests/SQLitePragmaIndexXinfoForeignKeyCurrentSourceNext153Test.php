<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext153;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$catalog = static function (bool $drift = false) use ($record): SQLiteAttachedSchemaCatalog {
    return new SQLiteAttachedSchemaCatalog([
        $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT)', 1),
        $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 2),
        $record(
            'index',
            'wp_options_name_autoload',
            'wp_options',
            6,
            $drift
                ? 'CREATE UNIQUE INDEX wp_options_name_autoload ON wp_options(option_name COLLATE binary, autoload DESC)'
                : 'CREATE UNIQUE INDEX wp_options_name_autoload ON wp_options(option_name COLLATE nocase DESC, autoload)',
            3,
        ),
        $record('index', 'wp_options_value_expr', 'wp_options', 7, 'CREATE INDEX wp_options_value_expr ON wp_options(length(option_value), autoload DESC)', 4),
    ]);
};

$schemas = static function (int $missing = 2): array {
    $options = [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ];
    for ($i = 1; $i <= $missing; $i++) {
        $options[] = [
            'rowid' => 'missing-' . $i,
            'option_id' => $i + 1,
            'option_name' => 'missing_plugin_' . $i,
            'option_value' => 'a:0:{}',
            'autoload' => 'no',
        ];
    }

    return [
        'main' => [
            'tables' => [
                'wp_option_names' => [
                    ['rowid' => 1, 'name' => 'siteurl'],
                ],
                'wp_options' => $options,
            ],
            'foreignKeys' => [
                ['id' => 153, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
    ];
};

$page = static fn (
    int $offset = 0,
    int $limit = 153,
    ?array $cursor = null,
    ?SQLiteAttachedSchemaCatalog $nextCatalog = null,
    ?array $nextSchemas = null,
    string $indexSql = 'PRAGMA main.index_xinfo(wp_options_name_autoload)',
    string $foreignKeySql = 'PRAGMA main.foreign_key_check(wp_options)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext153::page(
    $catalog(),
    $nextCatalog ?? $catalog(),
    $indexSql,
    $schemas(3),
    $nextSchemas ?? $schemas(0),
    $foreignKeySql,
    $offset,
    $limit,
    $tableValued,
    $cursor,
);

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$cases = [
    'status ok after next fk repair' => [static fn (): array => $page(), 'status', 'ok'],
    'default limit next153' => [static fn (): array => $page(), 'limit', 153],
    'total current plus next xinfo fk' => [static fn (): array => $page(), 'total', 9],
    'count full page' => [static fn (): array => $page(), 'count', 9],
    'complete true' => [static fn (): array => $page(), 'complete', true],
    'source id length' => [static fn (): array => ['length' => strlen($page()['source_id'])], 'length', 64],
    'current source hash length' => [static fn (): array => ['length' => strlen($page()['current_source']['catalog_hash'])], 'length', 64],
    'next source hash length' => [static fn (): array => ['length' => strlen($page()['next_source']['catalog_hash'])], 'length', 64],
    'current normalized index sql' => [static fn (): array => $page(), 'current_source.index_xinfo_sql', 'pragma main.index_xinfo(wp_options_name_autoload)'],
    'current normalized fk sql' => [static fn (): array => $page(), 'current_source.foreign_key_sql', 'pragma main.foreign_key_check(wp_options)'],
    'current index schema main' => [static fn (): array => $page(), 'current_source.index_schema', 'main'],
    'current index target' => [static fn (): array => $page(), 'current_source.index_target', 'wp_options_name_autoload'],
    'current table valued false' => [static fn (): array => $page(), 'current_source.index_table_valued', false],
    'current fk table valued false' => [static fn (): array => $page(), 'current_source.foreign_key_table_valued', false],
    'current index xinfo count' => [static fn (): array => $page(), 'current.index_xinfo', 3],
    'current key columns count' => [static fn (): array => $page(), 'current.index_key_columns', 2],
    'current auxiliary columns count' => [static fn (): array => $page(), 'current.index_auxiliary_columns', 1],
    'current expression count zero' => [static fn (): array => $page(), 'current.index_expression_columns', 0],
    'current fk count three' => [static fn (): array => $page(), 'current.foreign_key', 3],
    'current fk tables one' => [static fn (): array => $page(), 'current.foreign_key_tables', 1],
    'next index xinfo count' => [static fn (): array => $page(), 'next_counts.index_xinfo', 3],
    'next key columns count' => [static fn (): array => $page(), 'next_counts.index_key_columns', 2],
    'next auxiliary columns count' => [static fn (): array => $page(), 'next_counts.index_auxiliary_columns', 1],
    'next fk count zero' => [static fn (): array => $page(), 'next_counts.foreign_key', 0],
    'delta index unchanged' => [static fn (): array => $page(), 'delta.index_signature_changed', false],
    'delta xinfo count stable' => [static fn (): array => $page(), 'delta.index_xinfo', 0],
    'delta key columns stable' => [static fn (): array => $page(), 'delta.index_key_columns', 0],
    'delta fk cleared count' => [static fn (): array => $page(), 'delta.foreign_key', -3],
    'delta fk tables cleared count' => [static fn (): array => $page(), 'delta.foreign_key_tables', -1],
    'delta foreign keys cleared true' => [static fn (): array => $page(), 'delta.foreign_keys_cleared', true],
    'next ready true' => [static fn (): array => $page(), 'next_state.ready', true],
    'next blocking empty' => [static fn (): array => $page(), 'next_state.blocking', []],
    'row0 current side' => [static fn (): array => $page(), 'rows.0.side', 'current'],
    'row0 index kind' => [static fn (): array => $page(), 'rows.0.kind', 'index_xinfo'],
    'row0 index phase' => [static fn (): array => $page(), 'rows.0.phase', 'index_xinfo'],
    'row0 index name' => [static fn (): array => $page(), 'rows.0.name', 'option_name'],
    'row0 index desc' => [static fn (): array => $page(), 'rows.0.desc', 1],
    'row0 index coll nocase' => [static fn (): array => $page(), 'rows.0.coll', 'NOCASE'],
    'row1 autoload key' => [static fn (): array => $page(), 'rows.1.key', 1],
    'row1 autoload desc zero' => [static fn (): array => $page(), 'rows.1.desc', 0],
    'row2 rowid auxiliary' => [static fn (): array => $page(), 'rows.2.key', 0],
    'row2 rowid cid' => [static fn (): array => $page(), 'rows.2.cid', -1],
    'row3 fk kind' => [static fn (): array => $page(), 'rows.3.kind', 'foreign_key_check'],
    'row3 fk table' => [static fn (): array => $page(), 'rows.3.table', 'wp_options'],
    'row3 fk rowid' => [static fn (): array => $page(), 'rows.3.rowid', 'missing-1'],
    'row3 fk parent' => [static fn (): array => $page(), 'rows.3.parent', 'wp_option_names'],
    'row3 fk id' => [static fn (): array => $page(), 'rows.3.fkid', 153],
    'row3 fk message' => [static fn (): array => $page(), 'rows.3.message', 'foreign key mismatch in main.wp_options rowid missing-1 references wp_option_names fkid 153'],
    'row6 next side' => [static fn (): array => $page(), 'rows.6.side', 'next'],
    'row6 next index' => [static fn (): array => $page(), 'rows.6.kind', 'index_xinfo'],
    'row8 next auxiliary rowid' => [static fn (): array => $page(), 'rows.8.cid', -1],
    'page first count' => [static fn (): array => $page(0, 4), 'count', 4],
    'page first next offset' => [static fn (): array => $page(0, 4), 'next.offset', 4],
    'page second offset' => [static fn (): array => $page(4, 4, $page(0, 4)['next']), 'offset', 4],
    'page second starts fk' => [static fn (): array => $page(4, 4, $page(0, 4)['next']), 'rows.0.kind', 'foreign_key_check'],
    'page third complete' => [static fn (): array => $page(8, 4, $page(4, 4, $page(0, 4)['next'])['next']), 'complete', true],
    'past tail count zero' => [static fn (): array => $page(20, 4), 'count', 0],
    'table valued index expression count' => [static fn (): array => $page(0, 153, null, null, null, "pragma_index_xinfo('wp_options_value_expr','main')", "SELECT * FROM pragma_foreign_key_check('wp_options')", true), 'current.index_expression_columns', 1],
    'table valued index target' => [static fn (): array => $page(0, 153, null, null, null, "pragma_index_xinfo('wp_options_value_expr','main')", "SELECT * FROM pragma_foreign_key_check('wp_options')", true), 'current_source.index_target', 'wp_options_value_expr'],
    'table valued fk flag true' => [static fn (): array => $page(0, 153, null, null, null, "pragma_index_xinfo('wp_options_value_expr','main')", "SELECT * FROM pragma_foreign_key_check('wp_options')", true), 'current_source.foreign_key_table_valued', true],
    'table valued expression row cid' => [static fn (): array => $page(0, 153, null, null, null, "pragma_index_xinfo('wp_options_value_expr','main')", "SELECT * FROM pragma_foreign_key_check('wp_options')", true), 'rows.0.cid', -2],
    'table valued expression row name null' => [static fn (): array => $page(0, 153, null, null, null, "pragma_index_xinfo('wp_options_value_expr','main')", "SELECT * FROM pragma_foreign_key_check('wp_options')", true), 'rows.0.name', null],
];

$tests = [];
foreach ($cases as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey current source next153 ' . $name] = static function (TestRunner $t) use ($factory, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey current source next153 blocks on next index drift'] = static function (TestRunner $t) use ($page, $catalog, $schemas): void {
    $result = $page(0, 153, null, $catalog(true), $schemas(0));

    $t->same('blocked', $result['status']);
    $t->same(false, $result['next_state']['ready']);
    $t->same(['index_xinfo_drift'], $result['next_state']['blocking']);
    $t->same(true, $result['delta']['index_signature_changed']);
    $t->same('BINARY', $result['rows'][6]['coll']);
    $t->same(0, $result['next_counts']['foreign_key']);
};

$tests['pragma index xinfo foreignkey current source next153 blocks on remaining next foreign keys'] = static function (TestRunner $t) use ($page, $schemas): void {
    $result = $page(0, 153, null, null, $schemas(1));

    $t->same('blocked', $result['status']);
    $t->same(false, $result['next_state']['ready']);
    $t->same(['foreign_key_check'], $result['next_state']['blocking']);
    $t->same(1, $result['next_counts']['foreign_key']);
    $t->same(-2, $result['delta']['foreign_key']);
};

$tests['pragma index xinfo foreignkey current source next153 source changes with schemas'] = static function (TestRunner $t) use ($page, $schemas): void {
    $first = $page(0, 153, null, null, $schemas(0));
    $second = $page(0, 153, null, null, $schemas(2));

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(0, $first['next_counts']['foreign_key']);
    $t->same(2, $second['next_counts']['foreign_key']);
};

$tests['pragma index xinfo foreignkey current source next153 source changes with next catalog'] = static function (TestRunner $t) use ($page, $catalog): void {
    $first = $page();
    $second = $page(0, 153, null, $catalog(true));

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same($first['current_source']['catalog_hash'], $second['current_source']['catalog_hash']);
    $t->same(true, $first['next_source']['catalog_hash'] !== $second['next_source']['catalog_hash']);
};

$tests['pragma index xinfo foreignkey current source next153 rejects stale cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 4);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page(5, 4, $first['next']));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page(4, 4, ['source_id' => str_repeat('0', 64), 'offset' => 4]));
};

$tests['pragma index xinfo foreignkey current source next153 rejects invalid bounds and pragma'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page(-1));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page(0, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page(0, 153, null, null, null, 'PRAGMA main.table_info(wp_options)'));
};

return $tests;
