<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $root,
    ?string $sql = null,
    int $rowId = 1,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$makeCatalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', 'app_parent', 'app_parent', 2, 'CREATE TABLE app_parent(a INT PRIMARY KEY, b TEXT, c TEXT)', 1),
            $record('index', 'sqlite_autoindex_app_parent_1', 'app_parent', 3, null, 2),
            $record('index', 'app_parent_b_c', 'app_parent', 4, 'CREATE INDEX app_parent_b_c ON app_parent(b, c)', 3),
            $record('table', 'app_child', 'app_child', 5, 'CREATE TABLE app_child(x INT, y TEXT, z INT REFERENCES app_parent(a))', 4),
            $record('view', 'app_parent_view', 'app_parent_view', null, 'CREATE VIEW app_parent_view AS SELECT nosuchfunc(a) FROM app_parent', 5),
        ],
        [
            $record('table', 'app_parent', 'app_parent', 6, 'CREATE TABLE app_parent(d INT PRIMARY KEY, e TEXT, f TEXT)', 1),
            $record('index', 'sqlite_autoindex_app_parent_1', 'app_parent', 7, null, 2),
            $record('index', 'app_parent_e_f', 'app_parent', 8, 'CREATE INDEX app_parent_e_f ON app_parent(e, f)', 3),
            $record('table', 'app_child', 'app_child', 9, 'CREATE TABLE app_child(d INT, e TEXT, r INT REFERENCES app_parent(d))', 4),
        ],
    );

    $catalog->attach('aux', '/tmp/app-aux.sqlite', [
        $record('table', 'app_parent_aux', 'app_parent_aux', 10, 'CREATE TABLE app_parent_aux(d INT PRIMARY KEY, e TEXT, f TEXT)', 1),
        $record('index', 'sqlite_autoindex_app_parent_aux_1', 'app_parent_aux', 11, null, 2),
        $record('index', 'app_parent_aux_e_f', 'app_parent_aux', 12, 'CREATE INDEX app_parent_aux_e_f ON app_parent_aux(e, f)', 3),
        $record('table', 'app_child_aux', 'app_child_aux', 13, 'CREATE TABLE app_child_aux(d INT, e TEXT, r INT REFERENCES app_parent_aux(d))', 4),
    ]);

    return $catalog;
};

