<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsLockByteUriShmCurrentSourceNext;

$tests = [];

$run = static fn (array $ops, array $options = []): array => SQLiteVfsLockByteUriShmCurrentSourceNext::plan($ops, $options);

$application = static function () use ($run): array {
    static $result = null;
    if ($result === null) {
        $result = $run([
            'open(file:/srv/www/wp-content/database/wp%20copy.sqlite-shm?mode=rw&cache=shared)',
            'shm read0 shared wp-reader',
            'open(file://localhost/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=shared)',
            'lock shared wp-reader 11 on main',
            'source(shm)',
            'shm write exclusive wp-import',
            'source(main)',
            'lock reserved wp-import 22',
            'open(file:/srv/www/wp-content/database/wp%20copy.sqlite-wal?mode=rw&cache=private)',
            'shm checkpoint exclusive wp-import',
            'source(main)',
            'lock exclusive wp-import 22',
            'yield wp-reader',
            'lock exclusive wp-import 22',
            'shm read0 unlock wp-reader on shm',
        ]);
    }

    return $result;
};

$readonlyNolock = static fn (): array => $run([
    'open(file:/srv/www/wp-content/database/archive.sqlite-shm?mode=ro&nolock=1)',
    'lock shared wp-repair 3',
    'shm recover exclusive wp-repair',
]);

$multiOwner = static fn (): array => $run([
    'open(file:/srv/www/wp-content/database/main.sqlite?mode=rw)',
    'lock shared a 1',
    'open(file:/srv/www/wp-content/database/other.sqlite-shm?mode=rw)',
    'shm read1 shared b',
    'open(file:/srv/www/wp-content/database/other.sqlite?mode=rw)',
    'lock reserved b 2',
]);

$memory = static fn (): array => $run([
    'open(file::memory:?mode=memory&cache=shared)',
    'lock shared mem-reader 4',
    'shm read2 shared mem-reader',
    'open(file::memory:?mode=memory&cache=shared)',
    'lock shared mem-reader-2 5',
]);

$current = static fn (): array => $run([
    'source(main)',
    'lock exclusive wp-import 8',
    'yield wp-reader',
    'lock exclusive wp-import 8',
], [
    'current' => [
        'sequence' => 2,
        'current_source' => 'main',
        'source_handles' => ['main' => 'vfs97-1', 'shm' => 'vfs97-2'],
        'handles' => [
            'vfs97-1' => [
                'id' => 'vfs97-1',
                'status' => 'main-open',
                'source' => 'main',
                'path' => '/srv/www/wp-content/database/wp copy.sqlite',
                'owner' => '/srv/www/wp-content/database/wp copy.sqlite',
                'readonly' => false,
                'nolock' => false,
                'owner_had_main_open' => false,
                'uri' => ['is_uri' => true, 'path' => '/srv/www/wp-content/database/wp copy.sqlite', 'mode' => 'rw', 'cache' => 'shared', 'immutable' => false, 'nolock' => false, 'vfs' => null, 'authority' => null],
            ],
            'vfs97-2' => [
                'id' => 'vfs97-2',
                'status' => 'shm-open',
                'source' => 'shm',
                'path' => '/srv/www/wp-content/database/wp copy.sqlite-shm',
                'owner' => '/srv/www/wp-content/database/wp copy.sqlite',
                'readonly' => false,
                'nolock' => false,
                'owner_had_main_open' => true,
                'uri' => ['is_uri' => true, 'path' => '/srv/www/wp-content/database/wp copy.sqlite-shm', 'mode' => 'rw', 'cache' => 'shared', 'immutable' => false, 'nolock' => false, 'vfs' => null, 'authority' => null],
            ],
        ],
        'lock_holders' => [
            '/srv/www/wp-content/database/wp copy.sqlite' => ['wp-reader' => 'shared'],
        ],
        'shared_slots' => [
            '/srv/www/wp-content/database/wp copy.sqlite' => ['wp-reader' => 17],
        ],
        'shm_locks' => [
            '/srv/www/wp-content/database/wp copy.sqlite' => ['read0' => ['wp-reader' => 'shared']],
        ],
    ],
]);

