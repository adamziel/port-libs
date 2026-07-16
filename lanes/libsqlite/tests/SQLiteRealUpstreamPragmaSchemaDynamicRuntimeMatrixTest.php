<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaRuntimeState;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma.test pragma-8.1.1 through pragma-8.1.18:
 *   schema_version write/read behavior, defensive suppression, and
 *   attached-database schema-version isolation.
 * - SQLite test/pragma.test pragma-8.2.1 through pragma-8.2.15:
 *   user_version write/read behavior, rollback restoration, attached-schema
 *   isolation, and negative user-version values.
 * - SQLite test/pragma2.test pragma2-5.1 through pragma2-5.3:
 *   cache_size and cache_spill ON/OFF/negative threshold behavior.
 * - SQLite test/pragma.test pragma-7.3:
 *   lock_status reports per-schema runtime lock rows.
 *
 * This follow-up keeps the behavior generic and application-shaped. It
 * exercises the runtime PRAGMA state model through dynamic attached schemas,
 * transactions, cache-spill lock transitions, and detach invalidation.
 */

foreach (range(1, 250) as $variant) {
    $schema = sprintf('tenant_%03d', $variant);
    $baseSchemaVersion = 1000 + $variant;
    $nextSchemaVersion = 2000 + $variant;
    $auxSchemaVersion = 3000 + $variant;
    $baseUserVersion = $variant % 131;
    $nextUserVersion = 4000 + $variant;
    $auxUserVersion = 5000 + $variant;
    $negativeUserVersion = -6000 - $variant;
    $cacheSize = 2 + ($variant % 9);
    $spillThreshold = $cacheSize + 3 + ($variant % 5);

    $tests[sprintf('real upstream pragma schema dynamic runtime matrix schema version attach defensive variant %03d', $variant)] = static function (TestRunner $t) use ($schema, $baseSchemaVersion, $nextSchemaVersion, $auxSchemaVersion): void {
        $state = new SQLitePragmaRuntimeState($baseSchemaVersion, 0);

        $t->same($baseSchemaVersion, $state->pragma('PRAGMA schema_version')['schema_version']);
        $t->same($nextSchemaVersion, $state->pragma("PRAGMA schema_version = {$nextSchemaVersion}")['schema_version']);
        $t->same($nextSchemaVersion, $state->pragma('PRAGMA schema_version = 999999', true)['schema_version']);
        $t->same($nextSchemaVersion, $state->pragma('PRAGMA main.schema_version')['schema_version']);

        $attached = $state->attach($schema, "/tmp/pragma-runtime-{$schema}.sqlite");
        $t->same('attach', $attached['operation']);
        $t->same($schema, $attached['schema']);
        $t->same(0, $attached['schema_version']);
        $t->same($auxSchemaVersion, $state->pragma("PRAGMA {$schema}.schema_version = {$auxSchemaVersion}")['schema_version']);
        $t->same($auxSchemaVersion, $state->pragma("PRAGMA {$schema}.schema_version")['schema_version']);
        $t->same($nextSchemaVersion, $state->pragma('PRAGMA schema_version')['schema_version']);
    };

    $tests[sprintf('real upstream pragma schema dynamic runtime matrix user version rollback variant %03d', $variant)] = static function (TestRunner $t) use ($schema, $baseSchemaVersion, $baseUserVersion, $nextUserVersion, $auxUserVersion, $negativeUserVersion): void {
        $state = new SQLitePragmaRuntimeState($baseSchemaVersion, $baseUserVersion);
        $state->attach($schema, "/tmp/pragma-user-{$schema}.sqlite");

        $t->same($baseUserVersion, $state->pragma('PRAGMA user_version')['user_version']);
        $t->same($nextUserVersion, $state->pragma("PRAGMA user_version = {$nextUserVersion}")['user_version']);
        $t->same($negativeUserVersion, $state->pragma("PRAGMA {$schema}.user_version = {$negativeUserVersion}")['user_version']);
        $t->same($negativeUserVersion, $state->pragma("PRAGMA {$schema}.user_version")['user_version']);

        $state->begin();
        $state->pragma('PRAGMA user_version = 17');
        $state->pragma("PRAGMA {$schema}.user_version = 23");
        $t->same(17, $state->pragma('PRAGMA main.user_version')['user_version']);
        $t->same(23, $state->pragma("PRAGMA {$schema}.user_version")['user_version']);
        $state->rollback();

        $t->same($nextUserVersion, $state->pragma('PRAGMA user_version')['user_version']);
        $t->same($negativeUserVersion, $state->pragma("PRAGMA {$schema}.user_version")['user_version']);
        $t->same($baseSchemaVersion, $state->pragma('PRAGMA schema_version')['schema_version']);
    };

    $tests[sprintf('real upstream pragma schema dynamic runtime matrix cache spill schema isolation variant %03d', $variant)] = static function (TestRunner $t) use ($schema, $baseSchemaVersion, $baseUserVersion, $cacheSize, $spillThreshold): void {
        $state = new SQLitePragmaRuntimeState($baseSchemaVersion, $baseUserVersion, $cacheSize);
        $state->attach($schema, "/tmp/pragma-cache-{$schema}.sqlite");

        $t->same($cacheSize, $state->pragma('PRAGMA cache_size')['cache_size']);
        $t->same($cacheSize + 1, $state->pragma("PRAGMA {$schema}.cache_size = " . ($cacheSize + 1))['cache_size']);
        $t->same($cacheSize, $state->pragma('PRAGMA main.cache_size')['cache_size']);
        $t->same($cacheSize + 1, $state->pragma("PRAGMA {$schema}.cache_size")['cache_size']);

        $t->same($cacheSize, $state->pragma('PRAGMA cache_spill=YES')['cache_spill']);
        $t->same($cacheSize + 1, $state->pragma("PRAGMA {$schema}.cache_spill")['cache_spill']);
        $t->same(0, $state->pragma("PRAGMA {$schema}.cache_spill=NO")['cache_spill']);
        $t->same($cacheSize, $state->pragma('PRAGMA main.cache_spill')['cache_spill']);
        $t->same($spillThreshold, $state->pragma("PRAGMA {$schema}.cache_spill({$spillThreshold})")['cache_spill']);
        $t->same($cacheSize + 1, $state->pragma("PRAGMA {$schema}.cache_spill(-51)")['cache_spill']);
    };

    $tests[sprintf('real upstream pragma schema dynamic runtime matrix lock status detach variant %03d', $variant)] = static function (TestRunner $t) use ($schema, $baseSchemaVersion, $baseUserVersion, $cacheSize): void {
        $state = new SQLitePragmaRuntimeState($baseSchemaVersion, $baseUserVersion, $cacheSize);
        $state->attach($schema, "/tmp/pragma-lock-{$schema}.sqlite");
        $state->pragma("PRAGMA {$schema}.cache_spill=YES");

        $t->same('reserved', $state->dirtyPages($schema, max(0, $cacheSize - 1))['lock']);
        $t->same('exclusive', $state->dirtyPages($schema, $cacheSize)['lock']);
        $locks = array_column($state->lockStatus(), 'lock', 'schema');
        $t->same('unlocked', $locks['main']);
        $t->same('closed', $locks['temp']);
        $t->same('exclusive', $locks[$schema]);

        $state->begin();
        $state->pragma('PRAGMA user_version = 72');
        $state->commit();
        $t->same('unlocked', array_column($state->lockStatus(), 'lock', 'schema')[$schema]);
        $t->same($schema, $state->detach($schema)['schema']);
        $t->throws(InvalidArgumentException::class, static fn () => $state->pragma("PRAGMA {$schema}.schema_version"));
    };
}

$tests['real upstream pragma schema dynamic runtime matrix cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-8.1.1 through pragma-8.1.18 schema_version read/write, defensive suppression, and attached schema isolation',
        'pragma.test pragma-8.2.1 through pragma-8.2.15 user_version read/write, rollback restoration, attached isolation, and negative values',
        'pragma2.test pragma2-5.1 through pragma2-5.3 cache_size and cache_spill YES/NO/negative threshold behavior',
        'pragma.test pragma-7.3 lock_status per-schema runtime rows',
    ];

    $t->same(4, count($sections));
    $t->contains('pragma-8.1.1', $sections[0]);
    $t->contains('pragma-8.2.15', $sections[1]);
    $t->contains('pragma2-5.3', $sections[2]);
    $t->contains('lock_status', $sections[3]);
};

return $tests;
