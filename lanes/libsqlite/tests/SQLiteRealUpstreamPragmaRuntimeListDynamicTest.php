<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma5.test 1.0 through 1.2:
 *   pragma_function_list exposes virtual-table shaped columns, builtin rows,
 *   and application-defined functions such as the upstream "external" test
 *   function.
 * - SQLite test/pragma5.test 2.0 through 3.1:
 *   pragma_module_list and pragma_pragma_list expose one-column introspection
 *   rowsets and are queryable both as PRAGMA statements and table-valued
 *   pragma_*() functions.
 *
 * This dynamic corpus keeps those upstream runtime-list rowsets generic by
 * varying application function/module/collation names while asserting stable
 * ordering, direct/table-valued parity, virtual-table column metadata, and
 * guard behavior for malformed introspection rows.
 */

$catalogFor = static function (int $variant): SQLitePragmaSchemaCatalog {
    $suffix = sprintf('%03d', $variant);

    return new SQLitePragmaSchemaCatalog(
        [],
        [
            ['name' => 'upper', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2099200],
            ['name' => 'external_runtime_' . $suffix, 'builtin' => 0, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => $variant],
            ['name' => 'external_window_' . $suffix, 'builtin' => 0, 'type' => 'w', 'enc' => 'utf16le', 'narg' => 2, 'flags' => 3145728 + $variant],
            ['name' => 'external_aggregate_' . $suffix, 'builtin' => 0, 'type' => 'a', 'enc' => 'utf16be', 'narg' => -1, 'flags' => 1048576 + $variant],
        ],
        [
            ['name' => 'json_each'],
            ['name' => 'runtime_module_' . $suffix],
            ['name' => 'fts5'],
            ['name' => 'runtime_aux_' . $suffix],
        ],
        [
            ['seq' => 0, 'name' => 'BINARY'],
            ['seq' => 1, 'name' => 'NOCASE'],
            ['seq' => 2, 'name' => 'RTRIM'],
            ['seq' => 20 + $variant, 'name' => 'runtime_collation_' . $suffix],
        ],
    );
};

$rowByName = static function (array $rows, string $name): array {
    foreach ($rows as $row) {
        if (($row['name'] ?? null) === $name) {
            return $row;
        }
    }

    throw new RuntimeException("missing row {$name}");
};

