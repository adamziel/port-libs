<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsShmFileControlLockCurrentSourcePlan;

$run87 = static fn (array $ops, array $options = []): array => SQLiteVfsShmFileControlLockCurrentSourcePlan::planShmFileControlLock($ops, $options + [
    'filename' => 'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared',
]);

$mixed = static fn (): array => $run87([
    'open(main)',
    'file_control(chunk_size, 8192)',
    'open(shm)',
    'file_control(mmap_size, 65536)',
    'shm_lock(read1, shared)',
    'shm_lock(write, exclusive)',
    'source(main)',
    'file_control(persist_wal, on)',
    'source(shm)',
    'shm_lock(read1, unlock)',
    'close(shm)',
    'open(shm)',
]);

$readonly = static fn (): array => $run87([
    ['op' => 'open', 'source' => 'main', 'filename' => 'file:/srv/www/wp-content/database/archive.sqlite?mode=ro', 'readonly' => true],
    ['op' => 'open', 'source' => 'shm', 'filename' => 'file:/srv/www/wp-content/database/archive.sqlite?mode=ro', 'readonly' => true],
    ['op' => 'filecontrol', 'source' => 'shm', 'control' => 'chunk_size', 'value' => 16384],
    ['op' => 'filecontrol', 'source' => 'shm', 'control' => 'mmap_size', 'value' => 32768],
    ['op' => 'shmlock', 'source' => 'shm', 'lock' => 'read0', 'mode' => 'shared'],
    ['op' => 'shmlock', 'source' => 'shm', 'lock' => 'write', 'mode' => 'exclusive'],
]);

$nolock = static fn (): array => $run87([
    ['op' => 'open', 'source' => 'main', 'filename' => 'file:/srv/www/wp-content/database/.ht.sqlite?nolock=1', 'nolock' => true],
    ['op' => 'open', 'source' => 'shm', 'filename' => 'file:/srv/www/wp-content/database/.ht.sqlite?nolock=1', 'nolock' => true],
    ['op' => 'shmlock', 'lock' => 'recover', 'mode' => 'exclusive'],
    ['op' => 'filecontrol', 'control' => 'lock_timeout', 'value' => 250],
]);

$busy = static fn (): array => $run87([
    'open(main)',
    'open(shm)',
    'shm_lock(read2, shared)',
    'shm_lock(read2, exclusive)',
    'shm_lock(read2, unlock)',
    'shm_lock(read2, exclusive)',
]);

$walSource = static fn (): array => $run87([
    'open(main)',
    'open(wal)',
    'file_control(reserve_bytes, 32)',
    'open(shm)',
    ['op' => 'filecontrol', 'source' => 'wal', 'control' => 'powersafe_overwrite', 'value' => 'on'],
    ['op' => 'shmlock', 'source' => 'wal', 'lock' => 'checkpoint', 'mode' => 'exclusive'],
]);

