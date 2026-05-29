<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsShmFileControlLockCurrentSourcePlan;

$run92 = static fn (array $ops, array $options = []): array => SQLiteVfsShmFileControlLockCurrentSourcePlan::planUriShmFileControl($ops, $options + [
    'filename' => 'file://localhost/srv/www/wp-content/database/site%20cache.sqlite?mode=rw&cache=shared',
]);

$localhost = static fn (): array => $run92([
    'open(main)',
    'file_control(chunk_size, 4096)',
    'open(wal, file://localhost/srv/www/wp-content/database/site%20cache.sqlite-wal?mode=rw&cache=shared)',
    'file_control(mmap_size, 65536)',
    'open(shm, file://localhost/srv/www/wp-content/database/site%20cache.sqlite-shm?mode=rw&cache=shared)',
    'shm_lock(read0, shared)',
    'source(wal)',
    'file_control(persist_wal, on)',
    'source(shm)',
    'shm_lock(write, exclusive)',
]);

$sidecarFirst = static fn (): array => $run92([
    'open(wal, file:/srv/www/wp-content/database/site%20cache.sqlite-wal?mode=rw&cache=shared)',
    'open(main, file:/srv/www/wp-content/database/site%20cache.sqlite?mode=rw&cache=shared)',
    'file_control(reserve_bytes, 24)',
    'open(shm, file:/srv/www/wp-content/database/site%20cache.sqlite-shm?mode=rw&cache=shared)',
    'shm_lock(checkpoint, exclusive)',
]);

$readonly = static fn (): array => $run92([
    'open(main, file://localhost/srv/www/wp-content/database/archive%20copy.sqlite?mode=ro&cache=private)',
    'open(shm, file://localhost/srv/www/wp-content/database/archive%20copy.sqlite-shm?mode=ro&cache=private)',
    'file_control(chunk_size, 8192)',
    'file_control(mmap_size, 32768)',
    'shm_lock(read1, shared)',
    'shm_lock(write, exclusive)',
]);

$nolock = static fn (): array => $run92([
    'open(main, file:/srv/www/wp-content/database/nolock.sqlite?nolock=1&mode=rw)',
    'open(shm, file:/srv/www/wp-content/database/nolock.sqlite-shm?nolock=1&mode=rw)',
    'shm_lock(recover, exclusive)',
    'file_control(lock_timeout, 500)',
]);

$repeated = static fn (): array => $run92([
    'open(main, file:/srv/www/wp-content/database/repeated.sqlite?mode=ro&mode=rw&cache=private&cache=shared)',
    'file_control(powersafe_overwrite, 1)',
    'open(shm, file:/srv/www/wp-content/database/repeated.sqlite-shm?mode=ro&mode=rw&cache=private&cache=shared)',
    'shm_lock(read2, shared)',
    'close(shm)',
    'open(shm, file:/srv/www/wp-content/database/repeated.sqlite-shm?mode=rw&cache=shared)',
]);

$explicitCurrent = static fn (): array => $run92([
    'open(main, file://localhost/srv/www/wp-content/database/site%20cache.sqlite?mode=rw)',
    'open(shm, file://localhost/srv/www/wp-content/database/site%20cache.sqlite-shm?mode=rw)',
], [
    'current' => [
        'persistent_controls' => [
            '/srv/www/wp-content/database/site cache.sqlite' => ['mmap_size' => 1024],
        ],
        'persistent_shm_locks' => [
            '/srv/www/wp-content/database/site cache.sqlite' => ['read3' => 'shared'],
        ],
    ],
]);