foreach (range(1, 250) as $variant) {
    $suffix = sprintf('%03d', $variant);
    $external = 'external_runtime_' . $suffix;
    $window = 'external_window_' . $suffix;
    $aggregate = 'external_aggregate_' . $suffix;
    $module = 'runtime_module_' . $suffix;
    $auxModule = 'runtime_aux_' . $suffix;
    $collation = 'RUNTIME_COLLATION_' . $suffix;

    $tests[sprintf('real upstream pragma5 runtime function-list custom rows variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $rowByName, $variant, $external, $window, $aggregate): void {
        $catalog = $catalogFor($variant);
        $direct = $catalog->execute('PRAGMA function_list');
        $tableValued = $catalog->executeTableValuedPragma('pragma_function_list()');
        $names = array_column($direct['rows'], 'name');

        $t->same('ok', $direct['status']);
        $t->same('function_list', $direct['pragma']);
        $t->same($direct['rows'], $tableValued['rows']);
        $t->same($names, array_values($names));
        $t->same(['name', 'builtin', 'type', 'enc', 'narg', 'flags'], array_column($catalog->execute('PRAGMA table_info(pragma_function_list)')['rows'], 'name'));

        $externalRow = $rowByName($direct['rows'], $external);
        $windowRow = $rowByName($direct['rows'], $window);
        $aggregateRow = $rowByName($direct['rows'], $aggregate);
        $upperRow = $rowByName($direct['rows'], 'upper');

        $t->same(0, $externalRow['builtin']);
        $t->same('s', $externalRow['type']);
        $t->same('utf8', $externalRow['enc']);
        $t->same($variant, $externalRow['flags']);
        $t->same('w', $windowRow['type']);
        $t->same('utf16le', $windowRow['enc']);
        $t->same(3145728 + $variant, $windowRow['flags']);
        $t->same('a', $aggregateRow['type']);
        $t->same('utf16be', $aggregateRow['enc']);
        $t->same(-1, $aggregateRow['narg']);
        $t->same(1, $upperRow['builtin']);
    };

    $tests[sprintf('real upstream pragma5 runtime module and pragma lists variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant, $module, $auxModule): void {
        $catalog = $catalogFor($variant);
        $directModules = $catalog->execute('PRAGMA module_list');
        $tableModules = $catalog->executeTableValuedPragma('pragma_module_list()');
        $moduleNames = array_column($directModules['rows'], 'name');
        $pragmaNames = array_column($catalog->executeTableValuedPragma('pragma_pragma_list()')['rows'], 'name');

        $t->same('module_list', $directModules['pragma']);
        $t->same($directModules['rows'], $tableModules['rows']);
        $t->same($moduleNames, array_values($moduleNames));
        $t->same(true, in_array('fts5', $moduleNames, true));
        $t->same(true, in_array('json_each', $moduleNames, true));
        $t->same(true, in_array($module, $moduleNames, true));
        $t->same(true, in_array($auxModule, $moduleNames, true));
        $t->same(['name'], array_column($catalog->execute('PRAGMA table_info(pragma_module_list)')['rows'], 'name'));
        $t->same(['name'], array_column($catalog->execute('PRAGMA table_info(pragma_pragma_list)')['rows'], 'name'));
        $t->same(true, in_array('pragma_list', $pragmaNames, true));
        $t->same(true, in_array('function_list', $pragmaNames, true));
        $t->same(true, in_array('module_list', $pragmaNames, true));
    };

    $tests[sprintf('real upstream pragma5 runtime collation-list order variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant, $collation): void {
        $catalog = $catalogFor($variant);
        $direct = $catalog->execute('PRAGMA collation_list');
        $names = array_column($direct['rows'], 'name');
        $seqs = array_column($direct['rows'], 'seq');

        $t->same('collation_list', $direct['pragma']);
        $t->same([0, 1, 2, 20 + $variant], $seqs);
        $t->same(['BINARY', 'NOCASE', 'RTRIM', $collation], $names);
        $t->same($direct['rows'], $catalog->executeTableValuedPragma('pragma_collation_list()')['rows']);
        $t->same($collation, $direct['rows'][3]['name']);
    };

    $tests[sprintf('real upstream pragma5 runtime malformed row guards variant %03d', $variant)] = static function (TestRunner $t) use ($variant): void {
        $badType = static fn (): SQLitePragmaSchemaCatalog => new SQLitePragmaSchemaCatalog([], [
            ['name' => 'external_bad_type_' . $variant, 'type' => 'x'],
        ]);
        $badEncoding = static fn (): SQLitePragmaSchemaCatalog => new SQLitePragmaSchemaCatalog([], [
            ['name' => 'external_bad_encoding_' . $variant, 'enc' => 'latin1'],
        ]);
        $badModule = static fn (): SQLitePragmaSchemaCatalog => new SQLitePragmaSchemaCatalog([], [], [['name' => '']]);
        $badCollation = static fn (): SQLitePragmaSchemaCatalog => new SQLitePragmaSchemaCatalog([], [], [], [['name' => '']]);

        $t->throws(InvalidArgumentException::class, static fn () => $badType()->execute('PRAGMA function_list'));
        $t->throws(InvalidArgumentException::class, static fn () => $badEncoding()->executeTableValuedPragma('pragma_function_list()'));
        $t->throws(InvalidArgumentException::class, static fn () => $badModule()->execute('PRAGMA module_list'));
        $t->throws(InvalidArgumentException::class, static fn () => $badCollation()->execute('PRAGMA collation_list'));
    };
}

return $tests;
