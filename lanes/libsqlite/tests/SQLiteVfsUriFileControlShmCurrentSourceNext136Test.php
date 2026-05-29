<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsLockByteUriShmCurrentSourceNext97;

$tests = [];

$run136 = static fn (array $ops, array $options = []): array => SQLiteVfsLockByteUriShmCurrentSourceNext97::currentSourceNext136($ops, $options + [
    'filename' => 'file:/srv/www/wp-content/database/wp%20136.sqlite?mode=rw&cache=shared&role=default',
]);

$wordpress = static function () use ($run136): array {
    static $result = null;
    if ($result === null) {
        $result = $run136([
            'open(file:/srv/www/wp-content/database/wp%20136.sqlite-shm?mode=rw&cache=shared&role=reader&readmark=3&checkpoint=on)',
            'shm read0 shared wp-reader',
            'shm write exclusive wp-import',
            'file_control(uri_parameter, role) on shm',
            'open(file:/srv/www/wp-content/database/wp%20136.sqlite?mode=rw&cache=shared&role=writer&busy=200&checkpoint=off&psow=1)',
            'file_control(uri_parameter, role) on main',
            'file_control(uri_int, busy) on main',
            'lock reserved wp-import 8 on main',
            'file_control(persist_wal, on) on main',
            'source(shm)',
            'file_control(data_version)',
            'file_control(uri_boolean, checkpoint) on shm',
            'close(shm)',
            'open(file:/srv/www/wp-content/database/wp%20136.sqlite-shm?mode=rw&cache=shared&role=reopened&readmark=4&checkpoint=no)',
            'file_control(uri_parameter, role) on shm',
            'file_control(uri_boolean, checkpoint) on shm',
        ]);
    }

    return $result;
};

$closeMain = static fn (): array => $run136([
    'open(file:/srv/www/wp-content/database/close-main.sqlite-shm?mode=rw&role=reader)',
    'shm read1 shared wp-reader',
    'open(file:/srv/www/wp-content/database/close-main.sqlite?mode=rw&role=writer)',
    'close(main)',
]);

$explicitCurrent = static fn (): array => $run136([
    'file_control(uri_parameter, role) on shm',
    'close(shm)',
], [
    'current' => [
        'sequence' => 4,
        'current_source' => 'shm',
        'source_handles' => ['main' => 'vfs97-2', 'shm' => 'vfs97-4'],
        'handles' => [
            'vfs97-2' => [
                'id' => 'vfs97-2',
                'status' => 'main-open',
                'source' => 'main',
                'path' => '/srv/www/wp-content/database/replay.sqlite',
                'owner' => '/srv/www/wp-content/database/replay.sqlite',
                'readonly' => false,
                'nolock' => false,
                'source_generation' => 5,
                'uri' => ['all_query_parameters' => ['role' => ['writer']], 'path' => '/srv/www/wp-content/database/replay.sqlite'],
            ],
            'vfs97-4' => [
                'id' => 'vfs97-4',
                'status' => 'shm-open',
                'source' => 'shm',
                'path' => '/srv/www/wp-content/database/replay.sqlite-shm',
                'owner' => '/srv/www/wp-content/database/replay.sqlite',
                'readonly' => false,
                'nolock' => false,
                'source_generation' => 4,
                'uri' => ['all_query_parameters' => ['role' => ['reader']], 'path' => '/srv/www/wp-content/database/replay.sqlite-shm'],
            ],
        ],
        'lock_holders' => ['/srv/www/wp-content/database/replay.sqlite' => ['wp-import' => 'reserved']],
        'shared_slots' => ['/srv/www/wp-content/database/replay.sqlite' => ['wp-import' => 9]],
        'shm_locks' => ['/srv/www/wp-content/database/replay.sqlite' => ['read0' => ['wp-reader' => 'shared'], 'write' => ['wp-import' => 'exclusive']]],
        'shm_lock_sources' => ['/srv/www/wp-content/database/replay.sqlite' => ['read0' => ['wp-reader' => 'shm'], 'write' => ['wp-import' => 'shm']]],
        'persistent_controls' => ['/srv/www/wp-content/database/replay.sqlite' => ['data_version' => 5, 'persist_wal' => true]],
        'persistent_generations' => ['/srv/www/wp-content/database/replay.sqlite' => 5],
    ],
]);

$owner = '/srv/www/wp-content/database/wp 136.sqlite';

