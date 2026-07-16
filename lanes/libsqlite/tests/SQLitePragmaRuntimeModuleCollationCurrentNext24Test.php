<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaRuntimeCatalog;

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

$makeCatalog = static function (): SQLitePragmaRuntimeCatalog {
    $catalog = new SQLitePragmaRuntimeCatalog();
    $catalog->addCollation('wp_slug');
    $catalog->addCollation('nocase');
    $catalog->addModule('wp_option_tokens');
    $catalog->addModule('json_each');
    $catalog->addFunction('wp_option_checksum', 1, 0x800, 's', 'utf8', 0);
    $catalog->addFunction('wp_option_match', 2, 0x800, 's', 'utf8', 0);

    return $catalog;
};

$cases = [
    'collation-list status ok' => ['PRAGMA collation_list', 'status', 'ok'],
    'collation-list pragma name' => ['PRAGMA collation_list', 'pragma', 'collation_list'],
    'collation-list row count includes extension' => ['PRAGMA collation_list', 'rows.count', 4],
    'collation-list rtrim first' => ['PRAGMA collation_list', 'rows.0.name', 'RTRIM'],
    'collation-list rtrim seq' => ['PRAGMA collation_list', 'rows.0.seq', 0],
    'collation-list nocase second' => ['PRAGMA collation_list', 'rows.1.name', 'NOCASE'],
    'collation-list nocase seq' => ['PRAGMA collation_list', 'rows.1.seq', 1],
    'collation-list binary third' => ['PRAGMA collation_list', 'rows.2.name', 'BINARY'],
    'collation-list binary seq' => ['PRAGMA collation_list', 'rows.2.seq', 2],
    'collation-list extension appended' => ['PRAGMA collation_list', 'rows.3.name', 'wp_slug'],
    'collation-list extension seq' => ['PRAGMA collation_list', 'rows.3.seq', 3],
    'collation-list parenthesis form' => ['PRAGMA collation_list()', 'rows.1.name', 'NOCASE'],
    'collation-list main schema form' => ['PRAGMA main.collation_list', 'rows.2.name', 'BINARY'],
    'collation-list trailing semicolon' => ['PRAGMA collation_list;', 'rows.0.name', 'RTRIM'],
    'collation-list case insensitive parse' => ['PrAgMa CoLlAtIoN_LiSt', 'pragma', 'collation_list'],
    'module-list status ok' => ['PRAGMA module_list', 'status', 'ok'],
    'module-list pragma name' => ['PRAGMA module_list', 'pragma', 'module_list'],
    'module-list row count includes extension' => ['PRAGMA module_list', 'rows.count', 5],
    'module-list json-tree first' => ['PRAGMA module_list', 'rows.0.name', 'json_tree'],
    'module-list json-each second' => ['PRAGMA module_list', 'rows.1.name', 'json_each'],
    'module-list fts5 third' => ['PRAGMA module_list', 'rows.2.name', 'fts5'],
    'module-list rtree fourth' => ['PRAGMA module_list', 'rows.3.name', 'rtree'],
    'module-list extension appended' => ['PRAGMA module_list', 'rows.4.name', 'wp_option_tokens'],
    'module-list parenthesis form' => ['PRAGMA module_list()', 'rows.0.name', 'json_tree'],
    'module-list main schema form' => ['PRAGMA main.module_list', 'rows.1.name', 'json_each'],
    'module-list trailing semicolon' => ['PRAGMA module_list;', 'rows.4.name', 'wp_option_tokens'],
    'module-list case insensitive parse' => ['PRAGMA MODULE_LIST', 'pragma', 'module_list'],
    'function-list status ok' => ['PRAGMA function_list', 'status', 'ok'],
    'function-list pragma name' => ['PRAGMA function_list', 'pragma', 'function_list'],
    'function-list row count includes extension' => ['PRAGMA function_list', 'rows.count', 12],
    'function-list json-extract first' => ['PRAGMA function_list', 'rows.0.name', 'json_extract'],
    'function-list json-extract variadic' => ['PRAGMA function_list', 'rows.0.narg', -1],
    'function-list json-extract builtin' => ['PRAGMA function_list', 'rows.0.builtin', 1],
    'function-list json-extract encoding' => ['PRAGMA function_list', 'rows.0.enc', 'utf8'],
    'function-list jsonb-extract second' => ['PRAGMA function_list', 'rows.1.name', 'jsonb_extract'],
    'function-list json-valid variadic' => ['PRAGMA function_list', 'rows.2.narg', -1],
    'function-list json-error-position unary' => ['PRAGMA function_list', 'rows.3.narg', 1],
    'function-list lower unary' => ['PRAGMA function_list', 'rows.4.name', 'lower'],
    'function-list upper unary' => ['PRAGMA function_list', 'rows.5.name', 'upper'],
    'function-list length unary' => ['PRAGMA function_list', 'rows.6.name', 'length'],
    'function-list like binary' => ['PRAGMA function_list', 'rows.7.narg', 2],
    'function-list like ternary' => ['PRAGMA function_list', 'rows.8.narg', 3],
    'function-list glob binary' => ['PRAGMA function_list', 'rows.9.name', 'glob'],
    'function-list extension checksum name' => ['PRAGMA function_list', 'rows.10.name', 'wp_option_checksum'],
    'function-list extension checksum builtin' => ['PRAGMA function_list', 'rows.10.builtin', 0],
    'function-list extension checksum flags' => ['PRAGMA function_list', 'rows.10.flags', 0x800],
    'function-list extension match name' => ['PRAGMA function_list', 'rows.11.name', 'wp_option_match'],
    'function-list extension match arity' => ['PRAGMA function_list', 'rows.11.narg', 2],
    'function-list parenthesis form' => ['PRAGMA function_list()', 'rows.7.name', 'like'],
    'function-list main schema form' => ['PRAGMA main.function_list', 'rows.9.narg', 2],
    'function-list trailing semicolon' => ['PRAGMA function_list;', 'rows.10.name', 'wp_option_checksum'],
    'function-list case insensitive parse' => ['PRAGMA FUNCTION_LIST', 'pragma', 'function_list'],
];

