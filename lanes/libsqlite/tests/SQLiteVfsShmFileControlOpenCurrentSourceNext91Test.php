<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsShmOpenFileControlCurrentSourcePlan;

$tests = [];

$run91 = static fn (array $ops, array $options = []): array => SQLiteVfsShmOpenFileControlCurrentSourcePlan::planShmOpenFileControl(
    $ops,
    $options + ['filename' => 'file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=shared'],
);

$sidecarFirst = static function () use ($run91): array {
    static $result = null;
    if ($result === null) {
        $result = $run91([
            'open(file:/srv/www/wp-content/database/wp%20copy.sqlite-shm?mode=rw&cache=shared)',
            'file_control(persist_wal, on)',
            'file_control(chunk_size, 16384)',
            'open(main)',
            'source(main)',
            'file_control(mmap_size, 262144)',
            'close(shm)',
            'open(file://localhost/srv/www/wp-content/database/wp%20copy.sqlite-wal?mode=rw&cache=private)',
            'file_control(reserve_bytes, 32)',
        ]);
    }

    return $result;
};

$readonly = static fn (): array => $run91([
    'open(file:/srv/www/wp-content/database/archive%20copy.sqlite-shm?mode=ro)',
    'file_control(chunk_size, 4096)',
    'file_control(mmap_size, 65536)',
    'open(file:/srv/www/wp-content/database/archive%20copy.sqlite?mode=ro)',
], ['filename' => 'file:/srv/www/wp-content/database/archive%20copy.sqlite?mode=ro']);

$memory = static fn (): array => $run91([
    'open(file::memory:?mode=memory&cache=shared)',
    'file_control(persist_wal, on)',
    'open(file::memory:?mode=memory&cache=shared)',
]);

$explicit = static fn (): array => $run91([
    'open(file://localhost/srv/www/wp-content/database/wp%20copy.sqlite-shm?mode=rw)',
    'open(file://localhost/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw)',
], [
    'current' => [
        'persistent_controls' => [
            '/srv/www/wp-content/database/wp copy.sqlite' => [
                'persist_wal' => true,
                'chunk_size' => 8192,
            ],
        ],
    ],
]);

$missing = static fn (): array => $run91([
    'source(shm)',
    'file_control(mmap_size, 1)',
]);

