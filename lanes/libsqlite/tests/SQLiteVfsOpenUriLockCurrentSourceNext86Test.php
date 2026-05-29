<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsOpenLockFileControlCurrentSource;

$run86 = static fn (array $ops, array $options = []): array => SQLiteVfsOpenLockFileControlCurrentSource::planUriOpenLock($ops, $options + [
    'filename' => 'file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=shared',
]);

$decoded = static fn (): array => $run86([
    'open(file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=shared)',
    'file_control(chunk_size, 12288)',
    'file_control(persist_wal, 1)',
    'lock(shared)',
    'close',
    'open(file://localhost/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=private)',
]);

$immutable = static fn (): array => $run86([
    'open(file://localhost/srv/www/wp-content/database/archive%20copy.sqlite?mode=rw&immutable=1&nolock=0)',
    'file_control(mmap_size, 262144)',
    'file_control(chunk_size, 4096)',
    'lock(shared)',
    'close',
    'open(file:/srv/www/wp-content/database/archive%20copy.sqlite?mode=ro&immutable=1)',
]);

$memory = static fn (): array => $run86([
    'open(file::memory:?cache=shared&mode=memory)',
    'file_control(persist_wal, 1)',
    'close',
    'open(file::memory:?cache=shared&mode=memory)',
]);

$explicit = static fn (): array => $run86([
    'open(file://localhost/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw)',
], [
    'current' => [
        'persistent_controls' => [
            '/srv/www/wp-content/database/wp copy.sqlite' => ['chunk_size' => 8192, 'persist_wal' => true],
        ],
        'persistent_locks' => [
            '/srv/www/wp-content/database/wp copy.sqlite' => 'reserved',
        ],
    ],
]);

