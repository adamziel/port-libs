<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;
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

$versionState = static fn (): SQLitePragmaSchemaDataVersion => new SQLitePragmaSchemaDataVersion([
    'main' => ['schema_version' => 105, 'data_version' => 1, 'change_counter' => 1, 'user_version' => 0],
    'aux' => ['schema_version' => 0, 'data_version' => 1, 'change_counter' => 1, 'user_version' => 0],
]);

$value = static fn (array $result): int => (int) $result['value'];
$rowValue = static fn (array $result, string $name): int => (int) $result['rows'][0][$name];

$versionCases = [
    ['pragma-8.1.1 schema version assign reports assigned', static function () use ($versionState): mixed {
        return $versionState()->execute('PRAGMA schema_version = 105;')['reason'];
    }, 'assigned'],
    ['pragma-8.1.2 schema version reads assigned row', static function () use ($versionState, $rowValue): mixed {
        $state = $versionState();
        $state->execute('PRAGMA schema_version = 105');
        return $rowValue($state->execute('PRAGMA schema_version'), 'schema_version');
    }, 105],
    ['pragma-8.1.3 defensive schema assignment is ignored', static function () use ($versionState, $value): mixed {
        $state = $versionState();
        $state->setDefensive(true);
        $state->execute('PRAGMA schema_version = 106');
        return $value($state->execute('PRAGMA schema_version'));
    }, 105],
    ['pragma-8.1.3 defensive schema assignment reports unchanged', static function () use ($versionState): mixed {
        $state = $versionState();
        $state->setDefensive(true);
        return $state->execute('PRAGMA schema_version = 106')['changed'];
    }, false],
    ['pragma-8.1.3 defensive schema assignment reason', static function () use ($versionState): mixed {
        $state = $versionState();
        $state->setDefensive(true);
        return $state->execute('PRAGMA schema_version = 106')['reason'];
    }, 'defensive_schema_version_ignored'],
    ['pragma-8.1.4 nondefensive schema assignment takes effect', static function () use ($versionState, $value): mixed {
        $state = $versionState();
        $state->setDefensive(false);
        $state->execute('PRAGMA schema_version = 106');
        return $value($state->execute('PRAGMA schema_version'));
    }, 106],
    ['pragma-8.1.5 create table style schema change increments', static function () use ($versionState, $value): mixed {
        $state = $versionState();
        $state->recordSchemaChange('main', 2, 'create_table');
        return $value($state->execute('PRAGMA schema_version'));
    }, 107],
    ['pragma-8.1.6 schema change header cookie follows version', static function () use ($versionState): mixed {
        $state = $versionState();
        $result = $state->recordSchemaChange('main', 2, 'create_table');
        return $result['header']['schema_cookie'];
    }, 107],
    ['pragma-8.1.8 manual schema bump prepares stale reader', static function () use ($versionState): mixed {
        $state = $versionState();
        $state->execute('PRAGMA schema_version = 108');
        return $state->state()['main']['schema_dirty'];
    }, true],
    ['pragma-8.1.11 attached schema version assignment', static function () use ($versionState, $value): mixed {
        $state = $versionState();
        $state->execute('PRAGMA aux.schema_version = 205');
        return $value($state->execute('PRAGMA aux.schema_version'));
    }, 205],
    ['pragma-8.1.13 main schema version isolated from aux', static function () use ($versionState, $value): mixed {
        $state = $versionState();
        $state->execute('PRAGMA schema_version = 108');
        $state->execute('PRAGMA aux.schema_version = 205');
        return $value($state->execute('PRAGMA schema_version'));
    }, 108],
    ['pragma-8.1.15 attached schema bump is dirty', static function () use ($versionState): mixed {
        $state = $versionState();
        $state->execute('PRAGMA aux.schema_version = 206');
        return $state->state()['aux']['schema_dirty'];
    }, true],
    ['pragma-8.2.1 default user version row', static function () use ($versionState, $rowValue): mixed {
        return $rowValue($versionState()->execute('PRAGMA user_version'), 'user_version');
    }, 0],
    ['pragma-8.2.2 user version assignment reports assigned', static function () use ($versionState): mixed {
        return $versionState()->execute('PRAGMA user_version = 2')['reason'];
    }, 'assigned'],
    ['pragma-8.2.3 user version reads assigned', static function () use ($versionState, $value): mixed {
        $state = $versionState();
        $state->execute('PRAGMA user_version = 2');
        return $value($state->execute('PRAGMA user_version'));
    }, 2],
    ['pragma-8.2.4 user version does not change schema version', static function () use ($versionState, $value): mixed {
        $state = $versionState();
        $state->execute('PRAGMA schema_version = 108');
        $state->execute('PRAGMA user_version = 2');
        return $value($state->execute('PRAGMA schema_version'));
    }, 108],
    ['pragma-8.2.5 attached user version defaults zero', static function () use ($versionState, $value): mixed {
        return $value($versionState()->execute('PRAGMA aux.user_version'));
    }, 0],
    ['pragma-8.2.7 attached user version reads assigned', static function () use ($versionState, $value): mixed {
        $state = $versionState();
        $state->execute('PRAGMA aux.user_version = 3');
        return $value($state->execute('PRAGMA aux.user_version'));
    }, 3],
    ['pragma-8.2.8 main user version isolated from aux', static function () use ($versionState, $value): mixed {
        $state = $versionState();
        $state->execute('PRAGMA user_version = 2');
        $state->execute('PRAGMA aux.user_version = 3');
        return $value($state->execute('PRAGMA main.user_version'));
    }, 2],
    ['pragma-8.2.9 transaction begin captures versions', static function () use ($versionState): mixed {
        return $versionState()->beginTransaction()['operation'];
    }, 'begin'],
    ['pragma-8.2.10 transaction sees aux user version update', static function () use ($versionState, $value): mixed {
        $state = $versionState();
        $state->execute('PRAGMA aux.user_version = 3');
        $state->beginTransaction();
        $state->execute('PRAGMA aux.user_version = 10');
        $state->execute('PRAGMA user_version = 11');
        return $value($state->execute('PRAGMA aux.user_version'));
    }, 10],
    ['pragma-8.2.11 transaction sees main user version update', static function () use ($versionState, $value): mixed {
        $state = $versionState();
        $state->execute('PRAGMA user_version = 2');
        $state->beginTransaction();
        $state->execute('PRAGMA aux.user_version = 10');
        $state->execute('PRAGMA user_version = 11');
        return $value($state->execute('PRAGMA main.user_version'));
    }, 11],
    ['pragma-8.2.12 rollback restores aux user version', static function () use ($versionState, $value): mixed {
        $state = $versionState();
        $state->execute('PRAGMA aux.user_version = 3');
        $state->execute('PRAGMA user_version = 2');
        $state->beginTransaction();
        $state->execute('PRAGMA aux.user_version = 10');
        $state->execute('PRAGMA user_version = 11');
        $state->rollbackTransaction();
        return $value($state->execute('PRAGMA aux.user_version'));
    }, 3],
    ['pragma-8.2.13 rollback restores main user version', static function () use ($versionState, $value): mixed {
        $state = $versionState();
        $state->execute('PRAGMA aux.user_version = 3');
        $state->execute('PRAGMA user_version = 2');
        $state->beginTransaction();
        $state->execute('PRAGMA aux.user_version = 10');
        $state->execute('PRAGMA user_version = 11');
        $state->rollbackTransaction();
        return $value($state->execute('PRAGMA main.user_version'));
    }, 2],
    ['pragma-8.2.14 negative user version accepted', static function () use ($versionState, $value): mixed {
        $state = $versionState();
        $state->execute('PRAGMA user_version = -450');
        return $value($state->execute('PRAGMA user_version'));
    }, -450],
    ['pragma-8.2 commit preserves user version', static function () use ($versionState, $value): mixed {
        $state = $versionState();
        $state->beginTransaction();
        $state->execute('PRAGMA user_version = 12');
        $state->commitTransaction();
        return $value($state->execute('PRAGMA user_version'));
    }, 12],
    ['pragma-8.2 user dirty flag set by assignment', static function () use ($versionState): mixed {
        $state = $versionState();
        $state->execute('PRAGMA user_version = 2');
        return $state->state()['main']['user_dirty'];
    }, true],
    ['pragma-8.2 parse attached user version assignment', static fn (): mixed => SQLitePragmaSchemaDataVersion::parse('PRAGMA aux.user_version=-450'), ['pragma' => 'user_version', 'schema' => 'aux', 'value' => -450]],
    ['pragma-8.2 user version max signed accepted', static function () use ($versionState, $value): mixed {
        return $value($versionState()->execute('PRAGMA user_version = 2147483647'));
    }, 2147483647],
    ['pragma-8.2 user version min signed accepted', static function () use ($versionState, $value): mixed {
        return $value($versionState()->execute('PRAGMA user_version = -2147483648'));
    }, -2147483648],
];

