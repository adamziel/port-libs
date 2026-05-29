<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsCapabilityPlan;
use PortLibs\LibSqlite\SQLiteVfsOpenLockFileControlCurrentSource;

$run103 = static fn (array $ops, array $options = []): array => SQLiteVfsOpenLockFileControlCurrentSource::planOpenDeviceCharacteristics($ops, $options + [
    'filename' => 'file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=shared&vfs=unix',
    'device_flags' => ['powersafe_overwrite', 'safe_append', 'sequential'],
    'sector_size' => 4096,
]);

$map = SQLiteVfsCapabilityPlan::deviceFlagMap();

$source = static function () use ($run103): array {
    static $result = null;
    if ($result === null) {
        $result = $run103([
            'open(file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=shared&vfs=unix)',
            'file_control(device_characteristics)',
            'lock(reserved)',
            'file_control(powersafe_overwrite, off)',
            'file_control(device_characteristics)',
            ['op' => 'open', 'filename' => 'file://localhost/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=private&vfs=unix', 'device_flags' => ['safe_append', 'sequential']],
            ['op' => 'filecontrol', 'handle' => 'db-2', 'control' => 'device_characteristics'],
            ['op' => 'filecontrol', 'handle' => 'db-2', 'control' => 'data_version'],
            ['op' => 'filecontrol', 'handle' => 'db-1', 'control' => 'data_version'],
            ['op' => 'lock', 'handle' => 'db-2', 'value' => 'pending'],
            ['op' => 'filecontrol', 'handle' => 'db-2', 'control' => 'reserve_bytes', 'value' => 24],
            ['op' => 'filecontrol', 'handle' => 'db-1', 'control' => 'data_version'],
        ]);
    }

    return $result;
};

$readonly = static fn (): array => $run103([
    ['op' => 'open', 'filename' => 'file:/srv/www/wp-content/database/archive.sqlite?mode=ro&immutable=1', 'device_flags' => ['atomic4k', 'undeletable_when_open', 'powersafe_overwrite']],
    'file_control(device_characteristics)',
    'lock(reserved)',
    'file_control(powersafe_overwrite, off)',
    'file_control(device_characteristics)',
]);

$memory = static fn (): array => $run103([
    ['op' => 'open', 'filename' => 'file::memory:?cache=shared', 'device_flags' => ['powersafe_overwrite', 'safe_append']],
    'file_control(device_characteristics)',
    'file_control(powersafe_overwrite, on)',
    'file_control(device_characteristics)',
]);

$nolock = static fn (): array => $run103([
    ['op' => 'open', 'filename' => 'file:/srv/www/wp-content/database/nolock.sqlite?mode=rw&nolock=1', 'device_flags' => ['safe_append', 'undeletable_when_open', 'powersafe_overwrite']],
    'file_control(device_characteristics)',
    'lock(reserved)',
]);

$explicitCurrent = static fn (): array => $run103([
    ['op' => 'open', 'filename' => 'file:/srv/www/wp-content/database/current.sqlite?mode=rw', 'device_flags' => ['batch_atomic', 'powersafe_overwrite'], 'sector_size' => 8192],
    'file_control(device_characteristics)',
    'lock(exclusive)',
    'file_control(powersafe_overwrite, off)',
    'file_control(device_characteristics)',
], [
    'filename' => 'file:/srv/www/wp-content/database/current.sqlite?mode=rw',
    'current' => [
        'persistent_controls' => [
            '/srv/www/wp-content/database/current.sqlite' => [
                'powersafe_overwrite' => true,
                'data_version' => 6,
            ],
        ],
        'persistent_generations' => [
            '/srv/www/wp-content/database/current.sqlite' => 3,
        ],
    ],
]);