$hydrated = [
    'sequence' => 3,
    'current_source' => 'shm',
    'source_handles' => ['main' => 'vfs97-1', 'shm' => 'vfs97-2'],
    'handles' => [
        'vfs97-1' => [
            'id' => 'vfs97-1',
            'source' => 'main',
            'path' => '/srv/www/wp-content/database/wp copy.sqlite',
            'owner' => '/srv/www/wp-content/database/wp copy.sqlite',
            'readonly' => false,
            'nolock' => false,
            'uri' => ['is_uri' => true, 'path' => '/srv/www/wp-content/database/wp copy.sqlite', 'mode' => 'rw', 'cache' => 'shared', 'immutable' => false, 'nolock' => false, 'vfs' => null, 'authority' => null],
        ],
        'vfs97-2' => [
            'id' => 'vfs97-2',
            'source' => 'shm',
            'path' => '/srv/www/wp-content/database/wp copy.sqlite-shm',
            'owner' => '/srv/www/wp-content/database/wp copy.sqlite',
            'readonly' => false,
            'nolock' => false,
            'uri' => ['is_uri' => true, 'path' => '/srv/www/wp-content/database/wp copy.sqlite-shm', 'mode' => 'rw', 'cache' => 'shared', 'immutable' => false, 'nolock' => false, 'vfs' => null, 'authority' => null],
        ],
    ],
    'lock_holders' => [
        '/srv/www/wp-content/database/wp copy.sqlite' => ['wp-reader' => 'shared'],
    ],
    'shared_slots' => [
        '/srv/www/wp-content/database/wp copy.sqlite' => ['wp-reader' => 17],
    ],
    'shm_locks' => [
        '/srv/www/wp-content/database/wp copy.sqlite' => ['read0' => ['wp-reader' => 'shared']],
    ],
    'shm_lock_sources' => [
        '/srv/www/wp-content/database/wp copy.sqlite' => ['read0' => ['wp-reader' => 'shm']],
    ],
    'persistent_generations' => [
        '/srv/www/wp-content/database/wp copy.sqlite' => 4,
    ],
];

$hydratedReplay = static fn (): array => $run(['shm read0 exclusive wp-import'], ['current' => $hydrated]);

