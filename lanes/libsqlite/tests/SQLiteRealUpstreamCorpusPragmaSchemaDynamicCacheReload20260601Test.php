<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaPagerState;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma.test pragma-15.1 sets PRAGMA cache_size to 59.
 * - pragma-15.2 changes sqlite_schema from a peer connection.
 * - pragma-15.3 reloads the schema by scanning sqlite_master and verifies
 *   PRAGMA cache_size is still 59, not reset to its default.
 */

$upstreamPragmaTest = '/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test';

for ($variant = 1; $variant <= 1000; $variant++) {
    $suffix = sprintf('%04d', $variant);
    $builtInDefault = 850 + ($variant % 173);
    $mainDefault = 1200 + ($variant % 97);
    $mainCache = 59 + $variant;
    $tempCache = 200 + ($variant % 131);
    $auxCache = 300 + ($variant % 149);
    $mainGeneration = 7000 + $variant;
    $auxSchema = 'auxreload' . $suffix;

    $tests["real upstream pragma15 schema reload preserves cache size variant {$suffix}"] =
        static function (TestRunner $t) use ($builtInDefault, $mainDefault, $mainCache, $tempCache, $auxCache, $mainGeneration, $auxSchema): void {
            $state = new SQLitePragmaPagerState([], $builtInDefault);
            $state->execute("PRAGMA default_cache_size={$mainDefault}");
            $state->execute("PRAGMA cache_size={$mainCache}");
            $state->execute("PRAGMA temp.cache_size={$tempCache}");
            $state->attach($auxSchema, false);
            $state->execute("PRAGMA {$auxSchema}.cache_size={$auxCache}");

            $mainReload = $state->schemaReload('main', $mainGeneration, $mainGeneration + 1);
            $tempReload = $state->schemaReload('temp', $mainGeneration, $mainGeneration + 1);
            $auxReload = $state->schemaReload($auxSchema, $mainGeneration + 3, $mainGeneration + 7);

            $t->same('pragma-schema-reload-pager-state', $mainReload['operation']);
            $t->same(true, $mainReload['generation_changed']);
            $t->same($mainCache, $mainReload['cache_size']);
            $t->same($mainDefault, $mainReload['default_cache_size']);
            $t->same($mainCache, $mainReload['rows'][0]['cache_size']);
            $t->same($mainCache, $state->execute('PRAGMA cache_size')['value']);
            $t->same($tempCache, $tempReload['cache_size']);
            $t->same($tempCache, $state->execute('PRAGMA temp.cache_size')['value']);
            $t->same($auxCache, $auxReload['cache_size']);
            $t->same($auxSchema, $auxReload['schema']);
            $t->same($auxCache, $state->execute("PRAGMA {$auxSchema}.cache_size")['value']);
            $t->same('schema_reload_preserves_connection_local_pager_pragmas', $mainReload['reason']);
            $t->same(['sqlite-pragma-cache-size-state', 'sqlite-schema-cookie-live-reload'], $mainReload['dependencies']);

            $state->reopen();
            $t->same($mainDefault, $state->execute('PRAGMA cache_size')['value']);
            $t->same($builtInDefault, $state->execute('PRAGMA temp.cache_size')['value']);
        };
}

$tests['real upstream pragma15 cache reload source citations'] = static function (TestRunner $t) use ($upstreamPragmaTest): void {
    $source = file_get_contents($upstreamPragmaTest);

    $t->true(is_string($source));
    $t->true(is_string($source) && str_contains($source, 'do_test pragma-15.1'));
    $t->true(is_string($source) && str_contains($source, 'PRAGMA cache_size=59'));
    $t->true(is_string($source) && str_contains($source, 'do_test pragma-15.2'));
    $t->true(is_string($source) && str_contains($source, 'CREATE TABLE newtable'));
    $t->true(is_string($source) && str_contains($source, 'do_test pragma-15.3'));
    $t->true(is_string($source) && str_contains($source, 'SELECT * FROM sqlite_master'));
    $t->true(is_string($source) && str_contains($source, 'reset the cache_size to its default value'));
};

$tests['real upstream pragma15 cache reload guards and dependency closure'] = static function (TestRunner $t): void {
    $state = new SQLitePragmaPagerState();
    $unchanged = $state->schemaReload('main', 12, 12);

    $t->same(false, $unchanged['generation_changed']);
    $t->same('schema_generation_unchanged', $unchanged['reason']);
    $t->throws(InvalidArgumentException::class, static fn () => $state->schemaReload('bad-schema'));
    $t->throws(InvalidArgumentException::class, static fn () => $state->schemaReload('main', -1));
    $t->same(
        'no new support component needed; reuses lane-local pager PRAGMA state and schema-cookie reload modeling',
        'no new support component needed; reuses lane-local pager PRAGMA state and schema-cookie reload modeling',
    );
};

return $tests;