$tests = [];
foreach ($cases as $name => [$sql, $path, $expected]) {
    $tests['pragma runtime module collation current next24 ' . $name] = static function (TestRunner $t) use ($makeCatalog, $valueAt, $sql, $path, $expected): void {
        $t->same($expected, $valueAt($makeCatalog()->execute($sql), $path));
    };
}

$tests['pragma runtime module collation current next24 cursor freezes collation rows'] = static function (TestRunner $t) use ($makeCatalog): void {
    $catalog = $makeCatalog();
    $cursor = $catalog->executeCursor('PRAGMA collation_list');
    $catalog->addCollation('wp_locale');

    $t->same('collation_list', $cursor->metadata()['pragma']);
    $t->same('RTRIM', $cursor->current()['name']);
    $t->same('NOCASE', $cursor->next()['name']);
    $cursor->next();
    $t->same(['BINARY', 'wp_slug'], array_column($cursor->remainingRows(), 'name'));
};

$tests['pragma runtime module collation current next24 cursor walks module rows'] = static function (TestRunner $t) use ($makeCatalog): void {
    $cursor = $makeCatalog()->executeCursor('PRAGMA module_list');

    $t->same('module_list', $cursor->metadata()['pragma']);
    $t->same('json_tree', $cursor->current()['name']);
    $t->same('json_each', $cursor->next()['name']);
    $cursor->next();
    $t->same(['fts5', 'rtree', 'wp_option_tokens'], array_column($cursor->remainingRows(), 'name'));
};

$tests['pragma runtime module collation current next24 cursor walks function rows'] = static function (TestRunner $t) use ($makeCatalog): void {
    $cursor = $makeCatalog()->executeCursor('PRAGMA function_list');

    $t->same('function_list', $cursor->metadata()['pragma']);
    $t->same('json_extract', $cursor->current()['name']);
    $t->same('jsonb_extract', $cursor->next()['name']);
    $rows = $cursor->rows();
    $t->same('wp_option_match', $rows[count($rows) - 1]['name']);
};

$tests['pragma runtime module collation current next24 custom constructor preserves explicit order'] = static function (TestRunner $t): void {
    $catalog = new SQLitePragmaRuntimeCatalog(
        ['application', 'NOCASE', 'application'],
        ['wp_settings', 'json_tree', 'wp_settings'],
        [
            ['name' => 'wp_json', 'builtin' => 0, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 7],
            ['name' => 'wp_json', 'builtin' => 0, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 9],
        ],
    );

    $t->same(['application', 'NOCASE'], array_column($catalog->execute('PRAGMA collation_list')['rows'], 'name'));
    $t->same(['wp_settings', 'json_tree'], array_column($catalog->execute('PRAGMA module_list')['rows'], 'name'));
    $t->same(1, count($catalog->execute('PRAGMA function_list')['rows']));
    $t->same(9, $catalog->execute('PRAGMA function_list')['rows'][0]['flags']);
};

$tests['pragma runtime module collation current next24 replacing extension function keeps row slot'] = static function (TestRunner $t) use ($makeCatalog): void {
    $catalog = $makeCatalog();
    $catalog->addFunction('WP_OPTION_MATCH', 2, 0x1000, 's', 'utf8', 0);
    $rows = $catalog->execute('PRAGMA function_list')['rows'];

    $t->same(12, count($rows));
    $t->same('wp_option_match', $rows[11]['name']);
    $t->same(0x1000, $rows[11]['flags']);
};

$tests['pragma runtime module collation current next24 rejects malformed pragma shapes'] = static function (TestRunner $t) use ($makeCatalog): void {
    $catalog = $makeCatalog();

    $t->throws(InvalidArgumentException::class, static fn () => $catalog->execute('PRAGMA collation_list(wp_options)'));
    $t->throws(InvalidArgumentException::class, static fn () => $catalog->execute('PRAGMA temp.module_list'));
    $t->throws(InvalidArgumentException::class, static fn () => $catalog->execute('PRAGMA function_list = json_extract'));
    $t->throws(InvalidArgumentException::class, static fn () => $catalog->execute('PRAGMA pragma_module_list'));
    $t->throws(InvalidArgumentException::class, static fn () => $catalog->addModule(''));
    $t->throws(InvalidArgumentException::class, static fn () => new SQLitePragmaRuntimeCatalog([], [], [['name' => '', 'narg' => 1]]));
};

return $tests;
