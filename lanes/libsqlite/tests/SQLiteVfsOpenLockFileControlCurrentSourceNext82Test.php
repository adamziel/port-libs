<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsOpenLockFileControlCurrentSource;

$run82 = static fn (array $ops, array $options = []): array => SQLiteVfsOpenLockFileControlCurrentSource::planOpenLockFileControl($ops, $options + [
    'filename' => 'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared',
]);

$persisted = static fn (): array => $run82([
    'open',
    'file_control(chunk_size, 8192)',
    'file_control(mmap_size, 65536)',
    'file_control(persist_wal, on)',
    'file_control(name_hint, "wp import")',
    'lock(reserved)',
    'close',
    'open(file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared)',
]);
$memory = static fn (): array => $run82([
    'open(file::memory:?cache=shared&mode=memory)',
    'file_control(chunk_size, 4096)',
    'lock(exclusive)',
    'close',
]);
$delete = static fn (): array => $run82([
    ['op' => 'open', 'filename' => '/srv/www/wp-content/database/delete-me.sqlite', 'delete_on_close' => true],
    ['op' => 'filecontrol', 'control' => 'chunk_size', 'value' => 4096],
    ['op' => 'lock', 'value' => 'exclusive'],
    ['op' => 'close'],
    ['op' => 'open', 'filename' => '/srv/www/wp-content/database/delete-me.sqlite'],
]);
$readonly = static fn (): array => $run82([
    'open(file:/srv/www/wp-content/database/.ht.sqlite?mode=rw)',
    'file_control(mmap_size, 32768)',
    'close',
    'open(file:/srv/www/wp-content/database/.ht.sqlite?mode=ro)',
    'file_control(chunk_size, 16384)',
    'file_control(mmap_size, 65536)',
]);
$nolock = static fn (): array => $run82([
    'open(file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&nolock=1)',
    'lock(shared)',
    'file_control(lock_timeout, 250)',
]);

