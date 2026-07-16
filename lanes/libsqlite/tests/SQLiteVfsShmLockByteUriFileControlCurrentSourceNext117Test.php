<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsLockByteUriShmCurrentSourceNext;

$tests = [];

$run117 = static fn (array $ops, array $options = []): array => SQLiteVfsLockByteUriShmCurrentSourceNext::planShmLockByteUriFileControl($ops, $options + [
    'filename' => 'file://localhost/srv/www/wp-content/database/wp%20refresh.sqlite?mode=rw&cache=shared',
]);

$application = static function () use ($run117): array {
    static $result = null;
    if ($result === null) {
        $result = $run117([
            'open(file://localhost/srv/www/wp-content/database/wp%20refresh.sqlite-shm?mode=rw&cache=shared)',
            'shm read0 shared wp-reader',
            'file_control(data_version)',
            'open(file:/srv/www/wp-content/database/wp%20refresh.sqlite?mode=rw&cache=shared)',
            'lock reserved wp-import 19 on main',
            'file_control(persist_wal, on)',
            'source(shm)',
            'file_control(data_version)',
            'file_control(data_version, refresh)',
            'file_control(data_version)',
            'open(file:/srv/www/wp-content/database/wp%20refresh.sqlite-wal?mode=rw&cache=shared)',
            'file_control(data_version)',
            'source(main)',
            'file_control(reserve_bytes, 24)',
            'source(wal)',
            'file_control(data_version)',
            'file_control(data_version, refresh)',
            'file_control(data_version)',
            'yield wp-reader',
            'lock exclusive wp-import 19',
            'file_control(chunk_size, 4096)',
        ]);
    }

    return $result;
};

$seeded = static fn (): array => $run117([
    'open(file:/srv/www/wp-content/database/seeded-refresh.sqlite-shm?mode=rw)',
    'file_control(data_version)',
    'file_control(data_version, refresh)',
    'file_control(data_version)',
], [
    'current' => [
        'persistent_controls' => [
            '/srv/www/wp-content/database/seeded-refresh.sqlite' => ['data_version' => 5],
        ],
        'persistent_generations' => [
            '/srv/www/wp-content/database/seeded-refresh.sqlite' => 5,
        ],
    ],
]);

$readonly = static fn (): array => $run117([
    'open(file:/srv/www/wp-content/database/readonly-refresh.sqlite-shm?mode=ro&immutable=1)',
    'file_control(data_version)',
    'file_control(data_version, refresh)',
    'file_control(data_version)',
]);

