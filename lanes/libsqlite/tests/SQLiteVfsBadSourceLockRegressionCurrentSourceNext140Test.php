<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsShmFileControlLockCurrentSourcePlan;

$run87 = static fn (array $ops, array $options = []): array => SQLiteVfsShmFileControlLockCurrentSourcePlan::currentSourceNext87($ops, $options + [
    'filename' => 'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared',
]);
$run126 = static fn (array $ops, array $options = []): array => SQLiteVfsShmFileControlLockCurrentSourcePlan::currentSourceNext126($ops, $options + [
    'filename' => 'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared',
]);
$run131 = static fn (array $ops, array $options = []): array => SQLiteVfsShmFileControlLockCurrentSourcePlan::currentSourceNext131($ops, $options + [
    'filename' => 'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared',
]);

$badCurrentSource = ['current' => ['current_source' => 'temp']];
$badSourceHandle = ['current' => ['source_handles' => ['temp' => 'vfs87-1']]];
$badHandleSource = ['current' => [
    'handles' => [
        'vfs87-1' => [
            'source' => 'temp',
            'owner' => '/srv/www/wp-content/database/.ht.sqlite',
            'path' => '/srv/www/wp-content/database/.ht.sqlite-temp',
            'readonly' => false,
            'nolock' => false,
            'controls' => [],
            'shm_locks' => [],
        ],
    ],
]];
$badHandleLock = ['current' => [
    'handles' => [
        'vfs87-2' => [
            'source' => 'shm',
            'owner' => '/srv/www/wp-content/database/.ht.sqlite',
            'path' => '/srv/www/wp-content/database/.ht.sqlite-shm',
            'readonly' => false,
            'nolock' => false,
            'controls' => [],
            'shm_locks' => ['temp' => 'shared'],
        ],
    ],
]];
$badHandleLockMode = ['current' => [
    'handles' => [
        'vfs87-2' => [
            'source' => 'shm',
            'owner' => '/srv/www/wp-content/database/.ht.sqlite',
            'path' => '/srv/www/wp-content/database/.ht.sqlite-shm',
            'readonly' => false,
            'nolock' => false,
            'controls' => [],
            'shm_locks' => ['read0' => 'pending'],
        ],
    ],
]];
$badHandleOwnerLock = ['current' => [
    'handles' => [
        'vfs87-2' => [
            'source' => 'shm',
            'owner' => '/srv/www/wp-content/database/.ht.sqlite',
            'path' => '/srv/www/wp-content/database/.ht.sqlite-shm',
            'readonly' => false,
            'nolock' => false,
            'controls' => [],
            'shm_locks' => ['read0' => 'shared'],
            'shm_lock_owners' => ['temp' => ['wp-admin']],
        ],
    ],
]];
$badPersistentLock = ['current' => [
    'persistent_shm_locks' => [
        '/srv/www/wp-content/database/.ht.sqlite' => ['temp' => 'shared'],
    ],
]];
$badPersistentLockMode = ['current' => [
    'persistent_shm_locks' => [
        '/srv/www/wp-content/database/.ht.sqlite' => ['read0' => 'pending'],
    ],
]];
$badPersistentOwnerLock = ['current' => [
    'persistent_shm_lock_owners' => [
        '/srv/www/wp-content/database/.ht.sqlite' => ['temp' => ['wp-admin']],
    ],
]];

$validHydrated = static fn (): array => $run131([
    'open(main)',
    'open(shm)',
    ['op' => 'shmlock', 'lock' => 'read0', 'span' => 2, 'mode' => 'shared', 'connection' => 'wp-cron'],
    ['op' => 'shmlock', 'lock' => 'read1', 'mode' => 'unlock', 'connection' => 'wp-admin'],
    ['op' => 'shmlock', 'lock' => 'read1', 'mode' => 'unlock', 'connection' => 'wp-cron'],
    ['op' => 'shmlock', 'lock' => 'read1', 'mode' => 'exclusive', 'connection' => 'wp-cron'],
], [
    'current' => [
        'source_handles' => [
            'main' => 'vfs87-1',
            'shm' => 'vfs87-2',
        ],
        'handles' => [
            'vfs87-1' => [
                'source' => 'main',
                'owner' => '/srv/www/wp-content/database/.ht.sqlite',
                'path' => '/srv/www/wp-content/database/.ht.sqlite',
                'readonly' => false,
                'nolock' => false,
                'controls' => ['mmap_size' => 4096],
                'shm_locks' => [],
            ],
            'vfs87-2' => [
                'source' => 'shm',
                'owner' => '/srv/www/wp-content/database/.ht.sqlite',
                'path' => '/srv/www/wp-content/database/.ht.sqlite-shm',
                'readonly' => false,
                'nolock' => false,
                'controls' => [],
                'shm_locks' => ['read1' => 'shared'],
                'shm_lock_owners' => ['read1' => ['wp-admin']],
            ],
        ],
        'persistent_shm_locks' => [
            '/srv/www/wp-content/database/.ht.sqlite' => ['read1' => 'shared'],
        ],
        'persistent_shm_lock_owners' => [
            '/srv/www/wp-content/database/.ht.sqlite' => ['read1' => ['wp-admin']],
        ],
    ],
]);