return [
    'vfs xopen device characteristics current source next103 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-xopen-device-characteristics-current-source-next103', $source()['dependencies'], true)),
    'vfs xopen device characteristics current source next103 xopen dependency' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-xopen', $source()['dependencies'], true)),
    'vfs xopen device characteristics current source next103 xdevice dependency' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-xdevicecharacteristics', $source()['dependencies'], true)),
    'vfs xopen device characteristics current source next103 final status ok' => static fn (TestRunner $t) => $t->same('ok', $source()['status']),
    'vfs xopen device characteristics current source next103 decoded first source' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $source()['events'][0]['source_key']),
    'vfs xopen device characteristics current source next103 shared xopen flag' => static fn (TestRunner $t) => $t->same(true, in_array('sharedcache', $source()['events'][0]['xopen_flags'], true)),
    'vfs xopen device characteristics current source next103 readwrite xopen flag' => static fn (TestRunner $t) => $t->same(true, in_array('readwrite', $source()['events'][0]['xopen_flags'], true)),
    'vfs xopen device characteristics current source next103 sector size from open' => static fn (TestRunner $t) => $t->same(4096, $source()['events'][0]['sector_size']),
    'vfs xopen device characteristics current source next103 first device flags' => static fn (TestRunner $t) => $t->same(['safe_append', 'sequential', 'powersafe_overwrite'], $source()['events'][0]['device_flags']),
    'vfs xopen device characteristics current source next103 first device bitmask' => static fn (TestRunner $t) => $t->same($map['safe_append'] | $map['sequential'] | $map['powersafe_overwrite'], $source()['events'][0]['device_characteristics']),
    'vfs xopen device characteristics current source next103 first powersafe control true' => static fn (TestRunner $t) => $t->same(true, $source()['events'][0]['powersafe_overwrite']),
    'vfs xopen device characteristics current source next103 first read reports bitmask' => static fn (TestRunner $t) => $t->same($map['safe_append'] | $map['sequential'] | $map['powersafe_overwrite'], $source()['events'][1]['value']),
    'vfs xopen device characteristics current source next103 first read reports flags' => static fn (TestRunner $t) => $t->same(['safe_append', 'sequential', 'powersafe_overwrite'], $source()['events'][1]['device_flags']),
    'vfs xopen device characteristics current source next103 first read unchanged' => static fn (TestRunner $t) => $t->same(false, $source()['events'][1]['changed']),
    'vfs xopen device characteristics current source next103 reserved lock ok' => static fn (TestRunner $t) => $t->same('ok', $source()['events'][2]['status']),
    'vfs xopen device characteristics current source next103 powersafe off ok' => static fn (TestRunner $t) => $t->same('ok', $source()['events'][3]['status']),
    'vfs xopen device characteristics current source next103 powersafe off changed' => static fn (TestRunner $t) => $t->same(true, $source()['events'][3]['changed']),
    'vfs xopen device characteristics current source next103 powersafe off bumps generation' => static fn (TestRunner $t) => $t->same(2, $source()['events'][3]['source_generation']),
    'vfs xopen device characteristics current source next103 powersafe off handle flags' => static fn (TestRunner $t) => $t->same(['safe_append', 'sequential'], $source()['events'][3]['next']['handles']['db-1']['device_flags']),
    'vfs xopen device characteristics current source next103 powersafe off bitmask' => static fn (TestRunner $t) => $t->same($map['safe_append'] | $map['sequential'], $source()['events'][4]['value']),
    'vfs xopen device characteristics current source next103 powersafe off read false' => static fn (TestRunner $t) => $t->same(false, $source()['events'][4]['powersafe_overwrite']),
    'vfs xopen device characteristics current source next103 second open same source key' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $source()['events'][5]['source_key']),
    'vfs xopen device characteristics current source next103 second open private cache flag' => static fn (TestRunner $t) => $t->same(true, in_array('privatecache', $source()['events'][5]['xopen_flags'], true)),
    'vfs xopen device characteristics current source next103 second open generation current' => static fn (TestRunner $t) => $t->same(2, $source()['events'][5]['next']['handles']['db-2']['source_generation']),
    'vfs xopen device characteristics current source next103 second open reuses controls' => static fn (TestRunner $t) => $t->same(true, $source()['events'][5]['reused_controls']),
    'vfs xopen device characteristics current source next103 second open keeps requested flags' => static fn (TestRunner $t) => $t->same(['safe_append', 'sequential'], $source()['events'][5]['device_flags']),
    'vfs xopen device characteristics current source next103 second read bitmask' => static fn (TestRunner $t) => $t->same($map['safe_append'] | $map['sequential'], $source()['events'][6]['value']),
    'vfs xopen device characteristics current source next103 second read flags' => static fn (TestRunner $t) => $t->same(['safe_append', 'sequential'], $source()['events'][6]['device_flags']),
    'vfs xopen device characteristics current source next103 second data version fresh' => static fn (TestRunner $t) => $t->same(false, $source()['events'][7]['stale_current_source']),
    'vfs xopen device characteristics current source next103 first data version fresh after own write' => static fn (TestRunner $t) => $t->same(false, $source()['events'][8]['stale_current_source']),
    'vfs xopen device characteristics current source next103 pending lock ok' => static fn (TestRunner $t) => $t->same('ok', $source()['events'][9]['status']),
    'vfs xopen device characteristics current source next103 reserve bytes ok' => static fn (TestRunner $t) => $t->same('ok', $source()['events'][10]['status']),
    'vfs xopen device characteristics current source next103 reserve bytes bumps generation' => static fn (TestRunner $t) => $t->same(3, $source()['events'][10]['source_generation']),
    'vfs xopen device characteristics current source next103 first handle stale after sibling write' => static fn (TestRunner $t) => $t->same(true, $source()['events'][11]['stale_current_source']),
    'vfs xopen device characteristics current source next103 final open count' => static fn (TestRunner $t) => $t->same(2, $source()['next']['open_count']),
    'vfs xopen device characteristics current source next103 final generation count' => static fn (TestRunner $t) => $t->same(1, $source()['next']['persistent_generation_count']),

    'vfs xopen device characteristics current source next103 readonly xopen flag' => static fn (TestRunner $t) => $t->same(true, in_array('readonly', $readonly()['events'][0]['xopen_flags'], true)),
    'vfs xopen device characteristics current source next103 immutable xopen flag' => static fn (TestRunner $t) => $t->same(true, in_array('immutable', $readonly()['events'][0]['xopen_flags'], true)),
    'vfs xopen device characteristics current source next103 readonly device bitmask' => static fn (TestRunner $t) => $t->same($map['atomic4k'] | $map['undeletable_when_open'] | $map['powersafe_overwrite'], $readonly()['events'][1]['value']),
    'vfs xopen device characteristics current source next103 readonly lock blocked' => static fn (TestRunner $t) => $t->same('blocked', $readonly()['events'][2]['status']),
    'vfs xopen device characteristics current source next103 readonly powersafe ignored' => static fn (TestRunner $t) => $t->same('ignored', $readonly()['events'][3]['status']),
    'vfs xopen device characteristics current source next103 readonly characteristics unchanged' => static fn (TestRunner $t) => $t->same($map['atomic4k'] | $map['undeletable_when_open'] | $map['powersafe_overwrite'], $readonly()['events'][4]['value']),

    'vfs xopen device characteristics current source next103 memory xopen flag' => static fn (TestRunner $t) => $t->same(true, in_array('memory', $memory()['events'][0]['xopen_flags'], true)),
    'vfs xopen device characteristics current source next103 memory removes psow' => static fn (TestRunner $t) => $t->same(['safe_append'], $memory()['events'][0]['device_flags']),
    'vfs xopen device characteristics current source next103 memory bitmask' => static fn (TestRunner $t) => $t->same($map['safe_append'], $memory()['events'][1]['value']),
    'vfs xopen device characteristics current source next103 memory persist count zero' => static fn (TestRunner $t) => $t->same(0, $memory()['next']['persistent_control_count']),

    'vfs xopen device characteristics current source next103 nolock xopen flag' => static fn (TestRunner $t) => $t->same(true, in_array('nolock', $nolock()['events'][0]['xopen_flags'], true)),
    'vfs xopen device characteristics current source next103 nolock drops undeletable' => static fn (TestRunner $t) => $t->same(['safe_append', 'powersafe_overwrite'], $nolock()['events'][0]['device_flags']),
    'vfs xopen device characteristics current source next103 nolock bitmask' => static fn (TestRunner $t) => $t->same($map['safe_append'] | $map['powersafe_overwrite'], $nolock()['events'][1]['value']),
    'vfs xopen device characteristics current source next103 nolock lock blocked' => static fn (TestRunner $t) => $t->same('blocked', $nolock()['events'][2]['status']),

    'vfs xopen device characteristics current source next103 explicit sector size' => static fn (TestRunner $t) => $t->same(8192, $explicitCurrent()['events'][0]['sector_size']),
    'vfs xopen device characteristics current source next103 explicit generation restored' => static fn (TestRunner $t) => $t->same(3, $explicitCurrent()['events'][0]['next']['handles']['db-1']['source_generation']),
    'vfs xopen device characteristics current source next103 explicit batch atomic bitmask' => static fn (TestRunner $t) => $t->same($map['powersafe_overwrite'] | $map['batch_atomic'], $explicitCurrent()['events'][1]['value']),
    'vfs xopen device characteristics current source next103 explicit exclusive lock ok' => static fn (TestRunner $t) => $t->same('ok', $explicitCurrent()['events'][2]['status']),
    'vfs xopen device characteristics current source next103 explicit powersafe off bitmask' => static fn (TestRunner $t) => $t->same($map['batch_atomic'], $explicitCurrent()['events'][4]['value']),
    'vfs xopen device characteristics current source next103 rejects bad flag' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run103([['op' => 'open', 'device_flags' => ['bogus']]])),
    'vfs xopen device characteristics current source next103 rejects bad sector' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run103([['op' => 'open', 'sector_size' => 1000]])),
    'vfs xopen device characteristics current source next103 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsOpenLockFileControlCurrentSource::planOpenDeviceCharacteristics([])),
];
