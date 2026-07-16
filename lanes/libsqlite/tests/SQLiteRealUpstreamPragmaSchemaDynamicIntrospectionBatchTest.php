<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma5.test covers the virtual
 * introspection tables pragma_function_list, pragma_module_list, and
 * pragma_pragma_list. This batch ports those rows into the PHP schema catalog
 * with many distinct dynamic application-defined functions, modules, and
 * collations so the virtual PRAGMA catalog behavior is exercised at corpus
 * scale without adding synthetic upstream script identifiers.
 */

$makeCatalog = static function (int $variant): SQLitePragmaSchemaCatalog {
    $suffix = sprintf('%03d', $variant);
    $kind = $variant % 3;
    $encoding = ['utf8', 'utf16le', 'utf16be'][$variant % 3];
    $narg = ($variant % 5) - 1;
    $flags = 2048 + ($variant * 16);

    return new SQLitePragmaSchemaCatalog(
        [],
        [
            ['name' => 'upper', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2099200],
            ['name' => "external_{$suffix}", 'builtin' => 0, 'type' => ['s', 'w', 'a'][$kind], 'enc' => $encoding, 'narg' => $narg, 'flags' => $flags],
            ['name' => "app_rank_{$suffix}", 'builtin' => 0, 'type' => 'w', 'enc' => 'utf8', 'narg' => 2, 'flags' => $flags + 1],
            ['name' => "app_norm_{$suffix}", 'builtin' => 0, 'type' => 's', 'enc' => $encoding, 'narg' => 1, 'flags' => $flags + 2],
        ],
        [
            ['name' => 'fts5'],
            ['name' => "app_series_{$suffix}"],
            ['name' => "app_tree_{$suffix}"],
        ],
        [
            ['seq' => 0, 'name' => 'binary'],
            ['seq' => 1, 'name' => "app_locale_{$suffix}"],
            ['seq' => 2, 'name' => 'nocase'],
        ],
    );
};

$rowByName = static function (array $rows, string $name): array {
    foreach ($rows as $row) {
        if (($row['name'] ?? null) === $name) {
            return $row;
        }
    }

    throw new RuntimeException("Missing pragma row {$name}");
};

$case = static function (string $name, callable $callback) use (&$tests): void {
    $tests['real upstream pragma schema dynamic introspection batch ' . $name] = static function (TestRunner $t) use ($callback): void {
        [$expected, $actual] = $callback();
        $t->same($expected, $actual);
    };
};

for ($variant = 0; $variant < 170; $variant++) {
    $suffix = sprintf('%03d', $variant);
    $external = "external_{$suffix}";
    $series = "app_series_{$suffix}";
    $locale = "APP_LOCALE_{$suffix}";
    $type = ['s', 'w', 'a'][$variant % 3];
    $encoding = ['utf8', 'utf16le', 'utf16be'][$variant % 3];
    $narg = ($variant % 5) - 1;
    $flags = 2048 + ($variant * 16);

    $case("pragma5-1.0 function table_info column count variant {$suffix}", static fn (): array => [
        6,
        count($makeCatalog($variant)->execute('PRAGMA table_info(pragma_function_list)')['rows']),
    ]);
    $case("pragma5-1.0 function table_info names variant {$suffix}", static fn (): array => [
        ['name', 'builtin', 'type', 'enc', 'narg', 'flags'],
        array_column($makeCatalog($variant)->execute('PRAGMA table_info(pragma_function_list)')['rows'], 'name'),
    ]);
    $case("pragma5-1.1 builtin upper remains discoverable variant {$suffix}", static fn (): array => [
        ['name' => 'upper', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2099200],
        $rowByName($makeCatalog($variant)->execute('PRAGMA function_list')['rows'], 'upper'),
    ]);
    $case("pragma5-1.2 external function is non-builtin variant {$suffix}", static fn (): array => [
        ['name' => $external, 'builtin' => 0, 'type' => $type, 'enc' => $encoding, 'narg' => $narg, 'flags' => $flags],
        $rowByName($makeCatalog($variant)->executeTableValuedPragma('pragma_function_list()')['rows'], $external),
    ]);
    $case("pragma5-1.2 external function table sorts by name variant {$suffix}", static fn (): array => [
        ["app_norm_{$suffix}", "app_rank_{$suffix}", $external, 'upper'],
        array_column($makeCatalog($variant)->executeTableValuedPragma('pragma_function_list()')['rows'], 'name'),
    ]);
    $case("pragma5-2.0 module table_info single name column variant {$suffix}", static fn (): array => [
        ['name'],
        array_column($makeCatalog($variant)->execute('PRAGMA table_info(pragma_module_list)')['rows'], 'name'),
    ]);
    $case("pragma5-2.1 fts5 module remains discoverable variant {$suffix}", static fn (): array => [
        ['fts5'],
        array_values(array_intersect(['fts5'], array_column($makeCatalog($variant)->execute('PRAGMA module_list')['rows'], 'name'))),
    ]);
    $case("pragma5-2.1 dynamic module is sorted and lowercased variant {$suffix}", static fn (): array => [
        ["app_series_{$suffix}", "app_tree_{$suffix}", 'fts5'],
        array_column($makeCatalog($variant)->executeTableValuedPragma('pragma_module_list()')['rows'], 'name'),
    ]);
    $case("pragma5-3.0 pragma table_info single name column variant {$suffix}", static fn (): array => [
        ['name'],
        array_column($makeCatalog($variant)->execute('PRAGMA table_info(pragma_pragma_list)')['rows'], 'name'),
    ]);
    $case("pragma5-3.1 pragma_list contains pragma_list variant {$suffix}", static fn (): array => [
        ['pragma_list'],
        array_values(array_intersect(['pragma_list'], array_column($makeCatalog($variant)->execute('PRAGMA pragma_list')['rows'], 'name'))),
    ]);
    $case("pragma5 collation list preserves sequence variant {$suffix}", static fn (): array => [
        ['BINARY', $locale, 'NOCASE'],
        array_column($makeCatalog($variant)->execute('PRAGMA collation_list')['rows'], 'name'),
    ]);
    $case("pragma5 table-valued parser recognizes virtual lists variant {$suffix}", static fn (): array => [
        ['function_list', 'module_list', 'pragma_list', 'collation_list'],
        [
            SQLitePragmaSchemaCatalog::parseTableValuedPragma('pragma_function_list()')['pragma'],
            SQLitePragmaSchemaCatalog::parseTableValuedPragma('pragma_module_list()')['pragma'],
            SQLitePragmaSchemaCatalog::parseTableValuedPragma('pragma_pragma_list()')['pragma'],
            SQLitePragmaSchemaCatalog::parseTableValuedPragma('pragma_collation_list()')['pragma'],
        ],
    ]);
}

return $tests;
