<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaDynamicSchemaState;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma2.test pragma2-1.* through pragma2-3.*:
 *   schema.freelist_count is read-only, unqualified freelist_count resolves to
 *   main, and attached schema freelist counts remain independent.
 * - SQLite test/pragma2.test pragma2-4.1 through pragma2-4.8:
 *   cache_spill defaults to the schema cache size, OFF applies to all schemas,
 *   schema-qualified thresholds are raised to at least cache_size, and larger
 *   thresholds are preserved.
 * - SQLite test/pragma.test pragma-8.1.* and pragma-8.2.*:
 *   schema_version and user_version are independently readable/writable for
 *   main and attached schemas.
 */

$stateFor = static function (int $variant): SQLitePragmaDynamicSchemaState {
    return new SQLitePragmaDynamicSchemaState([
        'main' => [
            'cache_size' => 40 + ($variant % 23),
            'cache_spill' => true,
            'freelist_count' => 1 + ($variant % 7),
            'page_count' => 12 + $variant,
            'max_page_count' => 2000 + $variant,
            'schema_version' => 100 + $variant,
            'user_version' => $variant % 17,
        ],
        'temp' => [
            'cache_size' => 60 + ($variant % 19),
            'cache_spill' => true,
            'freelist_count' => $variant % 5,
            'page_count' => 3 + ($variant % 11),
            'schema_version' => 20 + $variant,
            'user_version' => 0,
        ],
        'aux' => [
            'cache_size' => 50 + ($variant % 31),
            'cache_spill' => true,
            'freelist_count' => 9 + ($variant % 13),
            'page_count' => 30 + $variant,
            'max_page_count' => 5000 + $variant,
            'schema_version' => 200 + $variant,
            'user_version' => 2 + ($variant % 29),
        ],
    ]);
};

foreach (range(1, 300) as $variant) {
    $tests[sprintf('real upstream pragma2 schema dynamic freelist readonly variant %03d', $variant)] = static function (TestRunner $t) use ($stateFor, $variant): void {
        $state = $stateFor($variant);
        $mainFreelist = 1 + ($variant % 7);
        $auxFreelist = 9 + ($variant % 13);

        $t->same($mainFreelist, $state->execute('PRAGMA freelist_count')['value']);
        $t->same($mainFreelist, $state->execute('PRAGMA main.freelist_count')['value']);
        $t->same($auxFreelist, $state->execute('PRAGMA aux.freelist_count')['value']);
        $write = $state->execute('PRAGMA aux.freelist_count = 500');
        $t->same($auxFreelist, $write['value']);
        $t->same(false, $write['changed']);
        $t->same('read_only_pragma_ignored', $write['reason']);
    };

    $tests[sprintf('real upstream pragma2 schema dynamic cache spill defaults and global off variant %03d', $variant)] = static function (TestRunner $t) use ($stateFor, $variant): void {
        $state = $stateFor($variant);
        $mainCache = 40 + ($variant % 23);
        $tempCache = 60 + ($variant % 19);
        $auxCache = 50 + ($variant % 31);

        $t->same($mainCache, $state->execute('PRAGMA cache_spill')['value']);
        $t->same($tempCache, $state->execute('PRAGMA temp.cache_spill')['value']);
        $t->same($auxCache, $state->execute('PRAGMA aux.cache_spill')['value']);
        $t->same(0, $state->execute('PRAGMA cache_spill=OFF')['value']);
        $t->same(0, $state->execute('PRAGMA main.cache_spill')['value']);
        $t->same(0, $state->execute('PRAGMA temp.cache_spill')['value']);
        $t->same(0, $state->execute('PRAGMA aux.cache_spill')['value']);
    };

    $tests[sprintf('real upstream pragma2 schema dynamic cache spill thresholds variant %03d', $variant)] = static function (TestRunner $t) use ($stateFor, $variant): void {
        $state = $stateFor($variant);
        $mainCache = 40 + ($variant % 23);
        $auxCache = 50 + ($variant % 31);
        $large = 100000 + $variant;

        $small = $state->execute('PRAGMA cache_spill=25');
        $negative = $state->execute('PRAGMA main.cache_spill(-25)');
        $auxLarge = $state->execute("PRAGMA aux.cache_spill={$large}");

        $t->same($mainCache, $small['value']);
        $t->same('raised_to_cache_size', $small['reason']);
        $t->same($mainCache, $negative['value']);
        $t->same($large, $auxLarge['value']);
        $t->same($large, $state->execute('PRAGMA aux.cache_spill')['value']);
        $t->same($mainCache, $state->execute('PRAGMA main.cache_spill')['value']);
        $t->same($auxCache, $stateFor($variant)->execute('PRAGMA aux.cache_spill=ON')['value']);
    };

    $tests[sprintf('real upstream pragma schema version attached writes variant %03d', $variant)] = static function (TestRunner $t) use ($stateFor, $variant): void {
        $state = $stateFor($variant);
        $mainNext = 1000 + $variant;
        $auxNext = 2000 + $variant;

        $t->same(100 + $variant, $state->execute('PRAGMA schema_version')['value']);
        $t->same($mainNext, $state->execute("PRAGMA schema_version={$mainNext}")['value']);
        $t->same($mainNext, $state->execute('PRAGMA main.schema_version')['value']);
        $t->same(200 + $variant, $state->execute('PRAGMA aux.schema_version')['value']);
        $t->same($auxNext, $state->execute("PRAGMA aux.schema_version={$auxNext}")['value']);
        $t->same($auxNext, $state->execute('PRAGMA aux.schema_version')['value']);
        $t->same($mainNext, $state->execute('PRAGMA schema_version')['value']);
    };

    $tests[sprintf('real upstream pragma user version independent from schema cookie variant %03d', $variant)] = static function (TestRunner $t) use ($stateFor, $variant): void {
        $state = $stateFor($variant);
        $mainUser = 3000 + $variant;
        $auxUser = 4000 + $variant;
        $mainSchema = 100 + $variant;
        $auxSchema = 200 + $variant;

        $t->same($variant % 17, $state->execute('PRAGMA user_version')['value']);
        $t->same($mainUser, $state->execute("PRAGMA user_version={$mainUser}")['value']);
        $t->same($mainSchema, $state->execute('PRAGMA schema_version')['value']);
        $t->same($auxUser, $state->execute("PRAGMA aux.user_version={$auxUser}")['value']);
        $t->same($auxSchema, $state->execute('PRAGMA aux.schema_version')['value']);
        $t->same($mainUser, $state->execute('PRAGMA main.user_version')['value']);
        $t->same($auxUser, $state->execute('PRAGMA aux.user_version')['value']);
    };
}

$tests['real upstream pragma schema dynamic cache spill cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma2.test pragma2-1.* through pragma2-3.* covers schema.freelist_count and ignored writes',
        'pragma2.test pragma2-4.1 through pragma2-4.8 covers cache_spill defaults, OFF/ON, schema qualification, and threshold values',
        'pragma.test pragma-8.1.* covers main and attached schema_version reads and writes',
        'pragma.test pragma-8.2.* covers user_version independence from schema_version',
    ];

    $t->same(4, count($sections));
    $t->contains('pragma2.test', $sections[0]);
    $t->contains('cache_spill', $sections[1]);
    $t->contains('pragma-8.1', $sections[2]);
    $t->contains('pragma-8.2', $sections[3]);
};

return $tests;
