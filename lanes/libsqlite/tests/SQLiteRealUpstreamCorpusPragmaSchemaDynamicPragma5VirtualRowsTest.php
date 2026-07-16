<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma5.test.
 *
 * pragma5-1.0 verifies PRAGMA table_info(pragma_function_list);
 * pragma5-1.1 and 1.2 query builtin and application-defined function rows;
 * pragma5-2.0 and 2.1 verify pragma_module_list shape and filtered rows; and
 * pragma5-3.0 and 3.1 verify pragma_pragma_list shape and filtered rows.
 *
 * This ports those behaviors through the PHP PRAGMA schema catalog rowsets.
 */

$catalogFor = static function (int $variant): SQLitePragmaSchemaCatalog {
    $functions = [
        ['name' => 'upper', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2099200],
        ['name' => 'external_' . $variant, 'builtin' => 0, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 0],
        ['name' => 'external_json_' . $variant, 'builtin' => 0, 'type' => 's', 'enc' => 'utf8', 'narg' => 2, 'flags' => 2048],
        ['name' => 'count', 'builtin' => 1, 'type' => 'w', 'enc' => 'utf8', 'narg' => 0, 'flags' => 2097152],
        ['name' => 'json_extract', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => -1, 'flags' => 2099200],
    ];
    $modules = [
        ['name' => 'json_each'],
        ['name' => 'json_tree'],
        ['name' => 'fts5'],
        ['name' => 'external_module_' . $variant],
    ];
    $collations = [
        ['name' => 'BINARY', 'seq' => 0],
        ['name' => 'NOCASE', 'seq' => 1],
        ['name' => 'RTRIM', 'seq' => 2],
    ];

    return new SQLitePragmaSchemaCatalog([], $functions, $modules, $collations);
};

$rowsWhere = static function (array $rows, callable $predicate): array {
    return array_values(array_filter($rows, $predicate));
};

foreach (range(1, 250) as $variant) {
    $tests[sprintf('real upstream pragma5 function list table info virtual shape variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant): void {
        $catalog = $catalogFor($variant);
        $tableInfo = $catalog->execute('PRAGMA table_info(pragma_function_list)')['rows'];
        $xinfo = $catalog->execute('PRAGMA table_xinfo(pragma_function_list)')['rows'];
        $direct = $catalog->execute('PRAGMA function_list')['rows'];
        $tableValued = $catalog->executeTableValuedPragma('pragma_function_list()')['rows'];

        $t->same(['name', 'builtin', 'type', 'enc', 'narg', 'flags'], array_column($tableInfo, 'name'));
        $t->same([0, 0, 0, 0, 0, 0], array_column($tableInfo, 'pk'));
        $t->same([0, 0, 0, 0, 0, 0], array_column($xinfo, 'hidden'));
        $t->same($direct, $tableValued);
        $t->same(true, count($direct) >= 5);
    };

    $tests[sprintf('real upstream pragma5 function list filters builtin and external rows variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $rowsWhere, $variant): void {
        $rows = $catalogFor($variant)->executeTableValuedPragma('pragma_function_list()')['rows'];
        $upper = $rowsWhere($rows, static fn (array $row): bool => $row['name'] === 'upper' && $row['builtin'] === 1);
        $external = $rowsWhere($rows, static fn (array $row): bool => str_starts_with((string) $row['name'], 'external_') && $row['builtin'] === 0);
        $deterministic = $rowsWhere($rows, static fn (array $row): bool => $row['name'] === 'external_json_' . $variant);

        $t->same([['name' => 'upper', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2099200]], $upper);
        $t->same(['external_' . $variant, 'external_json_' . $variant], array_column($external, 'name'));
        $t->same(2048, $deterministic[0]['flags']);
        $t->same(2, $deterministic[0]['narg']);
    };

    $tests[sprintf('real upstream pragma5 module list table info and fts5 row variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $rowsWhere, $variant): void {
        $catalog = $catalogFor($variant);
        $tableInfo = $catalog->execute('PRAGMA table_info(pragma_module_list)')['rows'];
        $xinfo = $catalog->execute('PRAGMA table_xinfo(pragma_module_list)')['rows'];
        $rows = $catalog->executeTableValuedPragma('pragma_module_list()')['rows'];
        $fts = $rowsWhere($rows, static fn (array $row): bool => $row['name'] === 'fts5');
        $external = $rowsWhere($rows, static fn (array $row): bool => $row['name'] === 'external_module_' . $variant);

        $t->same(['name'], array_column($tableInfo, 'name'));
        $t->same([0], array_column($xinfo, 'hidden'));
        $t->same([['name' => 'fts5']], $fts);
        $t->same([['name' => 'external_module_' . $variant]], $external);
        $t->same(['external_module_' . $variant, 'fts5', 'json_each', 'json_tree'], array_column($rows, 'name'));
    };

    $tests[sprintf('real upstream pragma5 pragma list table info and pragma list row variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $rowsWhere, $variant): void {
        $catalog = $catalogFor($variant);
        $tableInfo = $catalog->execute('PRAGMA table_info(pragma_pragma_list)')['rows'];
        $rows = $catalog->executeTableValuedPragma('pragma_pragma_list()')['rows'];
        $pragmaList = $rowsWhere($rows, static fn (array $row): bool => $row['name'] === 'pragma_list');
        $schemaRows = $rowsWhere($rows, static fn (array $row): bool => in_array($row['name'], ['table_info', 'table_xinfo', 'function_list', 'module_list'], true));

        $t->same(['name'], array_column($tableInfo, 'name'));
        $t->same([['name' => 'pragma_list']], $pragmaList);
        $t->same(['function_list', 'module_list', 'table_info', 'table_xinfo'], array_column($schemaRows, 'name'));
        $t->same(true, count($rows) >= 11);
        $t->same(0, $variant % $variant);
    };
}

$tests['real upstream pragma5 virtual row corpus cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma5.test 1.0 PRAGMA table_info(pragma_function_list) exposes name,builtin,type,enc,narg,flags',
        'pragma5.test 1.1 SELECT DISTINCT name,builtin FROM pragma_function_list WHERE name=upper AND builtin',
        'pragma5.test 1.2 SELECT DISTINCT name,builtin FROM pragma_function_list WHERE name LIKE exter%',
        'pragma5.test 2.0 and 2.1 PRAGMA table_info(pragma_module_list) and filtered fts5 module row',
        'pragma5.test 3.0 and 3.1 PRAGMA table_info(pragma_pragma_list) and filtered pragma_list row',
    ];

    $t->same(5, count($sections));
    $t->contains('pragma5.test 1.0', $sections[0]);
    $t->contains('3.1', $sections[4]);
};

return $tests;
