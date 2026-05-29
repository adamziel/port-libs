<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsShmFileControlLockCurrentSourcePlan;

$run104 = static fn (array $ops, array $options = []): array => SQLiteVfsShmFileControlLockCurrentSourcePlan::planUriShmFileControlWithGeneration($ops, $options + [
    'filename' => 'file://localhost/srv/www/wp-content/database/wp%20cache.sqlite?mode=rw&cache=shared',
]);

$sidecarGeneration = static function () use ($run104): array {
    static $result = null;
    if ($result === null) {
        $result = $run104([
            'open(shm, file://localhost/srv/www/wp-content/database/wp%20cache.sqlite-shm?mode=rw&cache=shared)',
            'open(main, file:/srv/www/wp-content/database/wp%20cache.sqlite?mode=rw&cache=shared)',
            'source(shm)',
            'file_control(data_version)',
            'source(main)',
            'file_control(persist_wal, on)',
            'source(shm)',
            'file_control(data_version)',
            'shm_lock(read0, shared)',
            'open(wal, file:/srv/www/wp-content/database/wp%20cache.sqlite-wal?mode=rw&cache=shared)',
            'file_control(reserve_bytes, 48)',
            'source(main)',
            'file_control(data_version)',
            'source(wal)',
            'file_control(data_version)',
        ]);
    }

    return $result;
};

$explicitCurrent = static fn (): array => $run104([
    'open(shm, file:/srv/www/wp-content/database/wp%20cache.sqlite-shm?mode=rw)',
    'open(main, file:/srv/www/wp-content/database/wp%20cache.sqlite?mode=rw)',
    'file_control(data_version)',
    'file_control(chunk_size, 8192)',
    'source(shm)',
    'file_control(data_version)',
], [
    'current' => [
        'persistent_controls' => [
            '/srv/www/wp-content/database/wp cache.sqlite' => [
                'persist_wal' => true,
                'data_version' => 12,
            ],
        ],
        'persistent_generations' => [
            '/srv/www/wp-content/database/wp cache.sqlite' => 12,
        ],
    ],
]);

$readonly = static fn (): array => $run104([
    'open(main, file:/srv/www/wp-content/database/archive.sqlite?mode=ro)',
    'open(shm, file:/srv/www/wp-content/database/archive.sqlite-shm?mode=ro)',
    'file_control(persist_wal, on)',
    'file_control(mmap_size, 32768)',
    'file_control(data_version)',
    'source(shm)',
    'file_control(data_version)',
]);

$invalid = static fn (array $ops): array => $run104($ops);

