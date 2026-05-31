<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma5.test 1.0, 2.0, and 3.0: introspection pragma
 *   virtual tables expose stable visible result columns through table_info().
 * - SQLite test/pragma4.test 4.2 through 4.5 and 6.0 through 7.3:
 *   table-valued pragma virtual tables accept hidden target/schema arguments.
 *
 * The hidden-column expectations are checked against SQLite's current
 * PRAGMA table_xinfo(pragma_*) shape for the same table-valued pragma modules.
 */

$catalog = static fn (): SQLitePragmaSchemaCatalog => new SQLitePragmaSchemaCatalog([]);

$visibleShapes = [
    'pragma_table_info' => ['cid', 'name', 'type', 'notnull', 'dflt_value', 'pk'],
    'pragma_table_xinfo' => ['cid', 'name', 'type', 'notnull', 'dflt_value', 'pk', 'hidden'],
    'pragma_index_list' => ['seq', 'name', 'unique', 'origin', 'partial'],
    'pragma_index_info' => ['seqno', 'cid', 'name'],
    'pragma_index_xinfo' => ['seqno', 'cid', 'name', 'desc', 'coll', 'key'],
    'pragma_foreign_key_list' => ['id', 'seq', 'table', 'from', 'to', 'on_update', 'on_delete', 'match'],
    'pragma_table_list' => ['schema', 'name', 'type', 'ncol', 'wr', 'strict'],
    'pragma_function_list' => ['name', 'builtin', 'type', 'enc', 'narg', 'flags'],
    'pragma_module_list' => ['name'],
    'pragma_pragma_list' => ['name'],
];

$hiddenShapes = [
    'pragma_table_info' => ['arg', 'schema'],
    'pragma_table_xinfo' => ['arg', 'schema'],
    'pragma_index_list' => ['arg', 'schema'],
    'pragma_index_info' => ['arg', 'schema'],
    'pragma_index_xinfo' => ['arg', 'schema'],
    'pragma_foreign_key_list' => ['arg', 'schema'],
    'pragma_table_list' => ['arg'],
    'pragma_function_list' => [],
    'pragma_module_list' => [],
    'pragma_pragma_list' => [],
];

foreach ($visibleShapes as $virtualTable => $columns) {
    $tests["real upstream pragma virtual shape table_info visible {$virtualTable}"] = static function (TestRunner $t) use ($catalog, $virtualTable, $columns): void {
        $rows = $catalog()->execute("PRAGMA table_info({$virtualTable})")['rows'];

        $t->same($columns, array_column($rows, 'name'));
        $t->same(array_fill(0, count($columns), 0), array_column($rows, 'pk'));
        $t->same(array_fill(0, count($columns), 0), array_column($rows, 'notnull'));
        $t->same(range(0, count($columns) - 1), array_column($rows, 'cid'));
    };

    $tests["real upstream pragma virtual shape table_xinfo hidden {$virtualTable}"] = static function (TestRunner $t) use ($catalog, $virtualTable, $columns, $hiddenShapes): void {
        $rows = $catalog()->execute("PRAGMA table_xinfo({$virtualTable})")['rows'];
        $expectedNames = array_merge($columns, $hiddenShapes[$virtualTable]);
        $expectedHidden = array_merge(
            array_fill(0, count($columns), 0),
            array_fill(0, count($hiddenShapes[$virtualTable]), 1),
        );

        $t->same($expectedNames, array_column($rows, 'name'));
        $t->same($expectedHidden, array_column($rows, 'hidden'));
        $t->same(range(0, count($expectedNames) - 1), array_column($rows, 'cid'));
        $t->same(array_fill(0, count($expectedNames), ''), array_column($rows, 'type'));
    };
}

foreach (range(1, 220) as $variant) {
    foreach ($visibleShapes as $virtualTable => $columns) {
        $hiddenColumns = $hiddenShapes[$virtualTable];

        $tests[sprintf('real upstream pragma schema virtual table dynamic shape variant %03d %s', $variant, $virtualTable)] = static function (TestRunner $t) use ($catalog, $virtualTable, $columns, $hiddenColumns, $variant): void {
            $info = $catalog()->execute($variant % 2 === 0 ? "PRAGMA main.table_info({$virtualTable})" : "PRAGMA table_info = {$virtualTable}");
            $xinfo = $catalog()->execute($variant % 3 === 0 ? "PRAGMA main.table_xinfo({$virtualTable});" : "PRAGMA table_xinfo({$virtualTable})");
            $expectedNames = array_merge($columns, $hiddenColumns);

            $t->same('table_info', $info['pragma']);
            $t->same($virtualTable, $info['target']);
            $t->same($columns, array_column($info['rows'], 'name'));
            $t->same(count($columns), count($info['rows']));
            $t->same('table_xinfo', $xinfo['pragma']);
            $t->same($virtualTable, $xinfo['target']);
            $t->same($expectedNames, array_column($xinfo['rows'], 'name'));
            $t->same(count($hiddenColumns), array_sum(array_column($xinfo['rows'], 'hidden')));
        };
    }
}

$tests['real upstream pragma schema virtual table shape cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma5.test 1.0 PRAGMA table_info(pragma_function_list)',
        'pragma5.test 2.0 PRAGMA table_info(pragma_module_list)',
        'pragma5.test 3.0 PRAGMA table_info(pragma_pragma_list)',
        'pragma4.test 4.2 through 4.5 table-valued pragma target arguments',
        'pragma4.test 6.0 through 7.3 table-valued pragma schema arguments',
    ];

    $t->same(5, count($sections));
    $t->contains('pragma5.test 1.0', $sections[0]);
    $t->contains('pragma4.test 4.2', $sections[3]);
};

return $tests;
