<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsShmFileControlLockCurrentSourcePlan;

$run126 = static fn (array $ops, array $options = []): array => SQLiteVfsShmFileControlLockCurrentSourcePlan::planUriShmFileControlLocks($ops, $options + [
    'filename' => 'file://localhost/srv/www/wp-content/database/wp%20cache.sqlite?mode=rw&cache=shared',
]);

$sharedReaders = static function () use ($run126): array {
    static $result = null;
    if ($result === null) {
        $result = $run126([
            'open(main, file://localhost/srv/www/wp-content/database/wp%20cache.sqlite?mode=rw&cache=shared)',
            'open(shm, file://localhost/srv/www/wp-content/database/wp%20cache.sqlite-shm?mode=rw&cache=shared)',
            ['op' => 'shmlock', 'lock' => 'read0', 'mode' => 'shared', 'connection' => 'wp-admin'],
            ['op' => 'shmlock', 'lock' => 'read0', 'mode' => 'shared', 'connection' => 'wp-cron'],
            ['op' => 'shmlock', 'lock' => 'read0', 'mode' => 'exclusive', 'connection' => 'wp-admin'],
            ['op' => 'shmlock', 'lock' => 'read0', 'mode' => 'unlock', 'connection' => 'wp-cron'],
            ['op' => 'shmlock', 'lock' => 'read0', 'mode' => 'exclusive', 'connection' => 'wp-admin'],
            ['op' => 'shmlock', 'lock' => 'read0', 'mode' => 'unlock', 'connection' => 'wp-admin'],
            ['op' => 'shmlock', 'lock' => 'write', 'mode' => 'exclusive', 'connection' => 'wp-admin'],
            ['op' => 'shmlock', 'lock' => 'write', 'mode' => 'shared', 'connection' => 'wp-rest'],
            ['op' => 'shmlock', 'lock' => 'write', 'mode' => 'unlock', 'connection' => 'wp-admin'],
            ['op' => 'shmlock', 'lock' => 'write', 'mode' => 'shared', 'connection' => 'wp-rest'],
            'source(main)',
            'file_control(persist_wal, on)',
            'source(shm)',
            'file_control(data_version)',
        ]);
    }

    return $result;
};

$reopen = static fn (): array => $run126([
    'open(shm, file:/srv/www/wp-content/database/reopen.sqlite-shm?mode=rw)',
    ['op' => 'shmlock', 'lock' => 'read1', 'mode' => 'shared', 'connection' => 'wp-reader'],
    'close(shm)',
    'open(shm, file:/srv/www/wp-content/database/reopen.sqlite-shm?mode=rw)',
]);

$explicitCurrent = static fn (): array => $run126([
    'open(main, file:/srv/www/wp-content/database/persisted.sqlite?mode=rw)',
    'open(shm, file:/srv/www/wp-content/database/persisted.sqlite-shm?mode=rw)',
    ['op' => 'shmlock', 'lock' => 'read2', 'mode' => 'exclusive', 'connection' => 'wp-writer'],
    ['op' => 'shmlock', 'lock' => 'read2', 'mode' => 'shared', 'connection' => 'wp-reader'],
], [
    'current' => [
        'persistent_shm_locks' => [
            '/srv/www/wp-content/database/persisted.sqlite' => ['read2' => 'shared'],
        ],
        'persistent_shm_lock_owners' => [
            '/srv/www/wp-content/database/persisted.sqlite' => ['read2' => ['wp-reader']],
        ],
        'persistent_controls' => [
            '/srv/www/wp-content/database/persisted.sqlite' => ['data_version' => 8],
        ],
        'persistent_generations' => [
            '/srv/www/wp-content/database/persisted.sqlite' => 8,
        ],
    ],
]);

$readonly = static fn (): array => $run126([
    'open(main, file:/srv/www/wp-content/database/archive.sqlite?mode=ro)',
    'open(shm, file:/srv/www/wp-content/database/archive.sqlite-shm?mode=ro)',
    ['op' => 'shmlock', 'lock' => 'read3', 'mode' => 'shared', 'connection' => 'wp-reader'],
    ['op' => 'shmlock', 'lock' => 'read3', 'mode' => 'exclusive', 'connection' => 'wp-reader'],
    'file_control(mmap_size, 4096)',
    'file_control(persist_wal, on)',
]);

$nolock = static fn (): array => $run126([
    'open(main, file:/srv/www/wp-content/database/nolock.sqlite?mode=rw&nolock=1)',
    'open(shm, file:/srv/www/wp-content/database/nolock.sqlite-shm?mode=rw&nolock=1)',
    ['op' => 'shmlock', 'lock' => 'recover', 'mode' => 'exclusive', 'connection' => 'wp-recovery'],
]);