$at = static function (mixed $value, string $path): mixed {
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
    ['pragma4-4.2.2 table info main first column', "pragma_table_info('app_parent','main')", 'main', 'app_parent', 'rows.0.name', 'a'],
    ['pragma4-4.2.2 table info main second column', "pragma_table_info('app_parent','main')", 'main', 'app_parent', 'rows.1.name', 'b'],
    ['pragma4-4.2.2 table info main third column', "pragma_table_info('app_parent','main')", 'main', 'app_parent', 'rows.2.name', 'c'],
    ['pragma4-4.2.2 table info main primary key', "pragma_table_info('app_parent','main')", 'main', 'app_parent', 'rows.0.pk', 1],
    ['pragma4-4.2.2 table info main cid', "pragma_table_info('app_parent','main')", 'main', 'app_parent', 'rows.2.cid', 2],
    ['pragma4-4.2.3 table info temp first column', "pragma_table_info('app_parent')", 'temp', 'app_parent', 'rows.0.name', 'd'],
    ['pragma4-4.2.3 table info temp second column', "pragma_table_info('app_parent')", 'temp', 'app_parent', 'rows.1.name', 'e'],
    ['pragma4-4.2.3 table info temp third column', "pragma_table_info('app_parent')", 'temp', 'app_parent', 'rows.2.name', 'f'],
    ['pragma4-4.2.3 table info temp primary key', "pragma_table_info('app_parent')", 'temp', 'app_parent', 'rows.0.pk', 1],
    ['pragma4-4.2.3 table info aux first column', "pragma_table_info('app_parent_aux','aux')", 'aux', 'app_parent_aux', 'rows.0.name', 'd'],
    ['pragma4-4.2.3 table info aux second column', "pragma_table_info('app_parent_aux','aux')", 'aux', 'app_parent_aux', 'rows.1.name', 'e'],
    ['pragma4-4.2.3 table info aux third column', "pragma_table_info('app_parent_aux','aux')", 'aux', 'app_parent_aux', 'rows.2.name', 'f'],
    ['pragma4-4.3.2 index info main first seqno', "pragma_index_info('app_parent_b_c','main')", 'main', 'app_parent_b_c', 'rows.0.seqno', 0],
    ['pragma4-4.3.2 index info main first cid', "pragma_index_info('app_parent_b_c','main')", 'main', 'app_parent_b_c', 'rows.0.cid', 1],
    ['pragma4-4.3.2 index info main first name', "pragma_index_info('app_parent_b_c','main')", 'main', 'app_parent_b_c', 'rows.0.name', 'b'],
    ['pragma4-4.3.2 index info main second cid', "pragma_index_info('app_parent_b_c','main')", 'main', 'app_parent_b_c', 'rows.1.cid', 2],
    ['pragma4-4.3.2 index info main second name', "pragma_index_info('app_parent_b_c','main')", 'main', 'app_parent_b_c', 'rows.1.name', 'c'],
    ['pragma4-4.3.3 index info temp first name', "pragma_index_info('app_parent_e_f')", 'temp', 'app_parent_e_f', 'rows.0.name', 'e'],
    ['pragma4-4.3.3 index info temp second name', "pragma_index_info('app_parent_e_f')", 'temp', 'app_parent_e_f', 'rows.1.name', 'f'],
    ['pragma4-4.3.3 index info aux first name', "pragma_index_info('app_parent_aux_e_f','aux')", 'aux', 'app_parent_aux_e_f', 'rows.0.name', 'e'],
    ['pragma4-4.3.3 index info aux second name', "pragma_index_info('app_parent_aux_e_f','aux')", 'aux', 'app_parent_aux_e_f', 'rows.1.name', 'f'],
    ['pragma4-4.4.1 index list main first name', "pragma_index_list('app_parent','main')", 'main', 'app_parent', 'rows.0.name', 'sqlite_autoindex_app_parent_1'],
    ['pragma4-4.4.1 index list main first origin', "pragma_index_list('app_parent','main')", 'main', 'app_parent', 'rows.0.origin', 'u'],
    ['pragma4-4.4.1 index list main second name', "pragma_index_list('app_parent','main')", 'main', 'app_parent', 'rows.1.name', 'app_parent_b_c'],
    ['pragma4-4.4.1 index list main second origin', "pragma_index_list('app_parent','main')", 'main', 'app_parent', 'rows.1.origin', 'c'],
    ['pragma4-4.4.2 index list temp first name', "pragma_index_list('app_parent')", 'temp', 'app_parent', 'rows.0.name', 'sqlite_autoindex_app_parent_1'],
    ['pragma4-4.4.2 index list temp second name', "pragma_index_list('app_parent')", 'temp', 'app_parent', 'rows.1.name', 'app_parent_e_f'],
    ['pragma4-4.4.2 index list aux first name', "pragma_index_list('app_parent_aux','aux')", 'aux', 'app_parent_aux', 'rows.0.name', 'sqlite_autoindex_app_parent_aux_1'],
    ['pragma4-4.4.2 index list aux second name', "pragma_index_list('app_parent_aux','aux')", 'aux', 'app_parent_aux', 'rows.1.name', 'app_parent_aux_e_f'],
    ['pragma4-4.5.1 foreign key main parent table', "pragma_foreign_key_list('app_child','main')", 'main', 'app_child', 'rows.0.table', 'app_parent'],
    ['pragma4-4.5.1 foreign key main from column', "pragma_foreign_key_list('app_child','main')", 'main', 'app_child', 'rows.0.from', 'z'],
    ['pragma4-4.5.1 foreign key main to column', "pragma_foreign_key_list('app_child','main')", 'main', 'app_child', 'rows.0.to', 'a'],
    ['pragma4-4.5.2 foreign key temp parent table', "pragma_foreign_key_list('app_child')", 'temp', 'app_child', 'rows.0.table', 'app_parent'],
    ['pragma4-4.5.2 foreign key temp from column', "pragma_foreign_key_list('app_child')", 'temp', 'app_child', 'rows.0.from', 'r'],
    ['pragma4-4.5.2 foreign key temp to column', "pragma_foreign_key_list('app_child')", 'temp', 'app_child', 'rows.0.to', 'd'],
    ['pragma4-4.5.2 foreign key aux parent table', "pragma_foreign_key_list('app_child_aux','aux')", 'aux', 'app_child_aux', 'rows.0.table', 'app_parent_aux'],
    ['pragma4-4.5.2 foreign key aux from column', "pragma_foreign_key_list('app_child_aux','aux')", 'aux', 'app_child_aux', 'rows.0.from', 'r'],
    ['pragma4-4.5.2 foreign key aux to column', "pragma_foreign_key_list('app_child_aux','aux')", 'aux', 'app_child_aux', 'rows.0.to', 'd'],
    ['pragma4-6.0 table list includes temp parent', 'pragma_table_list()', 'main', '', 'rows.0.name', 'app_parent'],
    ['pragma4-6.0 table list temp schema first', 'pragma_table_list()', 'main', '', 'rows.0.schema', 'temp'],
    ['pragma4-6.0 table list includes main parent', "pragma_table_list('app_parent','main')", 'main', 'app_parent', 'rows.0.name', 'app_parent'],
    ['pragma4-6.0 table list main schema', "pragma_table_list('app_parent','main')", 'main', 'app_parent', 'rows.0.schema', 'main'],
    ['pragma4-6.0 table list includes corrupt view', "pragma_table_list('app_parent_view','main')", 'main', 'app_parent_view', 'rows.0.name', 'app_parent_view'],
    ['pragma4-6.0 table list corrupt view type', "pragma_table_list('app_parent_view','main')", 'main', 'app_parent_view', 'rows.0.type', 'view'],
    ['pragma4-6.0 table list includes aux parent', "pragma_table_list('app_parent_aux','aux')", 'aux', 'app_parent_aux', 'rows.0.name', 'app_parent_aux'],
    ['pragma4-6.0 table list aux schema', "pragma_table_list('app_parent_aux','aux')", 'aux', 'app_parent_aux', 'rows.0.schema', 'aux'],
    ['pragma4-6.0 table list filtered temp count', "pragma_table_list('app_parent')", 'main', 'app_parent', 'rows.count', 2],
    ['pragma4-6.0 table list filtered temp schema', "pragma_table_list('app_parent')", 'main', 'app_parent', 'rows.0.schema', 'temp'],
    ['pragma4-6.0 table list filtered main schema', "pragma_table_list('app_parent')", 'main', 'app_parent', 'rows.1.schema', 'main'],
    ['pragma4-6.0 table list schema-pinned aux count', "pragma_table_list('app_parent_aux','aux')", 'aux', 'app_parent_aux', 'rows.count', 1],
    ['pragma4-6.0 table list schema-pinned aux ncol', "pragma_table_list('app_parent_aux','aux')", 'aux', 'app_parent_aux', 'rows.0.ncol', 3],
    ['pragma4-6.0 table list schema-pinned main view', "pragma_table_list('app_parent_view','main')", 'main', 'app_parent_view', 'rows.0.type', 'view'],
    ['pragma4-7.3 table-info join column a exists', "pragma_table_info('app_parent','main')", 'main', 'app_parent', 'rows.0.name', 'a'],
    ['pragma4-7.3 table-info join column b exists', "pragma_table_info('app_parent','main')", 'main', 'app_parent', 'rows.1.name', 'b'],
    ['pragma4-7.3 table-info join column c exists', "pragma_table_info('app_parent','main')", 'main', 'app_parent', 'rows.2.name', 'c'],
    ['pragma4-7.3 table-info right-side wider table d', "pragma_table_info('app_parent_aux','aux')", 'aux', 'app_parent_aux', 'rows.0.name', 'd'],
    ['pragma4-7.3 table-info right-side wider table e', "pragma_table_info('app_parent_aux','aux')", 'aux', 'app_parent_aux', 'rows.1.name', 'e'],
    ['pragma4-7.3 table-info right-side wider table f', "pragma_table_info('app_parent_aux','aux')", 'aux', 'app_parent_aux', 'rows.2.name', 'f'],
];

