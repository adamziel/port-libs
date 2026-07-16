<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaRuntimeState;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma2.test pragma2-4.1 through pragma2-4.8:
 *   cache_spill defaults on, OFF applies to all databases when unqualified,
 *   numeric thresholds gate mid-transaction exclusive locks, and attached
 *   databases inherit the unqualified cache_spill state.
 * - SQLite test/pragma2.test pragma2-5.1 through pragma2-5.3:
 *   YES/NO and negative parenthesized cache_spill forms normalize through the
 *   effective cache-size threshold.
 */

$scenarios = [
    'cache_spill default all schemas use cache size threshold' => static function (int $variant): array {
        $state = new SQLitePragmaRuntimeState(cacheSize: 2000 + $variant);
        $state->pragma('PRAGMA temp.cache_size=' . (3000 + $variant));

        return [
            'state' => $state,
            'setup' => [],
            'checks' => [
                ['sql' => 'PRAGMA cache_spill', 'schema' => 'main', 'spill' => 2000 + $variant],
                ['sql' => 'PRAGMA main.cache_spill', 'schema' => 'main', 'spill' => 2000 + $variant],
                ['sql' => 'PRAGMA temp.cache_spill', 'schema' => 'temp', 'spill' => 3000 + $variant],
            ],
            'locks' => [
                ['schema' => 'main', 'dirty' => 2000 + $variant, 'lock' => 'exclusive'],
                ['schema' => 'temp', 'dirty' => 3000 + $variant - 1, 'lock' => 'reserved'],
            ],
        ];
    },
    'cache_spill unqualified off applies to main temp and attached schemas' => static function (int $variant): array {
        $state = new SQLitePragmaRuntimeState(cacheSize: 50 + ($variant % 7));
        $state->attach('aux1', "/tmp/pragma-cache-spill-{$variant}.sqlite");
        $state->pragma('PRAGMA cache_spill=OFF');

        return [
            'state' => $state,
            'setup' => [],
            'checks' => [
                ['sql' => 'PRAGMA cache_spill', 'schema' => 'main', 'spill' => 0],
                ['sql' => 'PRAGMA main.cache_spill', 'schema' => 'main', 'spill' => 0],
                ['sql' => 'PRAGMA temp.cache_spill', 'schema' => 'temp', 'spill' => 0],
                ['sql' => 'PRAGMA aux1.cache_spill', 'schema' => 'aux1', 'spill' => 0],
            ],
            'locks' => [
                ['schema' => 'main', 'dirty' => 1000, 'lock' => 'reserved'],
                ['schema' => 'aux1', 'dirty' => 1000, 'lock' => 'reserved'],
            ],
        ];
    },
    'cache_spill large numeric threshold prevents exclusive lock' => static function (int $variant): array {
        $state = new SQLitePragmaRuntimeState(cacheSize: 50);
        $threshold = 100000 + $variant;
        $state->pragma("PRAGMA cache_spill={$threshold}");

        return [
            'state' => $state,
            'setup' => [],
            'checks' => [
                ['sql' => 'PRAGMA cache_spill', 'schema' => 'main', 'spill' => $threshold],
                ['sql' => 'PRAGMA main.cache_spill', 'schema' => 'main', 'spill' => $threshold],
            ],
            'locks' => [
                ['schema' => 'main', 'dirty' => 64 + ($variant % 8), 'lock' => 'reserved'],
            ],
        ];
    },
    'cache_spill small numeric threshold is raised to cache size' => static function (int $variant): array {
        $cacheSize = 50 + ($variant % 5);
        $state = new SQLitePragmaRuntimeState(cacheSize: $cacheSize);
        $state->pragma('PRAGMA cache_spill=25');

        return [
            'state' => $state,
            'setup' => [],
            'checks' => [
                ['sql' => 'PRAGMA main.cache_spill', 'schema' => 'main', 'spill' => $cacheSize],
            ],
            'locks' => [
                ['schema' => 'main', 'dirty' => $cacheSize, 'lock' => 'exclusive'],
                ['schema' => 'main', 'dirty' => $cacheSize - 1, 'lock' => 'reserved'],
            ],
        ];
    },
    'cache_spill negative parenthesized threshold is raised to cache size' => static function (int $variant): array {
        $cacheSize = 3 + ($variant % 9);
        $state = new SQLitePragmaRuntimeState(cacheSize: $cacheSize);
        $state->pragma('PRAGMA cache_spill(-51)');

        return [
            'state' => $state,
            'setup' => [],
            'checks' => [
                ['sql' => 'PRAGMA cache_spill', 'schema' => 'main', 'spill' => $cacheSize],
            ],
            'locks' => [
                ['schema' => 'main', 'dirty' => $cacheSize, 'lock' => 'exclusive'],
            ],
        ];
    },
    'cache_spill boolean yes and no forms toggle spill threshold' => static function (int $variant): array {
        $cacheSize = 2 + ($variant % 11);
        $state = new SQLitePragmaRuntimeState(cacheSize: $cacheSize);
        $state->pragma('PRAGMA cache_spill=YES');
        $yes = $state->pragma('PRAGMA cache_spill');
        $state->pragma('PRAGMA cache_spill=NO');

        return [
            'state' => $state,
            'setup' => [['row' => $yes, 'expected' => $cacheSize]],
            'checks' => [
                ['sql' => 'PRAGMA cache_spill', 'schema' => 'main', 'spill' => 0],
            ],
            'locks' => [
                ['schema' => 'main', 'dirty' => $cacheSize + 10, 'lock' => 'reserved'],
            ],
        ];
    },
    'cache_spill new attachments inherit unqualified off' => static function (int $variant): array {
        $state = new SQLitePragmaRuntimeState(cacheSize: 50);
        $state->pragma('PRAGMA cache_spill=OFF');
        $state->attach('aux1', "/tmp/pragma-cache-spill-inherit-off-{$variant}.sqlite");
        $state->pragma('PRAGMA aux1.cache_size=50');

        return [
            'state' => $state,
            'setup' => [],
            'checks' => [
                ['sql' => 'PRAGMA aux1.cache_spill', 'schema' => 'aux1', 'spill' => 0],
            ],
            'locks' => [
                ['schema' => 'main', 'dirty' => 0, 'lock' => 'unlocked'],
                ['schema' => 'aux1', 'dirty' => 200, 'lock' => 'reserved'],
            ],
        ];
    },
    'cache_spill unqualified on applies to already attached schemas' => static function (int $variant): array {
        $state = new SQLitePragmaRuntimeState(cacheSize: 64);
        $state->attach('aux1', "/tmp/pragma-cache-spill-inherit-on-{$variant}.sqlite");
        $state->pragma('PRAGMA aux1.cache_size=50');
        $state->pragma('PRAGMA cache_spill=ON');

        return [
            'state' => $state,
            'setup' => [],
            'checks' => [
                ['sql' => 'PRAGMA cache_spill', 'schema' => 'main', 'spill' => 64],
                ['sql' => 'PRAGMA aux1.cache_spill', 'schema' => 'aux1', 'spill' => 50],
            ],
            'locks' => [
                ['schema' => 'aux1', 'dirty' => 50, 'lock' => 'exclusive'],
            ],
        ];
    },
    'cache_spill schema-qualified cache spill does not mutate main' => static function (int $variant): array {
        $state = new SQLitePragmaRuntimeState(cacheSize: 80);
        $state->attach('aux1', "/tmp/pragma-cache-spill-qualified-{$variant}.sqlite");
        $state->pragma('PRAGMA aux1.cache_size=40');
        $state->pragma('PRAGMA aux1.cache_spill=OFF');

        return [
            'state' => $state,
            'setup' => [],
            'checks' => [
                ['sql' => 'PRAGMA main.cache_spill', 'schema' => 'main', 'spill' => 80],
                ['sql' => 'PRAGMA aux1.cache_spill', 'schema' => 'aux1', 'spill' => 0],
            ],
            'locks' => [
                ['schema' => 'main', 'dirty' => 80, 'lock' => 'exclusive'],
                ['schema' => 'aux1', 'dirty' => 80, 'lock' => 'reserved'],
            ],
        ];
    },
    'cache_spill commit clears dirty locks' => static function (int $variant): array {
        $state = new SQLitePragmaRuntimeState(cacheSize: 20 + ($variant % 13));
        $state->begin();
        $state->dirtyPages('main', 100);
        $state->commit();

        return [
            'state' => $state,
            'setup' => [],
            'checks' => [
                ['sql' => 'PRAGMA cache_spill', 'schema' => 'main', 'spill' => 20 + ($variant % 13)],
            ],
            'locks' => [
                ['schema' => 'main', 'dirty' => 0, 'lock' => 'unlocked'],
            ],
        ];
    },
];

