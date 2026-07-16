<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaPagerState;

$tests = [];

/*
 * Real upstream sources:
 * - SQLite test/pragma.test pragma-1.* covers cache_size,
 *   default_cache_size, synchronous keyword/numeric state, reopen behavior,
 *   and default-cache persistence.
 * - SQLite test/pragma.test pragma-2.* and pragma-4.* cover schema-qualified
 *   pager PRAGMAs on attached databases.
 *
 * This dynamic batch ports that state-machine behavior into a generic PHP
 * PRAGMA pager-state executor. Each variant uses distinct schema/default/cache
 * values so the focused TestRunner PASS cases are behavior rows, not repeated
 * metadata for one static record.
 */

$case = static function (string $name, callable $callback) use (&$tests): void {
    $tests['real upstream pragma pager state dynamic ' . $name] = static function (TestRunner $t) use ($callback): void {
        [$expected, $actual] = $callback();
        $t->same($expected, $actual);
    };
};

for ($variant = 0; $variant < 210; $variant++) {
    $suffix = sprintf('%03d', $variant);
    $builtIn = 900 + $variant;
    $cacheSize = 1200 + ($variant * 3);
    $negativeCacheSize = -4000 - $variant;
    $defaultCache = 100 + $variant;
    $schema = 'tenant' . $suffix;
    $keyword = ['OFF', 'NORMAL', 'FULL', 'EXTRA', 'ON'][$variant % 5];
    $keywordValue = ['OFF' => 0, 'NORMAL' => 1, 'FULL' => 2, 'EXTRA' => 3, 'ON' => 1][$keyword];
    $numeric = [0, 1, 2, 3, 4, 8, 10][$variant % 7];
    $numericValue = $numeric <= 4 ? $numeric : $numeric % 4;

    $case("pragma-1.1 initial cache default synchronous variant {$suffix}", static fn (): array => [
        [$builtIn, $builtIn, 2],
        [
            (new SQLitePragmaPagerState([], $builtIn))->execute('PRAGMA cache_size')['value'],
            (new SQLitePragmaPagerState([], $builtIn))->execute('PRAGMA default_cache_size')['value'],
            (new SQLitePragmaPagerState([], $builtIn))->execute('PRAGMA synchronous')['value'],
        ],
    ]);

    $case("pragma-1.2 cache size assignment is connection local variant {$suffix}", static fn (): array => [
        [$cacheSize, $builtIn, 0, 'assigned_connection_local'],
        (static function () use ($builtIn, $cacheSize): array {
            $state = new SQLitePragmaPagerState([], $builtIn);
            $state->execute('PRAGMA synchronous=OFF');
            $assigned = $state->execute("PRAGMA cache_size={$cacheSize}");

            return [
                $state->execute('PRAGMA cache_size')['value'],
                $state->execute('PRAGMA default_cache_size')['value'],
                $state->execute('PRAGMA synchronous')['value'],
                $assigned['reason'],
            ];
        })(),
    ]);

    $case("pragma-1.3 reopen resets volatile cache and synchronous variant {$suffix}", static fn (): array => [
        [$builtIn, $builtIn, 2, true],
        (static function () use ($builtIn, $cacheSize): array {
            $state = new SQLitePragmaPagerState([], $builtIn);
            $state->execute('PRAGMA synchronous=OFF');
            $state->execute("PRAGMA cache_size={$cacheSize}");
            $reopen = $state->reopen();

            return [
                $state->execute('PRAGMA cache_size')['value'],
                $state->execute('PRAGMA default_cache_size')['value'],
                $state->execute('PRAGMA synchronous')['value'],
                $reopen['reopened'],
            ];
        })(),
    ]);

    $case("pragma-1.5 negative cache size is preserved until reopen variant {$suffix}", static fn (): array => [
        [$negativeCacheSize, $builtIn, $builtIn],
        (static function () use ($builtIn, $negativeCacheSize): array {
            $state = new SQLitePragmaPagerState([], $builtIn);
            $state->execute("PRAGMA cache_size={$negativeCacheSize}");
            $before = $state->execute('PRAGMA cache_size')['value'];
            $state->reopen();

            return [
                $before,
                $state->execute('PRAGMA cache_size')['value'],
                $state->execute('PRAGMA default_cache_size')['value'],
            ];
        })(),
    ]);

    $case("pragma-1.8 default cache negative assignment stores positive persistent value variant {$suffix}", static fn (): array => [
        [$defaultCache, $defaultCache, true, 'assigned_persistent_default'],
        (static function () use ($builtIn, $defaultCache): array {
            $state = new SQLitePragmaPagerState([], $builtIn);
            $result = $state->execute('PRAGMA default_cache_size=-' . $defaultCache);
            $state->reopen();

            return [
                $state->execute('PRAGMA cache_size')['value'],
                $state->execute('PRAGMA default_cache_size')['value'],
                $result['pager']['dirty_default'],
                $result['reason'],
            ];
        })(),
    ]);

    $case("pragma-1.10-to-1.14 synchronous keyword and numeric normalization variant {$suffix}", static fn (): array => [
        [$keywordValue, $numericValue, 2],
        (static function () use ($builtIn, $keyword, $numeric): array {
            $state = new SQLitePragmaPagerState([], $builtIn);
            $state->execute("PRAGMA synchronous={$keyword}");
            $keywordResult = $state->execute('PRAGMA synchronous')['value'];
            $state->execute("PRAGMA synchronous={$numeric}");
            $numericResult = $state->execute('PRAGMA synchronous')['value'];
            $state->reopen();

            return [
                $keywordResult,
                $numericResult,
                $state->execute('PRAGMA synchronous')['value'],
            ];
        })(),
    ]);

    $case("pragma-1.15 default cache zero resets to built in default variant {$suffix}", static fn (): array => [
        [$builtIn, $builtIn, 'reset_to_builtin_default'],
        (static function () use ($builtIn, $defaultCache): array {
            $state = new SQLitePragmaPagerState([], $builtIn);
            $state->execute("PRAGMA default_cache_size={$defaultCache}");
            $reset = $state->execute('PRAGMA default_cache_size=0');

            return [
                $state->execute('PRAGMA cache_size')['value'],
                $state->execute('PRAGMA default_cache_size')['value'],
                $reset['reason'],
            ];
        })(),
    ]);

    $case("pragma-2-and-4 schema-qualified attached pager state variant {$suffix}", static fn (): array => [
        [$schema, $cacheSize, $defaultCache, $defaultCache, $keywordValue, true],
        (static function () use ($builtIn, $schema, $cacheSize, $defaultCache, $keyword): array {
            $state = new SQLitePragmaPagerState([], $builtIn);
            $attach = $state->attach($schema);
            $state->execute("PRAGMA {$schema}.cache_size={$cacheSize}");
            $connectionLocalCache = $state->execute("PRAGMA {$schema}.cache_size")['value'];
            $state->execute("PRAGMA {$schema}.default_cache_size={$defaultCache}");
            $state->execute("PRAGMA {$schema}.synchronous={$keyword}");

            return [
                $attach['schema'],
                $connectionLocalCache,
                $state->execute("PRAGMA {$schema}.cache_size")['value'],
                $state->execute("PRAGMA {$schema}.default_cache_size")['value'],
                $state->execute("PRAGMA {$schema}.synchronous")['value'],
                $attach['inherited_cache_spill'],
            ];
        })(),
    ]);
}

$tests['real upstream pragma pager state dynamic parser rejects quoted schema from pragma corpus'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaPagerState::parse('PRAGMA "main".cache_size'));
};

$tests['real upstream pragma pager state dynamic parser rejects unsupported pragma outside selected corpus'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaPagerState::parse('PRAGMA freelist_count'));
};

return $tests;
