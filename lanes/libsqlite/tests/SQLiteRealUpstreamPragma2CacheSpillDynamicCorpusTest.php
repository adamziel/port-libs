<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaDynamicSchemaState;
use PortLibs\LibSqlite\SQLitePragmaPagerState;

$tests = [];

foreach (range(1, 250) as $variant) {
    $mainFree = 1 + ($variant % 7);
    $auxFree = 4 + ($variant % 11);
    $aux = 'auxpragma' . $variant;

    $tests["real upstream pragma2 1-3 freelist_count read only attached variant {$variant}"] = static function (TestRunner $t) use ($mainFree, $auxFree, $aux): void {
        $state = new SQLitePragmaDynamicSchemaState([
            'main' => ['freelist_count' => $mainFree],
            $aux => ['freelist_count' => $auxFree],
        ]);

        $main = $state->execute('PRAGMA freelist_count');
        $mainWrite = $state->execute('PRAGMA freelist_count = 500');
        $auxRead = $state->execute("PRAGMA {$aux}.freelist_count");
        $auxWrite = $state->execute("PRAGMA {$aux}.freelist_count = 500");

        $t->same($mainFree, $main['rows'][0]['freelist_count']);
        $t->same($mainFree, $mainWrite['rows'][0]['freelist_count']);
        $t->same('read_only_pragma_ignored', $mainWrite['reason']);
        $t->same($auxFree, $auxRead['rows'][0]['freelist_count']);
        $t->same($auxFree, $auxWrite['rows'][0]['freelist_count']);
        $t->same('read_only_pragma_ignored', $auxWrite['reason']);
    };

    $tests["real upstream pragma2 4.1-4.5 cache_spill lock threshold variant {$variant}"] = static function (TestRunner $t) use ($variant): void {
        $cacheSize = 45 + ($variant % 15);
        $state = new SQLitePragmaPagerState([
            'main' => ['cache_size' => $cacheSize, 'page_size' => 1024],
            'temp' => ['cache_size' => 2000, 'page_size' => 1024],
        ]);

        $defaultMain = $state->execute('PRAGMA main.cache_spill');
        $off = $state->execute('PRAGMA cache_spill=OFF');
        $tempAfterOff = $state->state()['temp']['cache_spill'];
        $large = $state->execute('PRAGMA cache_spill=100000');
        $negative = $state->execute('PRAGMA cache_spill(-25)');

        $t->same($cacheSize, $defaultMain['value']);
        $t->same(0, $off['value']);
        $t->same(0, $tempAfterOff);
        $t->same(100000, $large['value']);
        $t->same(25, $negative['value']);
        $t->same(25, $state->state()['temp']['cache_spill']);
        $t->same('assigned_connection_local', $negative['reason']);
    };

    $tests["real upstream pragma2 4.6 attached schema inherits disabled cache_spill variant {$variant}"] = static function (TestRunner $t) use ($variant, $aux): void {
        $cacheSize = 40 + ($variant % 13);
        $state = new SQLitePragmaPagerState([
            'main' => ['cache_size' => $cacheSize, 'page_size' => 1024],
            'temp' => ['cache_size' => 2000, 'page_size' => 1024],
        ]);

        $state->execute('PRAGMA cache_spill=OFF');
        $attach = $state->attach($aux);
        $state->execute("PRAGMA {$aux}.cache_size=50");
        $auxSpill = $state->execute("PRAGMA {$aux}.cache_spill");
        $mainSpill = $state->execute('PRAGMA main.cache_spill');

        $t->same('ok', $attach['status']);
        $t->same($aux, $attach['schema']);
        $t->same(0, $attach['cache_spill']);
        $t->same(0, $auxSpill['value']);
        $t->same(0, $mainSpill['value']);
        $t->same(50, $state->state()[$aux]['cache_size']);
    };

    $tests["real upstream pragma2 4.8-5.3 cache_spill on and negative page-size units variant {$variant}"] = static function (TestRunner $t) use ($variant, $aux): void {
        $state = new SQLitePragmaPagerState([
            'main' => ['cache_size' => 2, 'page_size' => 16384],
            $aux => ['cache_size' => 50, 'page_size' => 1024, 'cache_spill' => 0],
        ]);

        $on = $state->execute('PRAGMA cache_spill=YES');
        $off = $state->execute('PRAGMA cache_spill=NO');
        $negative = $state->execute('PRAGMA cache_spill(-51)');
        $auxOn = $state->execute("PRAGMA {$aux}.cache_spill=ON");

        $t->same(2, $on['value']);
        $t->same(50, $state->state()[$aux]['cache_spill']);
        $t->same(0, $off['value']);
        $t->same(3, $negative['value']);
        $t->same(50, $auxOn['value']);
        $t->same(3, $state->state()['main']['cache_spill']);
    };
}

return $tests;
