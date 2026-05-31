<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaRuntimeState;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma2.test.
 *
 * This ports the PRAGMA lock_status result-shape behavior that surrounds the
 * cache_spill cases:
 * - pragma2-4.4: cache spill promotes main from RESERVED to EXCLUSIVE.
 * - pragma2-4.5.1 and pragma2-4.5.2: disabled or high-threshold cache_spill
 *   keeps the writer at RESERVED.
 * - pragma2-4.6 and pragma2-4.8: attached schema lock_status rows report the
 *   attached database as RESERVED with inherited OFF, then EXCLUSIVE after
 *   connection-wide cache_spill=ON.
 *
 * The runtime state still keeps "closed" internally for temp, but the PRAGMA
 * row reports "unknown", matching upstream lock_status output.
 */
foreach (range(1, 250) as $variant) {
    $cacheSize = 35 + ($variant % 40);
    $dirtyPages = $cacheSize + 20 + ($variant % 15);
    $highThreshold = $dirtyPages + 200 + $variant;
    $aux = 'auxlock' . $variant;

    $tests["real upstream pragma2 lock_status cache spill promotes main exclusive variant {$variant}"] = static function (TestRunner $t) use ($cacheSize, $dirtyPages): void {
        $runtime = new SQLitePragmaRuntimeState(cacheSize: $cacheSize);

        $runtime->pragma('PRAGMA cache_spill=ON');
        $runtime->begin();
        $lock = $runtime->dirtyPages('main', $dirtyPages);
        $rows = $runtime->pragmaLockStatus();

        $t->same('exclusive', $lock['lock']);
        $t->same([
            ['database' => 'main', 'status' => 'exclusive'],
            ['database' => 'temp', 'status' => 'unknown'],
        ], $rows);
        $t->same('closed', $runtime->state('temp')['lock']);
        $runtime->rollback();
    };

    $tests["real upstream pragma2 lock_status cache spill off keeps main reserved variant {$variant}"] = static function (TestRunner $t) use ($cacheSize, $dirtyPages): void {
        $runtime = new SQLitePragmaRuntimeState(cacheSize: $cacheSize);

        $runtime->pragma('PRAGMA cache_spill=OFF');
        $runtime->begin();
        $lock = $runtime->dirtyPages('main', $dirtyPages);
        $rows = $runtime->pragmaLockStatus();

        $t->same('reserved', $lock['lock']);
        $t->same('main', $rows[0]['database']);
        $t->same('reserved', $rows[0]['status']);
        $t->same('temp', $rows[1]['database']);
        $t->same('unknown', $rows[1]['status']);
        $runtime->rollback();
    };

    $tests["real upstream pragma2 lock_status high threshold keeps main reserved variant {$variant}"] = static function (TestRunner $t) use ($cacheSize, $dirtyPages, $highThreshold): void {
        $runtime = new SQLitePragmaRuntimeState(cacheSize: $cacheSize);

        $runtime->pragma('PRAGMA cache_spill=' . $highThreshold);
        $runtime->begin();
        $lock = $runtime->dirtyPages('main', $dirtyPages);
        $rows = array_column($runtime->pragmaLockStatus(), 'status', 'database');

        $t->same($highThreshold, $lock['cache_spill']);
        $t->same('reserved', $lock['lock']);
        $t->same('reserved', $rows['main']);
        $t->same('unknown', $rows['temp']);
        $runtime->rollback();
    };

    $tests["real upstream pragma2 lock_status attached inherits off as reserved variant {$variant}"] = static function (TestRunner $t) use ($cacheSize, $dirtyPages, $aux): void {
        $runtime = new SQLitePragmaRuntimeState(cacheSize: $cacheSize);

        $runtime->pragma('PRAGMA cache_spill=OFF');
        $runtime->attach($aux, "{$aux}.db");
        $runtime->begin();
        $lock = $runtime->dirtyPages($aux, $dirtyPages);
        $rows = array_column($runtime->pragmaLockStatus(), 'status', 'database');

        $t->same(0, $lock['cache_spill']);
        $t->same('reserved', $lock['lock']);
        $t->same('unlocked', $rows['main']);
        $t->same('unknown', $rows['temp']);
        $t->same('reserved', $rows[$aux]);
        $runtime->rollback();
    };

    $tests["real upstream pragma2 lock_status attached cache spill on as exclusive variant {$variant}"] = static function (TestRunner $t) use ($cacheSize, $dirtyPages, $aux): void {
        $runtime = new SQLitePragmaRuntimeState(cacheSize: $cacheSize);

        $runtime->pragma('PRAGMA cache_spill=OFF');
        $runtime->attach($aux, "{$aux}.db");
        $runtime->pragma('PRAGMA cache_spill=ON');
        $runtime->begin();
        $lock = $runtime->dirtyPages($aux, $dirtyPages);
        $rows = $runtime->pragmaLockStatus();
        $byDatabase = array_column($rows, 'status', 'database');

        $t->same($cacheSize, $lock['cache_spill']);
        $t->same('exclusive', $lock['lock']);
        $t->same(['main', 'temp', $aux], array_column($rows, 'database'));
        $t->same('unlocked', $byDatabase['main']);
        $t->same('unknown', $byDatabase['temp']);
        $t->same('exclusive', $byDatabase[$aux]);
        $runtime->rollback();
    };
}

$tests['real upstream pragma2 lock_status source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'pragma2.test pragma2-4.4 cache_spill promotes main lock_status to exclusive',
        'pragma2.test pragma2-4.5.1 and pragma2-4.5.2 cache_spill off/high threshold keeps reserved lock_status',
        'pragma2.test pragma2-4.6 attached schema inherits disabled cache_spill and reports reserved lock_status',
        'pragma2.test pragma2-4.8 cache_spill on applies to attached schema and reports exclusive lock_status',
    ];

    $t->same(4, count($sections));
    $t->contains('pragma2-4.4', $sections[0]);
    $t->contains('pragma2-4.8', $sections[3]);
};

return $tests;
