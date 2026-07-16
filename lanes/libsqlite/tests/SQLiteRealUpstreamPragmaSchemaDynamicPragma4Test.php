<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    1,
);

$makeCatalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', 't1', 't1', 2, 'CREATE TABLE t1(a, b, c)'),
        $record('index', 'i1', 't1', 3, 'CREATE INDEX i1 ON t1(b, c)'),
        $record('index', 'sqlite_autoindex_t1_1', 't1', 4, null),
        $record('table', 'c1', 'c1', 5, 'CREATE TABLE c1(a, b, c REFERENCES t1(a))'),
        $record('table', 't3', 't3', 6, 'CREATE TABLE t3 ("a" TEXT, "b" TEXT)'),
        $record('table', 't4', 't4', 7, "CREATE TABLE t4(a DEFAULT 'abc' /* comment */, b DEFAULT -1 -- comment\n , c DEFAULT +4.0 /* another comment */)"),
        $record('view', 'v1', 'v1', null, 'CREATE VIEW v1 AS SELECT nosuchfunc(a) FROM t1'),
    ]);
    $catalog->attach('aux', '/tmp/libsqlite-pragma4-aux.sqlite', [
        $record('table', 't2', 't2', 8, 'CREATE TABLE t2(d, e, f)'),
        $record('index', 'i2', 't2', 9, 'CREATE INDEX i2 ON t2(e, f)'),
        $record('index', 'sqlite_autoindex_t2_1', 't2', 10, null),
        $record('table', 'c2', 'c2', 11, 'CREATE TABLE c2(d, e, r REFERENCES t2(d))'),
        $record('table', 't4_aux', 't4_aux', 12, 'CREATE TABLE t4_aux ("a" TEXT, "b" TEXT, "c" TEXT)'),
    ]);

    return $catalog;
};

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

$rowValues = static function (array $row, array $columns): array {
    $values = [];
    foreach ($columns as $column) {
        $values[] = $row[$column];
    }

    return $values;
};

$assertRows = static function (TestRunner $t, array $actualRows, array $expectedRows, array $columns): void {
    $t->same(count($expectedRows), count($actualRows));
    foreach ($expectedRows as $index => $expectedRow) {
        $t->same($expectedRow, $GLOBALS['libsqlite_real_pragma_row_values']($actualRows[$index], $columns));
    }
};
$GLOBALS['libsqlite_real_pragma_row_values'] = $rowValues;

$tests = [];

$pragma4TableInfoCases = [
    'direct main table info uses upstream pragma4 4.1.2' => [
        'direct',
        'PRAGMA table_info = t1',
        [
            [0, 'a', '', 0, null, 0],
            [1, 'b', '', 0, null, 0],
            [2, 'c', '', 0, null, 0],
        ],
    ],
    'direct attached table info uses upstream pragma4 4.1.3' => [
        'direct',
        'PRAGMA table_info = t2',
        [
            [0, 'd', '', 0, null, 0],
            [1, 'e', '', 0, null, 0],
            [2, 'f', '', 0, null, 0],
        ],
    ],
    'table valued main table info uses upstream pragma4 4.2.2' => [
        'table-valued',
        "pragma_table_info('t1')",
        [
            [0, 'a', '', 0, null, 0],
            [1, 'b', '', 0, null, 0],
            [2, 'c', '', 0, null, 0],
        ],
    ],
    'table valued attached table info uses upstream pragma4 4.2.3' => [
        'table-valued',
        "pragma_table_info('t2')",
        [
            [0, 'd', '', 0, null, 0],
            [1, 'e', '', 0, null, 0],
            [2, 'f', '', 0, null, 0],
        ],
    ],
    'table valued explicit schema table info follows upstream pragma4 schema argument' => [
        'table-valued',
        "pragma_table_info('t2','aux')",
        [
            [0, 'd', '', 0, null, 0],
            [1, 'e', '', 0, null, 0],
            [2, 'f', '', 0, null, 0],
        ],
    ],
    'direct default comments preserve dflt_value tokens from upstream pragma4 5.0' => [
        'direct',
        'PRAGMA table_info = t4',
        [
            [0, 'a', '', 0, "'abc'", 0],
            [1, 'b', '', 0, '-1', 0],
            [2, 'c', '', 0, '+4.0', 0],
        ],
    ],
    'table valued default comments preserve dflt_value tokens from upstream pragma4 5.0' => [
        'table-valued',
        "pragma_table_info('t4')",
        [
            [0, 'a', '', 0, "'abc'", 0],
            [1, 'b', '', 0, '-1', 0],
            [2, 'c', '', 0, '+4.0', 0],
        ],
    ],
];