$tests['vfs shm filecontrol open current source next91 status'] = static fn (TestRunner $t) => $t->same('ok', $sidecarFirst()['status']);
$tests['vfs shm filecontrol open current source next91 dependency marker'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-shm-filecontrol-open-current-source-next91', $sidecarFirst()['dependencies'], true));
$tests['vfs shm filecontrol open current source next91 starts with shm source'] = static fn (TestRunner $t) => $t->same('shm', $sidecarFirst()['events'][0]['source']);
$tests['vfs shm filecontrol open current source next91 shm path suffix'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite-shm', $sidecarFirst()['events'][0]['path']);
$tests['vfs shm filecontrol open current source next91 shm owner canonical'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $sidecarFirst()['events'][0]['owner']);
$tests['vfs shm filecontrol open current source next91 shm sidecar first'] = static fn (TestRunner $t) => $t->same(true, $sidecarFirst()['events'][0]['sidecar_open_first']);
$tests['vfs shm filecontrol open current source next91 first uri decoded path'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite-shm', $sidecarFirst()['events'][0]['uri']['path']);
$tests['vfs shm filecontrol open current source next91 first cache shared'] = static fn (TestRunner $t) => $t->same('shared', $sidecarFirst()['events'][0]['uri']['cache']);
$tests['vfs shm filecontrol open current source next91 persist wal routes owner database'] = static fn (TestRunner $t) => $t->same('owner-database', $sidecarFirst()['events'][1]['routed_to']);
$tests['vfs shm filecontrol open current source next91 persist wal status ok'] = static fn (TestRunner $t) => $t->same('ok', $sidecarFirst()['events'][1]['status']);
$tests['vfs shm filecontrol open current source next91 persist wal value true'] = static fn (TestRunner $t) => $t->same(true, $sidecarFirst()['events'][1]['value']);
$tests['vfs shm filecontrol open current source next91 persist wal stored by owner'] = static fn (TestRunner $t) => $t->same(true, $sidecarFirst()['events'][1]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['persist_wal']);
$tests['vfs shm filecontrol open current source next91 chunk changed'] = static fn (TestRunner $t) => $t->same(true, $sidecarFirst()['events'][2]['changed']);
$tests['vfs shm filecontrol open current source next91 chunk stored by owner'] = static fn (TestRunner $t) => $t->same(16384, $sidecarFirst()['events'][2]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['chunk_size']);
$tests['vfs shm filecontrol open current source next91 main reopen source'] = static fn (TestRunner $t) => $t->same('main', $sidecarFirst()['events'][3]['source']);
$tests['vfs shm filecontrol open current source next91 main reopen path'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $sidecarFirst()['events'][3]['path']);
$tests['vfs shm filecontrol open current source next91 main reopen reuses controls'] = static fn (TestRunner $t) => $t->same(true, $sidecarFirst()['events'][3]['reused_controls']);
$tests['vfs shm filecontrol open current source next91 main handle sees persist wal'] = static fn (TestRunner $t) => $t->same(true, $sidecarFirst()['events'][3]['next']['handles']['vfs91-2']['controls']['persist_wal']);
$tests['vfs shm filecontrol open current source next91 main handle sees chunk'] = static fn (TestRunner $t) => $t->same(16384, $sidecarFirst()['events'][3]['next']['handles']['vfs91-2']['controls']['chunk_size']);
$tests['vfs shm filecontrol open current source next91 source main ok'] = static fn (TestRunner $t) => $t->same('ok', $sidecarFirst()['events'][4]['status']);
$tests['vfs shm filecontrol open current source next91 source main current'] = static fn (TestRunner $t) => $t->same('main', $sidecarFirst()['events'][4]['next']['current_source']);
$tests['vfs shm filecontrol open current source next91 mmap stored by owner'] = static fn (TestRunner $t) => $t->same(262144, $sidecarFirst()['events'][5]['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['mmap_size']);
$tests['vfs shm filecontrol open current source next91 mmap updates shm handle too'] = static fn (TestRunner $t) => $t->same(262144, $sidecarFirst()['events'][5]['next']['handles']['vfs91-1']['controls']['mmap_size']);
$tests['vfs shm filecontrol open current source next91 close shm status'] = static fn (TestRunner $t) => $t->same('closed', $sidecarFirst()['events'][6]['status']);
$tests['vfs shm filecontrol open current source next91 close shm retains controls'] = static fn (TestRunner $t) => $t->same(true, $sidecarFirst()['events'][6]['persistent_controls_retained']);
$tests['vfs shm filecontrol open current source next91 close shm leaves main current'] = static fn (TestRunner $t) => $t->same('main', $sidecarFirst()['events'][6]['next']['current_source']);
$tests['vfs shm filecontrol open current source next91 wal uri source'] = static fn (TestRunner $t) => $t->same('wal', $sidecarFirst()['events'][7]['source']);
$tests['vfs shm filecontrol open current source next91 wal path suffix'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite-wal', $sidecarFirst()['events'][7]['path']);
$tests['vfs shm filecontrol open current source next91 wal authority localhost'] = static fn (TestRunner $t) => $t->same('localhost', $sidecarFirst()['events'][7]['uri']['authority']);
$tests['vfs shm filecontrol open current source next91 wal cache private'] = static fn (TestRunner $t) => $t->same('private', $sidecarFirst()['events'][7]['uri']['cache']);
$tests['vfs shm filecontrol open current source next91 wal reuses controls'] = static fn (TestRunner $t) => $t->same(true, $sidecarFirst()['events'][7]['reused_controls']);
$tests['vfs shm filecontrol open current source next91 wal handle sees mmap'] = static fn (TestRunner $t) => $t->same(262144, $sidecarFirst()['events'][7]['next']['handles']['vfs91-3']['controls']['mmap_size']);
$tests['vfs shm filecontrol open current source next91 reserve routes through wal owner'] = static fn (TestRunner $t) => $t->same('owner-database', $sidecarFirst()['events'][8]['routed_to']);
$tests['vfs shm filecontrol open current source next91 reserve stored'] = static fn (TestRunner $t) => $t->same(32, $sidecarFirst()['next']['persistent_controls']['/srv/www/wp-content/database/wp copy.sqlite']['reserve_bytes']);
$tests['vfs shm filecontrol open current source next91 final current source wal'] = static fn (TestRunner $t) => $t->same('wal', $sidecarFirst()['next']['current_source']);
$tests['vfs shm filecontrol open current source next91 final open by source'] = static fn (TestRunner $t) => $t->same(['main' => 1, 'wal' => 1, 'shm' => 0], $sidecarFirst()['next']['open_by_source']);
$tests['vfs shm filecontrol open current source next91 final owner count'] = static fn (TestRunner $t) => $t->same(1, $sidecarFirst()['next']['owner_count']);
$tests['vfs shm filecontrol open current source next91 final persistent control count'] = static fn (TestRunner $t) => $t->same(1, $sidecarFirst()['next']['persistent_control_count']);

$tests['vfs shm filecontrol open current source next91 readonly shm source'] = static fn (TestRunner $t) => $t->same('shm', $readonly()['events'][0]['source']);
$tests['vfs shm filecontrol open current source next91 readonly chunk ignored'] = static fn (TestRunner $t) => $t->same('ignored', $readonly()['events'][1]['status']);
$tests['vfs shm filecontrol open current source next91 readonly chunk reason'] = static fn (TestRunner $t) => $t->same('readonly_owner_handle', $readonly()['events'][1]['reason']);
$tests['vfs shm filecontrol open current source next91 readonly mmap ok'] = static fn (TestRunner $t) => $t->same('ok', $readonly()['events'][2]['status']);
$tests['vfs shm filecontrol open current source next91 readonly mmap stored'] = static fn (TestRunner $t) => $t->same(65536, $readonly()['events'][2]['next']['persistent_controls']['/srv/www/wp-content/database/archive copy.sqlite']['mmap_size']);
$tests['vfs shm filecontrol open current source next91 readonly main reuses mmap'] = static fn (TestRunner $t) => $t->same(65536, $readonly()['events'][3]['next']['handles']['vfs91-2']['controls']['mmap_size']);

$tests['vfs shm filecontrol open current source next91 memory first path empty'] = static fn (TestRunner $t) => $t->same('', $memory()['events'][0]['path']);
$tests['vfs shm filecontrol open current source next91 memory owner unique'] = static fn (TestRunner $t) => $t->same('memory:vfs91-1', $memory()['events'][0]['owner']);
$tests['vfs shm filecontrol open current source next91 memory filecontrol ignored'] = static fn (TestRunner $t) => $t->same('ignored', $memory()['events'][1]['status']);
$tests['vfs shm filecontrol open current source next91 memory reason'] = static fn (TestRunner $t) => $t->same('memory_source_has_no_persistent_owner', $memory()['events'][1]['reason']);
$tests['vfs shm filecontrol open current source next91 memory second owner distinct'] = static fn (TestRunner $t) => $t->same('memory:vfs91-2', $memory()['events'][2]['owner']);
$tests['vfs shm filecontrol open current source next91 memory final no persistent controls'] = static fn (TestRunner $t) => $t->same(0, $memory()['next']['persistent_control_count']);

$tests['vfs shm filecontrol open current source next91 explicit current shm reuses controls'] = static fn (TestRunner $t) => $t->same(true, $explicit()['events'][0]['reused_controls']);
$tests['vfs shm filecontrol open current source next91 explicit current shm chunk'] = static fn (TestRunner $t) => $t->same(8192, $explicit()['events'][0]['next']['handles']['vfs91-1']['controls']['chunk_size']);
$tests['vfs shm filecontrol open current source next91 explicit current main reuses controls'] = static fn (TestRunner $t) => $t->same(true, $explicit()['events'][1]['reused_controls']);
$tests['vfs shm filecontrol open current source next91 explicit current main persist wal'] = static fn (TestRunner $t) => $t->same(true, $explicit()['events'][1]['next']['handles']['vfs91-2']['controls']['persist_wal']);

$tests['vfs shm filecontrol open current source next91 missing source switch'] = static fn (TestRunner $t) => $t->same('missing-handle', $missing()['events'][0]['status']);
$tests['vfs shm filecontrol open current source next91 missing filecontrol handle'] = static fn (TestRunner $t) => $t->same('missing-handle', $missing()['events'][1]['status']);
$tests['vfs shm filecontrol open current source next91 rejects empty operations'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsShmOpenFileControlCurrentSourcePlan::planShmOpenFileControl([]));
$tests['vfs shm filecontrol open current source next91 rejects remote authority'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run91(['open(file://example.com/srv/db.sqlite-shm?mode=rw)']));
$tests['vfs shm filecontrol open current source next91 rejects bad percent'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run91(['open(file:/srv/db%2.sqlite-shm?mode=rw)']));
$tests['vfs shm filecontrol open current source next91 rejects bad source'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run91([['op' => 'open', 'source' => 'temp']]));
$tests['vfs shm filecontrol open current source next91 rejects bad chunk'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run91(['open(shm)', ['op' => 'filecontrol', 'control' => 'chunk_size', 'value' => -1]]));
$tests['vfs shm filecontrol open current source next91 rejects empty name hint'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run91(['open(shm)', ['op' => 'filecontrol', 'control' => 'name_hint', 'value' => '']]));

return $tests;