return [
    'vfs uri shm filecontrol current source next92 status' => static fn (TestRunner $t) => $t->same('ok', $localhost()['status']),
    'vfs uri shm filecontrol current source next92 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-uri-shm-filecontrol-current-source-next92', $localhost()['dependencies'], true)),
    'vfs uri shm filecontrol current source next92 keeps base dependencies' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-file-control-application', $localhost()['dependencies'], true)),
    'vfs uri shm filecontrol current source next92 localhost main owner decoded' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/site cache.sqlite', $localhost()['events'][0]['owner']),
    'vfs uri shm filecontrol current source next92 localhost main path decoded' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/site cache.sqlite', $localhost()['events'][0]['path']),
    'vfs uri shm filecontrol current source next92 main open source' => static fn (TestRunner $t) => $t->same('main', $localhost()['events'][0]['source']),
    'vfs uri shm filecontrol current source next92 chunk persisted under decoded owner' => static fn (TestRunner $t) => $t->same(4096, $localhost()['events'][1]['next']['persistent_controls']['/srv/www/wp-content/database/site cache.sqlite']['chunk_size']),
    'vfs uri shm filecontrol current source next92 wal owner strips sidecar suffix' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/site cache.sqlite', $localhost()['events'][2]['owner']),
    'vfs uri shm filecontrol current source next92 wal path appends single wal suffix' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/site cache.sqlite-wal', $localhost()['events'][2]['path']),
    'vfs uri shm filecontrol current source next92 wal open switches current source' => static fn (TestRunner $t) => $t->same('wal', $localhost()['events'][2]['next']['current_source']),
    'vfs uri shm filecontrol current source next92 wal current filecontrol routes database' => static fn (TestRunner $t) => $t->same('database', $localhost()['events'][3]['routed_to']),
    'vfs uri shm filecontrol current source next92 wal current mmap lands on main handle' => static fn (TestRunner $t) => $t->same(65536, $localhost()['events'][3]['next']['handles']['vfs87-1']['controls']['mmap_size']),
    'vfs uri shm filecontrol current source next92 shm owner strips sidecar suffix' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/site cache.sqlite', $localhost()['events'][4]['owner']),
    'vfs uri shm filecontrol current source next92 shm path appends single shm suffix' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/site cache.sqlite-shm', $localhost()['events'][4]['path']),
    'vfs uri shm filecontrol current source next92 shm locks do not reuse initially' => static fn (TestRunner $t) => $t->same(false, $localhost()['events'][4]['reused_shm_locks']),
    'vfs uri shm filecontrol current source next92 shared read lock stored on shm' => static fn (TestRunner $t) => $t->same('shared', $localhost()['events'][5]['next']['handles']['vfs87-3']['shm_locks']['read0']),
    'vfs uri shm filecontrol current source next92 source wal status ok' => static fn (TestRunner $t) => $t->same('ok', $localhost()['events'][6]['status']),
    'vfs uri shm filecontrol current source next92 persist wal routes from wal to database' => static fn (TestRunner $t) => $t->same('database', $localhost()['events'][7]['routed_to']),
    'vfs uri shm filecontrol current source next92 persist wal stored on main' => static fn (TestRunner $t) => $t->same(true, $localhost()['events'][7]['next']['handles']['vfs87-1']['controls']['persist_wal']),
    'vfs uri shm filecontrol current source next92 source shm status ok' => static fn (TestRunner $t) => $t->same('ok', $localhost()['events'][8]['status']),
    'vfs uri shm filecontrol current source next92 write lock stored on shm' => static fn (TestRunner $t) => $t->same('exclusive', $localhost()['events'][9]['next']['handles']['vfs87-3']['shm_locks']['write']),
    'vfs uri shm filecontrol current source next92 final open source counts' => static fn (TestRunner $t) => $t->same(['main' => 1, 'wal' => 1, 'shm' => 1], $localhost()['next']['open_by_source']),
    'vfs uri shm filecontrol current source next92 final current source shm' => static fn (TestRunner $t) => $t->same('shm', $localhost()['next']['current_source']),
    'vfs uri shm filecontrol current source next92 final shm lock count' => static fn (TestRunner $t) => $t->same(2, $localhost()['next']['shm_lock_count']),
    'vfs uri shm filecontrol current source next92 final one persistent control owner' => static fn (TestRunner $t) => $t->same(1, $localhost()['next']['persistent_control_count']),

    'vfs uri shm filecontrol current source next92 sidecar first wal owner canonical' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/site cache.sqlite', $sidecarFirst()['events'][0]['owner']),
    'vfs uri shm filecontrol current source next92 sidecar first main owner matches wal' => static fn (TestRunner $t) => $t->same($sidecarFirst()['events'][0]['owner'], $sidecarFirst()['events'][1]['owner']),
    'vfs uri shm filecontrol current source next92 sidecar first reserve status ok' => static fn (TestRunner $t) => $t->same('ok', $sidecarFirst()['events'][2]['status']),
    'vfs uri shm filecontrol current source next92 sidecar first reserve persisted decoded owner' => static fn (TestRunner $t) => $t->same(24, $sidecarFirst()['events'][2]['next']['persistent_controls']['/srv/www/wp-content/database/site cache.sqlite']['reserve_bytes']),
    'vfs uri shm filecontrol current source next92 sidecar first shm owner matches main' => static fn (TestRunner $t) => $t->same($sidecarFirst()['events'][1]['owner'], $sidecarFirst()['events'][3]['owner']),
    'vfs uri shm filecontrol current source next92 sidecar first checkpoint exclusive' => static fn (TestRunner $t) => $t->same('exclusive', $sidecarFirst()['events'][4]['next']['handles']['vfs87-3']['shm_locks']['checkpoint']),

    'vfs uri shm filecontrol current source next92 readonly owner decoded' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/archive copy.sqlite', $readonly()['events'][0]['owner']),
    'vfs uri shm filecontrol current source next92 readonly chunk ignored from shm source' => static fn (TestRunner $t) => $t->same('ignored', $readonly()['events'][2]['status']),
    'vfs uri shm filecontrol current source next92 readonly chunk not persisted' => static fn (TestRunner $t) => $t->same(false, array_key_exists('/srv/www/wp-content/database/archive copy.sqlite', $readonly()['events'][2]['next']['persistent_controls'])),
    'vfs uri shm filecontrol current source next92 readonly mmap allowed' => static fn (TestRunner $t) => $t->same('ok', $readonly()['events'][3]['status']),
    'vfs uri shm filecontrol current source next92 readonly mmap persisted decoded owner' => static fn (TestRunner $t) => $t->same(32768, $readonly()['events'][3]['next']['persistent_controls']['/srv/www/wp-content/database/archive copy.sqlite']['mmap_size']),
    'vfs uri shm filecontrol current source next92 readonly shared lock ok' => static fn (TestRunner $t) => $t->same('ok', $readonly()['events'][4]['status']),
    'vfs uri shm filecontrol current source next92 readonly exclusive blocked' => static fn (TestRunner $t) => $t->same('blocked', $readonly()['events'][5]['status']),
    'vfs uri shm filecontrol current source next92 readonly exclusive reason' => static fn (TestRunner $t) => $t->same('readonly SHM handle cannot take exclusive locks', $readonly()['events'][5]['reason']),

    'vfs uri shm filecontrol current source next92 nolock exclusive blocked' => static fn (TestRunner $t) => $t->same('blocked', $nolock()['events'][2]['status']),
    'vfs uri shm filecontrol current source next92 nolock reason' => static fn (TestRunner $t) => $t->same('nolock VFS disables SHM byte-range locking', $nolock()['events'][2]['reason']),
    'vfs uri shm filecontrol current source next92 nolock filecontrol records on main' => static fn (TestRunner $t) => $t->same(500, $nolock()['events'][3]['next']['handles']['vfs87-1']['controls']['lock_timeout']),
    'vfs uri shm filecontrol current source next92 nolock no shm locks' => static fn (TestRunner $t) => $t->same(0, $nolock()['next']['shm_lock_count']),

    'vfs uri shm filecontrol current source next92 repeated mode final rw not readonly' => static fn (TestRunner $t) => $t->same(false, $repeated()['events'][0]['next']['handles']['vfs87-1']['readonly']),
    'vfs uri shm filecontrol current source next92 repeated powersafe accepted' => static fn (TestRunner $t) => $t->same('ok', $repeated()['events'][1]['status']),
    'vfs uri shm filecontrol current source next92 repeated powersafe persisted' => static fn (TestRunner $t) => $t->same(true, $repeated()['events'][1]['next']['persistent_controls']['/srv/www/wp-content/database/repeated.sqlite']['powersafe_overwrite']),
    'vfs uri shm filecontrol current source next92 repeated shm read lock stored' => static fn (TestRunner $t) => $t->same('shared', $repeated()['events'][3]['next']['handles']['vfs87-2']['shm_locks']['read2']),
    'vfs uri shm filecontrol current source next92 close shm releases decoded owner locks' => static fn (TestRunner $t) => $t->same(true, $repeated()['events'][4]['released_shm_locks']),
    'vfs uri shm filecontrol current source next92 reopen shm no stale lock' => static fn (TestRunner $t) => $t->same(false, $repeated()['events'][5]['reused_shm_locks']),

    'vfs uri shm filecontrol current source next92 explicit current reuses controls after uri decode' => static fn (TestRunner $t) => $t->same(true, $explicitCurrent()['events'][0]['reused_controls']),
    'vfs uri shm filecontrol current source next92 explicit current control value' => static fn (TestRunner $t) => $t->same(1024, $explicitCurrent()['events'][0]['next']['handles']['vfs87-1']['controls']['mmap_size']),
    'vfs uri shm filecontrol current source next92 explicit current reuses shm locks after suffix strip' => static fn (TestRunner $t) => $t->same(true, $explicitCurrent()['events'][1]['reused_shm_locks']),
    'vfs uri shm filecontrol current source next92 explicit current shm lock value' => static fn (TestRunner $t) => $t->same('shared', $explicitCurrent()['events'][1]['next']['handles']['vfs87-2']['shm_locks']['read3']),

    'vfs uri shm filecontrol current source next92 rejects remote authority' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run92(['open(main, file://example.com/srv/www/wp.sqlite?mode=rw)'])),
    'vfs uri shm filecontrol current source next92 rejects malformed percent path' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run92(['open(main, file:/srv/www/wp%ZZ.sqlite?mode=rw)'])),
    'vfs uri shm filecontrol current source next92 rejects invalid mode' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run92(['open(main, file:/srv/www/wp.sqlite?mode=delete)'])),
    'vfs uri shm filecontrol current source next92 rejects invalid cache' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run92(['open(main, file:/srv/www/wp.sqlite?cache=global)'])),
];