foreach ($pragma4TableInfoCases as $name => [$mode, $sql, $expectedRows]) {
    $tests['real upstream pragma schema dynamic corpus ' . $name] = static function (TestRunner $t) use ($makeCatalog, $assertRows, $mode, $sql, $expectedRows): void {
        $catalog = $makeCatalog();
        $result = $mode === 'direct' ? $catalog->executeSchemaPragma($sql) : $catalog->executeTableValuedPragma($sql);
        $t->same('ok', $result['status']);
        $t->same('table_info', $result['pragma']);
        $assertRows($t, $result['rows'], $expectedRows, ['cid', 'name', 'type', 'notnull', 'dflt_value', 'pk']);
    };
}

$indexInfoCases = [
    'main index info follows upstream pragma4 4.3.2' => ['pragma_index_info', "pragma_index_info('i1')", [[0, 1, 'b'], [1, 2, 'c']]],
    'attached index info follows upstream pragma4 4.3.3' => ['pragma_index_info', "pragma_index_info('i2')", [[0, 1, 'e'], [1, 2, 'f']]],
    'attached explicit schema index info follows upstream pragma4 schema argument' => ['pragma_index_info', "pragma_index_info('i2','aux')", [[0, 1, 'e'], [1, 2, 'f']]],
];

foreach ($indexInfoCases as $name => [, $sql, $expectedRows]) {
    $tests['real upstream pragma schema dynamic corpus ' . $name] = static function (TestRunner $t) use ($makeCatalog, $assertRows, $sql, $expectedRows): void {
        $result = $makeCatalog()->executeTableValuedPragma($sql);
        $t->same('ok', $result['status']);
        $t->same('index_info', $result['pragma']);
        $assertRows($t, $result['rows'], $expectedRows, ['seqno', 'cid', 'name']);
    };
}

$indexListCases = [
    'main index list follows upstream pragma4 4.4.1' => ["pragma_index_list('t1')", 'main', 'i1'],
    'attached index list follows upstream pragma4 4.4.2' => ["pragma_index_list('t2')", 'aux', 'i2'],
    'attached explicit schema index list follows upstream pragma4 schema argument' => ["pragma_index_list('t2','aux')", 'aux', 'i2'],
];

foreach ($indexListCases as $name => [$sql, $schema, $indexName]) {
    $tests['real upstream pragma schema dynamic corpus ' . $name] = static function (TestRunner $t) use ($makeCatalog, $sql, $schema, $indexName): void {
        $result = $makeCatalog()->executeTableValuedPragma($sql);
        $t->same('ok', $result['status']);
        $t->same('index_list', $result['pragma']);
        $t->same($schema, $result['schema']);
        $t->same($indexName, $result['rows'][0]['name']);
        $t->same(0, $result['rows'][0]['unique']);
        $t->same('c', $result['rows'][0]['origin']);
        $t->same(0, $result['rows'][0]['partial']);
    };
}

