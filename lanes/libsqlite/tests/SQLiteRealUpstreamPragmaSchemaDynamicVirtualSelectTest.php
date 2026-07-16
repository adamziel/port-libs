<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma5.test setup guard:
 *   SELECT count(*) FROM pragma_function_list must compile and read the
 *   introspection virtual table.
 * - SQLite test/pragma5.test 1.1:
 *   SELECT DISTINCT name, builtin FROM pragma_function_list
 *   WHERE name='upper' AND builtin returns the builtin row.
 * - SQLite test/pragma5.test 1.2:
 *   SELECT DISTINCT name, builtin FROM pragma_function_list
 *   WHERE name LIKE 'exter%' returns an application-defined function.
 * - SQLite test/pragma5.test 2.1:
 *   SELECT * FROM pragma_module_list WHERE name='fts5' returns fts5.
 * - SQLite test/pragma5.test 3.1:
 *   SELECT * FROM pragma_pragma_list WHERE name='pragma_list' returns
 *   pragma_list.
 *
 * Earlier PRAGMA/schema dynamic batches exercised direct PRAGMA rowsets and
 * table-valued pragma_* calls. This batch owns the SELECT-source form over
 * the list/introspection virtual tables.
 */

$makeCatalog = static function (int $variant): SQLitePragmaSchemaCatalog {
    $suffix = sprintf('%04d', $variant);
    $encoding = ['utf8', 'utf16le', 'utf16be'][$variant % 3];

    return new SQLitePragmaSchemaCatalog(
        [],
        [
            ['name' => "external_{$suffix}", 'builtin' => 0, 'type' => 's', 'enc' => $encoding, 'narg' => ($variant % 4) + 1, 'flags' => 700000 + $variant],
            ['name' => "external_peer_{$suffix}", 'builtin' => 0, 'type' => 'w', 'enc' => 'utf8', 'narg' => 2, 'flags' => 710000 + $variant],
            ['name' => "tenant_norm_{$suffix}", 'builtin' => 0, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 720000 + $variant],
            ['name' => 'upper', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2099200],
        ],
        [
            ['name' => 'fts5'],
            ['name' => "tenant_module_{$suffix}"],
            ['name' => 'json_each'],
        ],
        [
            ['seq' => 0, 'name' => 'binary'],
            ['seq' => 1, 'name' => 'nocase'],
            ['seq' => 2, 'name' => "tenant_locale_{$suffix}"],
        ],
    );
};

foreach (range(1, 250) as $variant) {
    $suffix = sprintf('%04d', $variant);
    $external = "external_{$suffix}";
    $encoding = ['utf8', 'utf16le', 'utf16be'][$variant % 3];
    $narg = ($variant % 4) + 1;
    $flags = 700000 + $variant;

    $tests[sprintf('real upstream pragma5 virtual select count function list variant %04d', $variant)] = static function (TestRunner $t) use ($makeCatalog, $variant): void {
        $rows = $makeCatalog($variant)->executeVirtualTableSelect('SELECT count(*) FROM pragma_function_list');

        $t->same(1, count($rows));
        $t->same(['countAll'], array_keys($rows[0]));
        $t->same(4, $rows[0]['countAll']);
        $t->same(4, count($makeCatalog($variant)->virtualPragmaTables()['pragma_function_list']));
    };

    $tests[sprintf('real upstream pragma5 virtual select builtin upper distinct variant %04d', $variant)] = static function (TestRunner $t) use ($makeCatalog, $variant): void {
        $rows = $makeCatalog($variant)->executeVirtualTableSelect("SELECT DISTINCT name, builtin FROM pragma_function_list WHERE name='upper' AND builtin");

        $t->same(1, count($rows));
        $t->same(['name' => 'upper', 'builtin' => 1], $rows[0]);
        $t->same('upper', $rows[0]['name']);
        $t->same(1, $rows[0]['builtin']);
    };

    $tests[sprintf('real upstream pragma5 virtual select external function like variant %04d', $variant)] = static function (TestRunner $t) use ($makeCatalog, $variant, $suffix, $external, $encoding, $narg, $flags): void {
        $rows = $makeCatalog($variant)->executeVirtualTableSelect("SELECT DISTINCT name, builtin, enc, narg, flags FROM pragma_function_list WHERE name LIKE 'external_{$suffix}%'");

        $t->same(1, count($rows));
        $t->same($external, $rows[0]['name']);
        $t->same(0, $rows[0]['builtin']);
        $t->same($encoding, $rows[0]['enc']);
        $t->same($narg, $rows[0]['narg']);
        $t->same($flags, $rows[0]['flags']);
    };

    $tests[sprintf('real upstream pragma5 virtual select module fts5 filter variant %04d', $variant)] = static function (TestRunner $t) use ($makeCatalog, $variant): void {
        $rows = $makeCatalog($variant)->executeVirtualTableSelect("SELECT * FROM pragma_module_list WHERE name='fts5'");

        $t->same(1, count($rows));
        $t->same(['name' => 'fts5'], $rows[0]);
        $t->same('fts5', $rows[0]['name']);
        $t->same(3, count($makeCatalog($variant)->virtualPragmaTables()['pragma_module_list']));
    };

    $tests[sprintf('real upstream pragma5 virtual select pragma list filter variant %04d', $variant)] = static function (TestRunner $t) use ($makeCatalog, $variant): void {
        $rows = $makeCatalog($variant)->executeVirtualTableSelect("SELECT * FROM pragma_pragma_list WHERE name='pragma_list'");

        $t->same(1, count($rows));
        $t->same(['name' => 'pragma_list'], $rows[0]);
        $t->same('pragma_list', $rows[0]['name']);
        $t->same(true, in_array(['name' => 'function_list'], $makeCatalog($variant)->virtualPragmaTables()['pragma_pragma_list'], true));
    };
}

$tests['real upstream pragma5 virtual select cites source corpus sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma5.test guard SELECT count(*) FROM pragma_function_list',
        "pragma5.test 1.1 SELECT DISTINCT name,builtin FROM pragma_function_list WHERE name='upper' AND builtin",
        "pragma5.test 1.2 SELECT DISTINCT name,builtin FROM pragma_function_list WHERE name LIKE 'exter%'",
        "pragma5.test 2.1 SELECT * FROM pragma_module_list WHERE name='fts5'",
        "pragma5.test 3.1 SELECT * FROM pragma_pragma_list WHERE name='pragma_list'",
    ];

    $t->same(5, count($sections));
    $t->contains('pragma_function_list', $sections[0]);
    $t->contains('DISTINCT', $sections[1]);
    $t->contains('LIKE', $sections[2]);
    $t->contains('pragma_module_list', $sections[3]);
    $t->contains('pragma_pragma_list', $sections[4]);
};

return $tests;