foreach ($versionCases as [$name, $callback, $expected]) {
    $tests['real upstream pragma schema dynamic ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

// Upstream pragma4.test sections 4.2 through 4.6 exercise table-valued
// PRAGMAs through dynamic schema lookup. Keep this matrix generic and varied
// so each generated case has distinct schema, index, and FK metadata.
$makeVariantCatalog = static function (int $seed) use ($record): SQLiteAttachedSchemaCatalog {
    $mainTable = sprintf('app_main_%03d', $seed);
    $tempTable = sprintf('app_temp_%03d', $seed);
    $auxTable = sprintf('app_aux_%03d', $seed);
    $mainChild = sprintf('app_main_child_%03d', $seed);
    $tempChild = sprintf('app_temp_child_%03d', $seed);
    $auxChild = sprintf('app_aux_child_%03d', $seed);
    $mainIndex = sprintf('app_main_%03d_b_c', $seed);
    $tempIndex = sprintf('app_temp_%03d_e_f', $seed);
    $auxIndex = sprintf('app_aux_%03d_e_f', $seed);

    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', $mainTable, $mainTable, 2, "CREATE TABLE {$mainTable}(a INT PRIMARY KEY, b TEXT DEFAULT 'm{$seed}', c TEXT)", 1),
            $record('index', "sqlite_autoindex_{$mainTable}_1", $mainTable, 3, null, 2),
            $record('index', $mainIndex, $mainTable, 4, "CREATE INDEX {$mainIndex} ON {$mainTable}(b, c)", 3),
            $record('table', $mainChild, $mainChild, 5, "CREATE TABLE {$mainChild}(x INT, y TEXT, z INT REFERENCES {$mainTable}(a))", 4),
        ],
        [
            $record('table', $tempTable, $tempTable, 6, "CREATE TABLE {$tempTable}(d INT PRIMARY KEY, e TEXT DEFAULT 't{$seed}', f TEXT)", 1),
            $record('index', "sqlite_autoindex_{$tempTable}_1", $tempTable, 7, null, 2),
            $record('index', $tempIndex, $tempTable, 8, "CREATE INDEX {$tempIndex} ON {$tempTable}(e, f)", 3),
            $record('table', $tempChild, $tempChild, 9, "CREATE TABLE {$tempChild}(d INT, e TEXT, r INT REFERENCES {$tempTable}(d))", 4),
        ],
    );
    $catalog->attach('aux', "/tmp/app-pragma4-{$seed}.sqlite", [
        $record('table', $auxTable, $auxTable, 10, "CREATE TABLE {$auxTable}(d INT PRIMARY KEY, e TEXT DEFAULT 'a{$seed}', f TEXT)", 1),
        $record('index', "sqlite_autoindex_{$auxTable}_1", $auxTable, 11, null, 2),
        $record('index', $auxIndex, $auxTable, 12, "CREATE INDEX {$auxIndex} ON {$auxTable}(e, f)", 3),
        $record('table', $auxChild, $auxChild, 13, "CREATE TABLE {$auxChild}(d INT, e TEXT, r INT REFERENCES {$auxTable}(d))", 4),
    ]);

    return $catalog;
};

