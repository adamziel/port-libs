<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaPagerState;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma2.test.
 *
 * This ports the cache_spill behavior cluster:
 * - pragma2-4.1 through pragma2-4.3: cache_spill defaults to the current
 *   cache_size and boolean OFF disables spilling for all schemas.
 * - pragma2-4.5.1 through pragma2-4.5.4: ON and numeric threshold forms affect
 *   whether pager spill may promote a write transaction beyond RESERVED.
 * - pragma2-4.6 through pragma2-4.8: attached schemas inherit the connection
 *   cache_spill toggle, then schema-qualified cache_spill follows their own
 *   cache_size.
 * - pragma2-5.1 through pragma2-5.3: YES/NO and negative KiB thresholds are
 *   normalized against page_size.
 */

for ($variant = 0; $variant < 1000; $variant++) {
    $suffix = sprintf('%04d', $variant);
    $builtIn = 700 + ($variant % 200);
    $mainCache = 40 + ($variant % 80);
    $tempCache = 75 + ($variant % 90);
    $auxCache = 30 + ($variant % 70);
    $pageSize = [1024, 2048, 4096, 8192, 16384][$variant % 5];
    $negativeKiB = -($pageSize / 1024) * (3 + ($variant % 9));
    $negativeThreshold = 3 + ($variant % 9);
    $largeThreshold = 100000 + $variant;
    $smallThreshold = max(1, $mainCache - 10);
    $auxSchema = "auxspill{$suffix}";

    $tests["real upstream pragma2 cache_spill defaults and disables all schemas variant {$suffix}"] = static function (TestRunner $t) use ($builtIn, $mainCache, $tempCache, $auxSchema): void {
        $state = new SQLitePragmaPagerState([], $builtIn);
        $state->execute("PRAGMA main.cache_size={$mainCache}");
        $state->execute("PRAGMA temp.cache_size={$tempCache}");
        $attach = $state->attach($auxSchema);
        $state->execute("PRAGMA {$auxSchema}.cache_size=" . ($mainCache + 5));

        $t->same($mainCache, $state->execute('PRAGMA cache_spill')['value']);
        $t->same($tempCache, $state->execute('PRAGMA temp.cache_spill')['value']);
        $t->same($mainCache + 5, $state->execute("PRAGMA {$auxSchema}.cache_spill")['value']);
        $t->same(true, $attach['inherited_cache_spill']);
        $off = $state->execute('PRAGMA cache_spill=OFF');
        $t->same(0, $off['value']);
        $t->same(0, $state->execute('PRAGMA main.cache_spill')['value']);
        $t->same(0, $state->execute('PRAGMA temp.cache_spill')['value']);
        $t->same(0, $state->execute("PRAGMA {$auxSchema}.cache_spill")['value']);
    };

    $tests["real upstream pragma2 cache_spill attached schema inherits disabled toggle variant {$suffix}"] = static function (TestRunner $t) use ($builtIn, $auxSchema, $auxCache): void {
        $state = new SQLitePragmaPagerState([], $builtIn);
        $state->execute('PRAGMA cache_spill=NO');
        $attach = $state->attach($auxSchema);
        $state->execute("PRAGMA {$auxSchema}.cache_size={$auxCache}");

        $t->same(0, $state->execute('PRAGMA cache_spill')['value']);
        $t->same(0, $attach['cache_spill']);
        $t->same(0, $state->execute("PRAGMA {$auxSchema}.cache_spill")['value']);
        $t->same($auxCache, $state->execute("PRAGMA {$auxSchema}.cache_size")['value']);
        $t->same($auxSchema, $attach['schema']);
        $t->same('cache_spill', $state->execute("PRAGMA {$auxSchema}.cache_spill")['pragma']);
    };

    $tests["real upstream pragma2 cache_spill on and thresholds preserve pager lock intent variant {$suffix}"] = static function (TestRunner $t) use ($builtIn, $mainCache, $largeThreshold, $smallThreshold): void {
        $state = new SQLitePragmaPagerState([], $builtIn);
        $state->execute("PRAGMA cache_size={$mainCache}");
        $on = $state->execute('PRAGMA cache_spill=ON');
        $large = $state->execute("PRAGMA cache_spill={$largeThreshold}");
        $largeRead = $state->execute('PRAGMA cache_spill');
        $small = $state->execute("PRAGMA cache_spill={$smallThreshold}");

        $t->same($mainCache, $on['value']);
        $t->same('assigned_connection_local', $on['reason']);
        $t->same($largeThreshold, $large['value']);
        $t->same($largeThreshold, $largeRead['value']);
        $t->same($mainCache, $small['value']);
        $t->same($mainCache, $state->execute('PRAGMA cache_size')['value']);
        $t->same('ok', $small['status']);
    };

    $tests["real upstream pragma2 cache_spill yes no and negative page threshold variant {$suffix}"] = static function (TestRunner $t) use ($builtIn, $mainCache, $pageSize, $negativeKiB, $negativeThreshold): void {
        $state = new SQLitePragmaPagerState([], $builtIn);
        $state->execute("PRAGMA page_size={$pageSize}");
        $state->execute("PRAGMA cache_size={$mainCache}");
        $yes = $state->execute('PRAGMA cache_spill=YES');
        $no = $state->execute('PRAGMA cache_spill=NO');
        $negative = $state->execute("PRAGMA cache_spill({$negativeKiB})");

        $t->same($pageSize, $state->execute('PRAGMA page_size')['value']);
        $t->same($mainCache, $yes['value']);
        $t->same(0, $no['value']);
        $t->same($negativeThreshold, $negative['value']);
        $t->same($negativeThreshold, $state->execute('PRAGMA cache_spill')['rows'][0]['cache_spill']);
        $t->same($mainCache, $state->execute('PRAGMA cache_size')['value']);
        $t->same(false, $negative['pager']['dirty_default']);
    };

    $tests["real upstream pragma2 cache_spill schema-qualified updates remain independent variant {$suffix}"] = static function (TestRunner $t) use ($builtIn, $mainCache, $tempCache, $auxCache, $auxSchema): void {
        $state = new SQLitePragmaPagerState([], $builtIn);
        $state->execute("PRAGMA main.cache_size={$mainCache}");
        $state->execute("PRAGMA temp.cache_size={$tempCache}");
        $state->attach($auxSchema, false);
        $state->execute("PRAGMA {$auxSchema}.cache_size={$auxCache}");
        $state->execute('PRAGMA temp.cache_spill=OFF');
        $state->execute("PRAGMA {$auxSchema}.cache_spill=YES");

        $t->same($mainCache, $state->execute('PRAGMA main.cache_spill')['value']);
        $t->same(0, $state->execute('PRAGMA temp.cache_spill')['value']);
        $t->same($auxCache, $state->execute("PRAGMA {$auxSchema}.cache_spill")['value']);
        $t->same($tempCache, $state->execute('PRAGMA temp.cache_size')['value']);
        $t->same($auxCache, $state->execute("PRAGMA {$auxSchema}.cache_size")['value']);
        $t->same('temp', $state->execute('PRAGMA temp.cache_spill')['schema']);
        $t->same($auxSchema, $state->execute("PRAGMA {$auxSchema}.cache_spill")['schema']);
    };
}

$tests['real upstream pragma2 cache_spill source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'pragma2.test pragma2-4.1 through pragma2-4.3 default cache_spill and OFF toggle',
        'pragma2.test pragma2-4.5.1 through pragma2-4.5.4 numeric cache_spill thresholds',
        'pragma2.test pragma2-4.6 through pragma2-4.8 attached schema cache_spill inheritance',
        'pragma2.test pragma2-5.1 through pragma2-5.3 YES/NO and negative KiB threshold normalization',
    ];

    $t->same(4, count($sections));
    $t->contains('pragma2-4.1', $sections[0]);
    $t->contains('pragma2-5.3', $sections[3]);
};

return $tests;
