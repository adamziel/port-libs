<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsLockByteUriShmCurrentSourceNext97;

$tests = [];

$run135 = static fn (array $ops, array $options = []): array => SQLiteVfsLockByteUriShmCurrentSourceNext97::currentSourceNext135($ops, $options + [
    'filename' => 'file:/srv/www/wp-content/database/wp%20fresh.sqlite?mode=rw&cache=shared&vfs=unix-dotfile',
]);

$wordpress = static function () use ($run135): array {
    static $result = null;
    if ($result === null) {
        $result = $run135([
            'open(file:/srv/www/wp-content/database/wp%20fresh.sqlite-shm?mode=rw&cache=shared&vfs=unix-dotfile&nolock=0)',
            'open(file:/srv/www/wp-content/database/wp%20fresh.sqlite?mode=rw&cache=shared&vfs=unix-dotfile&nolock=0)',
            'lock reserved wp-import 19 on main',
            'file_control(persist_wal, on)',
            'source(shm)',
            'file_control(chunk_size, 8192)',
            ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => 'vfs'],
            'file_control(data_version)',
            'file_control(data_version, refresh)',
            'file_control(chunk_size, 8192)',
            'open(file:/srv/www/wp-content/database/wp%20fresh.sqlite-wal?mode=rw&cache=shared&vfs=unix-dotfile&checkpoint_fullfsync=1)',
            'source(main)',
            'file_control(data_version, refresh)',
            'file_control(reserve_bytes, 32)',
            'source(wal)',
            'file_control(powersafe_overwrite, on)',
            ['op' => 'filecontrol', 'control' => 'uri_boolean', 'value' => ['parameter' => 'checkpoint_fullfsync', 'default' => false]],
            'file_control(data_version, refresh)',
            'file_control(powersafe_overwrite, on)',
            'source(shm)',
            'file_control(data_version)',
            'file_control(data_version, refresh)',
            'file_control(lock_timeout, 250)',
        ]);
    }

    return $result;
};

$noWriteLock = static fn (): array => $run135([
    'open(file:/srv/www/wp-content/database/no-lock.sqlite-shm?mode=rw&cache=shared)',
    'open(file:/srv/www/wp-content/database/no-lock.sqlite?mode=rw&cache=shared)',
    'lock reserved wp-import 19 on main',
    'file_control(persist_wal, on)',
    'lock none wp-import 19 on main',
    'source(shm)',
    'file_control(chunk_size, 4096)',
]);

$readonly = static fn (): array => $run135([
    'open(file:/srv/www/wp-content/database/readonly.sqlite-shm?mode=ro&immutable=1)',
    'open(file:/srv/www/wp-content/database/readonly.sqlite?mode=rw)',
    'lock reserved wp-import 19 on main',
    'file_control(persist_wal, on)',
    'source(shm)',
    'file_control(chunk_size, 4096)',
]);

$seeded = static fn (): array => $run135([
    'open(file:/srv/www/wp-content/database/seeded-next135.sqlite-shm?mode=rw)',
    'file_control(data_version)',
    'file_control(data_version, refresh)',
    'file_control(chunk_size, 2048)',
], [
    'current' => [
        'persistent_generations' => [
            '/srv/www/wp-content/database/seeded-next135.sqlite' => 7,
        ],
        'persistent_controls' => [
            '/srv/www/wp-content/database/seeded-next135.sqlite' => ['data_version' => 7],
        ],
        'lock_holders' => [
            '/srv/www/wp-content/database/seeded-next135.sqlite' => ['wp-import' => 'reserved'],
        ],
    ],
]);