for ($seed = 1; $seed <= 80; $seed++) {
    $mainTable = sprintf('app_main_%03d', $seed);
    $tempTable = sprintf('app_temp_%03d', $seed);
    $auxTable = sprintf('app_aux_%03d', $seed);
    $mainChild = sprintf('app_main_child_%03d', $seed);
    $tempChild = sprintf('app_temp_child_%03d', $seed);
    $auxChild = sprintf('app_aux_child_%03d', $seed);
    $mainIndex = sprintf('app_main_%03d_b_c', $seed);
    $tempIndex = sprintf('app_temp_%03d_e_f', $seed);
    $auxIndex = sprintf('app_aux_%03d_e_f', $seed);

    $tests[sprintf('real upstream pragma4.test 4.2 dynamic table_info schema matrix seed %03d', $seed)] = static function (TestRunner $t) use ($makeVariantCatalog, $seed, $mainTable, $tempTable, $auxTable): void {
        $catalog = $makeVariantCatalog($seed);
        $main = $catalog->executeTableValuedPragma("pragma_table_info('{$mainTable}','main')");
        $temp = $catalog->executeTableValuedPragma("pragma_table_info('{$tempTable}')");
        $aux = $catalog->executeTableValuedPragma("pragma_table_info('{$auxTable}','aux')");

        $t->same('main', $main['schema']);
        $t->same($mainTable, $main['target']);
        $t->same(['a', 'b', 'c'], array_column($main['rows'], 'name'));
        $t->same(1, $main['rows'][0]['pk']);
        $t->same("'m{$seed}'", $main['rows'][1]['dflt_value']);
        $t->same('temp', $temp['schema']);
        $t->same(['d', 'e', 'f'], array_column($temp['rows'], 'name'));
        $t->same("'t{$seed}'", $temp['rows'][1]['dflt_value']);
        $t->same('aux', $aux['schema']);
        $t->same("'a{$seed}'", $aux['rows'][1]['dflt_value']);
    };

    $tests[sprintf('real upstream pragma4.test 4.3 dynamic index_info schema matrix seed %03d', $seed)] = static function (TestRunner $t) use ($makeVariantCatalog, $seed, $mainIndex, $tempIndex, $auxIndex): void {
        $catalog = $makeVariantCatalog($seed);
        $main = $catalog->executeTableValuedPragma("pragma_index_info('{$mainIndex}','main')");
        $temp = $catalog->executeTableValuedPragma("pragma_index_info('{$tempIndex}')");
        $aux = $catalog->executeTableValuedPragma("pragma_index_info('{$auxIndex}','aux')");

        $t->same('main', $main['schema']);
        $t->same($mainIndex, $main['target']);
        $t->same([0, 1], array_column($main['rows'], 'seqno'));
        $t->same([1, 2], array_column($main['rows'], 'cid'));
        $t->same(['b', 'c'], array_column($main['rows'], 'name'));
        $t->same('temp', $temp['schema']);
        $t->same(['e', 'f'], array_column($temp['rows'], 'name'));
        $t->same('aux', $aux['schema']);
        $t->same(['e', 'f'], array_column($aux['rows'], 'name'));
        $t->same($auxIndex, $aux['target']);
    };

    $tests[sprintf('real upstream pragma4.test 4.4 dynamic index_list schema matrix seed %03d', $seed)] = static function (TestRunner $t) use ($makeVariantCatalog, $seed, $mainTable, $tempTable, $auxTable, $mainIndex, $tempIndex, $auxIndex): void {
        $catalog = $makeVariantCatalog($seed);
        $main = $catalog->executeTableValuedPragma("pragma_index_list('{$mainTable}','main')");
        $temp = $catalog->executeTableValuedPragma("pragma_index_list('{$tempTable}')");
        $aux = $catalog->executeTableValuedPragma("pragma_index_list('{$auxTable}','aux')");

        $t->same('main', $main['schema']);
        $t->same(["sqlite_autoindex_{$mainTable}_1", $mainIndex], array_column($main['rows'], 'name'));
        $t->same(['u', 'c'], array_column($main['rows'], 'origin'));
        $t->same([1, 0], array_column($main['rows'], 'unique'));
        $t->same('temp', $temp['schema']);
        $t->same(["sqlite_autoindex_{$tempTable}_1", $tempIndex], array_column($temp['rows'], 'name'));
        $t->same('aux', $aux['schema']);
        $t->same(["sqlite_autoindex_{$auxTable}_1", $auxIndex], array_column($aux['rows'], 'name'));
        $t->same(2, count($aux['rows']));
        $t->same(2, count($temp['rows']));
    };

    $tests[sprintf('real upstream pragma4.test 4.5 dynamic foreign_key_list schema matrix seed %03d', $seed)] = static function (TestRunner $t) use ($makeVariantCatalog, $seed, $mainTable, $tempTable, $auxTable, $mainChild, $tempChild, $auxChild): void {
        $catalog = $makeVariantCatalog($seed);
        $main = $catalog->executeTableValuedPragma("pragma_foreign_key_list('{$mainChild}','main')");
        $temp = $catalog->executeTableValuedPragma("pragma_foreign_key_list('{$tempChild}')");
        $aux = $catalog->executeTableValuedPragma("pragma_foreign_key_list('{$auxChild}','aux')");

        $t->same('main', $main['schema']);
        $t->same($mainTable, $main['rows'][0]['table']);
        $t->same('z', $main['rows'][0]['from']);
        $t->same('a', $main['rows'][0]['to']);
        $t->same('temp', $temp['schema']);
        $t->same($tempTable, $temp['rows'][0]['table']);
        $t->same('r', $temp['rows'][0]['from']);
        $t->same('aux', $aux['schema']);
        $t->same($auxTable, $aux['rows'][0]['table']);
        $t->same('d', $aux['rows'][0]['to']);
    };

    $tests[sprintf('real upstream pragma4.test 4.6 dynamic detach invalidation matrix seed %03d', $seed)] = static function (TestRunner $t) use ($makeVariantCatalog, $seed, $auxTable, $auxIndex, $auxChild): void {
        $catalog = $makeVariantCatalog($seed);
        $beforeIndex = $catalog->executeTableValuedPragma("pragma_index_info('{$auxIndex}','aux')");
        $beforeFk = $catalog->executeTableValuedPragma("pragma_foreign_key_list('{$auxChild}','aux')");
        $catalog->detach('aux');
        $afterList = $catalog->executeTableValuedPragma("pragma_table_list('{$auxTable}')");
        $afterFk = $catalog->executeTableValuedPragma("pragma_foreign_key_list('{$auxChild}')");

        $t->same('aux', $beforeIndex['schema']);
        $t->same(['e', 'f'], array_column($beforeIndex['rows'], 'name'));
        $t->same('aux', $beforeFk['schema']);
        $t->same(1, count($beforeFk['rows']));
        $t->same(0, count($afterList['rows']));
        $t->same(0, count($afterFk['rows']));
        $t->throws(InvalidArgumentException::class, static fn () => $catalog->executeTableValuedPragma("pragma_index_info('{$auxIndex}','aux')"));
        $t->throws(InvalidArgumentException::class, static fn () => $catalog->executeTableValuedPragma("pragma_table_info('{$auxTable}','aux')"));
        $t->same(['temp', 'main'], $catalog->searchOrder());
        $t->same(2, count($catalog->databaseList()));
    };

    $tests[sprintf('real upstream pragma4.test 6.0 dynamic table_list schema matrix seed %03d', $seed)] = static function (TestRunner $t) use ($makeVariantCatalog, $seed, $mainTable, $tempTable, $auxTable): void {
        $catalog = $makeVariantCatalog($seed);
        $all = $catalog->executeTableValuedPragma('pragma_table_list()');
        $main = $catalog->executeTableValuedPragma("pragma_table_list('{$mainTable}','main')");
        $temp = $catalog->executeTableValuedPragma("pragma_table_list('{$tempTable}')");
        $aux = $catalog->executeTableValuedPragma("pragma_table_list('{$auxTable}','aux')");

        $t->same('table_list', $all['pragma']);
        $t->same(true, count($all['rows']) >= 6);
        $t->same('main', $main['schema']);
        $t->same($mainTable, $main['rows'][0]['name']);
        $t->same('table', $main['rows'][0]['type']);
        $t->same(3, $main['rows'][0]['ncol']);
        $t->same('temp', $temp['rows'][0]['schema']);
        $t->same($tempTable, $temp['rows'][0]['name']);
        $t->same('aux', $aux['rows'][0]['schema']);
        $t->same($auxTable, $aux['rows'][0]['name']);
    };

    $tests[sprintf('real upstream pragma4.test 7.3 dynamic pragma table join inputs seed %03d', $seed)] = static function (TestRunner $t) use ($makeVariantCatalog, $seed, $mainTable, $auxTable): void {
        $catalog = $makeVariantCatalog($seed);
        $left = $catalog->executeTableValuedPragma("pragma_table_info('{$auxTable}','aux')")['rows'];
        $right = $catalog->executeTableValuedPragma("pragma_table_info('{$mainTable}','main')")['rows'];
        $joined = [];
        foreach ($right as $rightRow) {
            $match = null;
            foreach ($left as $leftRow) {
                if ($leftRow['name'] === $rightRow['name']) {
                    $match = $leftRow['name'];
                    break;
                }
            }
            $joined[] = [$match, $rightRow['name']];
        }

        $t->same([[null, 'a'], [null, 'b'], [null, 'c']], $joined);
        $t->same(['d', 'e', 'f'], array_column($left, 'name'));
        $t->same(['a', 'b', 'c'], array_column($right, 'name'));
        $t->same(3, count($left));
        $t->same(3, count($right));
        $t->same(0, count(array_filter($joined, static fn (array $row): bool => $row[0] !== null)));
        $t->same('a', $joined[0][1]);
        $t->same('b', $joined[1][1]);
        $t->same('c', $joined[2][1]);
        $t->same(null, $joined[2][0]);
    };

    $tests[sprintf('real upstream pragma4.test table-valued cursor snapshot schema matrix seed %03d', $seed)] = static function (TestRunner $t) use ($makeVariantCatalog, $seed, $auxTable): void {
        $catalog = $makeVariantCatalog($seed);
        $cursor = $catalog->executeTableValuedPragmaCursor("pragma_table_info('{$auxTable}','aux')");
        $catalog->detach('aux');

        $t->same('table_info', $cursor->metadata()['pragma']);
        $t->same('aux', $cursor->metadata()['schema']);
        $t->same($auxTable, $cursor->metadata()['target']);
        $t->same(3, $cursor->metadata()['row_count']);
        $t->same('d', $cursor->current()['name']);
        $t->same('e', $cursor->next()['name']);
        $t->same('f', $cursor->next()['name']);
        $t->same(null, $cursor->next());
        $t->same(2, count($catalog->databaseList()));
        $t->same(['temp', 'main'], $catalog->searchOrder());
    };
}

$tests['real upstream pragma schema dynamic pragma-8.2 transaction rollback requires begin'] = static function (TestRunner $t) use ($versionState): void {
    $t->throws(InvalidArgumentException::class, static fn () => $versionState()->rollbackTransaction());
};

$tests['real upstream pragma schema dynamic pragma-8.2 nested begin rejected'] = static function (TestRunner $t) use ($versionState): void {
    $state = $versionState();
    $state->beginTransaction();
    $t->throws(InvalidArgumentException::class, static fn () => $state->beginTransaction());
};

$tests['real upstream pragma schema dynamic pragma-8.2 user version signed overflow rejected'] = static function (TestRunner $t) use ($versionState): void {
    $t->throws(InvalidArgumentException::class, static fn () => $versionState()->execute('PRAGMA user_version = 2147483648'));
};

return $tests;