return [
    'vfs open uri lock current source next86 final status' => static fn (TestRunner $t) => $t->same('open', $decoded()['status']),
    'vfs open uri lock current source next86 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-open-uri-lock-current-source-next86', $decoded()['dependencies'], true)),
    'vfs open uri lock current source next86 decoded first path' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $decoded()['events'][0]['path']),
    'vfs open uri lock current source next86 decoded first source key' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $decoded()['events'][0]['source_key']),
    'vfs open uri lock current source next86 parsed first uri flag' => static fn (TestRunner $t) => $t->same(true, $decoded()['events'][0]['uri']['is_uri']),
    'vfs open uri lock current source next86 parsed first mode' => static fn (TestRunner $t) => $t->same('rw', $decoded()['events'][0]['uri']['mode']),
    'vfs open uri lock current source next86 parsed first cache shared' => static fn (TestRunner $t) => $t->same('shared', $decoded()['events'][0]['uri']['cache']),
    'vfs open uri lock current source next86 first open no controls' => static fn (TestRunner $t) => $t->same(false, $decoded()['events'][0]['reused_controls']),
    'vfs open uri lock current source next86 chunk persisted under decoded path' => static fn (TestRunner $t) => $t->same(12288, $decoded()['events'][1]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['chunk_size']),
    'vfs open uri lock current source next86 persist wal true' => static fn (TestRunner $t) => $t->same(true, $decoded()['events'][2]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['persist_wal']),
    'vfs open uri lock current source next86 shared lock persisted while open' => static fn (TestRunner $t) => $t->same('shared', $decoded()['events'][3]['next']['persistent_locks']['/srv/www/wp-content/database/wp copy.sqlite']),
    'vfs open uri lock current source next86 close unlocks source' => static fn (TestRunner $t) => $t->same('unlocked', $decoded()['events'][4]['next']['persistent_locks']['/srv/www/wp-content/database/wp copy.sqlite']),
    'vfs open uri lock current source next86 localhost reopen decoded same key' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $decoded()['events'][5]['source_key']),
    'vfs open uri lock current source next86 localhost authority captured' => static fn (TestRunner $t) => $t->same('localhost', $decoded()['events'][5]['uri']['authority']),
    'vfs open uri lock current source next86 localhost cache private' => static fn (TestRunner $t) => $t->same('private', $decoded()['events'][5]['uri']['cache']),
    'vfs open uri lock current source next86 localhost reused controls' => static fn (TestRunner $t) => $t->same(true, $decoded()['events'][5]['reused_controls']),
    'vfs open uri lock current source next86 localhost rehydrated chunk' => static fn (TestRunner $t) => $t->same(12288, $decoded()['events'][5]['next']['handles']['db-2']['controls']['chunk_size']),
    'vfs open uri lock current source next86 localhost rehydrated persist wal' => static fn (TestRunner $t) => $t->same(true, $decoded()['events'][5]['next']['handles']['db-2']['controls']['persist_wal']),
    'vfs open uri lock current source next86 close leaves no live lock count' => static fn (TestRunner $t) => $t->same(0, $decoded()['next']['persistent_lock_count']),
    'vfs open uri lock current source next86 final open path decoded' => static fn (TestRunner $t) => $t->same(['/srv/www/wp-content/database/wp copy.sqlite'], $decoded()['next']['open_paths']),

    'vfs open uri lock current source next86 immutable path decoded' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/archive copy.sqlite', $immutable()['events'][0]['path']),
    'vfs open uri lock current source next86 immutable flag parsed' => static fn (TestRunner $t) => $t->same(true, $immutable()['events'][0]['uri']['immutable']),
    'vfs open uri lock current source next86 immutable handle readonly' => static fn (TestRunner $t) => $t->same(true, $immutable()['events'][0]['next']['handles']['db-1']['readonly']),
    'vfs open uri lock current source next86 immutable nolock parameter false retained' => static fn (TestRunner $t) => $t->same(false, $immutable()['events'][0]['next']['handles']['db-1']['nolock']),
    'vfs open uri lock current source next86 immutable mmap allowed' => static fn (TestRunner $t) => $t->same('ok', $immutable()['events'][1]['status']),
    'vfs open uri lock current source next86 immutable mmap persisted' => static fn (TestRunner $t) => $t->same(262144, $immutable()['events'][1]['next']['persistent_controls']['/srv/www/wp-content/database/archive copy.sqlite']['mmap_size']),
    'vfs open uri lock current source next86 immutable chunk ignored' => static fn (TestRunner $t) => $t->same('ignored', $immutable()['events'][2]['status']),
    'vfs open uri lock current source next86 immutable chunk absent' => static fn (TestRunner $t) => $t->same(false, array_key_exists('chunk_size', $immutable()['events'][2]['next']['handles']['db-1']['controls'])),
    'vfs open uri lock current source next86 immutable lock blocked' => static fn (TestRunner $t) => $t->same('blocked', $immutable()['events'][3]['status']),
    'vfs open uri lock current source next86 immutable lock reason' => static fn (TestRunner $t) => $t->same('immutable URI disables locking and change detection', $immutable()['events'][3]['reason']),
    'vfs open uri lock current source next86 immutable lock remains unlocked' => static fn (TestRunner $t) => $t->same('unlocked', $immutable()['events'][3]['next']['handles']['db-1']['lock_state']),
    'vfs open uri lock current source next86 immutable reopen reused mmap' => static fn (TestRunner $t) => $t->same(true, $immutable()['events'][5]['reused_controls']),
    'vfs open uri lock current source next86 immutable reopen mmap value' => static fn (TestRunner $t) => $t->same(262144, $immutable()['events'][5]['next']['handles']['db-2']['controls']['mmap_size']),
    'vfs open uri lock current source next86 immutable final persistent source count' => static fn (TestRunner $t) => $t->same(1, $immutable()['next']['persistent_control_count']),

    'vfs open uri lock current source next86 memory first status' => static fn (TestRunner $t) => $t->same('memory-open', $memory()['events'][0]['status']),
    'vfs open uri lock current source next86 memory first key unique' => static fn (TestRunner $t) => $t->same('memory:db-1', $memory()['events'][0]['source_key']),
    'vfs open uri lock current source next86 memory controls not persistent' => static fn (TestRunner $t) => $t->same([], $memory()['events'][1]['next']['persistent_controls']),
    'vfs open uri lock current source next86 memory second key unique' => static fn (TestRunner $t) => $t->same('memory:db-2', $memory()['events'][3]['source_key']),
    'vfs open uri lock current source next86 memory second no control reuse' => static fn (TestRunner $t) => $t->same(false, $memory()['events'][3]['reused_controls']),
    'vfs open uri lock current source next86 memory final open path empty' => static fn (TestRunner $t) => $t->same([], $memory()['next']['open_paths']),

    'vfs open uri lock current source next86 explicit current decoded reused controls' => static fn (TestRunner $t) => $t->same(true, $explicit()['events'][0]['reused_controls']),
    'vfs open uri lock current source next86 explicit current decoded reused lock' => static fn (TestRunner $t) => $t->same(true, $explicit()['events'][0]['reused_lock']),
    'vfs open uri lock current source next86 explicit current chunk value' => static fn (TestRunner $t) => $t->same(8192, $explicit()['events'][0]['next']['handles']['db-1']['controls']['chunk_size']),
    'vfs open uri lock current source next86 explicit current lock value' => static fn (TestRunner $t) => $t->same('reserved', $explicit()['events'][0]['next']['handles']['db-1']['lock_state']),
    'vfs open uri lock current source next86 explicit current decoded source key' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $explicit()['events'][0]['source_key']),

    'vfs open uri lock current source next86 rejects remote authority' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run86(['open(file://example.com/srv/db.sqlite?mode=rw)'])),
    'vfs open uri lock current source next86 rejects malformed percent path' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run86(['open(file:/srv/www/wp-content/database/bad%2.sqlite?mode=rw)'])),
    'vfs open uri lock current source next86 rejects bad immutable boolean' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run86(['open(file:/srv/www/wp-content/database/archive.sqlite?immutable=yes)'])),
    'vfs open uri lock current source next86 rejects bad nolock boolean' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run86(['open(file:/srv/www/wp-content/database/archive.sqlite?nolock=true)'])),
    'vfs open uri lock current source next86 rejects unsupported mode' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run86(['open(file:/srv/www/wp-content/database/archive.sqlite?mode=readonly)'])),
    'vfs open uri lock current source next86 rejects unsupported cache' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run86(['open(file:/srv/www/wp-content/database/archive.sqlite?cache=global)'])),
];
