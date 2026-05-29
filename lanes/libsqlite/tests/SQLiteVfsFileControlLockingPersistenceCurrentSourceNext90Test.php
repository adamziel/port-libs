<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsOpenLockFileControlCurrentSource;

$run90 = static fn (array $ops, array $options = []): array => SQLiteVfsOpenLockFileControlCurrentSource::planLockingFileControlPersistence($ops, $options + [
    'filename' => 'file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=shared&vfs=unix',
]);

$locked = static function () use ($run90): array {
    static $result = null;
    if ($result === null) {
        $first = $run90([
            'open',
            'file_control(chunk_size, 8192)',
            'file_control(mmap_size, 65536)',
            'lock(shared)',
            'file_control(reserve_bytes, 16)',
            'lock(reserved)',
            'file_control(reserve_bytes, 16)',
            'file_control(chunk_size, 16384)',
            'file_control(powersafe_overwrite, off)',
            'file_control(lock_timeout, 250)',
            'close',
        ]);

        $second = $run90([
            'open(file://localhost/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=private&vfs=unix)',
            'file_control(mmap_size, 131072)',
        ], ['current' => $first['events'][10]['next']]);
        $result = $second + ['events' => []];
        $result['events'] = array_merge($first['events'], $second['events']);
    }

    return $result;
};

$delete = static function () use ($run90): array {
    static $result = null;
    if ($result === null) {
        $first = $run90([
            ['op' => 'open', 'filename' => 'file:/srv/www/wp-content/database/delete%20me.sqlite?mode=rw', 'delete_on_close' => true],
            'lock(exclusive)',
            'file_control(chunk_size, 4096)',
            'close',
        ]);

        $second = $run90([
            'open(file:/srv/www/wp-content/database/delete%20me.sqlite?mode=rw)',
        ], ['current' => $first['events'][3]['next']]);
        $result = $second + ['events' => []];
        $result['events'] = array_merge($first['events'], $second['events']);
    }

    return $result;
};

$readonly = static fn (): array => $run90([
    'open(file:/srv/www/wp-content/database/archive.sqlite?mode=ro)',
    'lock(reserved)',
    'file_control(chunk_size, 4096)',
    'file_control(mmap_size, 32768)',
]);

$nolock = static fn (): array => $run90([
    'open(file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&nolock=1)',
    'lock(reserved)',
    'file_control(chunk_size, 4096)',
    'file_control(mmap_size, 32768)',
]);

$explicitCurrent = static fn (): array => $run90([
    'open(file:/srv/www/wp-content/database/current.sqlite?mode=rw)',
    'lock(pending)',
    'file_control(reserve_bytes, 32)',
], [
    'filename' => 'file:/srv/www/wp-content/database/current.sqlite?mode=rw',
    'current' => [
        'persistent_controls' => [
            '/srv/www/wp-content/database/current.sqlite' => [
                'chunk_size' => 2048,
                'data_version' => 9,
            ],
        ],
        'persistent_locks' => [
            '/srv/www/wp-content/database/current.sqlite' => 'shared',
        ],
    ],
]);