$tests['vfs locking uri filecontrol current source next135 dependency marker'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-locking-uri-filecontrol-current-source-next135', $wordpress()['dependencies'], true));
$tests['vfs locking uri filecontrol current source next135 stale write dependency'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-stale-write-refresh', $wordpress()['dependencies'], true));
$tests['vfs locking uri filecontrol current source next135 uri dependency preserved'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-uri-file-control', $wordpress()['dependencies'], true));
$tests['vfs locking uri filecontrol current source next135 event count'] = static fn (TestRunner $t) => $t->same(23, count($wordpress()['events']));
$tests['vfs locking uri filecontrol current source next135 shm owner decoded'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp fresh.sqlite', $wordpress()['events'][0]['owner']);
$tests['vfs locking uri filecontrol current source next135 shm first generation'] = static fn (TestRunner $t) => $t->same(1, $wordpress()['events'][0]['next']['handles']['vfs97-1']['source_generation']);
$tests['vfs locking uri filecontrol current source next135 main second handle'] = static fn (TestRunner $t) => $t->same('main', $wordpress()['events'][1]['source']);
$tests['vfs locking uri filecontrol current source next135 reserved byte lock planned'] = static fn (TestRunner $t) => $t->same('planned', $wordpress()['events'][2]['status']);
$tests['vfs locking uri filecontrol current source next135 persist wal ok'] = static fn (TestRunner $t) => $t->same('ok', $wordpress()['events'][3]['status']);
$tests['vfs locking uri filecontrol current source next135 persist wal generation two'] = static fn (TestRunner $t) => $t->same(2, $wordpress()['events'][3]['source_generation']);
$tests['vfs locking uri filecontrol current source next135 persist wal marks shm stale'] = static fn (TestRunner $t) => $t->same(['vfs97-1'], $wordpress()['events'][3]['stale_handles']);
$tests['vfs locking uri filecontrol current source next135 selects stale shm'] = static fn (TestRunner $t) => $t->same('shm', $wordpress()['events'][4]['source']);
$tests['vfs locking uri filecontrol current source next135 blocks stale shm write'] = static fn (TestRunner $t) => $t->same('blocked', $wordpress()['events'][5]['status']);
$tests['vfs locking uri filecontrol current source next135 stale shm write reason'] = static fn (TestRunner $t) => $t->same('stale_current_source_requires_data_version_refresh', $wordpress()['events'][5]['reason']);
$tests['vfs locking uri filecontrol current source next135 blocked stale write opened generation'] = static fn (TestRunner $t) => $t->same(1, $wordpress()['events'][5]['opened_generation']);
$tests['vfs locking uri filecontrol current source next135 blocked stale write current generation'] = static fn (TestRunner $t) => $t->same(2, $wordpress()['events'][5]['source_generation']);
$tests['vfs locking uri filecontrol current source next135 blocked stale write changed false'] = static fn (TestRunner $t) => $t->same(false, $wordpress()['events'][5]['changed']);
$tests['vfs locking uri filecontrol current source next135 blocked stale write does not store chunk'] = static fn (TestRunner $t) => $t->same(false, array_key_exists('chunk_size', $wordpress()['events'][5]['next']['owners']['/srv/www/wp-content/database/wp fresh.sqlite']['controls']));
$tests['vfs locking uri filecontrol current source next135 uri parameter still reads while stale'] = static fn (TestRunner $t) => $t->same('unix-dotfile', $wordpress()['events'][6]['value']);
$tests['vfs locking uri filecontrol current source next135 uri parameter routed to current source'] = static fn (TestRunner $t) => $t->same('current-source-uri', $wordpress()['events'][6]['routed_to']);
$tests['vfs locking uri filecontrol current source next135 stale data version read ok'] = static fn (TestRunner $t) => $t->same('ok', $wordpress()['events'][7]['status']);
$tests['vfs locking uri filecontrol current source next135 stale data version flag true'] = static fn (TestRunner $t) => $t->same(true, $wordpress()['events'][7]['stale_current_source']);
$tests['vfs locking uri filecontrol current source next135 refresh changed'] = static fn (TestRunner $t) => $t->same(true, $wordpress()['events'][8]['changed']);
$tests['vfs locking uri filecontrol current source next135 refresh clears stale flag'] = static fn (TestRunner $t) => $t->same(false, $wordpress()['events'][8]['stale_current_source']);
$tests['vfs locking uri filecontrol current source next135 refreshed shm write ok'] = static fn (TestRunner $t) => $t->same('ok', $wordpress()['events'][9]['status']);
$tests['vfs locking uri filecontrol current source next135 refreshed shm write stores chunk'] = static fn (TestRunner $t) => $t->same(8192, $wordpress()['events'][9]['next']['owners']['/srv/www/wp-content/database/wp fresh.sqlite']['controls']['chunk_size']);
$tests['vfs locking uri filecontrol current source next135 refreshed shm write bumps generation'] = static fn (TestRunner $t) => $t->same(3, $wordpress()['events'][9]['source_generation']);
$tests['vfs locking uri filecontrol current source next135 wal opens at generation three'] = static fn (TestRunner $t) => $t->same(3, $wordpress()['events'][10]['next']['handles']['vfs97-3']['source_generation']);
$tests['vfs locking uri filecontrol current source next135 main refresh changed'] = static fn (TestRunner $t) => $t->same(true, $wordpress()['events'][12]['changed']);
$tests['vfs locking uri filecontrol current source next135 main refresh clears main stale'] = static fn (TestRunner $t) => $t->same(false, $wordpress()['events'][12]['stale_current_source']);
$tests['vfs locking uri filecontrol current source next135 main reserve ok'] = static fn (TestRunner $t) => $t->same('ok', $wordpress()['events'][13]['status']);
$tests['vfs locking uri filecontrol current source next135 main reserve generation four'] = static fn (TestRunner $t) => $t->same(4, $wordpress()['events'][13]['source_generation']);
$tests['vfs locking uri filecontrol current source next135 main reserve marks sidecars stale'] = static fn (TestRunner $t) => $t->same(['vfs97-1', 'vfs97-3'], $wordpress()['events'][13]['stale_handles']);
$tests['vfs locking uri filecontrol current source next135 stale wal write blocked'] = static fn (TestRunner $t) => $t->same('blocked', $wordpress()['events'][15]['status']);
$tests['vfs locking uri filecontrol current source next135 stale wal write reason'] = static fn (TestRunner $t) => $t->same('stale_current_source_requires_data_version_refresh', $wordpress()['events'][15]['reason']);
$tests['vfs locking uri filecontrol current source next135 stale wal write opened generation'] = static fn (TestRunner $t) => $t->same(3, $wordpress()['events'][15]['opened_generation']);
$tests['vfs locking uri filecontrol current source next135 uri boolean reads stale wal'] = static fn (TestRunner $t) => $t->same(true, $wordpress()['events'][16]['value']);
$tests['vfs locking uri filecontrol current source next135 wal refresh changed'] = static fn (TestRunner $t) => $t->same(true, $wordpress()['events'][17]['changed']);
$tests['vfs locking uri filecontrol current source next135 wal refresh leaves stale shm'] = static fn (TestRunner $t) => $t->same(['vfs97-1'], $wordpress()['events'][17]['stale_handles']);
$tests['vfs locking uri filecontrol current source next135 refreshed wal write ok'] = static fn (TestRunner $t) => $t->same('ok', $wordpress()['events'][18]['status']);
$tests['vfs locking uri filecontrol current source next135 refreshed wal write bumps generation'] = static fn (TestRunner $t) => $t->same(5, $wordpress()['events'][18]['source_generation']);
$tests['vfs locking uri filecontrol current source next135 shm stale after wal write'] = static fn (TestRunner $t) => $t->same(true, $wordpress()['events'][20]['stale_current_source']);
$tests['vfs locking uri filecontrol current source next135 shm stale reports generation five'] = static fn (TestRunner $t) => $t->same(5, $wordpress()['events'][20]['value']);
$tests['vfs locking uri filecontrol current source next135 shm second refresh changed'] = static fn (TestRunner $t) => $t->same(true, $wordpress()['events'][21]['changed']);
$tests['vfs locking uri filecontrol current source next135 nonwrite filecontrol allowed after refresh'] = static fn (TestRunner $t) => $t->same('ok', $wordpress()['events'][22]['status']);
$tests['vfs locking uri filecontrol current source next135 lock timeout stored'] = static fn (TestRunner $t) => $t->same(250, $wordpress()['events'][22]['next']['owners']['/srv/www/wp-content/database/wp fresh.sqlite']['controls']['lock_timeout']);
$tests['vfs locking uri filecontrol current source next135 final persist wal'] = static fn (TestRunner $t) => $t->same(true, $wordpress()['next']['owners']['/srv/www/wp-content/database/wp fresh.sqlite']['controls']['persist_wal']);
$tests['vfs locking uri filecontrol current source next135 final reserve bytes'] = static fn (TestRunner $t) => $t->same(32, $wordpress()['next']['owners']['/srv/www/wp-content/database/wp fresh.sqlite']['controls']['reserve_bytes']);
$tests['vfs locking uri filecontrol current source next135 final powersafe overwrite'] = static fn (TestRunner $t) => $t->same(true, $wordpress()['next']['owners']['/srv/www/wp-content/database/wp fresh.sqlite']['controls']['powersafe_overwrite']);
$tests['vfs locking uri filecontrol current source next135 final generation five'] = static fn (TestRunner $t) => $t->same(5, $wordpress()['next']['owners']['/srv/www/wp-content/database/wp fresh.sqlite']['generation']);
$tests['vfs locking uri filecontrol current source next135 final owner count'] = static fn (TestRunner $t) => $t->same(1, $wordpress()['next']['owner_count']);
$tests['vfs locking uri filecontrol current source next135 final current source shm'] = static fn (TestRunner $t) => $t->same('shm', $wordpress()['next']['current_source']);

$tests['vfs locking uri filecontrol current source next135 no lock stale write blocked by lock first'] = static fn (TestRunner $t) => $t->same('requires_reserved_pending_or_exclusive_byte_lock', $noWriteLock()['events'][6]['reason']);
$tests['vfs locking uri filecontrol current source next135 no lock stale write still stale'] = static fn (TestRunner $t) => $t->same(true, $noWriteLock()['events'][6]['stale_current_source']);
$tests['vfs locking uri filecontrol current source next135 no lock stale write unchanged'] = static fn (TestRunner $t) => $t->same(false, $noWriteLock()['events'][6]['changed']);
$tests['vfs locking uri filecontrol current source next135 readonly stale write ignored first'] = static fn (TestRunner $t) => $t->same('ignored', $readonly()['events'][5]['status']);
$tests['vfs locking uri filecontrol current source next135 readonly stale write reason'] = static fn (TestRunner $t) => $t->same('readonly_handle', $readonly()['events'][5]['reason']);
$tests['vfs locking uri filecontrol current source next135 readonly stale flag true'] = static fn (TestRunner $t) => $t->same(true, $readonly()['events'][5]['stale_current_source']);
$tests['vfs locking uri filecontrol current source next135 seeded data version seven'] = static fn (TestRunner $t) => $t->same(7, $seeded()['events'][1]['value']);
$tests['vfs locking uri filecontrol current source next135 seeded refresh unchanged'] = static fn (TestRunner $t) => $t->same(false, $seeded()['events'][2]['changed']);
$tests['vfs locking uri filecontrol current source next135 seeded fresh write ok'] = static fn (TestRunner $t) => $t->same('ok', $seeded()['events'][3]['status']);
$tests['vfs locking uri filecontrol current source next135 seeded fresh write generation eight'] = static fn (TestRunner $t) => $t->same(8, $seeded()['events'][3]['source_generation']);
$tests['vfs locking uri filecontrol current source next135 rejects empty operations'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsLockByteUriShmCurrentSourceNext97::currentSourceNext135([]));
$tests['vfs locking uri filecontrol current source next135 rejects bad stale refresh handle'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run135(['file_control(data_version, refresh)']));

return $tests;