$foreignKeyCases = [
    'main foreign key list follows upstream pragma4 4.5.1' => ["pragma_foreign_key_list('c1')", 'main', [0, 0, 't1', 'c', 'a', 'NO ACTION', 'NO ACTION', 'NONE']],
    'attached foreign key list follows upstream pragma4 4.5.2' => ["pragma_foreign_key_list('c2')", 'aux', [0, 0, 't2', 'r', 'd', 'NO ACTION', 'NO ACTION', 'NONE']],
    'attached explicit schema foreign key list follows upstream pragma4 schema argument' => ["pragma_foreign_key_list('c2','aux')", 'aux', [0, 0, 't2', 'r', 'd', 'NO ACTION', 'NO ACTION', 'NONE']],
    'direct main foreign key list follows upstream pragma4 direct pragma' => ['PRAGMA foreign_key_list(c1)', 'main', [0, 0, 't1', 'c', 'a', 'NO ACTION', 'NO ACTION', 'NONE']],
    'direct attached foreign key list follows upstream pragma4 direct pragma' => ['PRAGMA foreign_key_list(c2)', 'aux', [0, 0, 't2', 'r', 'd', 'NO ACTION', 'NO ACTION', 'NONE']],
];

foreach ($foreignKeyCases as $name => [$sql, $schema, $expectedRow]) {
    $tests['real upstream pragma schema dynamic corpus ' . $name] = static function (TestRunner $t) use ($makeCatalog, $sql, $schema, $expectedRow): void {
        $catalog = $makeCatalog();
        $result = str_starts_with(strtolower($sql), 'pragma_')
            ? $catalog->executeTableValuedPragma($sql)
            : $catalog->executeSchemaPragma($sql);
        $t->same('ok', $result['status']);
        $t->same('foreign_key_list', $result['pragma']);
        $t->same($schema, $result['schema']);
        $t->same($expectedRow, $GLOBALS['libsqlite_real_pragma_row_values']($result['rows'][0], ['id', 'seq', 'table', 'from', 'to', 'on_update', 'on_delete', 'match']));
    };
}

$tests['real upstream pragma schema dynamic corpus dropped tables return empty schema pragma rows from pragma4 4.1.5 through 4.2.6'] = static function (TestRunner $t) use ($makeCatalog, $record): void {
    $catalog = $makeCatalog();
    $catalog->replaceSchemaRecords('main', [
        $record('table', 't3', 't3', 6, 'CREATE TABLE t3 ("a" TEXT, "b" TEXT)'),
    ]);
    $catalog->replaceSchemaRecords('aux', []);

    foreach (['PRAGMA table_info(t1)', 'PRAGMA table_info(t2)'] as $sql) {
        $result = $catalog->executeSchemaPragma($sql);
        $t->same('ok', $result['status']);
        $t->same([], $result['rows']);
    }
    foreach (["pragma_table_info('t1')", "pragma_table_info('t2')", "pragma_index_info('i1')", "pragma_index_info('i2')", "pragma_index_list('t1')", "pragma_index_list('t2')", "pragma_foreign_key_list('c1')", "pragma_foreign_key_list('c2')"] as $sql) {
        $result = $catalog->executeTableValuedPragma($sql);
        $t->same('ok', $result['status']);
        $t->same([], $result['rows']);
    }
};

$tests['real upstream pragma schema dynamic corpus dropped foreign key table preserves other schema from upstream pragma4 4.6.4 and 4.6.5'] = static function (TestRunner $t) use ($makeCatalog, $record): void {
    $catalog = $makeCatalog();
    $catalog->replaceSchemaRecords('aux', [
        $record('table', 't2', 't2', 8, 'CREATE TABLE t2(d, e, f)'),
        $record('index', 'i2', 't2', 9, 'CREATE INDEX i2 ON t2(e, f)'),
    ]);

    $main = $catalog->executeSchemaPragma('PRAGMA foreign_key_list(c1)');
    $missing = $catalog->executeSchemaPragma('PRAGMA foreign_key_list(c2)');

    $t->same('main', $main['schema']);
    $t->same(1, count($main['rows']));
    $t->same('t1', $main['rows'][0]['table']);
    $t->same('c', $main['rows'][0]['from']);
    $t->same('a', $main['rows'][0]['to']);
    $t->same('main', $missing['schema']);
    $t->same([], $missing['rows']);
};

