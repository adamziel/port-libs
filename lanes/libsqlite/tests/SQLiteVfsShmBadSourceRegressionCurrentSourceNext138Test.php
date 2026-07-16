<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsShmFileControlLockCurrentSourcePlan;

$run138 = static fn (array $ops, array $options = []): array => SQLiteVfsShmFileControlLockCurrentSourcePlan::planShmBadSourceRegression($ops, $options + [
    'filename' => 'file:/srv/www/wp-content/database/bad-source.sqlite?mode=rw&cache=shared',
]);

$baseline = static fn (): array => $run138([
    'open(main)',
    'open(shm)',
    ['op' => 'shmlock', 'source' => 'shm', 'lock' => 'read0', 'mode' => 'shared', 'connection' => 'wp-reader'],
    'source(main)',
    'file_control(persist_wal, on)',
    'source(shm)',
    'file_control(data_version)',
]);

$badCases = [
    'array unsupported op' => [['op' => 'checkpoint']],
    'array empty op' => [['op' => '']],
    'array bad open source' => [['op' => 'open', 'source' => 'temp']],
    'array bad source switch' => [['op' => 'source', 'source' => 'temp']],
    'array bad close source' => [['op' => 'close', 'source' => 'temp']],
    'array bad filecontrol source' => [['op' => 'filecontrol', 'source' => 'temp', 'control' => 'mmap_size', 'value' => 4096]],
    'array bad shmlock source' => [['op' => 'shmlock', 'source' => 'temp', 'lock' => 'read0', 'mode' => 'shared']],
    'array bad xfilecontrol source' => [['op' => 'xFileControl', 'source' => 'temp', 'control' => 'mmap_size', 'value' => 4096]],
    'array bad xshmlock source' => [['op' => 'xShmLock', 'source' => 'temp', 'lock' => 'read0', 'mode' => 'shared']],
    'string unsupported op' => ['checkpoint'],
    'string bad source switch spelling' => ['source(temp)'],
    'string bad open source spelling' => ['open(temp)'],
];

$afterValidPrefix = static fn (array $suffix): array => array_merge([
    'open(main)',
    'open(shm)',
    ['op' => 'shmlock', 'source' => 'shm', 'lock' => 'read1', 'mode' => 'shared', 'connection' => 'wp-reader'],
], $suffix);

$tests = [
    'vfs shm bad source regression current source next138 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-shm-bad-source-regression-current-source-next138', $baseline()['dependencies'], true)),
    'vfs shm bad source regression current source next138 preserves base dependency' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-shm-current-source-routing', $baseline()['dependencies'], true)),
    'vfs shm bad source regression current source next138 final status ok' => static fn (TestRunner $t) => $t->same('ok', $baseline()['status']),
    'vfs shm bad source regression current source next138 owner canonical' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/bad-source.sqlite', $baseline()['events'][0]['owner']),
    'vfs shm bad source regression current source next138 shm source opened' => static fn (TestRunner $t) => $t->same('shm', $baseline()['events'][1]['source']),
    'vfs shm bad source regression current source next138 shared reader stored' => static fn (TestRunner $t) => $t->same(['wp-reader'], $baseline()['events'][2]['owners']),
    'vfs shm bad source regression current source next138 source main selected' => static fn (TestRunner $t) => $t->same('main', $baseline()['events'][3]['next']['current_source']),
    'vfs shm bad source regression current source next138 persist bumps generation' => static fn (TestRunner $t) => $t->same(2, $baseline()['events'][4]['source_generation']),
    'vfs shm bad source regression current source next138 source shm selected' => static fn (TestRunner $t) => $t->same('shm', $baseline()['events'][5]['next']['current_source']),
    'vfs shm bad source regression current source next138 stale shm detected' => static fn (TestRunner $t) => $t->same(true, $baseline()['events'][6]['stale_current_source']),
    'vfs shm bad source regression current source next138 stale opened generation' => static fn (TestRunner $t) => $t->same(1, $baseline()['events'][6]['opened_generation']),
    'vfs shm bad source regression current source next138 current generation value' => static fn (TestRunner $t) => $t->same(2, $baseline()['events'][6]['value']),
    'vfs shm bad source regression current source next138 final open by source' => static fn (TestRunner $t) => $t->same(['main' => 1, 'wal' => 0, 'shm' => 1], $baseline()['next']['open_by_source']),
    'vfs shm bad source regression current source next138 final shm lock count' => static fn (TestRunner $t) => $t->same(1, $baseline()['next']['shm_lock_count']),
    'vfs shm bad source regression current source next138 final connection count' => static fn (TestRunner $t) => $t->same(1, $baseline()['next']['persistent_shm_connection_count']),
    'vfs shm bad source regression current source next138 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsShmFileControlLockCurrentSourcePlan::planShmBadSourceRegression([])),
];

foreach ($badCases as $name => $ops) {
    $tests['vfs shm bad source regression current source next138 rejects ' . $name] = static function (TestRunner $t) use ($run138, $ops): void {
        $t->throws(InvalidArgumentException::class, static fn () => $run138($ops));
    };
    $tests['vfs shm bad source regression current source next138 rejects ' . $name . ' after valid prefix'] = static function (TestRunner $t) use ($run138, $afterValidPrefix, $ops): void {
        $t->throws(InvalidArgumentException::class, static fn () => $run138($afterValidPrefix($ops)));
    };
}

$tests['vfs shm bad source regression current source next138 still reports missing shm handle'] = static fn (TestRunner $t) => $t->same('missing-handle', $run138([['op' => 'shmlock', 'source' => 'shm', 'lock' => 'read0', 'mode' => 'shared']])['status']);
$tests['vfs shm bad source regression current source next138 still reports missing source switch'] = static fn (TestRunner $t) => $t->same('missing-handle', $run138(['source(shm)'])['status']);
$tests['vfs shm bad source regression current source next138 still reports missing database control handle'] = static fn (TestRunner $t) => $t->same('missing-handle', $run138([['op' => 'filecontrol', 'source' => 'shm', 'control' => 'mmap_size', 'value' => 4096]])['status']);

return $tests;
