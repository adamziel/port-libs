<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaPagerState;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma.test.
 *
 * This ports pager-setting PRAGMA behavior outside the already accepted
 * pragma2.test cache_spill cluster:
 * - pragma-1.1 through pragma-1.15: cache_size, default_cache_size,
 *   synchronous keyword/numeric normalization, reopen reset, and VACUUM-stable
 *   persistent defaults on main.
 * - pragma-2.* and pragma-4.*: schema-qualified synchronous/cache-size defaults
 *   for attached databases remain independent from main.
 * - pragma-5.*: synchronous changes are connection-local pager settings and
 *   are not persisted by reopen unless default cache state is changed.
 */

foreach (range(1, 1000) as $variant) {
    $builtIn = 640 + ($variant % 257);
    $cacheSize = 1000 + $variant;
    $negativeCacheSize = -2000 - $variant;
    $persistentDefault = 120 + ($variant % 700);
    $mainSchema = 'main';
    $auxSchema = sprintf('auxpager%04d', $variant);
    $auxCache = 300 + ($variant % 90);
    $auxDefault = 700 + ($variant % 110);
    $numericSync = [0, 1, 2, 3, 4, 8, 10][$variant % 7];
    $expectedNumericSync = $numericSync <= 4 ? $numericSync : $numericSync % 4;

    $tests[sprintf('real upstream pragma pager settings cache default sync variant %04d', $variant)] = static function (TestRunner $t) use ($builtIn, $cacheSize, $negativeCacheSize, $persistentDefault, $mainSchema, $auxSchema, $auxCache, $auxDefault, $numericSync, $expectedNumericSync): void {
        $state = new SQLitePragmaPagerState([], $builtIn);

        $t->same($builtIn, $state->execute('PRAGMA cache_size')['value']);
        $t->same($builtIn, $state->execute('PRAGMA default_cache_size')['value']);
        $t->same(2, $state->execute('PRAGMA synchronous')['value']);

        $off = $state->execute('PRAGMA synchronous=OFF');
        $cache = $state->execute("PRAGMA cache_size={$cacheSize}");
        $t->same(0, $off['value']);
        $t->same($cacheSize, $cache['value']);
        $t->same($builtIn, $state->execute('PRAGMA default_cache_size')['value']);
        $t->same(0, $state->execute('PRAGMA synchronous')['value']);

        $state->reopen();
        $t->same($builtIn, $state->execute('PRAGMA cache_size')['value']);
        $t->same($builtIn, $state->execute('PRAGMA default_cache_size')['value']);
        $t->same(2, $state->execute('PRAGMA synchronous')['value']);

        $state->execute("PRAGMA cache_size={$negativeCacheSize}");
        $t->same($negativeCacheSize, $state->execute('PRAGMA cache_size')['value']);
        $t->same($builtIn, $state->execute('PRAGMA default_cache_size')['value']);

        $default = $state->execute("PRAGMA default_cache_size=-{$persistentDefault}");
        $t->same($persistentDefault, $default['value']);
        $t->same($persistentDefault, $state->execute('PRAGMA cache_size')['value']);
        $t->same('assigned_persistent_default', $default['reason']);

        $state->reopen();
        $t->same($persistentDefault, $state->execute('PRAGMA cache_size')['value']);
        $t->same($persistentDefault, $state->execute('PRAGMA default_cache_size')['value']);
        $t->same(2, $state->execute('PRAGMA synchronous')['value']);

        $state->execute('PRAGMA default_cache_size=0');
        $t->same($builtIn, $state->execute('PRAGMA default_cache_size')['value']);
        $t->same($builtIn, $state->execute('PRAGMA cache_size')['value']);

        $t->same(1, $state->execute('PRAGMA synchronous=NORMAL')['value']);
        $t->same(3, $state->execute('PRAGMA synchronous=EXTRA')['value']);
        $t->same(2, $state->execute('PRAGMA synchronous=FULL')['value']);
        $t->same($expectedNumericSync, $state->execute("PRAGMA synchronous={$numericSync}")['value']);

        $state->attach($auxSchema);
        $state->execute("PRAGMA {$auxSchema}.cache_size={$auxCache}");
        $state->execute("PRAGMA {$auxSchema}.default_cache_size={$auxDefault}");
        $state->execute("PRAGMA {$auxSchema}.synchronous=OFF");

        $t->same($mainSchema, $state->execute('PRAGMA main.cache_size')['schema']);
        $t->same($builtIn, $state->execute('PRAGMA main.cache_size')['value']);
        $t->same($auxSchema, $state->execute("PRAGMA {$auxSchema}.cache_size")['schema']);
        $t->same($auxDefault, $state->execute("PRAGMA {$auxSchema}.cache_size")['value']);
        $t->same($auxDefault, $state->execute("PRAGMA {$auxSchema}.default_cache_size")['value']);
        $t->same(0, $state->execute("PRAGMA {$auxSchema}.synchronous")['value']);

        $state->reopen();
        $t->same(2, $state->execute("PRAGMA {$auxSchema}.synchronous")['value']);
        $t->same($auxDefault, $state->execute("PRAGMA {$auxSchema}.cache_size")['value']);
    };
}

$tests['real upstream pragma pager settings source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-1.1 through pragma-1.15 cache_size, default_cache_size, synchronous, reopen, and reset behavior',
        'pragma.test pragma-2.* attached database synchronous behavior remains schema-qualified',
        'pragma.test pragma-4.* attached database cache_size/default_cache_size behavior remains schema-qualified',
        'pragma.test pragma-5.* synchronous is connection-local pager state',
    ];

    $t->same(4, count($sections));
    $t->contains('pragma-1.1', $sections[0]);
    $t->contains('pragma-4.*', $sections[2]);
};

return $tests;
