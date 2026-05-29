<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsLockByteUriShmCurrentSourceNext;

$tests = [];

$run141 = static fn (array $ops, array $options = []): array => SQLiteVfsLockByteUriShmCurrentSourceNext::planUriShmFileControlRegression($ops, $options + [
    'filename' => 'file:/srv/www/wp-content/database/wp%20close.sqlite?mode=rw&cache=shared&role=default',
]);

$wordpress = static function () use ($run141): array {
    static $result = null;
    if ($result === null) {
        $result = $run141([
            'open(file:/srv/www/wp-content/database/wp%20close.sqlite-shm?mode=rw&cache=shared&role=reader&readmark=1)',
            'shm read0 shared wp-reader',
            ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => 'role'],
            ['op' => 'close', 'source' => 'shm', 'connection' => 'wp-reader'],
            'open(file:/srv/www/wp-content/database/wp%20close.sqlite-shm?mode=rw&cache=shared&role=checkpoint&readmark=2)',
            'shm read0 exclusive wp-checkpoint',
            ['op' => 'filecontrol', 'control' => 'uri_int', 'value' => ['parameter' => 'readmark', 'default' => 0]],
            'open(file:/srv/www/wp-content/database/wp%20close.sqlite?mode=rw&cache=shared&role=writer&checkpoint=off)',
            'lock reserved wp-import 11 on main',
            'file_control(persist_wal, on)',
            'source(shm)',
            'file_control(data_version)',
            'file_control(data_version, refresh)',
            'file_control(data_version)',
            'source(main)',
            'file_control(data_version)',
        ]);
    }

    return $result;
};

$noConnectionClose = static fn (): array => $run141([
    'open(file:/srv/www/wp-content/database/wp%20blocked.sqlite-shm?mode=rw&cache=shared)',
    'shm read0 shared wp-reader',
    ['op' => 'close', 'source' => 'shm'],
    'open(file:/srv/www/wp-content/database/wp%20blocked.sqlite-shm?mode=rw&cache=shared)',
    'shm read0 exclusive wp-checkpoint',
]);

$multiLockClose = static fn (): array => $run141([
    'open(file:/srv/www/wp-content/database/wp%20multi.sqlite-shm?mode=rw&cache=shared)',
    'shm read0 shared wp-reader',
    'shm read1 shared wp-reader',
    'shm read0 shared wp-analytics',
    ['op' => 'close', 'source' => 'shm', 'connection' => 'wp-reader'],
    'open(file:/srv/www/wp-content/database/wp%20multi.sqlite-shm?mode=rw&cache=shared)',
    'shm read1 exclusive wp-checkpoint',
    'shm read0 exclusive wp-checkpoint',
]);

$readonly = static fn (): array => $run141([
    'open(file:/srv/www/wp-content/database/wp%20archive.sqlite-shm?mode=ro&immutable=1&role=archive)',
    ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => 'role'],
    'shm read0 shared wp-report',
    ['op' => 'close', 'source' => 'shm', 'connection' => 'wp-report'],
]);

$missingClose = static fn (): array => $run141([
    ['op' => 'close', 'source' => 'shm', 'connection' => 'wp-reader'],
]);

