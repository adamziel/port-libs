<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsOpenLockFileControlCurrentSource;

$run94 = static fn (array $ops, array $options = []): array => SQLiteVfsOpenLockFileControlCurrentSource::planPersistWalLockFileControl($ops, $options + [
    'filename' => 'file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=shared&vfs=unix',
]);

$persist = static function () use ($run94): array {
    static $result = null;
    if ($result === null) {
        $first = $run94([
            'open',
            'file_control(persist_wal, on)',
            'file_control(mmap_size, 65536)',
            'lock(shared)',
            'file_control(persist_wal, on)',
            'lock(reserved)',
            'file_control(persist_wal, on)',
            'close',
        ]);

        $second = $run94([
            'open(file://localhost/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=private&vfs=unix)',
            'file_control(persist_wal, off)',
            'lock(pending)',
            'file_control(persist_wal, off)',
            'file_control(lock_timeout, 500)',
        ], ['current' => $first['events'][7]['next']]);

        $result = $second + ['events' => []];
        $result['events'] = array_merge($first['events'], $second['events']);
    }

    return $result;
};

$readonly = static fn (): array => $run94([
    'open(file:/srv/www/wp-content/database/archive.sqlite?mode=ro)',
    'lock(reserved)',
    'file_control(persist_wal, on)',
    'file_control(mmap_size, 32768)',
]);

$nolock = static fn (): array => $run94([
    'open(file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&nolock=1)',
    'lock(reserved)',
    'file_control(persist_wal, on)',
    'file_control(mmap_size, 32768)',
]);

$explicitCurrent = static fn (): array => $run94([
    'open(file:/srv/www/wp-content/database/current.sqlite?mode=rw)',
    'file_control(persist_wal, off)',
], [
    'filename' => 'file:/srv/www/wp-content/database/current.sqlite?mode=rw',
    'current' => [
        'persistent_controls' => [
            '/srv/www/wp-content/database/current.sqlite' => [
                'persist_wal' => true,
                'data_version' => 6,
            ],
        ],
        'persistent_locks' => [
            '/srv/www/wp-content/database/current.sqlite' => 'reserved',
        ],
    ],
]);