return [
    'vfs filecontrol locking persistence current source next90 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-filecontrol-locking-persistence-current-source-next90', $locked()['dependencies'], true)),
    'vfs filecontrol locking persistence current source next90 final status ok' => static fn (TestRunner $t) => $t->same('ok', $locked()['status']),
    'vfs filecontrol locking persistence current source next90 first open decoded path' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $locked()['events'][0]['current']['handles']['db-1']['path'] ?? $locked()['events'][0]['path']),
    'vfs filecontrol locking persistence current source next90 first open source key' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $locked()['events'][0]['source_key']),
    'vfs filecontrol locking persistence current source next90 unlocked chunk blocked' => static fn (TestRunner $t) => $t->same('blocked', $locked()['events'][1]['status']),
    'vfs filecontrol locking persistence current source next90 unlocked chunk reason' => static fn (TestRunner $t) => $t->same('requires_reserved_or_exclusive_lock', $locked()['events'][1]['reason']),
    'vfs filecontrol locking persistence current source next90 unlocked chunk absent' => static fn (TestRunner $t) => $t->same(false, isset($locked()['events'][1]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['chunk_size'])),
    'vfs filecontrol locking persistence current source next90 mmap allowed without write lock' => static fn (TestRunner $t) => $t->same('ok', $locked()['events'][2]['status']),
    'vfs filecontrol locking persistence current source next90 mmap persisted before close' => static fn (TestRunner $t) => $t->same(65536, $locked()['events'][2]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['mmap_size']),
    'vfs filecontrol locking persistence current source next90 shared lock ok' => static fn (TestRunner $t) => $t->same('ok', $locked()['events'][3]['status']),
    'vfs filecontrol locking persistence current source next90 shared lock state' => static fn (TestRunner $t) => $t->same('shared', $locked()['events'][3]['lock_state']),
    'vfs filecontrol locking persistence current source next90 shared reserve blocked' => static fn (TestRunner $t) => $t->same('blocked', $locked()['events'][4]['status']),
    'vfs filecontrol locking persistence current source next90 shared reserve reason' => static fn (TestRunner $t) => $t->same('requires_reserved_or_exclusive_lock', $locked()['events'][4]['reason']),
    'vfs filecontrol locking persistence current source next90 reserved lock ok' => static fn (TestRunner $t) => $t->same('ok', $locked()['events'][5]['status']),
    'vfs filecontrol locking persistence current source next90 reserved lock persisted' => static fn (TestRunner $t) => $t->same('reserved', $locked()['events'][5]['next']['persistent_locks']['/srv/www/wp-content/database/wp copy.sqlite']),
    'vfs filecontrol locking persistence current source next90 reserve write ok' => static fn (TestRunner $t) => $t->same('ok', $locked()['events'][6]['status']),
    'vfs filecontrol locking persistence current source next90 reserve persisted' => static fn (TestRunner $t) => $t->same(16, $locked()['events'][6]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['reserve_bytes']),
    'vfs filecontrol locking persistence current source next90 reserve bumps data version' => static fn (TestRunner $t) => $t->same(2, $locked()['events'][6]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['data_version']),
    'vfs filecontrol locking persistence current source next90 chunk write ok after reserved' => static fn (TestRunner $t) => $t->same('ok', $locked()['events'][7]['status']),
    'vfs filecontrol locking persistence current source next90 chunk persisted after reserved' => static fn (TestRunner $t) => $t->same(16384, $locked()['events'][7]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['chunk_size']),
    'vfs filecontrol locking persistence current source next90 chunk bumps data version again' => static fn (TestRunner $t) => $t->same(3, $locked()['events'][7]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['data_version']),
    'vfs filecontrol locking persistence current source next90 powersafe persisted false' => static fn (TestRunner $t) => $t->same(false, $locked()['events'][8]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['powersafe_overwrite']),
    'vfs filecontrol locking persistence current source next90 powersafe bumps data version' => static fn (TestRunner $t) => $t->same(4, $locked()['events'][8]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['data_version']),
    'vfs filecontrol locking persistence current source next90 lock timeout does not bump data version' => static fn (TestRunner $t) => $t->same(4, $locked()['events'][9]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['data_version']),
    'vfs filecontrol locking persistence current source next90 close unlocks persistent lock' => static fn (TestRunner $t) => $t->same('unlocked', $locked()['events'][10]['next']['persistent_locks']['/srv/www/wp-content/database/wp copy.sqlite']),
    'vfs filecontrol locking persistence current source next90 localhost reopen reuses controls' => static fn (TestRunner $t) => $t->same(true, $locked()['events'][11]['reused_controls']),
    'vfs filecontrol locking persistence current source next90 localhost reopen does not reuse unlocked lock' => static fn (TestRunner $t) => $t->same(false, $locked()['events'][11]['reused_lock']),
    'vfs filecontrol locking persistence current source next90 localhost source key matches decoded path' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $locked()['events'][11]['source_key']),
    'vfs filecontrol locking persistence current source next90 reopened chunk preserved' => static fn (TestRunner $t) => $t->same(16384, $locked()['events'][11]['next']['handles']['db-2']['controls']['chunk_size']),
    'vfs filecontrol locking persistence current source next90 reopened reserve preserved' => static fn (TestRunner $t) => $t->same(16, $locked()['events'][11]['next']['handles']['db-2']['controls']['reserve_bytes']),
    'vfs filecontrol locking persistence current source next90 reopened data version preserved' => static fn (TestRunner $t) => $t->same(4, $locked()['events'][11]['next']['handles']['db-2']['controls']['data_version']),
    'vfs filecontrol locking persistence current source next90 reopened mmap read control ok' => static fn (TestRunner $t) => $t->same('ok', $locked()['events'][12]['status']),
    'vfs filecontrol locking persistence current source next90 reopened mmap changed' => static fn (TestRunner $t) => $t->same(131072, $locked()['events'][12]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['mmap_size']),
    'vfs filecontrol locking persistence current source next90 read control leaves data version' => static fn (TestRunner $t) => $t->same(4, $locked()['events'][12]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['data_version']),
    'vfs filecontrol locking persistence current source next90 final open count' => static fn (TestRunner $t) => $t->same(1, $locked()['next']['open_count']),
    'vfs filecontrol locking persistence current source next90 final persistent count' => static fn (TestRunner $t) => $t->same(1, $locked()['next']['persistent_control_count']),
    'vfs filecontrol locking persistence current source next90 final lock count zero' => static fn (TestRunner $t) => $t->same(0, $locked()['next']['persistent_lock_count']),

    'vfs filecontrol locking persistence current source next90 delete close drops controls' => static fn (TestRunner $t) => $t->same([], $delete()['events'][4]['next']['persistent_controls']),
    'vfs filecontrol locking persistence current source next90 delete reopen no reused controls' => static fn (TestRunner $t) => $t->same(false, $delete()['events'][4]['reused_controls']),
    'vfs filecontrol locking persistence current source next90 delete reopen no reused lock' => static fn (TestRunner $t) => $t->same(false, $delete()['events'][4]['reused_lock']),

    'vfs filecontrol locking persistence current source next90 readonly lock blocked by ro immutable rules absent' => static fn (TestRunner $t) => $t->same('ok', $readonly()['events'][1]['status']),
    'vfs filecontrol locking persistence current source next90 readonly write ignored' => static fn (TestRunner $t) => $t->same('ignored', $readonly()['events'][2]['status']),
    'vfs filecontrol locking persistence current source next90 readonly write reason' => static fn (TestRunner $t) => $t->same('readonly_handle', $readonly()['events'][2]['reason']),
    'vfs filecontrol locking persistence current source next90 readonly read control ok' => static fn (TestRunner $t) => $t->same('ok', $readonly()['events'][3]['status']),
    'vfs filecontrol locking persistence current source next90 readonly mmap persisted' => static fn (TestRunner $t) => $t->same(32768, $readonly()['events'][3]['next']['persistent_controls']['/srv/www/wp-content/database/archive.sqlite']['mmap_size']),

    'vfs filecontrol locking persistence current source next90 nolock lock blocked' => static fn (TestRunner $t) => $t->same('blocked', $nolock()['events'][1]['status']),
    'vfs filecontrol locking persistence current source next90 nolock write blocked without lock' => static fn (TestRunner $t) => $t->same('blocked', $nolock()['events'][2]['status']),
    'vfs filecontrol locking persistence current source next90 nolock read control ok' => static fn (TestRunner $t) => $t->same('ok', $nolock()['events'][3]['status']),

    'vfs filecontrol locking persistence current source next90 explicit current reused controls' => static fn (TestRunner $t) => $t->same(true, $explicitCurrent()['events'][0]['reused_controls']),
    'vfs filecontrol locking persistence current source next90 explicit current reused lock' => static fn (TestRunner $t) => $t->same(true, $explicitCurrent()['events'][0]['reused_lock']),
    'vfs filecontrol locking persistence current source next90 explicit current lock value' => static fn (TestRunner $t) => $t->same('shared', $explicitCurrent()['events'][0]['next']['handles']['db-1']['lock_state']),
    'vfs filecontrol locking persistence current source next90 pending lock ok' => static fn (TestRunner $t) => $t->same('ok', $explicitCurrent()['events'][1]['status']),
    'vfs filecontrol locking persistence current source next90 explicit data version continues' => static fn (TestRunner $t) => $t->same(10, $explicitCurrent()['events'][2]['next']['persistent_controls']['/srv/www/wp-content/database/current.sqlite']['data_version']),
    'vfs filecontrol locking persistence current source next90 explicit reserve persists' => static fn (TestRunner $t) => $t->same(32, $explicitCurrent()['events'][2]['next']['persistent_controls']['/srv/www/wp-content/database/current.sqlite']['reserve_bytes']),

    'vfs filecontrol locking persistence current source next90 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsOpenLockFileControlCurrentSource::planLockingFileControlPersistence([])),
    'vfs filecontrol locking persistence current source next90 rejects bad lock' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run90(['open', 'lock(writer)'])),
    'vfs filecontrol locking persistence current source next90 rejects bad chunk' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run90(['open', 'lock(reserved)', 'file_control(chunk_size, -1)'])),
];
