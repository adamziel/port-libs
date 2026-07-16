<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsLockByteUriShmCurrentSourceNext;

$tests = [];

$run139 = static fn (array $ops, array $options = []): array => SQLiteVfsLockByteUriShmCurrentSourceNext::planTempUriFileControlRegression($ops, $options + [
    'filename' => 'file:/tmp/wp-import-temp.sqlite?mode=rw&cache=private&role=default&checkpoint=off',
]);

$application = static function () use ($run139): array {
    static $result = null;
    if ($result === null) {
        $result = $run139([
            ['op' => 'open', 'source' => 'temp', 'filename' => 'file:/tmp/wp%20import%20one.sqlite?mode=rw&cache=private&role=sorter&checkpoint=on&scratch=alpha&scratch=beta&busy=225'],
            ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => 'role'],
            ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => 'scratch'],
            ['op' => 'filecontrol', 'control' => 'uri_boolean', 'value' => ['parameter' => 'checkpoint', 'default' => false]],
            ['op' => 'filecontrol', 'control' => 'uri_int', 'value' => ['parameter' => 'busy', 'default' => 10]],
            'file_control(chunk_size, 8192)',
            'file_control(persist_wal, on)',
            ['op' => 'open', 'source' => 'main', 'filename' => 'file:/srv/www/wp-content/database/wp%20live.sqlite?mode=rw&cache=shared&role=main'],
            'lock reserved wp-import 3 on main',
            'file_control(persist_wal, on)',
            ['op' => 'open', 'source' => 'temp', 'filename' => 'file:/tmp/wp%20import%20two.sqlite?mode=rw&cache=private&role=dedupe&checkpoint=off&busy=bad'],
            ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => 'role'],
            ['op' => 'filecontrol', 'control' => 'uri_boolean', 'value' => ['parameter' => 'checkpoint', 'default' => true]],
            ['op' => 'filecontrol', 'control' => 'uri_int', 'value' => ['parameter' => 'busy', 'default' => 77]],
            'file_control(reserve_bytes, 16)',
            'source(main)',
            ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => ['parameter' => 'role', 'default' => 'missing']],
            'file_control(data_version)',
            'source(temp)',
            'file_control(data_version)',
        ]);
    }

    return $result;
};

$plainTemp = static fn (): array => $run139([
    ['op' => 'open', 'source' => 'temp', 'filename' => '/tmp/plain-temp.sqlite'],
    ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => ['parameter' => 'role', 'default' => 'fallback']],
    ['op' => 'filecontrol', 'control' => 'uri_boolean', 'value' => ['parameter' => 'checkpoint', 'default' => true]],
    ['op' => 'filecontrol', 'control' => 'uri_int', 'value' => ['parameter' => 'busy', 'default' => 44]],
    'file_control(chunk_size, 4096)',
]);