return [
    'vfs uri shm filecontrol current source next104 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-uri-shm-filecontrol-current-source-next104', $sidecarGeneration()['dependencies'], true)),
    'vfs uri shm filecontrol current source next104 final status ok' => static fn (TestRunner $t) => $t->same('ok', $sidecarGeneration()['status']),
    'vfs uri shm filecontrol current source next104 shm opens first' => static fn (TestRunner $t) => $t->same('shm', $sidecarGeneration()['events'][0]['source']),
    'vfs uri shm filecontrol current source next104 shm owner decoded' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp cache.sqlite', $sidecarGeneration()['events'][0]['owner']),
    'vfs uri shm filecontrol current source next104 shm generation one' => static fn (TestRunner $t) => $t->same(1, $sidecarGeneration()['events'][0]['source_generation']),
    'vfs uri shm filecontrol current source next104 main owner matches shm' => static fn (TestRunner $t) => $t->same($sidecarGeneration()['events'][0]['owner'], $sidecarGeneration()['events'][1]['owner']),
    'vfs uri shm filecontrol current source next104 main generation one' => static fn (TestRunner $t) => $t->same(1, $sidecarGeneration()['events'][1]['source_generation']),
    'vfs uri shm filecontrol current source next104 source shm selected' => static fn (TestRunner $t) => $t->same('shm', $sidecarGeneration()['events'][2]['next']['current_source']),
    'vfs uri shm filecontrol current source next104 data version via shm routes database' => static fn (TestRunner $t) => $t->same('database', $sidecarGeneration()['events'][3]['routed_to']),
    'vfs uri shm filecontrol current source next104 initial data version value one' => static fn (TestRunner $t) => $t->same(1, $sidecarGeneration()['events'][3]['value']),
    'vfs uri shm filecontrol current source next104 initial data version fresh' => static fn (TestRunner $t) => $t->same(false, $sidecarGeneration()['events'][3]['stale_current_source']),
    'vfs uri shm filecontrol current source next104 persist wal routes database' => static fn (TestRunner $t) => $t->same('database', $sidecarGeneration()['events'][5]['routed_to']),
    'vfs uri shm filecontrol current source next104 persist wal ok' => static fn (TestRunner $t) => $t->same('ok', $sidecarGeneration()['events'][5]['status']),
    'vfs uri shm filecontrol current source next104 persist wal generation bump' => static fn (TestRunner $t) => $t->same(2, $sidecarGeneration()['events'][5]['source_generation']),
    'vfs uri shm filecontrol current source next104 persist wal stored' => static fn (TestRunner $t) => $t->same(true, $sidecarGeneration()['events'][5]['next']['persistent_controls']['/srv/www/wp-content/database/wp cache.sqlite']['persist_wal']),
    'vfs uri shm filecontrol current source next104 persist wal data version stored' => static fn (TestRunner $t) => $t->same(2, $sidecarGeneration()['events'][5]['next']['persistent_controls']['/srv/www/wp-content/database/wp cache.sqlite']['data_version']),
    'vfs uri shm filecontrol current source next104 persist wal marks stale shm' => static fn (TestRunner $t) => $t->same(['vfs87-1'], $sidecarGeneration()['events'][5]['stale_handles']),
    'vfs uri shm filecontrol current source next104 main handle refreshed after write' => static fn (TestRunner $t) => $t->same(2, $sidecarGeneration()['events'][5]['next']['handles']['vfs87-2']['source_generation']),
    'vfs uri shm filecontrol current source next104 shm handle remains old generation' => static fn (TestRunner $t) => $t->same(1, $sidecarGeneration()['events'][5]['next']['handles']['vfs87-1']['source_generation']),
    'vfs uri shm filecontrol current source next104 stale shm detects data version' => static fn (TestRunner $t) => $t->same(true, $sidecarGeneration()['events'][7]['stale_current_source']),
    'vfs uri shm filecontrol current source next104 stale shm reports current generation' => static fn (TestRunner $t) => $t->same(2, $sidecarGeneration()['events'][7]['value']),
    'vfs uri shm filecontrol current source next104 stale shm reports opened generation' => static fn (TestRunner $t) => $t->same(1, $sidecarGeneration()['events'][7]['opened_generation']),
    'vfs uri shm filecontrol current source next104 stale handles list shm' => static fn (TestRunner $t) => $t->same(['vfs87-1'], $sidecarGeneration()['events'][7]['stale_handles']),
    'vfs uri shm filecontrol current source next104 shm lock remains on shm handle' => static fn (TestRunner $t) => $t->same('shared', $sidecarGeneration()['events'][8]['next']['handles']['vfs87-1']['shm_locks']['read0']),
    'vfs uri shm filecontrol current source next104 wal opens current generation' => static fn (TestRunner $t) => $t->same(2, $sidecarGeneration()['events'][9]['source_generation']),
    'vfs uri shm filecontrol current source next104 wal owner canonical' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp cache.sqlite', $sidecarGeneration()['events'][9]['owner']),
    'vfs uri shm filecontrol current source next104 reserve bytes ok' => static fn (TestRunner $t) => $t->same('ok', $sidecarGeneration()['events'][10]['status']),
    'vfs uri shm filecontrol current source next104 reserve bytes generation bump' => static fn (TestRunner $t) => $t->same(3, $sidecarGeneration()['events'][10]['source_generation']),
    'vfs uri shm filecontrol current source next104 reserve bytes stored' => static fn (TestRunner $t) => $t->same(48, $sidecarGeneration()['events'][10]['next']['persistent_controls']['/srv/www/wp-content/database/wp cache.sqlite']['reserve_bytes']),
    'vfs uri shm filecontrol current source next104 reserve marks stale shm and main' => static fn (TestRunner $t) => $t->same(['vfs87-1', 'vfs87-2'], $sidecarGeneration()['events'][10]['stale_handles']),
    'vfs uri shm filecontrol current source next104 main detects stale after wal write' => static fn (TestRunner $t) => $t->same(true, $sidecarGeneration()['events'][12]['stale_current_source']),
    'vfs uri shm filecontrol current source next104 main stale opened generation' => static fn (TestRunner $t) => $t->same(2, $sidecarGeneration()['events'][12]['opened_generation']),
    'vfs uri shm filecontrol current source next104 main sees current generation three' => static fn (TestRunner $t) => $t->same(3, $sidecarGeneration()['events'][12]['value']),
    'vfs uri shm filecontrol current source next104 wal data version fresh' => static fn (TestRunner $t) => $t->same(false, $sidecarGeneration()['events'][14]['stale_current_source']),
    'vfs uri shm filecontrol current source next104 wal opened generation current' => static fn (TestRunner $t) => $t->same(3, $sidecarGeneration()['events'][14]['opened_generation']),
    'vfs uri shm filecontrol current source next104 final current source wal' => static fn (TestRunner $t) => $t->same('wal', $sidecarGeneration()['next']['current_source']),
    'vfs uri shm filecontrol current source next104 final open by source' => static fn (TestRunner $t) => $t->same(['main' => 1, 'wal' => 1, 'shm' => 1], $sidecarGeneration()['next']['open_by_source']),
    'vfs uri shm filecontrol current source next104 final generation count one' => static fn (TestRunner $t) => $t->same(1, $sidecarGeneration()['next']['persistent_generation_count']),
    'vfs uri shm filecontrol current source next104 final shm lock count one' => static fn (TestRunner $t) => $t->same(1, $sidecarGeneration()['next']['shm_lock_count']),

    'vfs uri shm filecontrol current source next104 explicit shm opens at persisted generation' => static fn (TestRunner $t) => $t->same(12, $explicitCurrent()['events'][0]['source_generation']),
    'vfs uri shm filecontrol current source next104 explicit main opens at persisted generation' => static fn (TestRunner $t) => $t->same(12, $explicitCurrent()['events'][1]['source_generation']),
    'vfs uri shm filecontrol current source next104 explicit main data version fresh' => static fn (TestRunner $t) => $t->same(false, $explicitCurrent()['events'][2]['stale_current_source']),
    'vfs uri shm filecontrol current source next104 explicit chunk bump from persisted generation' => static fn (TestRunner $t) => $t->same(13, $explicitCurrent()['events'][3]['source_generation']),
    'vfs uri shm filecontrol current source next104 explicit chunk marks shm stale' => static fn (TestRunner $t) => $t->same(['vfs87-1'], $explicitCurrent()['events'][3]['stale_handles']),
    'vfs uri shm filecontrol current source next104 explicit shm stale data version' => static fn (TestRunner $t) => $t->same(true, $explicitCurrent()['events'][5]['stale_current_source']),
    'vfs uri shm filecontrol current source next104 explicit shm current value' => static fn (TestRunner $t) => $t->same(13, $explicitCurrent()['events'][5]['value']),

    'vfs uri shm filecontrol current source next104 readonly persist wal ignored' => static fn (TestRunner $t) => $t->same('ignored', $readonly()['events'][2]['status']),
    'vfs uri shm filecontrol current source next104 readonly generation unchanged after ignored write' => static fn (TestRunner $t) => $t->same(1, $readonly()['events'][2]['source_generation']),
    'vfs uri shm filecontrol current source next104 readonly mmap ok' => static fn (TestRunner $t) => $t->same('ok', $readonly()['events'][3]['status']),
    'vfs uri shm filecontrol current source next104 readonly mmap does not bump generation' => static fn (TestRunner $t) => $t->same(1, $readonly()['events'][3]['source_generation']),
    'vfs uri shm filecontrol current source next104 readonly main data version fresh' => static fn (TestRunner $t) => $t->same(false, $readonly()['events'][4]['stale_current_source']),
    'vfs uri shm filecontrol current source next104 readonly shm data version fresh' => static fn (TestRunner $t) => $t->same(false, $readonly()['events'][6]['stale_current_source']),

    'vfs uri shm filecontrol current source next104 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsShmFileControlLockCurrentSourcePlan::planUriShmFileControlWithGeneration([])),
    'vfs uri shm filecontrol current source next104 rejects remote authority' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $invalid(['open(main, file://example.com/srv/www/wp.sqlite?mode=rw)'])),
    'vfs uri shm filecontrol current source next104 rejects bad shm lock' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $invalid(['open(shm)', 'shm_lock(read9, shared)'])),
    'vfs uri shm filecontrol current source next104 rejects bad reserve bytes' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $invalid(['open(main)', ['op' => 'filecontrol', 'control' => 'reserve_bytes', 'value' => -1]])),
];