return [
    'vfs uri shm filecontrol lock current source next126 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-uri-shm-filecontrol-lock-current-source-next126', $sharedReaders()['dependencies'], true)),
    'vfs uri shm filecontrol lock current source next126 final status ok' => static fn (TestRunner $t) => $t->same('ok', $sharedReaders()['status']),
    'vfs uri shm filecontrol lock current source next126 main owner decoded' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp cache.sqlite', $sharedReaders()['events'][0]['owner']),
    'vfs uri shm filecontrol lock current source next126 shm owner decoded' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp cache.sqlite', $sharedReaders()['events'][1]['owner']),
    'vfs uri shm filecontrol lock current source next126 first reader ok' => static fn (TestRunner $t) => $t->same('ok', $sharedReaders()['events'][2]['status']),
    'vfs uri shm filecontrol lock current source next126 first reader owner stored' => static fn (TestRunner $t) => $t->same(['wp-admin'], $sharedReaders()['events'][2]['owners']),
    'vfs uri shm filecontrol lock current source next126 second reader ok' => static fn (TestRunner $t) => $t->same('ok', $sharedReaders()['events'][3]['status']),
    'vfs uri shm filecontrol lock current source next126 second reader shares byte' => static fn (TestRunner $t) => $t->same('shared', $sharedReaders()['events'][3]['next']['handles']['vfs87-2']['shm_locks']['read0']),
    'vfs uri shm filecontrol lock current source next126 second reader owners stored' => static fn (TestRunner $t) => $t->same(['wp-admin', 'wp-cron'], $sharedReaders()['events'][3]['owners']),
    'vfs uri shm filecontrol lock current source next126 exclusive blocked by other reader' => static fn (TestRunner $t) => $t->same('busy', $sharedReaders()['events'][4]['status']),
    'vfs uri shm filecontrol lock current source next126 exclusive blocking owner' => static fn (TestRunner $t) => $t->same(['wp-cron'], $sharedReaders()['events'][4]['blocking_connections']),
    'vfs uri shm filecontrol lock current source next126 busy preserves shared owners' => static fn (TestRunner $t) => $t->same(['wp-admin', 'wp-cron'], $sharedReaders()['events'][4]['next']['handles']['vfs87-2']['shm_lock_owners']['read0']),
    'vfs uri shm filecontrol lock current source next126 unlock second reader ok' => static fn (TestRunner $t) => $t->same('ok', $sharedReaders()['events'][5]['status']),
    'vfs uri shm filecontrol lock current source next126 unlock leaves first reader owner' => static fn (TestRunner $t) => $t->same(['wp-admin'], $sharedReaders()['events'][5]['next']['handles']['vfs87-2']['shm_lock_owners']['read0']),
    'vfs uri shm filecontrol lock current source next126 exclusive after other unlock still sqlite-busy upgrade' => static fn (TestRunner $t) => $t->same('busy', $sharedReaders()['events'][6]['status']),
    'vfs uri shm filecontrol lock current source next126 exclusive upgrade reason preserved' => static fn (TestRunner $t) => $t->same('shared SHM lock must be released before exclusive lock', $sharedReaders()['events'][6]['reason']),
    'vfs uri shm filecontrol lock current source next126 unlock first reader clears byte' => static fn (TestRunner $t) => $t->same(false, array_key_exists('read0', $sharedReaders()['events'][7]['next']['handles']['vfs87-2']['shm_locks'])),
    'vfs uri shm filecontrol lock current source next126 writer exclusive ok' => static fn (TestRunner $t) => $t->same('ok', $sharedReaders()['events'][8]['status']),
    'vfs uri shm filecontrol lock current source next126 writer owner stored' => static fn (TestRunner $t) => $t->same(['wp-admin'], $sharedReaders()['events'][8]['owners']),
    'vfs uri shm filecontrol lock current source next126 shared blocked by exclusive writer' => static fn (TestRunner $t) => $t->same('busy', $sharedReaders()['events'][9]['status']),
    'vfs uri shm filecontrol lock current source next126 shared blocking writer' => static fn (TestRunner $t) => $t->same(['wp-admin'], $sharedReaders()['events'][9]['blocking_connections']),
    'vfs uri shm filecontrol lock current source next126 writer unlock ok' => static fn (TestRunner $t) => $t->same('ok', $sharedReaders()['events'][10]['status']),
    'vfs uri shm filecontrol lock current source next126 writer unlock clears owners' => static fn (TestRunner $t) => $t->same(false, array_key_exists('write', $sharedReaders()['events'][10]['next']['handles']['vfs87-2']['shm_lock_owners'])),
    'vfs uri shm filecontrol lock current source next126 rest shared after writer unlock ok' => static fn (TestRunner $t) => $t->same('ok', $sharedReaders()['events'][11]['status']),
    'vfs uri shm filecontrol lock current source next126 rest shared owner stored' => static fn (TestRunner $t) => $t->same(['wp-rest'], $sharedReaders()['events'][11]['owners']),
    'vfs uri shm filecontrol lock current source next126 source main selected' => static fn (TestRunner $t) => $t->same('main', $sharedReaders()['events'][12]['next']['current_source']),
    'vfs uri shm filecontrol lock current source next126 persist wal bumps generation' => static fn (TestRunner $t) => $t->same(2, $sharedReaders()['events'][13]['source_generation']),
    'vfs uri shm filecontrol lock current source next126 source shm selected after write' => static fn (TestRunner $t) => $t->same('shm', $sharedReaders()['events'][14]['next']['current_source']),
    'vfs uri shm filecontrol lock current source next126 shm sees stale data version' => static fn (TestRunner $t) => $t->same(true, $sharedReaders()['events'][15]['stale_current_source']),
    'vfs uri shm filecontrol lock current source next126 shm opened generation one' => static fn (TestRunner $t) => $t->same(1, $sharedReaders()['events'][15]['opened_generation']),
    'vfs uri shm filecontrol lock current source next126 shm current generation two' => static fn (TestRunner $t) => $t->same(2, $sharedReaders()['events'][15]['value']),
    'vfs uri shm filecontrol lock current source next126 final current source shm' => static fn (TestRunner $t) => $t->same('shm', $sharedReaders()['next']['current_source']),
    'vfs uri shm filecontrol lock current source next126 final lock count one' => static fn (TestRunner $t) => $t->same(1, $sharedReaders()['next']['shm_lock_count']),
    'vfs uri shm filecontrol lock current source next126 final connection count one' => static fn (TestRunner $t) => $t->same(1, $sharedReaders()['next']['persistent_shm_connection_count']),

    'vfs uri shm filecontrol lock current source next126 close releases connection owners' => static fn (TestRunner $t) => $t->same(true, $reopen()['events'][2]['released_shm_locks']),
    'vfs uri shm filecontrol lock current source next126 reopen does not reuse released lock' => static fn (TestRunner $t) => $t->same(false, $reopen()['events'][3]['reused_shm_locks']),
    'vfs uri shm filecontrol lock current source next126 reopen persistent connection count zero' => static fn (TestRunner $t) => $t->same(0, $reopen()['next']['persistent_shm_connection_count']),

    'vfs uri shm filecontrol lock current source next126 explicit current reuses shm locks' => static fn (TestRunner $t) => $t->same(true, $explicitCurrent()['events'][1]['reused_shm_locks']),
    'vfs uri shm filecontrol lock current source next126 explicit current reuses owners' => static fn (TestRunner $t) => $t->same(['wp-reader'], $explicitCurrent()['events'][1]['next']['handles']['vfs87-2']['shm_lock_owners']['read2']),
    'vfs uri shm filecontrol lock current source next126 explicit current writer blocked' => static fn (TestRunner $t) => $t->same('busy', $explicitCurrent()['events'][2]['status']),
    'vfs uri shm filecontrol lock current source next126 explicit current blocking reader' => static fn (TestRunner $t) => $t->same(['wp-reader'], $explicitCurrent()['events'][2]['blocking_connections']),
    'vfs uri shm filecontrol lock current source next126 explicit current reader can share' => static fn (TestRunner $t) => $t->same('ok', $explicitCurrent()['events'][3]['status']),
    'vfs uri shm filecontrol lock current source next126 explicit current generation reused' => static fn (TestRunner $t) => $t->same(8, $explicitCurrent()['events'][0]['source_generation']),

    'vfs uri shm filecontrol lock current source next126 readonly shared ok' => static fn (TestRunner $t) => $t->same('ok', $readonly()['events'][2]['status']),
    'vfs uri shm filecontrol lock current source next126 readonly exclusive blocked' => static fn (TestRunner $t) => $t->same('blocked', $readonly()['events'][3]['status']),
    'vfs uri shm filecontrol lock current source next126 readonly exclusive reason' => static fn (TestRunner $t) => $t->same('readonly SHM handle cannot take exclusive locks', $readonly()['events'][3]['reason']),
    'vfs uri shm filecontrol lock current source next126 readonly mmap allowed' => static fn (TestRunner $t) => $t->same('ok', $readonly()['events'][4]['status']),
    'vfs uri shm filecontrol lock current source next126 readonly persist wal ignored' => static fn (TestRunner $t) => $t->same('ignored', $readonly()['events'][5]['status']),

    'vfs uri shm filecontrol lock current source next126 nolock blocked' => static fn (TestRunner $t) => $t->same('blocked', $nolock()['events'][2]['status']),
    'vfs uri shm filecontrol lock current source next126 nolock reason' => static fn (TestRunner $t) => $t->same('nolock VFS disables SHM byte-range locking', $nolock()['events'][2]['reason']),

    'vfs uri shm filecontrol lock current source next126 rejects bad connection' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run126(['open(shm)', ['op' => 'shmlock', 'lock' => 'read0', 'mode' => 'shared', 'connection' => '../bad']])),
    'vfs uri shm filecontrol lock current source next126 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsShmFileControlLockCurrentSourcePlan::planUriShmFileControlLocks([])),
];