$tests['vfs uri shm filecontrol regression current source next141 dependency marker'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-uri-shm-filecontrol-regression-current-source-next141', $wordpress()['dependencies'], true));
$tests['vfs uri shm filecontrol regression current source next141 uri dependency marker'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-uri-file-control', $wordpress()['dependencies'], true));
$tests['vfs uri shm filecontrol regression current source next141 data version dependency marker'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-file-control-data-version', $wordpress()['dependencies'], true));
$tests['vfs uri shm filecontrol regression current source next141 final status ok'] = static fn (TestRunner $t) => $t->same('ok', $wordpress()['status']);
$tests['vfs uri shm filecontrol regression current source next141 event count'] = static fn (TestRunner $t) => $t->same(16, count($wordpress()['events']));
$tests['vfs uri shm filecontrol regression current source next141 first open shm'] = static fn (TestRunner $t) => $t->same('shm', $wordpress()['events'][0]['source']);
$tests['vfs uri shm filecontrol regression current source next141 owner decoded'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp close.sqlite', $wordpress()['events'][0]['owner']);
$tests['vfs uri shm filecontrol regression current source next141 read lock acquired'] = static fn (TestRunner $t) => $t->same('acquired', $wordpress()['events'][1]['status']);
$tests['vfs uri shm filecontrol regression current source next141 read lock stored'] = static fn (TestRunner $t) => $t->same(['wp-reader' => 'shared'], $wordpress()['events'][1]['next']['owners']['/srv/www/wp-content/database/wp close.sqlite']['shm_locks']['read0']);
$tests['vfs uri shm filecontrol regression current source next141 uri role reader'] = static fn (TestRunner $t) => $t->same('reader', $wordpress()['events'][2]['value']);
$tests['vfs uri shm filecontrol regression current source next141 close reports connection'] = static fn (TestRunner $t) => $t->same('wp-reader', $wordpress()['events'][3]['released_connection']);
$tests['vfs uri shm filecontrol regression current source next141 close reports shm release'] = static fn (TestRunner $t) => $t->same(true, $wordpress()['events'][3]['released_shm_locks']);
$tests['vfs uri shm filecontrol regression current source next141 close removes read0 holder'] = static fn (TestRunner $t) => $t->same([], $wordpress()['events'][3]['next']['owners']['/srv/www/wp-content/database/wp close.sqlite']['shm_locks']['read0']);
$tests['vfs uri shm filecontrol regression current source next141 close clears open shm'] = static fn (TestRunner $t) => $t->same(['main' => 0, 'wal' => 0, 'shm' => 0], $wordpress()['events'][3]['next']['open_by_source'] ?? ['main' => 0, 'wal' => 0, 'shm' => 0]);
$tests['vfs uri shm filecontrol regression current source next141 reopened shm generation one'] = static fn (TestRunner $t) => $t->same(1, $wordpress()['events'][4]['next']['handles']['vfs97-2']['source_generation']);
$tests['vfs uri shm filecontrol regression current source next141 checkpoint exclusive acquired'] = static fn (TestRunner $t) => $t->same('acquired', $wordpress()['events'][5]['status']);
$tests['vfs uri shm filecontrol regression current source next141 checkpoint holder stored'] = static fn (TestRunner $t) => $t->same(['wp-checkpoint' => 'exclusive'], $wordpress()['events'][5]['next']['owners']['/srv/www/wp-content/database/wp close.sqlite']['shm_locks']['read0']);
$tests['vfs uri shm filecontrol regression current source next141 readmark from reopened shm'] = static fn (TestRunner $t) => $t->same(2, $wordpress()['events'][6]['value']);
$tests['vfs uri shm filecontrol regression current source next141 main open same owner'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp close.sqlite', $wordpress()['events'][7]['owner']);
$tests['vfs uri shm filecontrol regression current source next141 reserved lock planned'] = static fn (TestRunner $t) => $t->same('planned', $wordpress()['events'][8]['status']);
$tests['vfs uri shm filecontrol regression current source next141 persist wal ok'] = static fn (TestRunner $t) => $t->same('ok', $wordpress()['events'][9]['status']);
$tests['vfs uri shm filecontrol regression current source next141 persist wal bumps generation'] = static fn (TestRunner $t) => $t->same(2, $wordpress()['events'][9]['source_generation']);
$tests['vfs uri shm filecontrol regression current source next141 persist wal marks reopened shm stale'] = static fn (TestRunner $t) => $t->same(['vfs97-2'], $wordpress()['events'][9]['stale_handles']);
$tests['vfs uri shm filecontrol regression current source next141 source shm selected'] = static fn (TestRunner $t) => $t->same('shm', $wordpress()['events'][10]['source']);
$tests['vfs uri shm filecontrol regression current source next141 stale shm detects generation'] = static fn (TestRunner $t) => $t->same(true, $wordpress()['events'][11]['stale_current_source']);
$tests['vfs uri shm filecontrol regression current source next141 stale shm opened generation one'] = static fn (TestRunner $t) => $t->same(1, $wordpress()['events'][11]['opened_generation']);
$tests['vfs uri shm filecontrol regression current source next141 stale shm current generation two'] = static fn (TestRunner $t) => $t->same(2, $wordpress()['events'][11]['value']);
$tests['vfs uri shm filecontrol regression current source next141 refresh changed'] = static fn (TestRunner $t) => $t->same(true, $wordpress()['events'][12]['changed']);
$tests['vfs uri shm filecontrol regression current source next141 refresh clears stale'] = static fn (TestRunner $t) => $t->same(false, $wordpress()['events'][12]['stale_current_source']);
$tests['vfs uri shm filecontrol regression current source next141 refreshed data version fresh'] = static fn (TestRunner $t) => $t->same(false, $wordpress()['events'][13]['stale_current_source']);
$tests['vfs uri shm filecontrol regression current source next141 main data version fresh'] = static fn (TestRunner $t) => $t->same(false, $wordpress()['events'][15]['stale_current_source']);
$tests['vfs uri shm filecontrol regression current source next141 final current source main'] = static fn (TestRunner $t) => $t->same('main', $wordpress()['next']['current_source']);
$tests['vfs uri shm filecontrol regression current source next141 final open by source'] = static fn (TestRunner $t) => $t->same(['main' => 1, 'wal' => 0, 'shm' => 1], $wordpress()['next']['open_by_source']);
$tests['vfs uri shm filecontrol regression current source next141 final shm lock count'] = static fn (TestRunner $t) => $t->same(1, $wordpress()['next']['shm_lock_count']);
$tests['vfs uri shm filecontrol regression current source next141 final control count'] = static fn (TestRunner $t) => $t->same(1, $wordpress()['next']['persistent_control_count']);

$tests['vfs uri shm filecontrol regression current source next141 no connection close leaves read lock'] = static fn (TestRunner $t) => $t->same(['wp-reader' => 'shared'], $noConnectionClose()['events'][2]['next']['owners']['/srv/www/wp-content/database/wp blocked.sqlite']['shm_locks']['read0']);
$tests['vfs uri shm filecontrol regression current source next141 no connection close reports no release'] = static fn (TestRunner $t) => $t->same(false, $noConnectionClose()['events'][2]['released_shm_locks']);
$tests['vfs uri shm filecontrol regression current source next141 no connection exclusive blocked'] = static fn (TestRunner $t) => $t->same('blocked', $noConnectionClose()['events'][4]['status']);
$tests['vfs uri shm filecontrol regression current source next141 no connection blocker named'] = static fn (TestRunner $t) => $t->same(['wp-reader:shared'], $noConnectionClose()['events'][4]['blocking']);

$tests['vfs uri shm filecontrol regression current source next141 multi close keeps other connection'] = static fn (TestRunner $t) => $t->same(['wp-analytics' => 'shared'], $multiLockClose()['events'][4]['next']['owners']['/srv/www/wp-content/database/wp multi.sqlite']['shm_locks']['read0']);
$tests['vfs uri shm filecontrol regression current source next141 multi close clears read1'] = static fn (TestRunner $t) => $t->same([], $multiLockClose()['events'][4]['next']['owners']['/srv/www/wp-content/database/wp multi.sqlite']['shm_locks']['read1']);
$tests['vfs uri shm filecontrol regression current source next141 multi read1 exclusive acquired'] = static fn (TestRunner $t) => $t->same('acquired', $multiLockClose()['events'][6]['status']);
$tests['vfs uri shm filecontrol regression current source next141 multi read0 exclusive blocked by analytics'] = static fn (TestRunner $t) => $t->same('blocked', $multiLockClose()['events'][7]['status']);
$tests['vfs uri shm filecontrol regression current source next141 multi read0 blocker analytics'] = static fn (TestRunner $t) => $t->same(['wp-analytics:shared'], $multiLockClose()['events'][7]['blocking']);

$tests['vfs uri shm filecontrol regression current source next141 readonly uri role'] = static fn (TestRunner $t) => $t->same('archive', $readonly()['events'][1]['value']);
$tests['vfs uri shm filecontrol regression current source next141 readonly shm shared acquired'] = static fn (TestRunner $t) => $t->same('acquired', $readonly()['events'][2]['status']);
$tests['vfs uri shm filecontrol regression current source next141 readonly close releases'] = static fn (TestRunner $t) => $t->same(true, $readonly()['events'][3]['released_shm_locks']);
$tests['vfs uri shm filecontrol regression current source next141 readonly close clears reader'] = static fn (TestRunner $t) => $t->same([], $readonly()['events'][3]['next']['owners']['/srv/www/wp-content/database/wp archive.sqlite']['shm_locks']['read0']);

$tests['vfs uri shm filecontrol regression current source next141 rejects empty operations'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsLockByteUriShmCurrentSourceNext::planUriShmFileControlRegression([]));
$tests['vfs uri shm filecontrol regression current source next141 rejects empty close connection'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run141(['open(file:/srv/www/wp-content/database/bad.sqlite-shm?mode=rw)', ['op' => 'close', 'source' => 'shm', 'connection' => '']]));
$tests['vfs uri shm filecontrol regression current source next141 missing close reports missing handle'] = static fn (TestRunner $t) => $t->same('missing-handle', $missingClose()['events'][0]['status']);
$tests['vfs uri shm filecontrol regression current source next141 missing close keeps no handles'] = static fn (TestRunner $t) => $t->same(['main' => 0, 'wal' => 0, 'shm' => 0], $missingClose()['next']['open_by_source']);

return $tests;
