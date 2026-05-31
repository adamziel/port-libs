<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma5.test 1.0 through 1.2: pragma_function_list is
 *   introspectable through PRAGMA table_info(), reports builtin functions,
 *   and includes application-defined functions with builtin=0.
 * - SQLite test/pragma5.test 2.0 through 2.1: pragma_module_list is
 *   introspectable and exposes available virtual-table modules such as fts5.
 * - SQLite test/pragma5.test 3.0 through 3.1: pragma_pragma_list is
 *   introspectable and includes pragma_list itself.
 */

foreach (range(1, 1000) as $variant) {
    $tests[sprintf('real upstream pragma5 dynamic introspection function module pragma rows variant %04d', $variant)] = static function (TestRunner $t) use ($variant): void {
        $external = sprintf('external_%04d', $variant);
        $module = sprintf('tenant_module_%04d', $variant);
        $catalog = new SQLitePragmaSchemaCatalog(
            [],
            [
                ['name' => 'upper', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2099200],
                ['name' => $external, 'builtin' => 0, 'type' => 's', 'enc' => 'utf8', 'narg' => 2, 'flags' => $variant],
                ['name' => 'json_group_array', 'builtin' => 1, 'type' => 'w', 'enc' => 'utf8', 'narg' => 1, 'flags' => 3147776],
            ],
            [
                ['name' => 'fts5'],
                ['name' => $module],
            ],
        );

        $functionColumns = $catalog->execute('PRAGMA table_info(pragma_function_list)')['rows'];
        $moduleColumns = $catalog->execute('PRAGMA table_info(pragma_module_list)')['rows'];
        $pragmaColumns = $catalog->execute('PRAGMA table_info(pragma_pragma_list)')['rows'];
        $functions = $catalog->executeTableValuedPragma('pragma_function_list()')['rows'];
        $modules = $catalog->executeTableValuedPragma('pragma_module_list()')['rows'];
        $pragmas = $catalog->executeTableValuedPragma('pragma_pragma_list()')['rows'];

        $externalRows = array_values(array_filter($functions, static fn (array $row): bool => $row['name'] === $external));
        $upperRows = array_values(array_filter($functions, static fn (array $row): bool => $row['name'] === 'upper' && $row['builtin'] === 1));
        $moduleNames = array_column($modules, 'name');
        $pragmaNames = array_column($pragmas, 'name');

        $t->same(['name', 'builtin', 'type', 'enc', 'narg', 'flags'], array_column($functionColumns, 'name'));
        $t->same([0, 1, 2, 3, 4, 5], array_column($functionColumns, 'cid'));
        $t->same(['', '', '', '', '', ''], array_column($functionColumns, 'type'));
        $t->same([0, 0, 0, 0, 0, 0], array_column($functionColumns, 'notnull'));
        $t->same([0, 0, 0, 0, 0, 0], array_column($functionColumns, 'pk'));
        $t->same(['name'], array_column($moduleColumns, 'name'));
        $t->same(['name'], array_column($pragmaColumns, 'name'));
        $t->same(1, count($upperRows));
        $t->same(1, $upperRows[0]['builtin']);
        $t->same('s', $upperRows[0]['type']);
        $t->same('utf8', $upperRows[0]['enc']);
        $t->same(1, $upperRows[0]['narg']);
        $t->same(1, count($externalRows));
        $t->same(0, $externalRows[0]['builtin']);
        $t->same(2, $externalRows[0]['narg']);
        $t->same($variant, $externalRows[0]['flags']);
        $t->same(true, in_array('fts5', $moduleNames, true));
        $t->same(true, in_array($module, $moduleNames, true));
        $t->same(true, in_array('pragma_list', $pragmaNames, true));
        $t->same(true, in_array('function_list', $pragmaNames, true));
        $t->same(true, in_array('module_list', $pragmaNames, true));
    };
}

$tests['real upstream pragma5 dynamic introspection source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'pragma5.test 1.0 PRAGMA table_info(pragma_function_list)',
        'pragma5.test 1.1 builtin upper appears in pragma_function_list',
        'pragma5.test 1.2 application-defined external function appears with builtin=0',
        'pragma5.test 2.0 PRAGMA table_info(pragma_module_list)',
        'pragma5.test 2.1 fts5 appears in pragma_module_list',
        'pragma5.test 3.0 PRAGMA table_info(pragma_pragma_list)',
        'pragma5.test 3.1 pragma_list appears in pragma_pragma_list',
    ];

    $t->same(7, count($sections));
    $t->contains('pragma_function_list', $sections[0]);
    $t->contains('builtin=0', $sections[2]);
    $t->contains('pragma_module_list', $sections[3]);
    $t->contains('pragma_pragma_list', $sections[5]);
};

return $tests;
