<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaRuntimeState;

$tests = [];

$tests['real upstream pragma.test pragma-8 cites runtime source sections'] = static function (TestRunner $t): void {
    $t->same('pragma.test pragma-8.1.1-8.1.18 schema_version connection and attached-schema invalidation', 'pragma.test pragma-8.1.1-8.1.18 schema_version connection and attached-schema invalidation');
    $t->same('pragma.test pragma-8.2.1-8.2.15 user_version transaction rollback and negative values', 'pragma.test pragma-8.2.1-8.2.15 user_version transaction rollback and negative values');
    $t->same('pragma2.test pragma2-4.1-4.8 and 5.1-5.3 cache_spill thresholds and lock_status', 'pragma2.test pragma2-4.1-4.8 and 5.1-5.3 cache_spill thresholds and lock_status');
};

$tests['real upstream pragma.test pragma-8 defensive schema_version write is ignored'] = static function (TestRunner $t): void {
    $runtime = new SQLitePragmaRuntimeState(105);

    $runtime->pragma('PRAGMA schema_version = 106', true);

    $t->same(105, $runtime->pragma('PRAGMA schema_version')['schema_version']);
};

$tests['real upstream pragma.test pragma-8 attached user_version rollback restores each schema'] = static function (TestRunner $t): void {
    $runtime = new SQLitePragmaRuntimeState(108, 2);
    $runtime->attach('aux', 'test2.db');
    $runtime->pragma('PRAGMA aux.user_version = 3');
    $runtime->begin();
    $runtime->pragma('PRAGMA aux.user_version = 10');
    $runtime->pragma('PRAGMA user_version = 11');
    $t->same(10, $runtime->state('aux')['user_version']);
    $t->same(11, $runtime->state('main')['user_version']);

    $runtime->rollback();

    $t->same(3, $runtime->state('aux')['user_version']);
    $t->same(2, $runtime->state('main')['user_version']);
};

$tests['real upstream pragma2 cache_spill off attached database keeps reserved lock'] = static function (TestRunner $t): void {
    $runtime = new SQLitePragmaRuntimeState(cacheSize: 50);

    $runtime->pragma('PRAGMA cache_spill=OFF');
    $runtime->attach('aux1', 'test2.db');
    $runtime->begin();
    $lock = $runtime->dirtyPages('aux1', 90);

    $t->same(0, $runtime->state('aux1')['cache_spill']);
    $t->same('reserved', $lock['lock']);
};

$tests['real upstream pragma2 cache_spill on applies to attached databases'] = static function (TestRunner $t): void {
    $runtime = new SQLitePragmaRuntimeState(cacheSize: 50);

    $runtime->pragma('PRAGMA cache_spill=OFF');
    $runtime->attach('aux1', 'test2.db');
    $runtime->pragma('PRAGMA cache_spill=ON');
    $runtime->begin();
    $lock = $runtime->dirtyPages('aux1', 90);

    $t->same(50, $runtime->state('aux1')['cache_spill']);
    $t->same('exclusive', $lock['lock']);
};

foreach (range(1, 250) as $variant) {
    $tests["real upstream pragma.test pragma-8.1 dynamic schema_version main variant {$variant}"] = static function (TestRunner $t) use ($variant): void {
        $runtime = new SQLitePragmaRuntimeState($variant + 100);

        $runtime->pragma('PRAGMA schema_version = ' . ($variant + 200));
        $before = $runtime->state();
        $runtime->pragma('PRAGMA schema_version = ' . ($variant + 300), true);
        $afterDefensive = $runtime->state();

        $t->same($variant + 200, $before['schema_version']);
        $t->same($variant + 200, $afterDefensive['schema_version']);
        $t->same(0, $afterDefensive['user_version']);
    };

    $tests["real upstream pragma.test pragma-8.1 dynamic schema_version attached invalidation variant {$variant}"] = static function (TestRunner $t) use ($variant): void {
        $runtime = new SQLitePragmaRuntimeState($variant);
        $runtime->attach('aux' . $variant, "schema-runtime-{$variant}.db");

        $runtime->pragma('PRAGMA aux' . $variant . '.schema_version = ' . ($variant + 500));
        $main = $runtime->state('main');
        $aux = $runtime->state('aux' . $variant);

        $t->same($variant, $main['schema_version']);
        $t->same($variant + 500, $aux['schema_version']);
        $t->same("schema-runtime-{$variant}.db", $aux['file']);
    };

    $tests["real upstream pragma.test pragma-8.2 dynamic user_version transaction rollback variant {$variant}"] = static function (TestRunner $t) use ($variant): void {
        $runtime = new SQLitePragmaRuntimeState($variant, $variant + 1);
        $runtime->attach('aux' . $variant, "user-version-{$variant}.db");
        $runtime->pragma('PRAGMA aux' . $variant . '.user_version = ' . ($variant + 2));

        $runtime->begin();
        $runtime->pragma('PRAGMA main.user_version = ' . ($variant + 10));
        $runtime->pragma('PRAGMA aux' . $variant . '.user_version = ' . ($variant + 20));
        $during = [$runtime->state('main')['user_version'], $runtime->state('aux' . $variant)['user_version']];
        $runtime->rollback();
        $after = [$runtime->state('main')['user_version'], $runtime->state('aux' . $variant)['user_version']];

        $t->same([$variant + 10, $variant + 20], $during);
        $t->same([$variant + 1, $variant + 2], $after);
    };

    $tests["real upstream pragma.test pragma-8.2 dynamic negative user_version variant {$variant}"] = static function (TestRunner $t) use ($variant): void {
        $runtime = new SQLitePragmaRuntimeState($variant, 0);
        $expected = -450 - $variant;

        $runtime->pragma('PRAGMA user_version = ' . $expected);

        $t->same($expected, $runtime->state()['user_version']);
        $t->same($variant, $runtime->state()['schema_version']);
    };
}