$tests['vfs lock byte uri shm current source next97 dependency marker'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-lock-byte-uri-shm-current-source-next97', $application()['dependencies'], true));
$tests['vfs lock byte uri shm current source next97 event count'] = static fn (TestRunner $t) => $t->same(15, count($application()['events']));
$tests['vfs lock byte uri shm current source next97 first open status'] = static fn (TestRunner $t) => $t->same('shm-open', $application()['events'][0]['status']);
$tests['vfs lock byte uri shm current source next97 first open source shm'] = static fn (TestRunner $t) => $t->same('shm', $application()['events'][0]['source']);
$tests['vfs lock byte uri shm current source next97 first open decoded path'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite-shm', $application()['events'][0]['path']);
$tests['vfs lock byte uri shm current source next97 first open owner canonical'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $application()['events'][0]['owner']);
$tests['vfs lock byte uri shm current source next97 first open sidecar first'] = static fn (TestRunner $t) => $t->same(true, $application()['events'][0]['sidecar_open_first']);
$tests['vfs lock byte uri shm current source next97 shm read acquired'] = static fn (TestRunner $t) => $t->same('acquired', $application()['events'][1]['status']);
$tests['vfs lock byte uri shm current source next97 shm read owner routed'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $application()['events'][1]['owner']);
$tests['vfs lock byte uri shm current source next97 shm read stored on owner'] = static fn (TestRunner $t) => $t->same(['wp-reader' => 'shared'], $application()['events'][1]['next']['owners']['/srv/www/wp-content/database/wp copy.sqlite']['shm_locks']['read0']);
$tests['vfs lock byte uri shm current source next97 main open status'] = static fn (TestRunner $t) => $t->same('main-open', $application()['events'][2]['status']);
$tests['vfs lock byte uri shm current source next97 main open not sidecar first'] = static fn (TestRunner $t) => $t->same(false, $application()['events'][2]['sidecar_open_first']);
$tests['vfs lock byte uri shm current source next97 main uri authority localhost'] = static fn (TestRunner $t) => $t->same('localhost', $application()['events'][2]['uri']['authority']);
$tests['vfs lock byte uri shm current source next97 shared lock status'] = static fn (TestRunner $t) => $t->same('planned', $application()['events'][3]['status']);
$tests['vfs lock byte uri shm current source next97 shared lock source main'] = static fn (TestRunner $t) => $t->same('main', $application()['events'][3]['source']);
$tests['vfs lock byte uri shm current source next97 shared lock path main'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $application()['events'][3]['path']);
$tests['vfs lock byte uri shm current source next97 shared lock byte offset'] = static fn (TestRunner $t) => $t->same(1073741837, $application()['events'][3]['plan']['acquire'][0]['offset']);
$tests['vfs lock byte uri shm current source next97 source shm ok'] = static fn (TestRunner $t) => $t->same('ok', $application()['events'][4]['status']);
$tests['vfs lock byte uri shm current source next97 write shm acquired'] = static fn (TestRunner $t) => $t->same('acquired', $application()['events'][5]['status']);
$tests['vfs lock byte uri shm current source next97 write shm stored'] = static fn (TestRunner $t) => $t->same(['wp-import' => 'exclusive'], $application()['events'][5]['next']['owners']['/srv/www/wp-content/database/wp copy.sqlite']['shm_locks']['write']);
$tests['vfs lock byte uri shm current source next97 source main ok'] = static fn (TestRunner $t) => $t->same('ok', $application()['events'][6]['status']);
$tests['vfs lock byte uri shm current source next97 reserved writer coexists'] = static fn (TestRunner $t) => $t->same('planned', $application()['events'][7]['status']);
$tests['vfs lock byte uri shm current source next97 reserved holder stored'] = static fn (TestRunner $t) => $t->same('reserved', $application()['events'][7]['next']['owners']['/srv/www/wp-content/database/wp copy.sqlite']['holders']['wp-import']);
$tests['vfs lock byte uri shm current source next97 wal open source'] = static fn (TestRunner $t) => $t->same('wal', $application()['events'][8]['source']);
$tests['vfs lock byte uri shm current source next97 wal open owner shared'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $application()['events'][8]['owner']);
$tests['vfs lock byte uri shm current source next97 wal open cache private'] = static fn (TestRunner $t) => $t->same('private', $application()['events'][8]['uri']['cache']);
$tests['vfs lock byte uri shm current source next97 checkpoint acquired via wal current'] = static fn (TestRunner $t) => $t->same('acquired', $application()['events'][9]['status']);
$tests['vfs lock byte uri shm current source next97 checkpoint source wal'] = static fn (TestRunner $t) => $t->same('wal', $application()['events'][9]['source']);
$tests['vfs lock byte uri shm current source next97 checkpoint stored owner'] = static fn (TestRunner $t) => $t->same(['wp-import' => 'exclusive'], $application()['events'][9]['next']['owners']['/srv/www/wp-content/database/wp copy.sqlite']['shm_locks']['checkpoint']);
$tests['vfs lock byte uri shm current source next97 exclusive blocked by reader'] = static fn (TestRunner $t) => $t->same('blocked', $application()['events'][11]['status']);
$tests['vfs lock byte uri shm current source next97 exclusive blocker names reader'] = static fn (TestRunner $t) => $t->same(['wp-reader:shared'], $application()['events'][11]['blocking']);
$tests['vfs lock byte uri shm current source next97 exclusive block reason'] = static fn (TestRunner $t) => $t->same('owner_byte_lock_conflict', $application()['events'][11]['reason']);
$tests['vfs lock byte uri shm current source next97 yield releases reader byte lock'] = static fn (TestRunner $t) => $t->same(false, array_key_exists('wp-reader', $application()['events'][12]['next']['owners']['/srv/www/wp-content/database/wp copy.sqlite']['holders']));
$tests['vfs lock byte uri shm current source next97 yield releases reader shm lock'] = static fn (TestRunner $t) => $t->same([], $application()['events'][12]['next']['owners']['/srv/www/wp-content/database/wp copy.sqlite']['shm_locks']['read0']);
$tests['vfs lock byte uri shm current source next97 exclusive succeeds after yield'] = static fn (TestRunner $t) => $t->same('planned', $application()['events'][13]['status']);
$tests['vfs lock byte uri shm current source next97 exclusive holder stored'] = static fn (TestRunner $t) => $t->same('exclusive', $application()['events'][13]['next']['owners']['/srv/www/wp-content/database/wp copy.sqlite']['holders']['wp-import']);
$tests['vfs lock byte uri shm current source next97 unlock idempotent released'] = static fn (TestRunner $t) => $t->same('released', $application()['events'][14]['status']);
$tests['vfs lock byte uri shm current source next97 final status released'] = static fn (TestRunner $t) => $t->same('released', $application()['status']);
$tests['vfs lock byte uri shm current source next97 final current source main'] = static fn (TestRunner $t) => $t->same('main', $application()['next']['current_source']);
$tests['vfs lock byte uri shm current source next97 final open by source'] = static fn (TestRunner $t) => $t->same(['main' => 1, 'wal' => 1, 'shm' => 1], $application()['next']['open_by_source']);
$tests['vfs lock byte uri shm current source next97 final one owner'] = static fn (TestRunner $t) => $t->same(1, $application()['next']['owner_count']);
$tests['vfs lock byte uri shm current source next97 final lock count'] = static fn (TestRunner $t) => $t->same(1, $application()['next']['lock_holder_count']);
$tests['vfs lock byte uri shm current source next97 final shm lock count'] = static fn (TestRunner $t) => $t->same(2, $application()['next']['shm_lock_count']);

$tests['vfs lock byte uri shm current source next97 nolock shared blocked'] = static fn (TestRunner $t) => $t->same('blocked', $readonlyNolock()['events'][1]['status']);
$tests['vfs lock byte uri shm current source next97 nolock reason'] = static fn (TestRunner $t) => $t->same('nolock VFS disables POSIX byte-range locking', $readonlyNolock()['events'][1]['reason']);
$tests['vfs lock byte uri shm current source next97 nolock shm still acquired'] = static fn (TestRunner $t) => $t->same('acquired', $readonlyNolock()['events'][2]['status']);
$tests['vfs lock byte uri shm current source next97 nolock readonly retained'] = static fn (TestRunner $t) => $t->same(true, $readonlyNolock()['events'][0]['readonly']);
$tests['vfs lock byte uri shm current source next97 nolock final no byte holders'] = static fn (TestRunner $t) => $t->same(0, $readonlyNolock()['next']['lock_holder_count']);

$tests['vfs lock byte uri shm current source next97 multi owner count'] = static fn (TestRunner $t) => $t->same(2, $multiOwner()['next']['owner_count']);
$tests['vfs lock byte uri shm current source next97 multi first owner holder'] = static fn (TestRunner $t) => $t->same(['a' => 'shared'], $multiOwner()['next']['owners']['/srv/www/wp-content/database/main.sqlite']['holders']);
$tests['vfs lock byte uri shm current source next97 multi second owner shm'] = static fn (TestRunner $t) => $t->same(['b' => 'shared'], $multiOwner()['next']['owners']['/srv/www/wp-content/database/other.sqlite']['shm_locks']['read1']);
$tests['vfs lock byte uri shm current source next97 multi second owner reserved'] = static fn (TestRunner $t) => $t->same(['b' => 'reserved'], $multiOwner()['next']['owners']['/srv/www/wp-content/database/other.sqlite']['holders']);
$tests['vfs lock byte uri shm current source next97 multi final current source main'] = static fn (TestRunner $t) => $t->same('main', $multiOwner()['next']['current_source']);

$tests['vfs lock byte uri shm current source next97 memory distinct owners'] = static fn (TestRunner $t) => $t->same(2, $memory()['next']['owner_count']);
$tests['vfs lock byte uri shm current source next97 memory first owner'] = static fn (TestRunner $t) => $t->same('memory:vfs97-1', $memory()['events'][0]['owner']);
$tests['vfs lock byte uri shm current source next97 memory second owner'] = static fn (TestRunner $t) => $t->same('memory:vfs97-2', $memory()['events'][3]['owner']);
$tests['vfs lock byte uri shm current source next97 memory first owner locks isolated'] = static fn (TestRunner $t) => $t->same(['mem-reader' => 'shared'], $memory()['next']['owners']['memory:vfs97-1']['holders']);
$tests['vfs lock byte uri shm current source next97 memory second owner locks isolated'] = static fn (TestRunner $t) => $t->same(['mem-reader-2' => 'shared'], $memory()['next']['owners']['memory:vfs97-2']['holders']);

$tests['vfs lock byte uri shm current source next97 current source replay starts blocked'] = static fn (TestRunner $t) => $t->same('blocked', $current()['events'][1]['status']);
$tests['vfs lock byte uri shm current source next97 current source replay blocker'] = static fn (TestRunner $t) => $t->same(['wp-reader:shared'], $current()['events'][1]['blocking']);
$tests['vfs lock byte uri shm current source next97 current source replay yield'] = static fn (TestRunner $t) => $t->same('released', $current()['events'][2]['status']);
$tests['vfs lock byte uri shm current source next97 current source replay exclusive succeeds'] = static fn (TestRunner $t) => $t->same('planned', $current()['events'][3]['status']);
$tests['vfs lock byte uri shm current source next97 current source replay shm cleared'] = static fn (TestRunner $t) => $t->same([], $current()['next']['owners']['/srv/www/wp-content/database/wp copy.sqlite']['shm_locks']['read0']);

$tests['vfs lock byte uri shm current source next97 hydrated shm source retained'] = static fn (TestRunner $t) => $t->same('shm', $hydratedReplay()['events'][0]['source']);
$tests['vfs lock byte uri shm current source next97 hydrated shm owner retained'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $hydratedReplay()['events'][0]['owner']);
$tests['vfs lock byte uri shm current source next97 hydrated shm blocker retained'] = static fn (TestRunner $t) => $t->same(['wp-reader:shared'], $hydratedReplay()['events'][0]['blocking']);
$tests['vfs lock byte uri shm current source next97 hydrated generation retained'] = static fn (TestRunner $t) => $t->same(4, $hydratedReplay()['events'][0]['next']['owners']['/srv/www/wp-content/database/wp copy.sqlite']['generation']);
$tests['vfs lock byte uri shm current source next97 rejects bad hydrated source handle key'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run(['source(shm)'], ['current' => array_replace($hydrated, ['source_handles' => ['temp-shm' => 'vfs97-2']])]));
$tests['vfs lock byte uri shm current source next97 rejects bad hydrated source mismatch'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run(['source(shm)'], ['current' => array_replace($hydrated, ['source_handles' => ['wal' => 'vfs97-2']])]));
$tests['vfs lock byte uri shm current source next97 rejects bad hydrated shm lock'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run(['source(shm)'], ['current' => array_replace($hydrated, ['shm_locks' => ['/srv/www/wp-content/database/wp copy.sqlite' => ['temp' => ['wp-reader' => 'shared']]]])]));
$tests['vfs lock byte uri shm current source next97 rejects bad hydrated shm mode'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run(['source(shm)'], ['current' => array_replace($hydrated, ['shm_locks' => ['/srv/www/wp-content/database/wp copy.sqlite' => ['read0' => ['wp-reader' => 'unlock']]]])]));
$tests['vfs lock byte uri shm current source next97 rejects bad hydrated shm source'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run(['source(shm)'], ['current' => array_replace($hydrated, ['shm_lock_sources' => ['/srv/www/wp-content/database/wp copy.sqlite' => ['read0' => ['wp-reader' => 'temp-shm']]]])]));
$tests['vfs lock byte uri shm current source next97 rejects bad hydrated byte level'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run(['source(shm)'], ['current' => array_replace($hydrated, ['lock_holders' => ['/srv/www/wp-content/database/wp copy.sqlite' => ['wp-reader' => 'bad']]])]));

$tests['vfs lock byte uri shm current source next97 same shm shared coexists'] = static fn (TestRunner $t) => $t->same('acquired', $run(['open(shm)', 'shm read0 shared a', 'shm read0 shared b'])['events'][2]['status']);
$tests['vfs lock byte uri shm current source next97 shm exclusive blocked by shared'] = static fn (TestRunner $t) => $t->same(['a:shared'], $run(['open(shm)', 'shm read0 shared a', 'shm read0 exclusive b'])['events'][2]['blocking']);
$tests['vfs lock byte uri shm current source next97 shm shared blocked by exclusive'] = static fn (TestRunner $t) => $t->same(['a:exclusive'], $run(['open(shm)', 'shm read0 exclusive a', 'shm read0 shared b'])['events'][2]['blocking']);
$tests['vfs lock byte uri shm current source next97 second reserved blocked'] = static fn (TestRunner $t) => $t->same(['a:reserved'], $run(['open(main)', 'lock reserved a 1', 'lock reserved b 2'])['events'][2]['blocking']);
$tests['vfs lock byte uri shm current source next97 pending blocked by pending'] = static fn (TestRunner $t) => $t->same(['a:pending'], $run(['open(main)', 'lock pending a 1', 'lock pending b 2'])['events'][2]['blocking']);

$tests['vfs lock byte uri shm current source next97 rejects empty operations'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsLockByteUriShmCurrentSourceNext::plan([]));
$tests['vfs lock byte uri shm current source next97 rejects lock before open'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run(['lock shared wp 1']));
$tests['vfs lock byte uri shm current source next97 rejects remote authority'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run(['open(file://example.com/srv/db.sqlite-shm?mode=rw)']));
$tests['vfs lock byte uri shm current source next97 rejects bad percent'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run(['open(file:/srv/db%2.sqlite-shm?mode=rw)']));
$tests['vfs lock byte uri shm current source next97 rejects bad source'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([['op' => 'open', 'source' => 'temp']]));
$tests['vfs lock byte uri shm current source next97 rejects bad level'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run(['open(main)', ['op' => 'lock', 'level' => 'bad', 'connection' => 'wp']]));
$tests['vfs lock byte uri shm current source next97 rejects bad shared slot'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run(['open(main)', ['op' => 'lock', 'level' => 'shared', 'connection' => 'wp', 'shared_slot' => 510]]));
$tests['vfs lock byte uri shm current source next97 rejects bad shm lock'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run(['open(shm)', ['op' => 'shm', 'lock' => 'read9', 'mode' => 'shared', 'connection' => 'wp']]));
$tests['vfs lock byte uri shm current source next97 rejects bad shm mode'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run(['open(shm)', ['op' => 'shm', 'lock' => 'read0', 'mode' => 'bad', 'connection' => 'wp']]));

return $tests;