return [
    'vfs filecontrol persistwal lock current source next94 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-filecontrol-persistwal-lock-current-source-next94', $persist()['dependencies'], true)),
    'vfs filecontrol persistwal lock current source next94 final status ok' => static fn (TestRunner $t) => $t->same('ok', $persist()['status']),
    'vfs filecontrol persistwal lock current source next94 decoded path' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $persist()['events'][0]['source_key']),
    'vfs filecontrol persistwal lock current source next94 open has no reused controls' => static fn (TestRunner $t) => $t->same(false, $persist()['events'][0]['reused_controls']),
    'vfs filecontrol persistwal lock current source next94 unlocked persist blocked' => static fn (TestRunner $t) => $t->same('blocked', $persist()['events'][1]['status']),
    'vfs filecontrol persistwal lock current source next94 unlocked persist reason' => static fn (TestRunner $t) => $t->same('requires_reserved_or_exclusive_lock', $persist()['events'][1]['reason']),
    'vfs filecontrol persistwal lock current source next94 unlocked persist absent' => static fn (TestRunner $t) => $t->same(false, isset($persist()['events'][1]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['persist_wal'])),
    'vfs filecontrol persistwal lock current source next94 unlocked persist unchanged flag' => static fn (TestRunner $t) => $t->same(false, $persist()['events'][1]['changed']),
    'vfs filecontrol persistwal lock current source next94 mmap ok before lock' => static fn (TestRunner $t) => $t->same('ok', $persist()['events'][2]['status']),
    'vfs filecontrol persistwal lock current source next94 mmap persisted' => static fn (TestRunner $t) => $t->same(65536, $persist()['events'][2]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['mmap_size']),
    'vfs filecontrol persistwal lock current source next94 shared lock ok' => static fn (TestRunner $t) => $t->same('ok', $persist()['events'][3]['status']),
    'vfs filecontrol persistwal lock current source next94 shared lock state' => static fn (TestRunner $t) => $t->same('shared', $persist()['events'][3]['lock_state']),
    'vfs filecontrol persistwal lock current source next94 shared persist blocked' => static fn (TestRunner $t) => $t->same('blocked', $persist()['events'][4]['status']),
    'vfs filecontrol persistwal lock current source next94 shared persist reason' => static fn (TestRunner $t) => $t->same('requires_reserved_or_exclusive_lock', $persist()['events'][4]['reason']),
    'vfs filecontrol persistwal lock current source next94 shared persist absent' => static fn (TestRunner $t) => $t->same(false, array_key_exists('persist_wal', $persist()['events'][4]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite'])),
    'vfs filecontrol persistwal lock current source next94 reserved lock ok' => static fn (TestRunner $t) => $t->same('ok', $persist()['events'][5]['status']),
    'vfs filecontrol persistwal lock current source next94 reserved lock stored' => static fn (TestRunner $t) => $t->same('reserved', $persist()['events'][5]['next']['persistent_locks']['/srv/www/wp-content/database/wp copy.sqlite']),
    'vfs filecontrol persistwal lock current source next94 reserved persist ok' => static fn (TestRunner $t) => $t->same('ok', $persist()['events'][6]['status']),
    'vfs filecontrol persistwal lock current source next94 reserved persist changed' => static fn (TestRunner $t) => $t->same(true, $persist()['events'][6]['changed']),
    'vfs filecontrol persistwal lock current source next94 persist stored true' => static fn (TestRunner $t) => $t->same(true, $persist()['events'][6]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['persist_wal']),
    'vfs filecontrol persistwal lock current source next94 persist bumps data version' => static fn (TestRunner $t) => $t->same(2, $persist()['events'][6]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['data_version']),
    'vfs filecontrol persistwal lock current source next94 close unlocks source' => static fn (TestRunner $t) => $t->same('unlocked', $persist()['events'][7]['next']['persistent_locks']['/srv/www/wp-content/database/wp copy.sqlite']),
    'vfs filecontrol persistwal lock current source next94 close keeps persist controls' => static fn (TestRunner $t) => $t->same(true, $persist()['events'][7]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['persist_wal']),
    'vfs filecontrol persistwal lock current source next94 localhost reopen reuses controls' => static fn (TestRunner $t) => $t->same(true, $persist()['events'][8]['reused_controls']),
    'vfs filecontrol persistwal lock current source next94 localhost reopen path decoded' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $persist()['events'][8]['source_key']),
    'vfs filecontrol persistwal lock current source next94 reopen persist true' => static fn (TestRunner $t) => $t->same(true, $persist()['events'][8]['next']['handles']['db-2']['controls']['persist_wal']),
    'vfs filecontrol persistwal lock current source next94 reopen data version preserved' => static fn (TestRunner $t) => $t->same(2, $persist()['events'][8]['next']['handles']['db-2']['controls']['data_version']),
    'vfs filecontrol persistwal lock current source next94 reopen unlocked persist off blocked' => static fn (TestRunner $t) => $t->same('blocked', $persist()['events'][9]['status']),
    'vfs filecontrol persistwal lock current source next94 reopen blocked leaves true' => static fn (TestRunner $t) => $t->same(true, $persist()['events'][9]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['persist_wal']),
    'vfs filecontrol persistwal lock current source next94 pending lock ok' => static fn (TestRunner $t) => $t->same('ok', $persist()['events'][10]['status']),
    'vfs filecontrol persistwal lock current source next94 pending persist off ok' => static fn (TestRunner $t) => $t->same('ok', $persist()['events'][11]['status']),
    'vfs filecontrol persistwal lock current source next94 pending persist false' => static fn (TestRunner $t) => $t->same(false, $persist()['events'][11]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['persist_wal']),
    'vfs filecontrol persistwal lock current source next94 pending persist bumps data version' => static fn (TestRunner $t) => $t->same(3, $persist()['events'][11]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['data_version']),
    'vfs filecontrol persistwal lock current source next94 lock timeout ok' => static fn (TestRunner $t) => $t->same('ok', $persist()['events'][12]['status']),
    'vfs filecontrol persistwal lock current source next94 lock timeout no data bump' => static fn (TestRunner $t) => $t->same(3, $persist()['events'][12]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['data_version']),
    'vfs filecontrol persistwal lock current source next94 final open count' => static fn (TestRunner $t) => $t->same(1, $persist()['next']['open_count']),
    'vfs filecontrol persistwal lock current source next94 final persistent count' => static fn (TestRunner $t) => $t->same(1, $persist()['next']['persistent_control_count']),
    'vfs filecontrol persistwal lock current source next94 final lock count one pending' => static fn (TestRunner $t) => $t->same(1, $persist()['next']['persistent_lock_count']),

    'vfs filecontrol persistwal lock current source next94 readonly lock ok' => static fn (TestRunner $t) => $t->same('ok', $readonly()['events'][1]['status']),
    'vfs filecontrol persistwal lock current source next94 readonly persist ignored' => static fn (TestRunner $t) => $t->same('ignored', $readonly()['events'][2]['status']),
    'vfs filecontrol persistwal lock current source next94 readonly persist reason' => static fn (TestRunner $t) => $t->same('readonly_handle', $readonly()['events'][2]['reason']),
    'vfs filecontrol persistwal lock current source next94 readonly persist absent' => static fn (TestRunner $t) => $t->same(false, isset($readonly()['events'][2]['next']['persistent_controls']['/srv/www/wp-content/database/archive.sqlite']['persist_wal'])),
    'vfs filecontrol persistwal lock current source next94 readonly mmap ok' => static fn (TestRunner $t) => $t->same('ok', $readonly()['events'][3]['status']),
    'vfs filecontrol persistwal lock current source next94 readonly mmap stored' => static fn (TestRunner $t) => $t->same(32768, $readonly()['events'][3]['next']['persistent_controls']['/srv/www/wp-content/database/archive.sqlite']['mmap_size']),

    'vfs filecontrol persistwal lock current source next94 nolock lock blocked' => static fn (TestRunner $t) => $t->same('blocked', $nolock()['events'][1]['status']),
    'vfs filecontrol persistwal lock current source next94 nolock persist blocked' => static fn (TestRunner $t) => $t->same('blocked', $nolock()['events'][2]['status']),
    'vfs filecontrol persistwal lock current source next94 nolock persist reason' => static fn (TestRunner $t) => $t->same('requires_reserved_or_exclusive_lock', $nolock()['events'][2]['reason']),
    'vfs filecontrol persistwal lock current source next94 nolock mmap ok' => static fn (TestRunner $t) => $t->same('ok', $nolock()['events'][3]['status']),

    'vfs filecontrol persistwal lock current source next94 explicit current reused controls' => static fn (TestRunner $t) => $t->same(true, $explicitCurrent()['events'][0]['reused_controls']),
    'vfs filecontrol persistwal lock current source next94 explicit current reused lock' => static fn (TestRunner $t) => $t->same(true, $explicitCurrent()['events'][0]['reused_lock']),
    'vfs filecontrol persistwal lock current source next94 explicit current persist starts true' => static fn (TestRunner $t) => $t->same(true, $explicitCurrent()['events'][0]['next']['handles']['db-1']['controls']['persist_wal']),
    'vfs filecontrol persistwal lock current source next94 explicit current persist off ok' => static fn (TestRunner $t) => $t->same('ok', $explicitCurrent()['events'][1]['status']),
    'vfs filecontrol persistwal lock current source next94 explicit current persist false' => static fn (TestRunner $t) => $t->same(false, $explicitCurrent()['events'][1]['next']['persistent_controls']['/srv/www/wp-content/database/current.sqlite']['persist_wal']),
    'vfs filecontrol persistwal lock current source next94 explicit current data version continues' => static fn (TestRunner $t) => $t->same(7, $explicitCurrent()['events'][1]['next']['persistent_controls']['/srv/www/wp-content/database/current.sqlite']['data_version']),

    'vfs filecontrol persistwal lock current source next94 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsOpenLockFileControlCurrentSource::planPersistWalLockFileControl([])),
    'vfs filecontrol persistwal lock current source next94 coerces persist integer true' => static fn (TestRunner $t) => $t->same(true, $run94(['open', 'lock(reserved)', 'file_control(persist_wal, 2)'])['events'][2]['value']),
];