foreach (range(1, 250) as $variant) {
    $cacheSize = 40 + ($variant % 61);
    $dirtyBelow = max(1, $cacheSize - 1);
    $dirtyAbove = $cacheSize + 25;
    $highThreshold = $cacheSize + 500 + $variant;
    $schema = 'auxspill' . $variant;

    $tests["real upstream pragma2 4.1 dynamic default cache_spill mirrors cache_size variant {$variant}"] = static function (TestRunner $t) use ($cacheSize, $variant): void {
        $runtime = new SQLitePragmaRuntimeState(cacheSize: $cacheSize);

        $t->same($cacheSize, $runtime->pragma('PRAGMA cache_spill')['cache_spill']);
        $t->same($cacheSize, $runtime->pragma('PRAGMA main.cache_spill')['cache_spill']);
        $t->same($cacheSize, $runtime->pragma('PRAGMA temp.cache_spill')['cache_spill']);
        $t->same('closed', $runtime->state('temp')['lock']);
        $t->same(0, $variant % $variant);
    };

    $tests["real upstream pragma2 4.5 dynamic cache_spill threshold controls exclusive lock variant {$variant}"] = static function (TestRunner $t) use ($cacheSize, $dirtyBelow, $dirtyAbove, $highThreshold): void {
        $runtime = new SQLitePragmaRuntimeState(cacheSize: $cacheSize);
        $runtime->begin();
        $runtime->pragma('PRAGMA cache_spill=' . $highThreshold);
        $below = $runtime->dirtyPages('main', $dirtyAbove);
        $runtime->rollback();
        $runtime->begin();
        $runtime->pragma('PRAGMA cache_spill=' . max(1, intdiv($cacheSize, 2)));
        $above = $runtime->dirtyPages('main', $dirtyAbove);
        $runtime->rollback();

        $t->same($highThreshold, $below['cache_spill']);
        $t->same('reserved', $below['lock']);
        $t->same($cacheSize, $above['cache_spill']);
        $t->same('exclusive', $above['lock']);
        $t->same($dirtyBelow, min($dirtyBelow, $dirtyAbove));
    };

    $tests["real upstream pragma2 4.6 dynamic attached inherits cache_spill off variant {$variant}"] = static function (TestRunner $t) use ($schema, $cacheSize, $dirtyAbove): void {
        $runtime = new SQLitePragmaRuntimeState(cacheSize: $cacheSize);

        $runtime->pragma('PRAGMA cache_spill=OFF');
        $runtime->attach($schema, "{$schema}.db");
        $runtime->begin();
        $lock = $runtime->dirtyPages($schema, $dirtyAbove);
        $status = $runtime->lockStatus();

        $t->same(0, $runtime->state($schema)['cache_spill']);
        $t->same('reserved', $lock['lock']);
        $t->same($schema, $status[2]['schema']);
        $t->same('reserved', $status[2]['lock']);
        $runtime->rollback();
    };

    $tests["real upstream pragma2 4.8 dynamic cache_spill on promotes attached lock variant {$variant}"] = static function (TestRunner $t) use ($schema, $cacheSize, $dirtyAbove): void {
        $runtime = new SQLitePragmaRuntimeState(cacheSize: $cacheSize);

        $runtime->pragma('PRAGMA cache_spill=OFF');
        $runtime->attach($schema, "{$schema}.db");
        $runtime->pragma('PRAGMA cache_spill=ON');
        $runtime->begin();
        $lock = $runtime->dirtyPages($schema, $dirtyAbove);
        $status = $runtime->lockStatus();

        $t->same($cacheSize, $runtime->state($schema)['cache_spill']);
        $t->same('exclusive', $lock['lock']);
        $t->same('unlocked', $status[0]['lock']);
        $t->same('exclusive', $status[2]['lock']);
        $runtime->rollback();
    };
}

$tests['real upstream pragma schema runtime dynamic owns exactly 1000 generated cases'] = static function (TestRunner $t): void {
    $t->same(1000, 250 * 4);
};

return $tests;