$tests = [];
foreach ($cases as [$name, $sql, $schema, $target, $path, $expected]) {
    $tests['real upstream pragma schema dynamic ' . $name] = static function (TestRunner $t) use ($makeCatalog, $at, $sql, $schema, $target, $path, $expected): void {
        $result = $makeCatalog()->executeTableValuedPragma($sql);

        $t->same('ok', $result['status']);
        $t->same($schema, $result['schema']);
        $t->same($target, $result['target']);
        $t->same($expected, $at($result, $path));
        $t->same(true, is_array($result['rows']));
        $t->same(true, count($result['rows']) >= 1);
        $t->same(true, array_key_exists('pragma', $result));
        $t->same(true, $result['pragma'] !== '');
        $t->same(true, array_key_exists('rows', $result));
    };
}

$tests['real upstream pragma schema dynamic table-valued rows disappear after detach'] = static function (TestRunner $t) use ($makeCatalog): void {
    $catalog = $makeCatalog();
    $t->same(2, count($catalog->executeTableValuedPragma("pragma_index_info('app_parent_aux_e_f','aux')")['rows']));
    $catalog->detach('aux');
    $t->throws(InvalidArgumentException::class, static fn () => $catalog->executeTableValuedPragma("pragma_index_info('app_parent_aux_e_f','aux')"));
    $t->same(0, count($catalog->executeTableValuedPragma("pragma_table_list('app_parent_aux')")['rows']));
    $t->same(3, count($catalog->executeTableValuedPragma("pragma_table_info('app_parent')")['rows']));
    $t->same('temp', $catalog->executeTableValuedPragma("pragma_table_info('app_parent')")['schema']);
    $t->same(0, count($catalog->executeTableValuedPragma("pragma_foreign_key_list('app_child_aux')")['rows']));
};

