<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsOpenLockFileControlCurrentSource;

$run99 = static fn (array $ops, array $options = []): array => SQLiteVfsOpenLockFileControlCurrentSource::planGeneratedSourceFileControls($ops, $options + [
    'filename' => 'file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=shared&vfs=unix',
]);

$freshness = static function () use ($run99): array {
    static $result = null;
    if ($result === null) {
        $result = $run99([
            'open(file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=shared&vfs=unix)',
            'open(file://localhost/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=private&vfs=unix)',
            ['op' => 'lock', 'handle' => 'db-1', 'value' => 'reserved'],
            ['op' => 'filecontrol', 'handle' => 'db-1', 'control' => 'persist_wal', 'value' => true],
            ['op' => 'filecontrol', 'handle' => 'db-2', 'control' => 'data_version'],
            ['op' => 'filecontrol', 'handle' => 'db-2', 'control' => 'mmap_size', 'value' => 65536],
            ['op' => 'filecontrol', 'handle' => 'db-2', 'control' => 'data_version'],
            ['op' => 'close', 'handle' => 'db-2'],
            'open(file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw)',
            ['op' => 'filecontrol', 'handle' => 'db-3', 'control' => 'data_version'],
            ['op' => 'lock', 'handle' => 'db-3', 'value' => 'pending'],
            ['op' => 'filecontrol', 'handle' => 'db-3', 'control' => 'reserve_bytes', 'value' => 32],
            ['op' => 'filecontrol', 'handle' => 'db-1', 'control' => 'data_version'],
        ]);
    }

    return $result;
};

$explicitCurrent = static fn (): array => $run99([
    'open(file:/srv/www/wp-content/database/current.sqlite?mode=rw)',
    'file_control(data_version)',
    'lock(reserved)',
    'file_control(powersafe_overwrite, off)',
    'file_control(data_version)',
], [
    'filename' => 'file:/srv/www/wp-content/database/current.sqlite?mode=rw',
    'current' => [
        'persistent_controls' => [
            '/srv/www/wp-content/database/current.sqlite' => [
                'persist_wal' => true,
                'data_version' => 9,
            ],
        ],
        'persistent_generations' => [
            '/srv/www/wp-content/database/current.sqlite' => 4,
        ],
    ],
]);

$memory = static fn (): array => $run99([
    'open(file::memory:?cache=shared)',
    'lock(reserved)',
    'file_control(persist_wal, on)',
    'file_control(data_version)',
]);

$readonly = static fn (): array => $run99([
    'open(file:/srv/www/wp-content/database/archive.sqlite?mode=ro)',
    'lock(reserved)',
    'file_control(persist_wal, on)',
    'file_control(data_version)',
]);