$tests['vfs temp uri filecontrol regression current source next139 dependency marker'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-temp-uri-filecontrol-regression-current-source-next139', $application()['dependencies'], true));
$tests['vfs temp uri filecontrol regression current source next139 uri dependency marker'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-uri-file-control', $application()['dependencies'], true));
$tests['vfs temp uri filecontrol regression current source next139 final status ok'] = static fn (TestRunner $t) => $t->same('ok', $application()['status']);
$tests['vfs temp uri filecontrol regression current source next139 event count'] = static fn (TestRunner $t) => $t->same(20, count($application()['events']));
$tests['vfs temp uri filecontrol regression current source next139 first source temp'] = static fn (TestRunner $t) => $t->same('temp', $application()['events'][0]['source']);
$tests['vfs temp uri filecontrol regression current source next139 first temporary flag'] = static fn (TestRunner $t) => $t->same(true, $application()['events'][0]['temporary']);
$tests['vfs temp uri filecontrol regression current source next139 first owner private'] = static fn (TestRunner $t) => $t->same('temp:vfs97-1:/tmp/wp import one.sqlite', $application()['events'][0]['owner']);
$tests['vfs temp uri filecontrol regression current source next139 first path private'] = static fn (TestRunner $t) => $t->same('temp:vfs97-1:/tmp/wp import one.sqlite', $application()['events'][0]['path']);
$tests['vfs temp uri filecontrol regression current source next139 first uri path decoded'] = static fn (TestRunner $t) => $t->same('/tmp/wp import one.sqlite', $application()['events'][0]['uri']['path']);
$tests['vfs temp uri filecontrol regression current source next139 first cache private'] = static fn (TestRunner $t) => $t->same('private', $application()['events'][0]['uri']['cache']);
$tests['vfs temp uri filecontrol regression current source next139 first role parameter'] = static fn (TestRunner $t) => $t->same('sorter', $application()['events'][1]['value']);
$tests['vfs temp uri filecontrol regression current source next139 repeated scratch last value'] = static fn (TestRunner $t) => $t->same('beta', $application()['events'][2]['value']);
$tests['vfs temp uri filecontrol regression current source next139 repeated scratch values retained'] = static fn (TestRunner $t) => $t->same(['alpha', 'beta'], $application()['events'][2]['values']);
$tests['vfs temp uri filecontrol regression current source next139 checkpoint true'] = static fn (TestRunner $t) => $t->same(true, $application()['events'][3]['value']);
$tests['vfs temp uri filecontrol regression current source next139 busy int'] = static fn (TestRunner $t) => $t->same(225, $application()['events'][4]['value']);
$tests['vfs temp uri filecontrol regression current source next139 temp chunk local route'] = static fn (TestRunner $t) => $t->same('temporary-handle', $application()['events'][5]['routed_to']);
$tests['vfs temp uri filecontrol regression current source next139 temp chunk changed'] = static fn (TestRunner $t) => $t->same(true, $application()['events'][5]['changed']);
$tests['vfs temp uri filecontrol regression current source next139 temp chunk stored on handle'] = static fn (TestRunner $t) => $t->same(8192, $application()['events'][5]['next']['handles']['vfs97-1']['controls']['chunk_size']);
$tests['vfs temp uri filecontrol regression current source next139 temp persist local route'] = static fn (TestRunner $t) => $t->same('temporary-handle', $application()['events'][6]['routed_to']);
$tests['vfs temp uri filecontrol regression current source next139 temp persist not owner persistent'] = static fn (TestRunner $t) => $t->same([], $application()['events'][6]['next']['owners']['temp:vfs97-1:/tmp/wp import one.sqlite']['controls']);
$tests['vfs temp uri filecontrol regression current source next139 main source open'] = static fn (TestRunner $t) => $t->same('main', $application()['events'][7]['source']);
$tests['vfs temp uri filecontrol regression current source next139 main not temporary'] = static fn (TestRunner $t) => $t->same(false, $application()['events'][7]['temporary']);
$tests['vfs temp uri filecontrol regression current source next139 main reserved planned'] = static fn (TestRunner $t) => $t->same('planned', $application()['events'][8]['status']);
$tests['vfs temp uri filecontrol regression current source next139 main persist database route'] = static fn (TestRunner $t) => $t->same('database', $application()['events'][9]['routed_to']);
$tests['vfs temp uri filecontrol regression current source next139 main persist stored on owner'] = static fn (TestRunner $t) => $t->same(true, $application()['events'][9]['next']['owners']['/srv/www/wp-content/database/wp live.sqlite']['controls']['persist_wal']);
$tests['vfs temp uri filecontrol regression current source next139 second temp owner private'] = static fn (TestRunner $t) => $t->same('temp:vfs97-3:/tmp/wp import two.sqlite', $application()['events'][10]['owner']);
$tests['vfs temp uri filecontrol regression current source next139 second temp role'] = static fn (TestRunner $t) => $t->same('dedupe', $application()['events'][11]['value']);
$tests['vfs temp uri filecontrol regression current source next139 second checkpoint false'] = static fn (TestRunner $t) => $t->same(false, $application()['events'][12]['value']);
$tests['vfs temp uri filecontrol regression current source next139 bad busy follows sqlite zero'] = static fn (TestRunner $t) => $t->same(0, $application()['events'][13]['value']);
$tests['vfs temp uri filecontrol regression current source next139 reserve local route'] = static fn (TestRunner $t) => $t->same('temporary-handle', $application()['events'][14]['routed_to']);
$tests['vfs temp uri filecontrol regression current source next139 reserve stored on second handle'] = static fn (TestRunner $t) => $t->same(16, $application()['events'][14]['next']['handles']['vfs97-3']['controls']['reserve_bytes']);
$tests['vfs temp uri filecontrol regression current source next139 source main selected'] = static fn (TestRunner $t) => $t->same('main', $application()['events'][15]['source']);
$tests['vfs temp uri filecontrol regression current source next139 main role parameter'] = static fn (TestRunner $t) => $t->same('main', $application()['events'][16]['value']);
$tests['vfs temp uri filecontrol regression current source next139 main data version two'] = static fn (TestRunner $t) => $t->same(2, $application()['events'][17]['value']);
$tests['vfs temp uri filecontrol regression current source next139 source temp selected'] = static fn (TestRunner $t) => $t->same('temp', $application()['events'][18]['source']);
$tests['vfs temp uri filecontrol regression current source next139 temp data version owner independent'] = static fn (TestRunner $t) => $t->same(1, $application()['events'][19]['value']);
$tests['vfs temp uri filecontrol regression current source next139 final temp open count'] = static fn (TestRunner $t) => $t->same(2, $application()['next']['open_by_source']['temp']);
$tests['vfs temp uri filecontrol regression current source next139 final main open count'] = static fn (TestRunner $t) => $t->same(1, $application()['next']['open_by_source']['main']);
$tests['vfs temp uri filecontrol regression current source next139 final owner count'] = static fn (TestRunner $t) => $t->same(3, $application()['next']['owner_count']);
$tests['vfs temp uri filecontrol regression current source next139 final persistent control count only main'] = static fn (TestRunner $t) => $t->same(1, $application()['next']['persistent_control_count']);
$tests['vfs temp uri filecontrol regression current source next139 temp source remains selected'] = static fn (TestRunner $t) => $t->same('temp', $application()['next']['current_source']);
$tests['vfs temp uri filecontrol regression current source next139 first temp handle remains open'] = static fn (TestRunner $t) => $t->same(true, isset($application()['next']['handles']['vfs97-1']));
$tests['vfs temp uri filecontrol regression current source next139 second temp handle remains open'] = static fn (TestRunner $t) => $t->same(true, isset($application()['next']['handles']['vfs97-3']));
$tests['vfs temp uri filecontrol regression current source next139 temp owners do not share'] = static fn (TestRunner $t) => $t->same(false, $application()['events'][0]['owner'] === $application()['events'][10]['owner']);

$tests['vfs temp uri filecontrol regression current source next139 plain temp default parameter'] = static fn (TestRunner $t) => $t->same('fallback', $plainTemp()['events'][1]['value']);
$tests['vfs temp uri filecontrol regression current source next139 plain temp missing reason'] = static fn (TestRunner $t) => $t->same('missing_uri_parameter', $plainTemp()['events'][1]['reason']);
$tests['vfs temp uri filecontrol regression current source next139 plain temp bool default'] = static fn (TestRunner $t) => $t->same(true, $plainTemp()['events'][2]['value']);
$tests['vfs temp uri filecontrol regression current source next139 plain temp int default'] = static fn (TestRunner $t) => $t->same(44, $plainTemp()['events'][3]['value']);
$tests['vfs temp uri filecontrol regression current source next139 plain temp chunk local'] = static fn (TestRunner $t) => $t->same('temporary-handle', $plainTemp()['events'][4]['routed_to']);
$tests['vfs temp uri filecontrol regression current source next139 plain temp no persistent owners'] = static fn (TestRunner $t) => $t->same(0, $plainTemp()['next']['persistent_control_count']);

$tests['vfs temp uri filecontrol regression current source next139 rejects empty operations'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsLockByteUriShmCurrentSourceNext::planTempUriFileControlRegression([]));
$tests['vfs temp uri filecontrol regression current source next139 rejects empty uri parameter'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run139([['op' => 'open', 'source' => 'temp', 'filename' => 'file:/tmp/bad.sqlite?mode=rw'], ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => '']]));
$tests['vfs temp uri filecontrol regression current source next139 rejects nul uri parameter'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run139([['op' => 'open', 'source' => 'temp', 'filename' => 'file:/tmp/bad.sqlite?mode=rw'], ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => "bad\0name"]]));

return $tests;