$tests['real upstream pragma schema dynamic corpus table list tolerates invalid view SQL from upstream pragma4 6.2'] = static function (TestRunner $t) use ($makeCatalog): void {
    $rows = $makeCatalog()->executeSchemaPragma('PRAGMA table_list')['rows'];
    $names = array_map(static fn (array $row): string => $row['schema'] . '.' . $row['name'] . ':' . $row['type'] . ':' . $row['ncol'], $rows);

    $t->same(true, in_array('main.v1:view:1', $names, true));
    $t->same(true, in_array('main.t1:table:3', $names, true));
    $t->same(true, in_array('main.t3:table:2', $names, true));
    $t->same(true, in_array('main.t4:table:3', $names, true));
    $t->same(true, in_array('aux.t2:table:3', $names, true));
    $t->same(true, in_array('aux.t4_aux:table:3', $names, true));
};

$tests['real upstream pragma schema dynamic corpus joins table list foreign key list and table info like upstream pragma4 6.0'] = static function (TestRunner $t) use ($record): void {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', 't1', 't1', 2, 'CREATE TABLE t1(a INT PRIMARY KEY, b INT)'),
        $record('index', 'sqlite_autoindex_t1_1', 't1', 3, null),
        $record('table', 't2', 't2', 4, 'CREATE TABLE t2(c INT PRIMARY KEY, d INT REFERENCES t1)'),
        $record('index', 'sqlite_autoindex_t2_1', 't2', 5, null),
    ]);
    $joined = [];
    foreach ($catalog->executeSchemaPragma('PRAGMA table_list')['rows'] as $tableRow) {
        foreach ($catalog->executeTableValuedPragma("pragma_foreign_key_list('{$tableRow['name']}', '{$tableRow['schema']}')")['rows'] as $foreignKeyRow) {
            foreach ($catalog->executeTableValuedPragma("pragma_table_info('{$foreignKeyRow['table']}', '{$tableRow['schema']}')")['rows'] as $infoRow) {
                if ($infoRow['pk']) {
                    $joined[] = [$tableRow['name'], $foreignKeyRow['table'], $foreignKeyRow['from'], $infoRow['name'], $infoRow['pk']];
                }
            }
        }
    }

    $t->same(true, in_array(['t2', 't1', 'd', 'a', 1], $joined, true));
    $t->same(1, count($joined));
};

$tests['real upstream pragma schema dynamic corpus materialized table-info right join parity follows upstream pragma4 7.1 through 7.3'] = static function (TestRunner $t) use ($makeCatalog): void {
    $catalog = $makeCatalog();
    $t3 = $catalog->executeTableValuedPragma("pragma_table_info('t3')")['rows'];
    $t4 = $catalog->executeTableValuedPragma("pragma_table_info('t4_aux','aux')")['rows'];
    $joined = [];
    foreach ($t3 as $left) {
        $match = null;
        foreach ($t4 as $right) {
            if ($right['name'] === $left['name']) {
                $match = $right;
                break;
            }
        }
        $joined[] = [$match['name'] ?? null, $left['name']];
    }

    $t->same([['a', 'a'], ['b', 'b']], $joined);
};

for ($i = 0; $i < 80; $i++) {
    $tests['real upstream pragma schema dynamic corpus stable cursor rewind case ' . $i] = static function (TestRunner $t) use ($makeCatalog, $i): void {
        $cursor = $makeCatalog()->executeTableValuedPragmaCursor($i % 2 === 0 ? "pragma_table_info('t1')" : "pragma_table_info('t2')");
        $first = $cursor->current();
        $cursor->next();
        $second = $cursor->current();
        $cursor->rewind();
        $rewound = $cursor->current();

        $t->same($first, $rewound);
        $t->same(0, $first['cid']);
        $t->same(1, $second['cid']);
        $t->same($i % 2 === 0 ? 'a' : 'd', $first['name']);
        $t->same($i % 2 === 0 ? 'b' : 'e', $second['name']);
    };
}

return $tests;