return [
    'vfs open lock filecontrol current source next82 status' => static fn (TestRunner $t) => $t->same('open', $persisted()['status']),
    'vfs open lock filecontrol current source next82 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-open-lock-filecontrol-current-source-next82', $persisted()['dependencies'], true)),
    'vfs open lock filecontrol current source next82 open path canonicalized' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/.ht.sqlite', $persisted()['events'][0]['path']),
    'vfs open lock filecontrol current source next82 first source key path' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/.ht.sqlite', $persisted()['events'][0]['source_key']),
    'vfs open lock filecontrol current source next82 first open no reused controls' => static fn (TestRunner $t) => $t->same(false, $persisted()['events'][0]['reused_controls']),
    'vfs open lock filecontrol current source next82 first open no reused lock' => static fn (TestRunner $t) => $t->same(false, $persisted()['events'][0]['reused_lock']),
    'vfs open lock filecontrol current source next82 chunk status ok' => static fn (TestRunner $t) => $t->same('ok', $persisted()['events'][1]['status']),
    'vfs open lock filecontrol current source next82 chunk changed' => static fn (TestRunner $t) => $t->same(true, $persisted()['events'][1]['changed']),
    'vfs open lock filecontrol current source next82 chunk next handle control' => static fn (TestRunner $t) => $t->same(8192, $persisted()['events'][1]['next']['handles']['db-1']['controls']['chunk_size']),
    'vfs open lock filecontrol current source next82 chunk persisted current source' => static fn (TestRunner $t) => $t->same(8192, $persisted()['events'][1]['next']['persistent_controls']['/srv/www/wp-content/database/.ht.sqlite']['chunk_size']),
    'vfs open lock filecontrol current source next82 mmap persisted' => static fn (TestRunner $t) => $t->same(65536, $persisted()['events'][2]['next']['persistent_controls']['/srv/www/wp-content/database/.ht.sqlite']['mmap_size']),
    'vfs open lock filecontrol current source next82 persist wal boolean' => static fn (TestRunner $t) => $t->same(true, $persisted()['events'][3]['value']),
    'vfs open lock filecontrol current source next82 name hint not persistent durable subset' => static fn (TestRunner $t) => $t->same(false, array_key_exists('name_hint', $persisted()['events'][4]['next']['persistent_controls']['/srv/www/wp-content/database/.ht.sqlite'])),
    'vfs open lock filecontrol current source next82 name hint stays on open handle' => static fn (TestRunner $t) => $t->same('wp import', $persisted()['events'][4]['next']['handles']['db-1']['controls']['name_hint']),
    'vfs open lock filecontrol current source next82 reserved lock status' => static fn (TestRunner $t) => $t->same('ok', $persisted()['events'][5]['status']),
    'vfs open lock filecontrol current source next82 reserved lock persisted while open' => static fn (TestRunner $t) => $t->same('reserved', $persisted()['events'][5]['next']['persistent_locks']['/srv/www/wp-content/database/.ht.sqlite']),
    'vfs open lock filecontrol current source next82 close status' => static fn (TestRunner $t) => $t->same('closed', $persisted()['events'][6]['status']),
    'vfs open lock filecontrol current source next82 close persists controls' => static fn (TestRunner $t) => $t->same(true, $persisted()['events'][6]['persisted_controls']),
    'vfs open lock filecontrol current source next82 close unlocks persisted lock' => static fn (TestRunner $t) => $t->same('unlocked', $persisted()['events'][6]['next']['persistent_locks']['/srv/www/wp-content/database/.ht.sqlite']),
    'vfs open lock filecontrol current source next82 reopen reused controls' => static fn (TestRunner $t) => $t->same(true, $persisted()['events'][7]['reused_controls']),
    'vfs open lock filecontrol current source next82 reopen lock not reused after close' => static fn (TestRunner $t) => $t->same(false, $persisted()['events'][7]['reused_lock']),
    'vfs open lock filecontrol current source next82 reopen chunk rehydrated' => static fn (TestRunner $t) => $t->same(8192, $persisted()['events'][7]['next']['handles']['db-2']['controls']['chunk_size']),
    'vfs open lock filecontrol current source next82 reopen mmap rehydrated' => static fn (TestRunner $t) => $t->same(65536, $persisted()['events'][7]['next']['handles']['db-2']['controls']['mmap_size']),
    'vfs open lock filecontrol current source next82 final open count' => static fn (TestRunner $t) => $t->same(1, $persisted()['next']['open_count']),
    'vfs open lock filecontrol current source next82 final persistent control count' => static fn (TestRunner $t) => $t->same(1, $persisted()['next']['persistent_control_count']),
    'vfs open lock filecontrol current source next82 final persistent lock count' => static fn (TestRunner $t) => $t->same(0, $persisted()['next']['persistent_lock_count']),

    'vfs open lock filecontrol current source next82 memory open status' => static fn (TestRunner $t) => $t->same('memory-open', $memory()['events'][0]['status']),
    'vfs open lock filecontrol current source next82 memory path empty' => static fn (TestRunner $t) => $t->same('', $memory()['events'][0]['path']),
    'vfs open lock filecontrol current source next82 memory controls not persistent while open' => static fn (TestRunner $t) => $t->same([], $memory()['events'][1]['next']['persistent_controls']),
    'vfs open lock filecontrol current source next82 memory lock not persistent while open' => static fn (TestRunner $t) => $t->same([], $memory()['events'][2]['next']['persistent_locks']),
    'vfs open lock filecontrol current source next82 memory close clears handles' => static fn (TestRunner $t) => $t->same(0, $memory()['next']['open_count']),
    'vfs open lock filecontrol current source next82 memory close not deleted' => static fn (TestRunner $t) => $t->same(false, $memory()['events'][3]['deleted']),

    'vfs open lock filecontrol current source next82 delete close deleted' => static fn (TestRunner $t) => $t->same(true, $delete()['events'][3]['deleted']),
    'vfs open lock filecontrol current source next82 delete close drops controls' => static fn (TestRunner $t) => $t->same([], $delete()['events'][3]['next']['persistent_controls']),
    'vfs open lock filecontrol current source next82 delete close drops lock' => static fn (TestRunner $t) => $t->same([], $delete()['events'][3]['next']['persistent_locks']),
    'vfs open lock filecontrol current source next82 reopen after delete has no controls' => static fn (TestRunner $t) => $t->same(false, $delete()['events'][4]['reused_controls']),
    'vfs open lock filecontrol current source next82 reopen after delete has no lock' => static fn (TestRunner $t) => $t->same(false, $delete()['events'][4]['reused_lock']),

    'vfs open lock filecontrol current source next82 readonly rehydrates mmap' => static fn (TestRunner $t) => $t->same(32768, $readonly()['events'][3]['next']['handles']['db-2']['controls']['mmap_size']),
    'vfs open lock filecontrol current source next82 readonly ignores chunk write' => static fn (TestRunner $t) => $t->same('ignored', $readonly()['events'][4]['status']),
    'vfs open lock filecontrol current source next82 readonly chunk unchanged absent' => static fn (TestRunner $t) => $t->same(false, array_key_exists('chunk_size', $readonly()['events'][4]['next']['handles']['db-2']['controls'])),
    'vfs open lock filecontrol current source next82 readonly mmap can change' => static fn (TestRunner $t) => $t->same('ok', $readonly()['events'][5]['status']),
    'vfs open lock filecontrol current source next82 readonly mmap final value' => static fn (TestRunner $t) => $t->same(65536, $readonly()['events'][5]['next']['handles']['db-2']['controls']['mmap_size']),

    'vfs open lock filecontrol current source next82 nolock lock blocked' => static fn (TestRunner $t) => $t->same('blocked', $nolock()['events'][1]['status']),
    'vfs open lock filecontrol current source next82 nolock reason' => static fn (TestRunner $t) => $t->same('nolock VFS disables POSIX byte-range locking', $nolock()['events'][1]['reason']),
    'vfs open lock filecontrol current source next82 nolock timeout still records control' => static fn (TestRunner $t) => $t->same(250, $nolock()['events'][2]['next']['handles']['db-1']['controls']['lock_timeout']),
    'vfs open lock filecontrol current source next82 nolock lock remains unlocked' => static fn (TestRunner $t) => $t->same('unlocked', $nolock()['events'][2]['next']['handles']['db-1']['lock_state']),

    'vfs open lock filecontrol current source next82 explicit current reuses controls' => static fn (TestRunner $t) => $t->same(true, $run82(['open'], ['current' => ['persistent_controls' => ['/srv/www/wp-content/database/.ht.sqlite' => ['chunk_size' => 2048]]]])['events'][0]['reused_controls']),
    'vfs open lock filecontrol current source next82 explicit current reuses lock' => static fn (TestRunner $t) => $t->same(true, $run82(['open'], ['current' => ['persistent_locks' => ['/srv/www/wp-content/database/.ht.sqlite' => 'shared']]])['events'][0]['reused_lock']),
    'vfs open lock filecontrol current source next82 explicit current lock value' => static fn (TestRunner $t) => $t->same('shared', $run82(['open'], ['current' => ['persistent_locks' => ['/srv/www/wp-content/database/.ht.sqlite' => 'shared']]])['events'][0]['next']['handles']['db-1']['lock_state']),
    'vfs open lock filecontrol current source next82 missing filecontrol reports missing' => static fn (TestRunner $t) => $t->same('missing-handle', $run82([['op' => 'filecontrol', 'handle' => 'missing', 'control' => 'mmap_size', 'value' => 1]])['status']),
    'vfs open lock filecontrol current source next82 missing lock reports missing' => static fn (TestRunner $t) => $t->same('missing-handle', $run82([['op' => 'lock', 'handle' => 'missing', 'value' => 'shared']])['status']),
    'vfs open lock filecontrol current source next82 missing close reports missing' => static fn (TestRunner $t) => $t->same('missing-handle', $run82([['op' => 'close', 'handle' => 'missing']])['status']),
    'vfs open lock filecontrol current source next82 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsOpenLockFileControlCurrentSource::planOpenLockFileControl([])),
    'vfs open lock filecontrol current source next82 rejects unsupported operation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run82(['checkpoint'])),
    'vfs open lock filecontrol current source next82 rejects bad lock level' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run82(['open', 'lock(bogus)'])),
    'vfs open lock filecontrol current source next82 rejects bad name hint' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run82(['open', ['op' => 'filecontrol', 'control' => 'name_hint', 'value' => '']])),
    'vfs open lock filecontrol current source next82 rejects bad chunk size' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run82(['open', ['op' => 'filecontrol', 'control' => 'chunk_size', 'value' => -1]])),
];
