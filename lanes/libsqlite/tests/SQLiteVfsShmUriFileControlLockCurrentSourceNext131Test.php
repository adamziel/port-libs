<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsShmFileControlLockCurrentSourcePlan;

$run131 = static fn (array $ops, array $options = []): array => SQLiteVfsShmFileControlLockCurrentSourcePlan::planShmUriFileControlLocks($ops, $options + [
    'filename' => 'file://localhost/srv/www/wp-content/database/wp%20range.sqlite?mode=rw&cache=shared',
]);

$range = static function () use ($run131): array {
    static $result = null;
    if ($result === null) {
        $result = $run131([
            'open(main, file://localhost/srv/www/wp-content/database/wp%20range.sqlite?mode=rw&cache=shared&role=writer)',
            'open(shm, file://localhost/srv/www/wp-content/database/wp%20range.sqlite-shm?mode=rw&cache=shared&role=reader)',
            ['op' => 'shmlock', 'lock' => 'read0', 'span' => 3, 'mode' => 'shared', 'connection' => 'wp-admin'],
            ['op' => 'shmlock', 'lock' => 'read2', 'span' => 2, 'mode' => 'exclusive', 'connection' => 'wp-cron'],
            ['op' => 'shmlock', 'lock' => 'read1', 'span' => 2, 'mode' => 'unlock', 'connection' => 'wp-admin'],
            ['op' => 'shmlock', 'lock' => 'read2', 'span' => 2, 'mode' => 'exclusive', 'connection' => 'wp-cron'],
            ['op' => 'shmlock', 'lock' => 'read0', 'span' => 1, 'mode' => 'unlock', 'connection' => 'wp-admin'],
            ['op' => 'shmlock', 'lock' => 'read2', 'span' => 2, 'mode' => 'unlock', 'connection' => 'wp-cron'],
            ['op' => 'shmlock', 'lock' => 'checkpoint', 'span' => 3, 'mode' => 'exclusive', 'connection' => 'wp-checkpoint'],
            'source(main)',
            'file_control(persist_wal, on)',
            'source(shm)',
            'file_control(data_version)',
        ]);
    }

    return $result;
};

$sharedRange = static fn (): array => $run131([
    'open(shm, file:/srv/www/wp-content/database/shared.sqlite-shm?mode=rw)',
    ['op' => 'shmlock', 'lock' => 'read1', 'span' => 2, 'mode' => 'shared', 'connection' => 'wp-reader-a'],
    ['op' => 'shmlock', 'lock' => 'read2', 'span' => 2, 'mode' => 'shared', 'connection' => 'wp-reader-b'],
]);

$closeReopen = static fn (): array => $run131([
    'open(shm, file:/srv/www/wp-content/database/reopen-range.sqlite-shm?mode=rw)',
    ['op' => 'shmlock', 'lock' => 'read0', 'span' => 5, 'mode' => 'shared', 'connection' => 'wp-reader'],
    'close(shm)',
    'open(shm, file:/srv/www/wp-content/database/reopen-range.sqlite-shm?mode=rw)',
]);

$readonly = static fn (): array => $run131([
    'open(main, file:/srv/www/wp-content/database/archive-range.sqlite?mode=ro)',
    'open(shm, file:/srv/www/wp-content/database/archive-range.sqlite-shm?mode=ro)',
    ['op' => 'shmlock', 'lock' => 'read0', 'span' => 2, 'mode' => 'shared', 'connection' => 'wp-reader'],
    ['op' => 'shmlock', 'lock' => 'read0', 'span' => 2, 'mode' => 'exclusive', 'connection' => 'wp-reader'],
    'file_control(mmap_size, 8192)',
    'file_control(persist_wal, on)',
]);

$nolock = static fn (): array => $run131([
    'open(shm, file:/srv/www/wp-content/database/nolock-range.sqlite-shm?mode=rw&nolock=1)',
    ['op' => 'shmlock', 'lock' => 'read0', 'span' => 2, 'mode' => 'shared', 'connection' => 'wp-reader'],
]);