return [
    'vfs open lock filecontrol current source next99 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-open-lock-filecontrol-current-source-next99', $freshness()['dependencies'], true)),
    'vfs open lock filecontrol current source next99 final status ok' => static fn (TestRunner $t) => $t->same('ok', $freshness()['status']),
    'vfs open lock filecontrol current source next99 first open generation' => static fn (TestRunner $t) => $t->same(1, $freshness()['events'][0]['next']['handles']['db-1']['source_generation']),
    'vfs open lock filecontrol current source next99 second open same decoded source' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $freshness()['events'][1]['source_key']),
    'vfs open lock filecontrol current source next99 second open generation' => static fn (TestRunner $t) => $t->same(1, $freshness()['events'][1]['next']['handles']['db-2']['source_generation']),
    'vfs open lock filecontrol current source next99 writer lock ok' => static fn (TestRunner $t) => $t->same('ok', $freshness()['events'][2]['status']),
    'vfs open lock filecontrol current source next99 persist wal ok' => static fn (TestRunner $t) => $t->same('ok', $freshness()['events'][3]['status']),
    'vfs open lock filecontrol current source next99 persist wal changed' => static fn (TestRunner $t) => $t->same(true, $freshness()['events'][3]['changed']),
    'vfs open lock filecontrol current source next99 source generation bumped' => static fn (TestRunner $t) => $t->same(2, $freshness()['events'][3]['source_generation']),
    'vfs open lock filecontrol current source next99 stale reader listed' => static fn (TestRunner $t) => $t->same(['db-2'], $freshness()['events'][3]['stale_handles']),
    'vfs open lock filecontrol current source next99 writer handle refreshed' => static fn (TestRunner $t) => $t->same(2, $freshness()['events'][3]['next']['handles']['db-1']['source_generation']),
    'vfs open lock filecontrol current source next99 reader still opened at old source' => static fn (TestRunner $t) => $t->same(1, $freshness()['events'][3]['next']['handles']['db-2']['source_generation']),
    'vfs open lock filecontrol current source next99 data version query ok' => static fn (TestRunner $t) => $t->same('ok', $freshness()['events'][4]['status']),
    'vfs open lock filecontrol current source next99 data version reports current generation' => static fn (TestRunner $t) => $t->same(2, $freshness()['events'][4]['value']),
    'vfs open lock filecontrol current source next99 data version reports opened generation' => static fn (TestRunner $t) => $t->same(1, $freshness()['events'][4]['opened_generation']),
    'vfs open lock filecontrol current source next99 data version marks stale' => static fn (TestRunner $t) => $t->same(true, $freshness()['events'][4]['stale_current_source']),
    'vfs open lock filecontrol current source next99 mmap read control ok' => static fn (TestRunner $t) => $t->same('ok', $freshness()['events'][5]['status']),
    'vfs open lock filecontrol current source next99 mmap does not bump generation' => static fn (TestRunner $t) => $t->same(2, $freshness()['events'][5]['source_generation']),
    'vfs open lock filecontrol current source next99 reader remains stale after read control' => static fn (TestRunner $t) => $t->same(true, $freshness()['events'][6]['stale_current_source']),
    'vfs open lock filecontrol current source next99 close reader status' => static fn (TestRunner $t) => $t->same('closed', $freshness()['events'][7]['status']),
    'vfs open lock filecontrol current source next99 reopen reuses controls' => static fn (TestRunner $t) => $t->same(true, $freshness()['events'][8]['reused_controls']),
    'vfs open lock filecontrol current source next99 reopen source generation current' => static fn (TestRunner $t) => $t->same(2, $freshness()['events'][8]['next']['handles']['db-3']['source_generation']),
    'vfs open lock filecontrol current source next99 reopened data version fresh' => static fn (TestRunner $t) => $t->same(false, $freshness()['events'][9]['stale_current_source']),
    'vfs open lock filecontrol current source next99 reopened data version value' => static fn (TestRunner $t) => $t->same(2, $freshness()['events'][9]['value']),
    'vfs open lock filecontrol current source next99 pending lock ok' => static fn (TestRunner $t) => $t->same('ok', $freshness()['events'][10]['status']),
    'vfs open lock filecontrol current source next99 reserve bytes ok' => static fn (TestRunner $t) => $t->same('ok', $freshness()['events'][11]['status']),
    'vfs open lock filecontrol current source next99 reserve bumps generation' => static fn (TestRunner $t) => $t->same(3, $freshness()['events'][11]['source_generation']),
    'vfs open lock filecontrol current source next99 stale original writer listed' => static fn (TestRunner $t) => $t->same(['db-1'], $freshness()['events'][11]['stale_handles']),
    'vfs open lock filecontrol current source next99 original writer detects stale' => static fn (TestRunner $t) => $t->same(true, $freshness()['events'][12]['stale_current_source']),
    'vfs open lock filecontrol current source next99 original writer data version value' => static fn (TestRunner $t) => $t->same(3, $freshness()['events'][12]['value']),
    'vfs open lock filecontrol current source next99 persistent generation stored' => static fn (TestRunner $t) => $t->same(3, $freshness()['events'][12]['next']['persistent_generations']['/srv/www/wp-content/database/wp copy.sqlite']),
    'vfs open lock filecontrol current source next99 final open count two' => static fn (TestRunner $t) => $t->same(2, $freshness()['next']['open_count']),
    'vfs open lock filecontrol current source next99 final generation count one' => static fn (TestRunner $t) => $t->same(1, $freshness()['next']['persistent_generation_count']),

    'vfs open lock filecontrol current source next99 explicit current reused controls' => static fn (TestRunner $t) => $t->same(true, $explicitCurrent()['events'][0]['reused_controls']),
    'vfs open lock filecontrol current source next99 explicit current generation restored' => static fn (TestRunner $t) => $t->same(4, $explicitCurrent()['events'][0]['next']['handles']['db-1']['source_generation']),
    'vfs open lock filecontrol current source next99 explicit data version fresh' => static fn (TestRunner $t) => $t->same(false, $explicitCurrent()['events'][1]['stale_current_source']),
    'vfs open lock filecontrol current source next99 explicit data version value' => static fn (TestRunner $t) => $t->same(4, $explicitCurrent()['events'][1]['value']),
    'vfs open lock filecontrol current source next99 explicit write ok' => static fn (TestRunner $t) => $t->same('ok', $explicitCurrent()['events'][3]['status']),
    'vfs open lock filecontrol current source next99 explicit write bumps from persisted generation' => static fn (TestRunner $t) => $t->same(5, $explicitCurrent()['events'][3]['source_generation']),
    'vfs open lock filecontrol current source next99 explicit next data version current' => static fn (TestRunner $t) => $t->same(5, $explicitCurrent()['events'][4]['value']),

    'vfs open lock filecontrol current source next99 memory source has no persistent generation' => static fn (TestRunner $t) => $t->same(0, $memory()['next']['persistent_generation_count']),
    'vfs open lock filecontrol current source next99 memory persist status ok' => static fn (TestRunner $t) => $t->same('ok', $memory()['events'][2]['status']),
    'vfs open lock filecontrol current source next99 memory data version stays local' => static fn (TestRunner $t) => $t->same(1, $memory()['events'][3]['value']),
    'vfs open lock filecontrol current source next99 memory data version fresh' => static fn (TestRunner $t) => $t->same(false, $memory()['events'][3]['stale_current_source']),

    'vfs open lock filecontrol current source next99 readonly persist ignored' => static fn (TestRunner $t) => $t->same('ignored', $readonly()['events'][2]['status']),
    'vfs open lock filecontrol current source next99 readonly generation unchanged' => static fn (TestRunner $t) => $t->same(1, $readonly()['events'][3]['value']),
    'vfs open lock filecontrol current source next99 readonly data version fresh' => static fn (TestRunner $t) => $t->same(false, $readonly()['events'][3]['stale_current_source']),
    'vfs open lock filecontrol current source next99 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsOpenLockFileControlCurrentSource::planGeneratedSourceFileControls([])),
];