$tests['vfs shm lockbyte uri filecontrol current source next117 dependency marker'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-shm-lockbyte-uri-filecontrol-current-source-next117', $application()['dependencies'], true));
$tests['vfs shm lockbyte uri filecontrol current source next117 keeps data version dependency'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-file-control-data-version', $application()['dependencies'], true));
$tests['vfs shm lockbyte uri filecontrol current source next117 event count'] = static fn (TestRunner $t) => $t->same(21, count($application()['events']));
$tests['vfs shm lockbyte uri filecontrol current source next117 first open shm'] = static fn (TestRunner $t) => $t->same('shm', $application()['events'][0]['source']);
$tests['vfs shm lockbyte uri filecontrol current source next117 localhost uri decoded owner'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp refresh.sqlite', $application()['events'][0]['owner']);
$tests['vfs shm lockbyte uri filecontrol current source next117 initial shm generation one'] = static fn (TestRunner $t) => $t->same(1, $application()['events'][0]['next']['handles']['vfs97-1']['source_generation']);
$tests['vfs shm lockbyte uri filecontrol current source next117 read lock acquired'] = static fn (TestRunner $t) => $t->same('acquired', $application()['events'][1]['status']);
$tests['vfs shm lockbyte uri filecontrol current source next117 initial data version fresh'] = static fn (TestRunner $t) => $t->same(false, $application()['events'][2]['stale_current_source']);
$tests['vfs shm lockbyte uri filecontrol current source next117 initial data version one'] = static fn (TestRunner $t) => $t->same(1, $application()['events'][2]['value']);
$tests['vfs shm lockbyte uri filecontrol current source next117 main open same owner'] = static fn (TestRunner $t) => $t->same($application()['events'][0]['owner'], $application()['events'][3]['owner']);
$tests['vfs shm lockbyte uri filecontrol current source next117 reserved planned'] = static fn (TestRunner $t) => $t->same('planned', $application()['events'][4]['status']);
$tests['vfs shm lockbyte uri filecontrol current source next117 reserved holder stored'] = static fn (TestRunner $t) => $t->same('reserved', $application()['events'][4]['next']['owners']['/srv/www/wp-content/database/wp refresh.sqlite']['holders']['wp-import']);
$tests['vfs shm lockbyte uri filecontrol current source next117 persist wal ok'] = static fn (TestRunner $t) => $t->same('ok', $application()['events'][5]['status']);
$tests['vfs shm lockbyte uri filecontrol current source next117 persist wal generation two'] = static fn (TestRunner $t) => $t->same(2, $application()['events'][5]['source_generation']);
$tests['vfs shm lockbyte uri filecontrol current source next117 persist wal marks shm stale'] = static fn (TestRunner $t) => $t->same(['vfs97-1'], $application()['events'][5]['stale_handles']);
$tests['vfs shm lockbyte uri filecontrol current source next117 source shm selected'] = static fn (TestRunner $t) => $t->same('shm', $application()['events'][6]['source']);
$tests['vfs shm lockbyte uri filecontrol current source next117 stale shm detects bump'] = static fn (TestRunner $t) => $t->same(true, $application()['events'][7]['stale_current_source']);
$tests['vfs shm lockbyte uri filecontrol current source next117 stale shm opened generation one'] = static fn (TestRunner $t) => $t->same(1, $application()['events'][7]['opened_generation']);
$tests['vfs shm lockbyte uri filecontrol current source next117 stale shm reports current generation two'] = static fn (TestRunner $t) => $t->same(2, $application()['events'][7]['value']);
$tests['vfs shm lockbyte uri filecontrol current source next117 refresh ok'] = static fn (TestRunner $t) => $t->same('ok', $application()['events'][8]['status']);
$tests['vfs shm lockbyte uri filecontrol current source next117 refresh marks changed'] = static fn (TestRunner $t) => $t->same(true, $application()['events'][8]['changed']);
$tests['vfs shm lockbyte uri filecontrol current source next117 refresh flag'] = static fn (TestRunner $t) => $t->same(true, $application()['events'][8]['refreshed_current_source']);
$tests['vfs shm lockbyte uri filecontrol current source next117 refresh opened generation two'] = static fn (TestRunner $t) => $t->same(2, $application()['events'][8]['opened_generation']);
$tests['vfs shm lockbyte uri filecontrol current source next117 refresh clears stale flag'] = static fn (TestRunner $t) => $t->same(false, $application()['events'][8]['stale_current_source']);
$tests['vfs shm lockbyte uri filecontrol current source next117 refresh clears stale handles'] = static fn (TestRunner $t) => $t->same([], $application()['events'][8]['stale_handles']);
$tests['vfs shm lockbyte uri filecontrol current source next117 second shm data version fresh'] = static fn (TestRunner $t) => $t->same(false, $application()['events'][9]['stale_current_source']);
$tests['vfs shm lockbyte uri filecontrol current source next117 second shm opened generation two'] = static fn (TestRunner $t) => $t->same(2, $application()['events'][9]['opened_generation']);
$tests['vfs shm lockbyte uri filecontrol current source next117 wal opens refreshed generation'] = static fn (TestRunner $t) => $t->same(2, $application()['events'][10]['next']['handles']['vfs97-3']['source_generation']);
$tests['vfs shm lockbyte uri filecontrol current source next117 wal data version fresh before reserve'] = static fn (TestRunner $t) => $t->same(false, $application()['events'][11]['stale_current_source']);
$tests['vfs shm lockbyte uri filecontrol current source next117 reserve bytes ok'] = static fn (TestRunner $t) => $t->same('ok', $application()['events'][13]['status']);
$tests['vfs shm lockbyte uri filecontrol current source next117 reserve bytes stored'] = static fn (TestRunner $t) => $t->same(24, $application()['events'][13]['next']['owners']['/srv/www/wp-content/database/wp refresh.sqlite']['controls']['reserve_bytes']);
$tests['vfs shm lockbyte uri filecontrol current source next117 reserve generation three'] = static fn (TestRunner $t) => $t->same(3, $application()['events'][13]['source_generation']);
$tests['vfs shm lockbyte uri filecontrol current source next117 reserve marks sidecars stale'] = static fn (TestRunner $t) => $t->same(['vfs97-1', 'vfs97-3'], $application()['events'][13]['stale_handles']);
$tests['vfs shm lockbyte uri filecontrol current source next117 source wal selected'] = static fn (TestRunner $t) => $t->same('wal', $application()['events'][14]['source']);
$tests['vfs shm lockbyte uri filecontrol current source next117 stale wal detects reserve'] = static fn (TestRunner $t) => $t->same(true, $application()['events'][15]['stale_current_source']);
$tests['vfs shm lockbyte uri filecontrol current source next117 stale wal opened generation two'] = static fn (TestRunner $t) => $t->same(2, $application()['events'][15]['opened_generation']);
$tests['vfs shm lockbyte uri filecontrol current source next117 wal refresh changed'] = static fn (TestRunner $t) => $t->same(true, $application()['events'][16]['changed']);
$tests['vfs shm lockbyte uri filecontrol current source next117 wal refresh leaves stale shm'] = static fn (TestRunner $t) => $t->same(['vfs97-1'], $application()['events'][16]['stale_handles']);
$tests['vfs shm lockbyte uri filecontrol current source next117 wal data version fresh after refresh'] = static fn (TestRunner $t) => $t->same(false, $application()['events'][17]['stale_current_source']);
$tests['vfs shm lockbyte uri filecontrol current source next117 yield releases shm reader'] = static fn (TestRunner $t) => $t->same([], $application()['events'][18]['next']['owners']['/srv/www/wp-content/database/wp refresh.sqlite']['shm_locks']['read0']);
$tests['vfs shm lockbyte uri filecontrol current source next117 exclusive succeeds after yield'] = static fn (TestRunner $t) => $t->same('planned', $application()['events'][19]['status']);
$tests['vfs shm lockbyte uri filecontrol current source next117 chunk size generation four'] = static fn (TestRunner $t) => $t->same(4, $application()['events'][20]['source_generation']);
$tests['vfs shm lockbyte uri filecontrol current source next117 final control count'] = static fn (TestRunner $t) => $t->same(1, $application()['next']['persistent_control_count']);
$tests['vfs shm lockbyte uri filecontrol current source next117 final generation count'] = static fn (TestRunner $t) => $t->same(1, $application()['next']['persistent_generation_count']);
$tests['vfs shm lockbyte uri filecontrol current source next117 final owner count'] = static fn (TestRunner $t) => $t->same(1, $application()['next']['owner_count']);

$tests['vfs shm lockbyte uri filecontrol current source next117 seeded first open generation five'] = static fn (TestRunner $t) => $t->same(5, $seeded()['events'][0]['next']['handles']['vfs97-1']['source_generation']);
$tests['vfs shm lockbyte uri filecontrol current source next117 seeded data version fresh'] = static fn (TestRunner $t) => $t->same(false, $seeded()['events'][1]['stale_current_source']);
$tests['vfs shm lockbyte uri filecontrol current source next117 seeded refresh unchanged'] = static fn (TestRunner $t) => $t->same(false, $seeded()['events'][2]['changed']);
$tests['vfs shm lockbyte uri filecontrol current source next117 seeded refresh opened generation five'] = static fn (TestRunner $t) => $t->same(5, $seeded()['events'][2]['opened_generation']);
$tests['vfs shm lockbyte uri filecontrol current source next117 seeded after refresh fresh'] = static fn (TestRunner $t) => $t->same(false, $seeded()['events'][3]['stale_current_source']);

$tests['vfs shm lockbyte uri filecontrol current source next117 readonly first data version fresh'] = static fn (TestRunner $t) => $t->same(false, $readonly()['events'][1]['stale_current_source']);
$tests['vfs shm lockbyte uri filecontrol current source next117 readonly refresh ok'] = static fn (TestRunner $t) => $t->same('ok', $readonly()['events'][2]['status']);
$tests['vfs shm lockbyte uri filecontrol current source next117 readonly refresh unchanged'] = static fn (TestRunner $t) => $t->same(false, $readonly()['events'][2]['changed']);
$tests['vfs shm lockbyte uri filecontrol current source next117 readonly remains fresh'] = static fn (TestRunner $t) => $t->same(false, $readonly()['events'][3]['stale_current_source']);

$tests['vfs shm lockbyte uri filecontrol current source next117 rejects empty operations'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsLockByteUriShmCurrentSourceNext::planShmLockByteUriFileControl([]));
$tests['vfs shm lockbyte uri filecontrol current source next117 rejects bad refresh handle'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run117(['file_control(data_version, refresh)']));

return $tests;