return [
    'vfs shm uri filecontrol lock current source next131 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-shm-uri-filecontrol-lock-current-source-next131', $range()['dependencies'], true)),
    'vfs shm uri filecontrol lock current source next131 final status ok' => static fn (TestRunner $t) => $t->same('ok', $range()['status']),
    'vfs shm uri filecontrol lock current source next131 main owner decoded' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp range.sqlite', $range()['events'][0]['owner']),
    'vfs shm uri filecontrol lock current source next131 shm path decoded' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp range.sqlite-shm', $range()['events'][1]['path']),
    'vfs shm uri filecontrol lock current source next131 read range status ok' => static fn (TestRunner $t) => $t->same('ok', $range()['events'][2]['status']),
    'vfs shm uri filecontrol lock current source next131 read range locks listed' => static fn (TestRunner $t) => $t->same(['read0', 'read1', 'read2'], $range()['events'][2]['locks']),
    'vfs shm uri filecontrol lock current source next131 read range span three' => static fn (TestRunner $t) => $t->same(3, $range()['events'][2]['span']),
    'vfs shm uri filecontrol lock current source next131 read0 shared' => static fn (TestRunner $t) => $t->same('shared', $range()['events'][2]['next']['handles']['vfs87-2']['shm_locks']['read0']),
    'vfs shm uri filecontrol lock current source next131 read1 shared' => static fn (TestRunner $t) => $t->same('shared', $range()['events'][2]['next']['handles']['vfs87-2']['shm_locks']['read1']),
    'vfs shm uri filecontrol lock current source next131 read2 shared' => static fn (TestRunner $t) => $t->same('shared', $range()['events'][2]['next']['handles']['vfs87-2']['shm_locks']['read2']),
    'vfs shm uri filecontrol lock current source next131 read0 owner stored' => static fn (TestRunner $t) => $t->same(['wp-admin'], $range()['events'][2]['owner_locks']['read0']),
    'vfs shm uri filecontrol lock current source next131 read2 owner stored' => static fn (TestRunner $t) => $t->same(['wp-admin'], $range()['events'][2]['owner_locks']['read2']),
    'vfs shm uri filecontrol lock current source next131 conflict status busy' => static fn (TestRunner $t) => $t->same('busy', $range()['events'][3]['status']),
    'vfs shm uri filecontrol lock current source next131 conflict locks listed' => static fn (TestRunner $t) => $t->same(['read2', 'read3'], $range()['events'][3]['locks']),
    'vfs shm uri filecontrol lock current source next131 conflict blocking read2' => static fn (TestRunner $t) => $t->same(['wp-admin'], $range()['events'][3]['blocking_locks']['read2']),
    'vfs shm uri filecontrol lock current source next131 conflict preserves read3 empty' => static fn (TestRunner $t) => $t->same(false, array_key_exists('read3', $range()['events'][3]['next']['handles']['vfs87-2']['shm_locks'])),
    'vfs shm uri filecontrol lock current source next131 conflict no partial owner' => static fn (TestRunner $t) => $t->same(false, array_key_exists('read3', $range()['events'][3]['next']['handles']['vfs87-2']['shm_lock_owners'])),
    'vfs shm uri filecontrol lock current source next131 partial unlock ok' => static fn (TestRunner $t) => $t->same('ok', $range()['events'][4]['status']),
    'vfs shm uri filecontrol lock current source next131 partial unlock changed locks' => static fn (TestRunner $t) => $t->same(['read1', 'read2'], $range()['events'][4]['changed_locks']),
    'vfs shm uri filecontrol lock current source next131 partial unlock keeps read0' => static fn (TestRunner $t) => $t->same('shared', $range()['events'][4]['next']['handles']['vfs87-2']['shm_locks']['read0']),
    'vfs shm uri filecontrol lock current source next131 exclusive range after partial unlock ok' => static fn (TestRunner $t) => $t->same('ok', $range()['events'][5]['status']),
    'vfs shm uri filecontrol lock current source next131 exclusive read2 stored' => static fn (TestRunner $t) => $t->same('exclusive', $range()['events'][5]['next']['handles']['vfs87-2']['shm_locks']['read2']),
    'vfs shm uri filecontrol lock current source next131 exclusive read3 stored' => static fn (TestRunner $t) => $t->same('exclusive', $range()['events'][5]['next']['handles']['vfs87-2']['shm_locks']['read3']),
    'vfs shm uri filecontrol lock current source next131 exclusive owners stored' => static fn (TestRunner $t) => $t->same(['wp-cron'], $range()['events'][5]['owner_locks']['read3']),
    'vfs shm uri filecontrol lock current source next131 unlock read0 ok' => static fn (TestRunner $t) => $t->same('ok', $range()['events'][6]['status']),
    'vfs shm uri filecontrol lock current source next131 read0 cleared' => static fn (TestRunner $t) => $t->same(false, array_key_exists('read0', $range()['events'][6]['next']['handles']['vfs87-2']['shm_locks'])),
    'vfs shm uri filecontrol lock current source next131 unlock exclusive range ok' => static fn (TestRunner $t) => $t->same('ok', $range()['events'][7]['status']),
    'vfs shm uri filecontrol lock current source next131 read2 cleared' => static fn (TestRunner $t) => $t->same(false, array_key_exists('read2', $range()['events'][7]['next']['handles']['vfs87-2']['shm_locks'])),
    'vfs shm uri filecontrol lock current source next131 read3 cleared' => static fn (TestRunner $t) => $t->same(false, array_key_exists('read3', $range()['events'][7]['next']['handles']['vfs87-2']['shm_locks'])),
    'vfs shm uri filecontrol lock current source next131 checkpoint range ok' => static fn (TestRunner $t) => $t->same('ok', $range()['events'][8]['status']),
    'vfs shm uri filecontrol lock current source next131 checkpoint range locks' => static fn (TestRunner $t) => $t->same(['checkpoint', 'recover', 'write'], $range()['events'][8]['locks']),
    'vfs shm uri filecontrol lock current source next131 checkpoint owner stored' => static fn (TestRunner $t) => $t->same(['wp-checkpoint'], $range()['events'][8]['owner_locks']['checkpoint']),
    'vfs shm uri filecontrol lock current source next131 recover owner stored' => static fn (TestRunner $t) => $t->same(['wp-checkpoint'], $range()['events'][8]['owner_locks']['recover']),
    'vfs shm uri filecontrol lock current source next131 write owner stored' => static fn (TestRunner $t) => $t->same(['wp-checkpoint'], $range()['events'][8]['owner_locks']['write']),
    'vfs shm uri filecontrol lock current source next131 source main selected' => static fn (TestRunner $t) => $t->same('main', $range()['events'][9]['next']['current_source']),
    'vfs shm uri filecontrol lock current source next131 persist wal generation bump' => static fn (TestRunner $t) => $t->same(2, $range()['events'][10]['source_generation']),
    'vfs shm uri filecontrol lock current source next131 source shm selected' => static fn (TestRunner $t) => $t->same('shm', $range()['events'][11]['next']['current_source']),
    'vfs shm uri filecontrol lock current source next131 stale shm data version true' => static fn (TestRunner $t) => $t->same(true, $range()['events'][12]['stale_current_source']),
    'vfs shm uri filecontrol lock current source next131 stale shm opened one' => static fn (TestRunner $t) => $t->same(1, $range()['events'][12]['opened_generation']),
    'vfs shm uri filecontrol lock current source next131 stale shm current two' => static fn (TestRunner $t) => $t->same(2, $range()['events'][12]['value']),
    'vfs shm uri filecontrol lock current source next131 final lock count three' => static fn (TestRunner $t) => $t->same(3, $range()['next']['shm_lock_count']),
    'vfs shm uri filecontrol lock current source next131 final connection count one' => static fn (TestRunner $t) => $t->same(1, $range()['next']['persistent_shm_connection_count']),

    'vfs shm uri filecontrol lock current source next131 overlapping shared range ok' => static fn (TestRunner $t) => $t->same('ok', $sharedRange()['events'][2]['status']),
    'vfs shm uri filecontrol lock current source next131 overlapping shared read2 owners' => static fn (TestRunner $t) => $t->same(['wp-reader-a', 'wp-reader-b'], $sharedRange()['events'][2]['owner_locks']['read2']),
    'vfs shm uri filecontrol lock current source next131 overlapping shared read3 owner' => static fn (TestRunner $t) => $t->same(['wp-reader-b'], $sharedRange()['events'][2]['owner_locks']['read3']),
    'vfs shm uri filecontrol lock current source next131 overlapping shared connection count' => static fn (TestRunner $t) => $t->same(2, $sharedRange()['next']['persistent_shm_connection_count']),

    'vfs shm uri filecontrol lock current source next131 close releases range locks' => static fn (TestRunner $t) => $t->same(true, $closeReopen()['events'][2]['released_shm_locks']),
    'vfs shm uri filecontrol lock current source next131 reopen does not reuse range locks' => static fn (TestRunner $t) => $t->same(false, $closeReopen()['events'][3]['reused_shm_locks']),
    'vfs shm uri filecontrol lock current source next131 reopen connection count zero' => static fn (TestRunner $t) => $t->same(0, $closeReopen()['next']['persistent_shm_connection_count']),

    'vfs shm uri filecontrol lock current source next131 readonly shared range ok' => static fn (TestRunner $t) => $t->same('ok', $readonly()['events'][2]['status']),
    'vfs shm uri filecontrol lock current source next131 readonly exclusive range blocked' => static fn (TestRunner $t) => $t->same('blocked', $readonly()['events'][3]['status']),
    'vfs shm uri filecontrol lock current source next131 readonly exclusive range reason' => static fn (TestRunner $t) => $t->same('readonly SHM handle cannot take exclusive locks', $readonly()['events'][3]['reason']),
    'vfs shm uri filecontrol lock current source next131 readonly mmap allowed' => static fn (TestRunner $t) => $t->same('ok', $readonly()['events'][4]['status']),
    'vfs shm uri filecontrol lock current source next131 readonly persist ignored' => static fn (TestRunner $t) => $t->same('ignored', $readonly()['events'][5]['status']),

    'vfs shm uri filecontrol lock current source next131 nolock range blocked' => static fn (TestRunner $t) => $t->same('blocked', $nolock()['events'][1]['status']),
    'vfs shm uri filecontrol lock current source next131 nolock range locks listed' => static fn (TestRunner $t) => $t->same(['read0', 'read1'], $nolock()['events'][1]['locks']),
    'vfs shm uri filecontrol lock current source next131 nolock reason' => static fn (TestRunner $t) => $t->same('nolock VFS disables SHM byte-range locking', $nolock()['events'][1]['reason']),

    'vfs shm uri filecontrol lock current source next131 string range syntax ok' => static fn (TestRunner $t) => $t->same(['read0', 'read1'], $run131(['open(shm)', 'shm_lock_range(read0, 2, shared)'])['events'][1]['locks']),
    'vfs shm uri filecontrol lock current source next131 rejects zero span' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run131(['open(shm)', ['op' => 'shmlock', 'lock' => 'read0', 'span' => 0, 'mode' => 'shared']])),
    'vfs shm uri filecontrol lock current source next131 rejects overflow span' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run131(['open(shm)', ['op' => 'shmlock', 'lock' => 'read3', 'span' => 3, 'mode' => 'shared']])),
    'vfs shm uri filecontrol lock current source next131 rejects bad connection' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run131(['open(shm)', ['op' => 'shmlock', 'lock' => 'read0', 'span' => 2, 'mode' => 'shared', 'connection' => '../bad']])),
    'vfs shm uri filecontrol lock current source next131 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsShmFileControlLockCurrentSourcePlan::planShmUriFileControlLocks([])),
];