return [
    'vfs shm filecontrol lock current source next87 status' => static fn (TestRunner $t) => $t->same('shm-open', $mixed()['status']),
    'vfs shm filecontrol lock current source next87 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-shm-filecontrol-lock-current-source-next87', $mixed()['dependencies'], true)),
    'vfs shm filecontrol lock current source next87 main open source' => static fn (TestRunner $t) => $t->same('main', $mixed()['events'][0]['source']),
    'vfs shm filecontrol lock current source next87 main path canonicalized' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/.ht.sqlite', $mixed()['events'][0]['path']),
    'vfs shm filecontrol lock current source next87 main owner path' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/.ht.sqlite', $mixed()['events'][0]['owner']),
    'vfs shm filecontrol lock current source next87 chunk routes database' => static fn (TestRunner $t) => $t->same('database', $mixed()['events'][1]['routed_to']),
    'vfs shm filecontrol lock current source next87 chunk persisted owner' => static fn (TestRunner $t) => $t->same(8192, $mixed()['events'][1]['next']['persistent_controls']['/srv/www/wp-content/database/.ht.sqlite']['chunk_size']),
    'vfs shm filecontrol lock current source next87 shm open source' => static fn (TestRunner $t) => $t->same('shm', $mixed()['events'][2]['source']),
    'vfs shm filecontrol lock current source next87 shm path suffix' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/.ht.sqlite-shm', $mixed()['events'][2]['path']),
    'vfs shm filecontrol lock current source next87 current source switches shm' => static fn (TestRunner $t) => $t->same('shm', $mixed()['events'][2]['next']['current_source']),
    'vfs shm filecontrol lock current source next87 unqualified control while shm routes database' => static fn (TestRunner $t) => $t->same('database', $mixed()['events'][3]['routed_to']),
    'vfs shm filecontrol lock current source next87 mmap lands on main handle' => static fn (TestRunner $t) => $t->same(65536, $mixed()['events'][3]['next']['handles']['vfs87-1']['controls']['mmap_size']),
    'vfs shm filecontrol lock current source next87 mmap not on shm handle' => static fn (TestRunner $t) => $t->same([], $mixed()['events'][3]['next']['handles']['vfs87-2']['controls']),
    'vfs shm filecontrol lock current source next87 read lock status' => static fn (TestRunner $t) => $t->same('ok', $mixed()['events'][4]['status']),
    'vfs shm filecontrol lock current source next87 read lock shared' => static fn (TestRunner $t) => $t->same('shared', $mixed()['events'][4]['next']['handles']['vfs87-2']['shm_locks']['read1']),
    'vfs shm filecontrol lock current source next87 write lock exclusive' => static fn (TestRunner $t) => $t->same('exclusive', $mixed()['events'][5]['next']['handles']['vfs87-2']['shm_locks']['write']),
    'vfs shm filecontrol lock current source next87 main source event ok' => static fn (TestRunner $t) => $t->same('ok', $mixed()['events'][6]['status']),
    'vfs shm filecontrol lock current source next87 current source is main after switch' => static fn (TestRunner $t) => $t->same('main', $mixed()['events'][6]['next']['current_source']),
    'vfs shm filecontrol lock current source next87 persist wal on main after source switch' => static fn (TestRunner $t) => $t->same(true, $mixed()['events'][7]['next']['handles']['vfs87-1']['controls']['persist_wal']),
    'vfs shm filecontrol lock current source next87 shm source event ok' => static fn (TestRunner $t) => $t->same('ok', $mixed()['events'][8]['status']),
    'vfs shm filecontrol lock current source next87 unlock removes read lock' => static fn (TestRunner $t) => $t->same(false, array_key_exists('read1', $mixed()['events'][9]['next']['handles']['vfs87-2']['shm_locks'])),
    'vfs shm filecontrol lock current source next87 write lock remains after read unlock' => static fn (TestRunner $t) => $t->same('exclusive', $mixed()['events'][9]['next']['handles']['vfs87-2']['shm_locks']['write']),
    'vfs shm filecontrol lock current source next87 close shm releases locks' => static fn (TestRunner $t) => $t->same(true, $mixed()['events'][10]['released_shm_locks']),
    'vfs shm filecontrol lock current source next87 close shm leaves main handle' => static fn (TestRunner $t) => $t->same(1, $mixed()['events'][10]['next']['source_handles']['main'] === 'vfs87-1' ? 1 : 0),
    'vfs shm filecontrol lock current source next87 close shm falls back main source' => static fn (TestRunner $t) => $t->same('main', $mixed()['events'][10]['next']['current_source']),
    'vfs shm filecontrol lock current source next87 reopen shm no reused locks' => static fn (TestRunner $t) => $t->same(false, $mixed()['events'][11]['reused_shm_locks']),
    'vfs shm filecontrol lock current source next87 reopen shm owner preserved' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/.ht.sqlite', $mixed()['events'][11]['owner']),
    'vfs shm filecontrol lock current source next87 final main and shm open' => static fn (TestRunner $t) => $t->same(['main' => 1, 'wal' => 0, 'shm' => 1], $mixed()['next']['open_by_source']),
    'vfs shm filecontrol lock current source next87 final persistent control count' => static fn (TestRunner $t) => $t->same(1, $mixed()['next']['persistent_control_count']),
    'vfs shm filecontrol lock current source next87 final shm lock count zero' => static fn (TestRunner $t) => $t->same(0, $mixed()['next']['shm_lock_count']),

    'vfs shm filecontrol lock current source next87 readonly chunk ignored' => static fn (TestRunner $t) => $t->same('ignored', $readonly()['events'][2]['status']),
    'vfs shm filecontrol lock current source next87 readonly mmap allowed' => static fn (TestRunner $t) => $t->same('ok', $readonly()['events'][3]['status']),
    'vfs shm filecontrol lock current source next87 readonly mmap persisted' => static fn (TestRunner $t) => $t->same(32768, $readonly()['events'][3]['next']['persistent_controls']['/srv/www/wp-content/database/archive.sqlite']['mmap_size']),
    'vfs shm filecontrol lock current source next87 readonly shared read lock ok' => static fn (TestRunner $t) => $t->same('ok', $readonly()['events'][4]['status']),
    'vfs shm filecontrol lock current source next87 readonly exclusive blocked' => static fn (TestRunner $t) => $t->same('blocked', $readonly()['events'][5]['status']),
    'vfs shm filecontrol lock current source next87 readonly exclusive reason' => static fn (TestRunner $t) => $t->same('readonly SHM handle cannot take exclusive locks', $readonly()['events'][5]['reason']),

    'vfs shm filecontrol lock current source next87 nolock shm blocked' => static fn (TestRunner $t) => $t->same('blocked', $nolock()['events'][2]['status']),
    'vfs shm filecontrol lock current source next87 nolock reason' => static fn (TestRunner $t) => $t->same('nolock VFS disables SHM byte-range locking', $nolock()['events'][2]['reason']),
    'vfs shm filecontrol lock current source next87 nolock filecontrol still records' => static fn (TestRunner $t) => $t->same(250, $nolock()['events'][3]['next']['handles']['vfs87-1']['controls']['lock_timeout']),
    'vfs shm filecontrol lock current source next87 nolock no shm lock count' => static fn (TestRunner $t) => $t->same(0, $nolock()['next']['shm_lock_count']),

    'vfs shm filecontrol lock current source next87 busy upgrade status' => static fn (TestRunner $t) => $t->same('busy', $busy()['events'][3]['status']),
    'vfs shm filecontrol lock current source next87 busy upgrade previous shared' => static fn (TestRunner $t) => $t->same('shared', $busy()['events'][3]['previous']),
    'vfs shm filecontrol lock current source next87 busy preserves shared lock' => static fn (TestRunner $t) => $t->same('shared', $busy()['events'][3]['next']['handles']['vfs87-2']['shm_locks']['read2']),
    'vfs shm filecontrol lock current source next87 unlock after busy ok' => static fn (TestRunner $t) => $t->same('ok', $busy()['events'][4]['status']),
    'vfs shm filecontrol lock current source next87 exclusive after unlock ok' => static fn (TestRunner $t) => $t->same('ok', $busy()['events'][5]['status']),
    'vfs shm filecontrol lock current source next87 exclusive after unlock stored' => static fn (TestRunner $t) => $t->same('exclusive', $busy()['events'][5]['next']['handles']['vfs87-2']['shm_locks']['read2']),

    'vfs shm filecontrol lock current source next87 wal source filecontrol routes database' => static fn (TestRunner $t) => $t->same('database', $walSource()['events'][2]['routed_to']),
    'vfs shm filecontrol lock current source next87 wal source reserve stored main' => static fn (TestRunner $t) => $t->same(32, $walSource()['events'][2]['next']['handles']['vfs87-1']['controls']['reserve_bytes']),
    'vfs shm filecontrol lock current source next87 explicit wal source routes database' => static fn (TestRunner $t) => $t->same('database', $walSource()['events'][4]['routed_to']),
    'vfs shm filecontrol lock current source next87 explicit wal powersafe boolean' => static fn (TestRunner $t) => $t->same(true, $walSource()['events'][4]['value']),
    'vfs shm filecontrol lock current source next87 wal shm lock missing' => static fn (TestRunner $t) => $t->same('missing-handle', $walSource()['events'][5]['status']),

    'vfs shm filecontrol lock current source next87 explicit current reuses controls' => static fn (TestRunner $t) => $t->same(true, $run87(['open(main)'], ['current' => ['persistent_controls' => ['/srv/www/wp-content/database/.ht.sqlite' => ['mmap_size' => 1024]]]])['events'][0]['reused_controls']),
    'vfs shm filecontrol lock current source next87 explicit current control value' => static fn (TestRunner $t) => $t->same(1024, $run87(['open(main)'], ['current' => ['persistent_controls' => ['/srv/www/wp-content/database/.ht.sqlite' => ['mmap_size' => 1024]]]])['events'][0]['next']['handles']['vfs87-1']['controls']['mmap_size']),
    'vfs shm filecontrol lock current source next87 explicit current reuses shm locks' => static fn (TestRunner $t) => $t->same(true, $run87(['open(shm)'], ['current' => ['persistent_shm_locks' => ['/srv/www/wp-content/database/.ht.sqlite' => ['read0' => 'shared']]]])['events'][0]['reused_shm_locks']),
    'vfs shm filecontrol lock current source next87 explicit current shm lock value' => static fn (TestRunner $t) => $t->same('shared', $run87(['open(shm)'], ['current' => ['persistent_shm_locks' => ['/srv/www/wp-content/database/.ht.sqlite' => ['read0' => 'shared']]]])['events'][0]['next']['handles']['vfs87-1']['shm_locks']['read0']),

    'vfs shm filecontrol lock current source next87 missing source switch' => static fn (TestRunner $t) => $t->same('missing-handle', $run87(['source(shm)'])['status']),
    'vfs shm filecontrol lock current source next87 missing filecontrol without main' => static fn (TestRunner $t) => $t->same('missing-handle', $run87([['op' => 'filecontrol', 'source' => 'shm', 'control' => 'mmap_size', 'value' => 1]])['status']),
    'vfs shm filecontrol lock current source next87 missing shm lock before open' => static fn (TestRunner $t) => $t->same('missing-handle', $run87([['op' => 'shmlock', 'lock' => 'read0', 'mode' => 'shared']])['status']),
    'vfs shm filecontrol lock current source next87 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsShmFileControlLockCurrentSourcePlan::planShmFileControlLock([])),
    'vfs shm filecontrol lock current source next87 rejects bad source' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run87([['op' => 'open', 'source' => 'temp']])),
    'vfs shm filecontrol lock current source next87 rejects bad operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run87(['checkpoint'])),
    'vfs shm filecontrol lock current source next87 rejects bad shm lock' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run87(['open(shm)', 'shm_lock(bogus, shared)'])),
    'vfs shm filecontrol lock current source next87 rejects bad shm mode' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run87([['op' => 'open', 'source' => 'shm'], ['op' => 'shmlock', 'lock' => 'read0', 'mode' => 'invalid']])),
    'vfs shm filecontrol lock current source next87 rejects bad chunk' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run87(['open(main)', ['op' => 'filecontrol', 'control' => 'chunk_size', 'value' => -1]])),
    'vfs shm filecontrol lock current source next87 rejects empty name hint' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run87(['open(main)', ['op' => 'filecontrol', 'control' => 'name_hint', 'value' => '']])),
];