$tests['vfs uri filecontrol shm current source next136 dependency marker'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-uri-filecontrol-shm-current-source-next136', $wordpress()['dependencies'], true));
$tests['vfs uri filecontrol shm current source next136 close release dependency'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-shm-close-release', $wordpress()['dependencies'], true));
$tests['vfs uri filecontrol shm current source next136 uri dependency retained'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-current-source-uri-file-control', $wordpress()['dependencies'], true));
$tests['vfs uri filecontrol shm current source next136 event count'] = static fn (TestRunner $t) => $t->same(16, count($wordpress()['events']));
$tests['vfs uri filecontrol shm current source next136 final status ok'] = static fn (TestRunner $t) => $t->same('ok', $wordpress()['status']);
$tests['vfs uri filecontrol shm current source next136 opens shm first'] = static fn (TestRunner $t) => $t->same('shm', $wordpress()['events'][0]['source']);
$tests['vfs uri filecontrol shm current source next136 sidecar first'] = static fn (TestRunner $t) => $t->same(true, $wordpress()['events'][0]['sidecar_open_first']);
$tests['vfs uri filecontrol shm current source next136 owner decoded'] = static fn (TestRunner $t) => $t->same($owner, $wordpress()['events'][0]['owner']);
$tests['vfs uri filecontrol shm current source next136 read shm acquired'] = static fn (TestRunner $t) => $t->same('acquired', $wordpress()['events'][1]['status']);
$tests['vfs uri filecontrol shm current source next136 read source tracked'] = static fn (TestRunner $t) => $t->same('shm', $wordpress()['events'][1]['next']['shm_lock_sources'][$owner]['read0']['wp-reader']);
$tests['vfs uri filecontrol shm current source next136 write shm acquired'] = static fn (TestRunner $t) => $t->same('acquired', $wordpress()['events'][2]['status']);
$tests['vfs uri filecontrol shm current source next136 write source tracked'] = static fn (TestRunner $t) => $t->same('shm', $wordpress()['events'][2]['next']['shm_lock_sources'][$owner]['write']['wp-import']);
$tests['vfs uri filecontrol shm current source next136 targeted shm role'] = static fn (TestRunner $t) => $t->same('reader', $wordpress()['events'][3]['value']);
$tests['vfs uri filecontrol shm current source next136 targeted shm source'] = static fn (TestRunner $t) => $t->same('shm', $wordpress()['events'][3]['source']);
$tests['vfs uri filecontrol shm current source next136 main open selected'] = static fn (TestRunner $t) => $t->same('main', $wordpress()['events'][4]['next']['current_source']);
$tests['vfs uri filecontrol shm current source next136 targeted main role'] = static fn (TestRunner $t) => $t->same('writer', $wordpress()['events'][5]['value']);
$tests['vfs uri filecontrol shm current source next136 targeted main busy int'] = static fn (TestRunner $t) => $t->same(200, $wordpress()['events'][6]['value']);
$tests['vfs uri filecontrol shm current source next136 reserved lock planned'] = static fn (TestRunner $t) => $t->same('planned', $wordpress()['events'][7]['status']);
$tests['vfs uri filecontrol shm current source next136 persist wal ok'] = static fn (TestRunner $t) => $t->same('ok', $wordpress()['events'][8]['status']);
$tests['vfs uri filecontrol shm current source next136 persist wal bumps generation'] = static fn (TestRunner $t) => $t->same(2, $wordpress()['events'][8]['source_generation']);
$tests['vfs uri filecontrol shm current source next136 stale shm data version'] = static fn (TestRunner $t) => $t->same(true, $wordpress()['events'][10]['stale_current_source']);
$tests['vfs uri filecontrol shm current source next136 stale shm opened generation'] = static fn (TestRunner $t) => $t->same(1, $wordpress()['events'][10]['opened_generation']);
$tests['vfs uri filecontrol shm current source next136 stale shm current generation'] = static fn (TestRunner $t) => $t->same(2, $wordpress()['events'][10]['source_generation']);
$tests['vfs uri filecontrol shm current source next136 stale shm bool stays handle local'] = static fn (TestRunner $t) => $t->same(true, $wordpress()['events'][11]['value']);
$tests['vfs uri filecontrol shm current source next136 close shm status'] = static fn (TestRunner $t) => $t->same('closed', $wordpress()['events'][12]['status']);
$tests['vfs uri filecontrol shm current source next136 close releases both shm locks'] = static fn (TestRunner $t) => $t->same(['read0:wp-reader', 'write:wp-import'], $wordpress()['events'][12]['released_shm_locks']);
$tests['vfs uri filecontrol shm current source next136 close clears read lock'] = static fn (TestRunner $t) => $t->same([], $wordpress()['events'][12]['next']['shm_locks'][$owner]['read0']);
$tests['vfs uri filecontrol shm current source next136 close clears write lock'] = static fn (TestRunner $t) => $t->same([], $wordpress()['events'][12]['next']['shm_locks'][$owner]['write']);
$tests['vfs uri filecontrol shm current source next136 close leaves byte holder'] = static fn (TestRunner $t) => $t->same(['wp-import' => 'reserved'], $wordpress()['events'][12]['next']['lock_holders'][$owner]);
$tests['vfs uri filecontrol shm current source next136 close selects main'] = static fn (TestRunner $t) => $t->same('main', $wordpress()['events'][12]['next']['current_source']);
$tests['vfs uri filecontrol shm current source next136 reopen shm generation fresh'] = static fn (TestRunner $t) => $t->same(2, $wordpress()['events'][13]['next']['handles']['vfs97-3']['source_generation']);
$tests['vfs uri filecontrol shm current source next136 reopened role'] = static fn (TestRunner $t) => $t->same('reopened', $wordpress()['events'][14]['value']);
$tests['vfs uri filecontrol shm current source next136 reopened not stale'] = static fn (TestRunner $t) => $t->same(false, $wordpress()['events'][14]['stale_current_source']);
$tests['vfs uri filecontrol shm current source next136 reopened checkpoint false'] = static fn (TestRunner $t) => $t->same(false, $wordpress()['events'][15]['value']);
$tests['vfs uri filecontrol shm current source next136 final current source shm'] = static fn (TestRunner $t) => $t->same('shm', $wordpress()['next']['current_source']);
$tests['vfs uri filecontrol shm current source next136 final open by source'] = static fn (TestRunner $t) => $t->same(['main' => 1, 'wal' => 0, 'shm' => 1], $wordpress()['next']['open_by_source']);
$tests['vfs uri filecontrol shm current source next136 final no shm locks'] = static fn (TestRunner $t) => $t->same(0, $wordpress()['next']['shm_lock_count']);
$tests['vfs uri filecontrol shm current source next136 final byte holder retained'] = static fn (TestRunner $t) => $t->same(['wp-import' => 'reserved'], $wordpress()['next']['owners'][$owner]['holders']);
$tests['vfs uri filecontrol shm current source next136 final controls retained'] = static fn (TestRunner $t) => $t->same(['data_version' => 2, 'persist_wal' => true], $wordpress()['next']['owners'][$owner]['controls']);
$tests['vfs uri filecontrol shm current source next136 final shm source map empty'] = static fn (TestRunner $t) => $t->same([], $wordpress()['next']['shm_lock_sources'][$owner]['read0']);

$tests['vfs uri filecontrol shm current source next136 close main does not release shm lock'] = static fn (TestRunner $t) => $t->same([], $closeMain()['events'][3]['released_shm_locks']);
$tests['vfs uri filecontrol shm current source next136 close main reader remains'] = static fn (TestRunner $t) => $t->same(['wp-reader' => 'shared'], $closeMain()['events'][3]['next']['shm_locks']['/srv/www/wp-content/database/close-main.sqlite']['read1']);
$tests['vfs uri filecontrol shm current source next136 close main current source becomes shm'] = static fn (TestRunner $t) => $t->same('shm', $closeMain()['events'][3]['next']['current_source']);

$tests['vfs uri filecontrol shm current source next136 explicit current role'] = static fn (TestRunner $t) => $t->same('reader', $explicitCurrent()['events'][0]['value']);
$tests['vfs uri filecontrol shm current source next136 explicit current stale'] = static fn (TestRunner $t) => $t->same(true, $explicitCurrent()['events'][0]['stale_current_source']);
$tests['vfs uri filecontrol shm current source next136 explicit close releases replay locks'] = static fn (TestRunner $t) => $t->same(['read0:wp-reader', 'write:wp-import'], $explicitCurrent()['events'][1]['released_shm_locks']);
$tests['vfs uri filecontrol shm current source next136 explicit close keeps reserved'] = static fn (TestRunner $t) => $t->same(['wp-import' => 'reserved'], $explicitCurrent()['events'][1]['next']['lock_holders']['/srv/www/wp-content/database/replay.sqlite']);
$tests['vfs uri filecontrol shm current source next136 explicit close leaves main open'] = static fn (TestRunner $t) => $t->same(['main' => 'vfs97-2'], $explicitCurrent()['events'][1]['next']['source_handles']);
$tests['vfs uri filecontrol shm current source next136 rejects empty operations'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsLockByteUriShmCurrentSourceNext97::currentSourceNext136([]));
$tests['vfs uri filecontrol shm current source next136 rejects missing target after close'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run136(['open(shm)', 'close(shm)', 'file_control(uri_parameter, role) on shm']));

return $tests;
