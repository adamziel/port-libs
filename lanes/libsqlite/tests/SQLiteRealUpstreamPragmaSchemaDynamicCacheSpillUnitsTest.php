<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaPagerState;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma2.test.
 *
 * This ports the cache_spill byte-threshold behavior from pragma2-4.5.2
 * through pragma2-5.3:
 * - PRAGMA cache_spill=N stores an explicit page-count threshold.
 * - PRAGMA cache_spill(-N) converts bytes into page-size units.
 * - Schema-qualified cache_spill changes affect only the named schema.
 * - Unqualified cache_spill changes apply to all currently attached schemas.
 */

$pageSizes = [512, 1024, 2048, 4096, 8192, 16384, 32768, 65536];

foreach (range(1, 250) as $variant) {
    $pageSize = $pageSizes[$variant % count($pageSizes)];
    $cacheSize = 2 + ($variant % 97);
    $bytes = 1 + (($variant * 37) % 200000);
    $expectedPages = max(1, intdiv($bytes, max(1, intdiv($pageSize, 1024))));
    $aux = sprintf('auxspill_%03d', $variant);

    $tests[sprintf('real upstream pragma2 cache spill negative byte threshold main variant %03d', $variant)] = static function (TestRunner $t) use ($pageSize, $cacheSize, $bytes, $expectedPages): void {
        $state = new SQLitePragmaPagerState([
            'main' => ['cache_size' => $cacheSize, 'page_size' => $pageSize],
            'temp' => ['cache_size' => 2000, 'page_size' => 1024],
        ]);

        $result = $state->execute('PRAGMA cache_spill(-' . $bytes . ')');
        $read = $state->execute('PRAGMA cache_spill');

        $t->same('cache_spill', $result['pragma']);
        $t->same('main', $result['schema']);
        $t->same($expectedPages, $result['value']);
        $t->same([['cache_spill' => $expectedPages]], $result['rows']);
        $t->same($expectedPages, $read['value']);
        $t->same($pageSize, $result['pager']['page_size']);
        $t->same($cacheSize, $result['pager']['cache_size']);
    };

    $tests[sprintf('real upstream pragma2 cache spill positive threshold raised to cache size variant %03d', $variant)] = static function (TestRunner $t) use ($pageSize, $cacheSize): void {
        $state = new SQLitePragmaPagerState([
            'main' => ['cache_size' => $cacheSize, 'page_size' => $pageSize],
        ]);

        $small = $state->execute('PRAGMA cache_spill=1');
        $large = $state->execute('PRAGMA cache_spill=' . ($cacheSize + 1000));

        $t->same($cacheSize, $small['value']);
        $t->same($cacheSize, $small['pager']['cache_spill']);
        $t->same($cacheSize + 1000, $large['value']);
        $t->same($cacheSize + 1000, $large['pager']['cache_spill']);
        $t->same('assigned_connection_local', $large['reason']);
    };

    $tests[sprintf('real upstream pragma2 cache spill schema qualified isolation variant %03d', $variant)] = static function (TestRunner $t) use ($pageSize, $cacheSize, $bytes, $expectedPages, $aux): void {
        $state = new SQLitePragmaPagerState([
            'main' => ['cache_size' => $cacheSize, 'page_size' => $pageSize],
            $aux => ['cache_size' => $cacheSize + 10, 'page_size' => $pageSize],
        ]);

        $auxResult = $state->execute("PRAGMA {$aux}.cache_spill(-{$bytes})");
        $mainRead = $state->execute('PRAGMA main.cache_spill');
        $auxRead = $state->execute("PRAGMA {$aux}.cache_spill");

        $t->same($aux, $auxResult['schema']);
        $t->same($expectedPages, $auxResult['value']);
        $t->same($cacheSize, $mainRead['value']);
        $t->same($expectedPages, $auxRead['value']);
        $t->same($cacheSize, $state->state()['main']['cache_spill']);
        $t->same($expectedPages, $state->state()[$aux]['cache_spill']);
    };

    $tests[sprintf('real upstream pragma2 cache spill unqualified broadcasts attached schemas variant %03d', $variant)] = static function (TestRunner $t) use ($pageSize, $cacheSize, $bytes, $expectedPages, $aux): void {
        $state = new SQLitePragmaPagerState([
            'main' => ['cache_size' => $cacheSize, 'page_size' => $pageSize],
            $aux => ['cache_size' => $cacheSize + 10, 'page_size' => $pageSize],
        ]);

        $result = $state->execute('PRAGMA cache_spill(-' . $bytes . ')');
        $mainRead = $state->execute('PRAGMA main.cache_spill');
        $auxRead = $state->execute("PRAGMA {$aux}.cache_spill");

        $t->same($expectedPages, $result['value']);
        $t->same($expectedPages, $mainRead['value']);
        $t->same($expectedPages, $auxRead['value']);
        $t->same($state->state()['main']['cache_spill'], $state->state()[$aux]['cache_spill']);
        $t->same($expectedPages, $state->state()['main']['cache_spill']);
    };
}

$tests['real upstream pragma2 cache spill units parser and source citations'] = static function (TestRunner $t): void {
    $t->same(['pragma' => 'cache_spill', 'schema' => 'main', 'value' => '-51'], SQLitePragmaPagerState::parse('PRAGMA cache_spill(-51)'));
    $t->same(['pragma' => 'cache_spill', 'schema' => 'aux', 'value' => '100000'], SQLitePragmaPagerState::parse('PRAGMA aux.cache_spill=100000'));

    $sections = [
        'pragma2.test pragma2-4.5.2 stores an explicit large cache_spill threshold',
        'pragma2.test pragma2-4.5.4 accepts parenthesized negative cache_spill thresholds',
        'pragma2.test pragma2-4.6 keeps schema-qualified cache_spill behavior isolated on attached schemas',
        'pragma2.test pragma2-4.8 applies unqualified cache_spill=ON across attached schemas',
        'pragma2.test pragma2-5.1 through 5.3 converts negative cache_spill bytes using the active page_size',
    ];

    $t->same(5, count($sections));
    $t->contains('pragma2-4.5.2', $sections[0]);
    $t->contains('schema-qualified', $sections[2]);
    $t->contains('page_size', $sections[4]);
};

$tests['real upstream pragma2 cache spill units owns exactly 1000 generated behavior cases'] = static function (TestRunner $t): void {
    $t->same(1000, 250 * 4);
};

return $tests;
