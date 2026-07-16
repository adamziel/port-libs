<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsLockByteUriShmCurrentSourceNext;

$tests = [];

$run128 = static fn (array $ops, array $options = []): array => SQLiteVfsLockByteUriShmCurrentSourceNext::planOpenShmFileControlUri($ops, $options + [
    'filename' => 'file://localhost/srv/www/wp-content/database/wp%20uri.sqlite?mode=rw&cache=shared&vfs=unix-excl&psow=1&role=front&trace=one&trace=two&busy=1500&checkpoint=on',
]);

$application = static function () use ($run128): array {
    static $result = null;
    if ($result === null) {
        $result = $run128([
            'open(file://localhost/srv/www/wp-content/database/wp%20uri.sqlite-shm?mode=rw&cache=shared&vfs=unix-excl&role=reader&readmark=2&checkpoint=on&probe=alpha&probe=beta)',
            ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => 'role'],
            ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => 'probe'],
            ['op' => 'filecontrol', 'control' => 'uri_int', 'value' => ['parameter' => 'readmark', 'default' => 0]],
            ['op' => 'filecontrol', 'control' => 'uri_boolean', 'value' => ['parameter' => 'checkpoint', 'default' => false]],
            'shm read0 shared wp-reader',
            'open(file:/srv/www/wp-content/database/wp%20uri.sqlite?mode=rw&cache=shared&vfs=unix-dotfile&role=writer&busy=2500&checkpoint=off&psow=1)',
            ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => 'vfs'],
            ['op' => 'filecontrol', 'control' => 'uri_int', 'value' => ['parameter' => 'busy', 'default' => 100]],
            ['op' => 'filecontrol', 'control' => 'uri_boolean', 'value' => ['parameter' => 'psow', 'default' => false]],
            'lock reserved wp-import 17 on main',
            'file_control(persist_wal, on)',
            'source(shm)',
            ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => 'role'],
            ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => ['parameter' => 'missing', 'default' => 'fallback']],
            ['op' => 'filecontrol', 'control' => 'uri_boolean', 'value' => ['parameter' => 'checkpoint', 'default' => true]],
            'file_control(data_version)',
            'file_control(data_version, refresh)',
            'file_control(data_version)',
            'open(file:/srv/www/wp-content/database/wp%20uri.sqlite-wal?mode=rw&cache=shared&role=wal&busy=bad&checkpoint=yes)',
            ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => 'role'],
            ['op' => 'filecontrol', 'control' => 'uri_int', 'value' => ['parameter' => 'busy', 'default' => 50]],
            ['op' => 'filecontrol', 'control' => 'uri_boolean', 'value' => ['parameter' => 'checkpoint', 'default' => false]],
            'source(main)',
            ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => 'role'],
            'file_control(data_version)',
        ]);
    }

    return $result;
};

$plain = static fn (): array => $run128([
    'open(/srv/www/wp-content/database/plain.sqlite-shm)',
    ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => ['parameter' => 'role', 'default' => 'none']],
    ['op' => 'filecontrol', 'control' => 'uri_boolean', 'value' => ['parameter' => 'checkpoint', 'default' => true]],
    ['op' => 'filecontrol', 'control' => 'uri_int', 'value' => ['parameter' => 'busy', 'default' => 99]],
]);

$readonly = static fn (): array => $run128([
    'open(file:/srv/www/wp-content/database/archive.sqlite-shm?mode=ro&immutable=1&role=archive&checkpoint=off)',
    ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => 'role'],
    ['op' => 'filecontrol', 'control' => 'uri_boolean', 'value' => ['parameter' => 'checkpoint', 'default' => true]],
    'file_control(persist_wal, on)',
    'file_control(data_version)',
]);