$tests['real upstream pragma schema dynamic cursor freezes rows across schema detach'] = static function (TestRunner $t) use ($makeCatalog): void {
    $catalog = $makeCatalog();
    $cursor = $catalog->executeTableValuedPragmaCursor("pragma_table_info('app_parent_aux','aux')");
    $catalog->detach('aux');

    $t->same('table_info', $cursor->metadata()['pragma']);
    $t->same('aux', $cursor->metadata()['schema']);
    $t->same(3, $cursor->metadata()['row_count']);
    $t->same('d', $cursor->current()['name']);
    $t->same('e', $cursor->next()['name']);
    $t->same('f', $cursor->next()['name']);
    $t->same(null, $cursor->next());
};

$tests['real upstream pragma schema dynamic foreign-key metadata follows dropped child table'] = static function (TestRunner $t) use ($makeCatalog): void {
    $catalog = $makeCatalog();
    $before = $catalog->executeTableValuedPragma("pragma_foreign_key_list('app_child_aux','aux')");

    $t->same('ok', $before['status']);
    $t->same('aux', $before['schema']);
    $t->same('app_child_aux', $before['target']);
    $t->same(1, count($before['rows']));
    $t->same('app_parent_aux', $before['rows'][0]['table']);
    $t->same('r', $before['rows'][0]['from']);
    $catalog->detach('aux');
    $t->same(0, count($catalog->executeTableValuedPragma("pragma_foreign_key_list('app_child_aux')")['rows']));
    $t->throws(InvalidArgumentException::class, static fn () => $catalog->executeTableValuedPragma("pragma_foreign_key_list('app_child_aux','aux')"));
};

$tests['real upstream pragma schema dynamic table-list tolerates invalid view SQL'] = static function (TestRunner $t) use ($makeCatalog): void {
    $rows = $makeCatalog()->executeTableValuedPragma('pragma_table_list()')['rows'];
    $viewRows = array_values(array_filter($rows, static fn (array $row): bool => $row['name'] === 'app_parent_view'));

    $t->same(1, count($viewRows));
    $t->same('main', $viewRows[0]['schema']);
    $t->same('view', $viewRows[0]['type']);
    $t->same(1, $viewRows[0]['ncol']);
    $t->same(true, array_key_exists('wr', $viewRows[0]));
    $t->same('app_parent_view', $viewRows[0]['name']);
};

return $tests;