$cases = [
    'bad current source' => $badCurrentSource,
    'bad source handle key' => $badSourceHandle,
    'bad hydrated handle source' => $badHandleSource,
    'bad hydrated handle lock' => $badHandleLock,
    'bad hydrated handle lock mode' => $badHandleLockMode,
    'bad hydrated handle owner lock' => $badHandleOwnerLock,
    'bad persistent shm lock' => $badPersistentLock,
    'bad persistent shm lock mode' => $badPersistentLockMode,
    'bad persistent owner lock' => $badPersistentOwnerLock,
];

$tests = [];
foreach ($cases as $label => $options) {
    $tests["vfs bad source lock regression next140 next87 rejects {$label}"] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run87(['open(main)'], $options));
    $tests["vfs bad source lock regression next140 next126 rejects {$label}"] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run126(['open(main)'], $options));
    $tests["vfs bad source lock regression next140 next131 rejects {$label}"] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run131(['open(main)'], $options));
}

$tests += [
    'vfs bad source lock regression next140 valid hydrated source handles survive' => static fn (TestRunner $t) => $t->same('shm', $validHydrated()['events'][1]['source']),
    'vfs bad source lock regression next140 valid hydrated main source normalized' => static fn (TestRunner $t) => $t->same('main', $validHydrated()['events'][0]['source']),
    'vfs bad source lock regression next140 valid hydrated main open status' => static fn (TestRunner $t) => $t->same('main-open', $validHydrated()['events'][0]['status']),
    'vfs bad source lock regression next140 valid hydrated shm lock reused' => static fn (TestRunner $t) => $t->same(true, $validHydrated()['events'][1]['reused_shm_locks']),
    'vfs bad source lock regression next140 valid hydrated owner lock reused' => static fn (TestRunner $t) => $t->same(['wp-admin'], $validHydrated()['events'][1]['next']['handles']['vfs87-2']['shm_lock_owners']['read1']),
    'vfs bad source lock regression next140 shared range observes hydrated owner' => static fn (TestRunner $t) => $t->same(['wp-admin', 'wp-cron'], $validHydrated()['events'][2]['owner_locks']['read1']),
    'vfs bad source lock regression next140 shared range lists hydrated lock' => static fn (TestRunner $t) => $t->same(['read0', 'read1'], $validHydrated()['events'][2]['locks']),
    'vfs bad source lock regression next140 partial unlock keeps other owner' => static fn (TestRunner $t) => $t->same(['wp-cron'], $validHydrated()['events'][3]['next']['handles']['vfs87-2']['shm_lock_owners']['read1']),
    'vfs bad source lock regression next140 second unlock clears shared owner' => static fn (TestRunner $t) => $t->same(false, array_key_exists('read1', $validHydrated()['events'][4]['next']['handles']['vfs87-2']['shm_lock_owners'])),
    'vfs bad source lock regression next140 exclusive after owner unlock ok' => static fn (TestRunner $t) => $t->same('ok', $validHydrated()['events'][5]['status']),
    'vfs bad source lock regression next140 exclusive owner stored' => static fn (TestRunner $t) => $t->same(['wp-cron'], $validHydrated()['events'][5]['owner_locks']['read1']),
    'vfs bad source lock regression next140 final shm lock count' => static fn (TestRunner $t) => $t->same(2, $validHydrated()['next']['shm_lock_count']),
    'vfs bad source lock regression next140 final persistent connection count' => static fn (TestRunner $t) => $t->same(1, $validHydrated()['next']['persistent_shm_connection_count']),
    'vfs bad source lock regression next140 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-shm-uri-filecontrol-lock-current-source-next131', $validHydrated()['dependencies'], true)),
];

return $tests;