$tests['vfs open shm filecontrol uri current source next128 dependency marker'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-open-shm-filecontrol-uri-current-source-next128', $application()['dependencies'], true));
$tests['vfs open shm filecontrol uri current source next128 uri dependency marker'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-uri-file-control', $application()['dependencies'], true));
$tests['vfs open shm filecontrol uri current source next128 data version dependency marker'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-file-control-data-version', $application()['dependencies'], true));
$tests['vfs open shm filecontrol uri current source next128 final status ok'] = static fn (TestRunner $t) => $t->same('ok', $application()['status']);
$tests['vfs open shm filecontrol uri current source next128 event count'] = static fn (TestRunner $t) => $t->same(26, count($application()['events']));
$tests['vfs open shm filecontrol uri current source next128 first open shm'] = static fn (TestRunner $t) => $t->same('shm', $application()['events'][0]['source']);
$tests['vfs open shm filecontrol uri current source next128 first owner decoded'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp uri.sqlite', $application()['events'][0]['owner']);
$tests['vfs open shm filecontrol uri current source next128 first path shm sidecar'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp uri.sqlite-shm', $application()['events'][0]['path']);
$tests['vfs open shm filecontrol uri current source next128 shm uri mode rw'] = static fn (TestRunner $t) => $t->same('rw', $application()['events'][0]['uri']['mode']);
$tests['vfs open shm filecontrol uri current source next128 shm uri cache shared'] = static fn (TestRunner $t) => $t->same('shared', $application()['events'][0]['uri']['cache']);
$tests['vfs open shm filecontrol uri current source next128 shm role parameter'] = static fn (TestRunner $t) => $t->same('reader', $application()['events'][1]['value']);
$tests['vfs open shm filecontrol uri current source next128 shm role values'] = static fn (TestRunner $t) => $t->same(['reader'], $application()['events'][1]['values']);
$tests['vfs open shm filecontrol uri current source next128 shm role routed uri'] = static fn (TestRunner $t) => $t->same('current-source-uri', $application()['events'][1]['routed_to']);
$tests['vfs open shm filecontrol uri current source next128 repeated parameter returns last'] = static fn (TestRunner $t) => $t->same('beta', $application()['events'][2]['value']);
$tests['vfs open shm filecontrol uri current source next128 repeated parameter values preserved'] = static fn (TestRunner $t) => $t->same(['alpha', 'beta'], $application()['events'][2]['values']);
$tests['vfs open shm filecontrol uri current source next128 readmark int'] = static fn (TestRunner $t) => $t->same(2, $application()['events'][3]['value']);
$tests['vfs open shm filecontrol uri current source next128 readmark default'] = static fn (TestRunner $t) => $t->same(0, $application()['events'][3]['default']);
$tests['vfs open shm filecontrol uri current source next128 checkpoint bool true'] = static fn (TestRunner $t) => $t->same(true, $application()['events'][4]['value']);
$tests['vfs open shm filecontrol uri current source next128 shm read lock acquired'] = static fn (TestRunner $t) => $t->same('acquired', $application()['events'][5]['status']);
$tests['vfs open shm filecontrol uri current source next128 main open same owner'] = static fn (TestRunner $t) => $t->same($application()['events'][0]['owner'], $application()['events'][6]['owner']);
$tests['vfs open shm filecontrol uri current source next128 main open selected'] = static fn (TestRunner $t) => $t->same('main', $application()['events'][6]['next']['current_source']);
$tests['vfs open shm filecontrol uri current source next128 main uri vfs parameter'] = static fn (TestRunner $t) => $t->same('unix-dotfile', $application()['events'][7]['value']);
$tests['vfs open shm filecontrol uri current source next128 main busy int'] = static fn (TestRunner $t) => $t->same(2500, $application()['events'][8]['value']);
$tests['vfs open shm filecontrol uri current source next128 main psow bool true'] = static fn (TestRunner $t) => $t->same(true, $application()['events'][9]['value']);
$tests['vfs open shm filecontrol uri current source next128 reserved lock planned'] = static fn (TestRunner $t) => $t->same('planned', $application()['events'][10]['status']);
$tests['vfs open shm filecontrol uri current source next128 reserved lock holder'] = static fn (TestRunner $t) => $t->same('reserved', $application()['events'][10]['next']['owners']['/srv/www/wp-content/database/wp uri.sqlite']['holders']['wp-import']);
$tests['vfs open shm filecontrol uri current source next128 persist wal ok'] = static fn (TestRunner $t) => $t->same('ok', $application()['events'][11]['status']);
$tests['vfs open shm filecontrol uri current source next128 persist wal bumps generation'] = static fn (TestRunner $t) => $t->same(2, $application()['events'][11]['source_generation']);
$tests['vfs open shm filecontrol uri current source next128 persist wal marks shm stale'] = static fn (TestRunner $t) => $t->same(['vfs97-1'], $application()['events'][11]['stale_handles']);
$tests['vfs open shm filecontrol uri current source next128 source switches back shm'] = static fn (TestRunner $t) => $t->same('shm', $application()['events'][12]['source']);
$tests['vfs open shm filecontrol uri current source next128 stale shm uri role still per handle'] = static fn (TestRunner $t) => $t->same('reader', $application()['events'][13]['value']);
$tests['vfs open shm filecontrol uri current source next128 stale shm uri detects generation'] = static fn (TestRunner $t) => $t->same(true, $application()['events'][13]['stale_current_source']);
$tests['vfs open shm filecontrol uri current source next128 missing parameter default returned'] = static fn (TestRunner $t) => $t->same('fallback', $application()['events'][14]['value']);
$tests['vfs open shm filecontrol uri current source next128 missing parameter reason'] = static fn (TestRunner $t) => $t->same('missing_uri_parameter', $application()['events'][14]['reason']);
$tests['vfs open shm filecontrol uri current source next128 stale checkpoint bool still true'] = static fn (TestRunner $t) => $t->same(true, $application()['events'][15]['value']);
$tests['vfs open shm filecontrol uri current source next128 stale data version before refresh'] = static fn (TestRunner $t) => $t->same(true, $application()['events'][16]['stale_current_source']);
$tests['vfs open shm filecontrol uri current source next128 refresh clears current shm staleness'] = static fn (TestRunner $t) => $t->same(false, $application()['events'][17]['stale_current_source']);
$tests['vfs open shm filecontrol uri current source next128 data version fresh after refresh'] = static fn (TestRunner $t) => $t->same(false, $application()['events'][18]['stale_current_source']);
$tests['vfs open shm filecontrol uri current source next128 wal opens generation two'] = static fn (TestRunner $t) => $t->same(2, $application()['events'][19]['next']['handles']['vfs97-3']['source_generation']);
$tests['vfs open shm filecontrol uri current source next128 wal role parameter'] = static fn (TestRunner $t) => $t->same('wal', $application()['events'][20]['value']);
$tests['vfs open shm filecontrol uri current source next128 wal bad int follows sqlite zero'] = static fn (TestRunner $t) => $t->same(0, $application()['events'][21]['value']);
$tests['vfs open shm filecontrol uri current source next128 wal bool yes true'] = static fn (TestRunner $t) => $t->same(true, $application()['events'][22]['value']);
$tests['vfs open shm filecontrol uri current source next128 source main selected'] = static fn (TestRunner $t) => $t->same('main', $application()['events'][23]['source']);
$tests['vfs open shm filecontrol uri current source next128 main role remains writer'] = static fn (TestRunner $t) => $t->same('writer', $application()['events'][24]['value']);
$tests['vfs open shm filecontrol uri current source next128 main data version fresh'] = static fn (TestRunner $t) => $t->same(false, $application()['events'][25]['stale_current_source']);
$tests['vfs open shm filecontrol uri current source next128 final current source main'] = static fn (TestRunner $t) => $t->same('main', $application()['next']['current_source']);
$tests['vfs open shm filecontrol uri current source next128 final open by source'] = static fn (TestRunner $t) => $t->same(['main' => 1, 'wal' => 1, 'shm' => 1], $application()['next']['open_by_source']);
$tests['vfs open shm filecontrol uri current source next128 final owner count'] = static fn (TestRunner $t) => $t->same(1, $application()['next']['owner_count']);
$tests['vfs open shm filecontrol uri current source next128 final shm lock count'] = static fn (TestRunner $t) => $t->same(1, $application()['next']['shm_lock_count']);
$tests['vfs open shm filecontrol uri current source next128 final persistent control count'] = static fn (TestRunner $t) => $t->same(1, $application()['next']['persistent_control_count']);
$tests['vfs open shm filecontrol uri current source next128 final persist wal stored'] = static fn (TestRunner $t) => $t->same(true, $application()['next']['owners']['/srv/www/wp-content/database/wp uri.sqlite']['controls']['persist_wal']);

$tests['vfs open shm filecontrol uri current source next128 plain parameter default'] = static fn (TestRunner $t) => $t->same('none', $plain()['events'][1]['value']);
$tests['vfs open shm filecontrol uri current source next128 plain parameter missing reason'] = static fn (TestRunner $t) => $t->same('missing_uri_parameter', $plain()['events'][1]['reason']);
$tests['vfs open shm filecontrol uri current source next128 plain bool default true'] = static fn (TestRunner $t) => $t->same(true, $plain()['events'][2]['value']);
$tests['vfs open shm filecontrol uri current source next128 plain int default'] = static fn (TestRunner $t) => $t->same(99, $plain()['events'][3]['value']);

$tests['vfs open shm filecontrol uri current source next128 readonly uri parameter works'] = static fn (TestRunner $t) => $t->same('archive', $readonly()['events'][1]['value']);
$tests['vfs open shm filecontrol uri current source next128 readonly bool off false'] = static fn (TestRunner $t) => $t->same(false, $readonly()['events'][2]['value']);
$tests['vfs open shm filecontrol uri current source next128 readonly persist ignored'] = static fn (TestRunner $t) => $t->same('ignored', $readonly()['events'][3]['status']);
$tests['vfs open shm filecontrol uri current source next128 readonly data version fresh'] = static fn (TestRunner $t) => $t->same(false, $readonly()['events'][4]['stale_current_source']);

$tests['vfs open shm filecontrol uri current source next128 rejects empty operations'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsLockByteUriShmCurrentSourceNext::planOpenShmFileControlUri([]));
$tests['vfs open shm filecontrol uri current source next128 rejects empty uri parameter'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run128(['open(file:/srv/www/wp-content/database/bad.sqlite?mode=rw)', ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => '']]));
$tests['vfs open shm filecontrol uri current source next128 rejects nul uri parameter'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run128(['open(file:/srv/www/wp-content/database/bad.sqlite?mode=rw)', ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => "bad\0name"]]));
$tests['vfs open shm filecontrol uri current source next128 rejects bad int default'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run128(['open(file:/srv/www/wp-content/database/bad.sqlite?mode=rw)', ['op' => 'filecontrol', 'control' => 'uri_int', 'value' => ['parameter' => 'missing', 'default' => 'abc']]]));

return $tests;