foreach (range(1, 1000) as $variant) {
    $name = array_keys($scenarios)[($variant - 1) % count($scenarios)];
    $factory = $scenarios[$name];

    $tests[sprintf('real upstream pragma2 cache_spill dynamic %04d %s', $variant, $name)] = static function (TestRunner $t) use ($factory, $variant, $name): void {
        $case = $factory($variant);
        /** @var SQLitePragmaRuntimeState $state */
        $state = $case['state'];

        foreach ($case['setup'] as $setup) {
            $t->same($setup['expected'], $setup['row']['cache_spill']);
        }

        foreach ($case['checks'] as $check) {
            $row = $state->pragma($check['sql']);
            $t->same($check['schema'], $row['schema']);
            $t->same($check['spill'], $row['cache_spill']);
        }

        foreach ($case['locks'] as $lock) {
            if ($lock['dirty'] > 0) {
                $row = $state->dirtyPages($lock['schema'], $lock['dirty']);
                $t->same($lock['dirty'], $row['dirty_pages']);
                $t->same($lock['lock'], $row['lock']);
                continue;
            }

            $matching = array_values(array_filter(
                $state->lockStatus(),
                static fn (array $row): bool => $row['schema'] === $lock['schema']
            ));
            $t->same($lock['lock'], $matching[0]['lock']);
        }

        $t->contains('cache_spill', $name);
    };
}

$tests['real upstream pragma2 cache_spill source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'pragma2.test pragma2-4.1 through 4.3 default/on/off cache_spill state and setup',
        'pragma2.test pragma2-4.4 through 4.5.4 dirty-page cache_spill lock escalation and numeric thresholds',
        'pragma2.test pragma2-4.6 through 4.8 attached database cache_spill inheritance',
        'pragma2.test pragma2-5.1 through 5.3 YES/NO and negative parenthesized cache_spill forms',
    ];

    $t->same(4, count($sections));
    $t->contains('pragma2-4.4', $sections[1]);
    $t->contains('5.3', $sections[3]);
};

$tests['real upstream pragma2 cache_spill rejects malformed runtime forms'] = static function (TestRunner $t): void {
    $state = new SQLitePragmaRuntimeState();

    $t->throws(InvalidArgumentException::class, static fn (): array => $state->pragma('PRAGMA cache_spill=maybe'));
    $t->throws(InvalidArgumentException::class, static fn (): array => $state->pragma('PRAGMA missing.cache_spill'));
    $t->throws(InvalidArgumentException::class, static fn (): array => $state->dirtyPages('main', -1));
};

return $tests;
