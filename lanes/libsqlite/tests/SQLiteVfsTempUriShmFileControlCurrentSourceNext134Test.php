<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsShmFileControlLockCurrentSourcePlan;

$run134 = static fn (array $ops, array $options = []): array => SQLiteVfsShmFileControlLockCurrentSourcePlan::planTempUriShmFileControl($ops, $options + [
    'filename' => 'file:/srv/www/wp-content/database/wp-options.sqlite?mode=rw&cache=shared',
]);

$tempImport = static function () use ($run134): array {
    static $result = null;
    if ($result === null) {
        $result = $run134([
            'open(main, file:/srv/www/wp-content/database/wp-options.sqlite?mode=rw&cache=shared)',
            'file_control(reserve_bytes, 48)',
            'open(temp, file:/srv/www/wp-content/database/wp%20import.sqlite?mode=memory&cache=private)',
            'file_control(chunk_size, 4096)',
            'file_control(persist_wal, on)',
            'open(temp-shm, file:/srv/www/wp-content/database/wp%20import.sqlite-shm?mode=memory&cache=private)',
            ['op' => 'shmlock', 'source' => 'temp-shm', 'lock' => 'read0', 'span' => 2, 'mode' => 'shared', 'connection' => 'wp-import'],
            'source(main)',
            'file_control(persist_wal, on)',
            'source(temp-shm)',
            'file_control(data_version)',
            'close(temp-shm)',
            'close(temp)',
            'open(temp, file:/srv/www/wp-content/database/wp%20import.sqlite?mode=memory&cache=private)',
            'file_control(data_version)',
        ]);
    }

    return $result;
};

$persistentShm = static function () use ($run134): array {
    static $result = null;
    if ($result === null) {
        $result = $run134([
            'open(main, file:/srv/www/wp-content/database/site.sqlite?mode=rw&cache=shared)',
            'open(shm, file:/srv/www/wp-content/database/site.sqlite-shm?mode=rw&cache=shared)',
            ['op' => 'shmlock', 'source' => 'shm', 'lock' => 'read1', 'span' => 2, 'mode' => 'shared', 'connection' => 'wp-reader-a'],
            ['op' => 'shmlock', 'source' => 'shm', 'lock' => 'read2', 'span' => 2, 'mode' => 'shared', 'connection' => 'wp-reader-b'],
            'source(main)',
            'file_control(chunk_size, 8192)',
            'source(shm)',
            'file_control(data_version)',
        ]);
    }

    return $result;
};

$readonlyTemp = static fn (): array => $run134([
    'open(temp, file:/srv/www/wp-content/database/archive-import.sqlite?mode=ro)',
    'file_control(reserve_bytes, 64)',
    'file_control(mmap_size, 32768)',
    'open(temp-shm, file:/srv/www/wp-content/database/archive-import.sqlite-shm?mode=ro)',
    ['op' => 'shmlock', 'source' => 'temp-shm', 'lock' => 'read0', 'span' => 1, 'mode' => 'shared', 'connection' => 'wp-reader'],
]);

$closeOnlyTempShm = static fn (): array => $run134([
    'open(temp-shm, file:/tmp/wp-sort.sqlite-shm?mode=memory)',
    'close(temp-shm)',
]);

$invalid = static fn (array $ops): array => $run134($ops);

return [
    'vfs temp uri shm filecontrol current source next134 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-temp-uri-shm-filecontrol-current-source-next134', $tempImport()['dependencies'], true)),
    'vfs temp uri shm filecontrol current source next134 final status ok' => static fn (TestRunner $t) => $t->same('ok', $tempImport()['status']),
    'vfs temp uri shm filecontrol current source next134 main opens persistent' => static fn (TestRunner $t) => $t->same(false, $tempImport()['events'][0]['temporary']),
    'vfs temp uri shm filecontrol current source next134 main owner decoded' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp-options.sqlite', $tempImport()['events'][0]['owner']),
    'vfs temp uri shm filecontrol current source next134 main reserve stored' => static fn (TestRunner $t) => $t->same(48, $tempImport()['events'][1]['next']['persistent_controls']['/srv/www/wp-content/database/wp-options.sqlite']['reserve_bytes']),
    'vfs temp uri shm filecontrol current source next134 main generation bumps reserve' => static fn (TestRunner $t) => $t->same(2, $tempImport()['events'][1]['source_generation']),
    'vfs temp uri shm filecontrol current source next134 temp opens temporary' => static fn (TestRunner $t) => $t->same(true, $tempImport()['events'][2]['temporary']),
    'vfs temp uri shm filecontrol current source next134 temp delete on close' => static fn (TestRunner $t) => $t->same(true, $tempImport()['events'][2]['delete_on_close']),
    'vfs temp uri shm filecontrol current source next134 temp owner scoped' => static fn (TestRunner $t) => $t->same('temp:temp:2', $tempImport()['events'][2]['owner']),
    'vfs temp uri shm filecontrol current source next134 temp path decoded' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp import.sqlite', $tempImport()['events'][2]['path']),
    'vfs temp uri shm filecontrol current source next134 temp source selected' => static fn (TestRunner $t) => $t->same('temp', $tempImport()['events'][2]['next']['current_source']),
    'vfs temp uri shm filecontrol current source next134 temp chunk ok' => static fn (TestRunner $t) => $t->same('ok', $tempImport()['events'][3]['status']),
    'vfs temp uri shm filecontrol current source next134 temp chunk not persistent' => static fn (TestRunner $t) => $t->same(false, array_key_exists('temp:temp:2', $tempImport()['events'][3]['next']['persistent_controls'])),
    'vfs temp uri shm filecontrol current source next134 temp chunk generation local' => static fn (TestRunner $t) => $t->same(2, $tempImport()['events'][3]['source_generation']),
    'vfs temp uri shm filecontrol current source next134 temp persist ignored' => static fn (TestRunner $t) => $t->same('ignored', $tempImport()['events'][4]['status']),
    'vfs temp uri shm filecontrol current source next134 temp persist reason' => static fn (TestRunner $t) => $t->same('temporary database handles do not persist WAL state', $tempImport()['events'][4]['reason']),
    'vfs temp uri shm filecontrol current source next134 temp persist unchanged generation' => static fn (TestRunner $t) => $t->same(2, $tempImport()['events'][4]['source_generation']),
    'vfs temp uri shm filecontrol current source next134 temp shm opens temporary' => static fn (TestRunner $t) => $t->same(true, $tempImport()['events'][5]['temporary']),
    'vfs temp uri shm filecontrol current source next134 temp shm path decoded' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp import.sqlite-shm', $tempImport()['events'][5]['path']),
    'vfs temp uri shm filecontrol current source next134 temp shm owner scoped' => static fn (TestRunner $t) => $t->same('temp:temp-shm:3', $tempImport()['events'][5]['owner']),
    'vfs temp uri shm filecontrol current source next134 temp shm lock blocked' => static fn (TestRunner $t) => $t->same('blocked', $tempImport()['events'][6]['status']),
    'vfs temp uri shm filecontrol current source next134 temp shm lock reason' => static fn (TestRunner $t) => $t->same('temporary database handles do not use persistent SHM byte-range locks', $tempImport()['events'][6]['reason']),
    'vfs temp uri shm filecontrol current source next134 temp shm no partial locks' => static fn (TestRunner $t) => $t->same([], $tempImport()['events'][6]['next']['handles']['vfs87-3']['shm_locks']),
    'vfs temp uri shm filecontrol current source next134 source main ok' => static fn (TestRunner $t) => $t->same('main', $tempImport()['events'][7]['next']['current_source']),
    'vfs temp uri shm filecontrol current source next134 main persist ok' => static fn (TestRunner $t) => $t->same('ok', $tempImport()['events'][8]['status']),
    'vfs temp uri shm filecontrol current source next134 main persist stored' => static fn (TestRunner $t) => $t->same(true, $tempImport()['events'][8]['next']['persistent_controls']['/srv/www/wp-content/database/wp-options.sqlite']['persist_wal']),
    'vfs temp uri shm filecontrol current source next134 main generation three' => static fn (TestRunner $t) => $t->same(3, $tempImport()['events'][8]['source_generation']),
    'vfs temp uri shm filecontrol current source next134 source temp shm ok' => static fn (TestRunner $t) => $t->same('temp-shm', $tempImport()['events'][9]['next']['current_source']),
    'vfs temp uri shm filecontrol current source next134 temp shm data version detects temp write' => static fn (TestRunner $t) => $t->same(true, $tempImport()['events'][10]['stale_current_source']),
    'vfs temp uri shm filecontrol current source next134 temp shm data value two' => static fn (TestRunner $t) => $t->same(2, $tempImport()['events'][10]['value']),
    'vfs temp uri shm filecontrol current source next134 close temp shm deletes owner' => static fn (TestRunner $t) => $t->same(true, $tempImport()['events'][11]['deleted_temp']),
    'vfs temp uri shm filecontrol current source next134 close temp deletes owner' => static fn (TestRunner $t) => $t->same(true, $tempImport()['events'][12]['deleted_temp']),
    'vfs temp uri shm filecontrol current source next134 closed temp handles removed' => static fn (TestRunner $t) => $t->same(false, array_key_exists('vfs87-2', $tempImport()['events'][12]['next']['handles'])),
    'vfs temp uri shm filecontrol current source next134 reopen temp owner changes' => static fn (TestRunner $t) => $t->same('temp:temp:4', $tempImport()['events'][13]['owner']),
    'vfs temp uri shm filecontrol current source next134 reopen temp controls not reused' => static fn (TestRunner $t) => $t->same(false, $tempImport()['events'][13]['reused_controls']),
    'vfs temp uri shm filecontrol current source next134 reopened temp data version one' => static fn (TestRunner $t) => $t->same(1, $tempImport()['events'][14]['value']),
    'vfs temp uri shm filecontrol current source next134 final current temp' => static fn (TestRunner $t) => $t->same('temp', $tempImport()['next']['current_source']),
    'vfs temp uri shm filecontrol current source next134 final open main and temp' => static fn (TestRunner $t) => $t->same(['main' => 1, 'wal' => 0, 'shm' => 0, 'temp' => 1, 'temp-shm' => 0], $tempImport()['next']['open_by_source']),
    'vfs temp uri shm filecontrol current source next134 final temp open count' => static fn (TestRunner $t) => $t->same(1, $tempImport()['next']['temp_open_count']),
    'vfs temp uri shm filecontrol current source next134 final persistent count one' => static fn (TestRunner $t) => $t->same(1, $tempImport()['next']['persistent_control_count']),
    'vfs temp uri shm filecontrol current source next134 final deleted temp owners' => static fn (TestRunner $t) => $t->same(['temp:temp-shm:3', 'temp:temp:2'], $tempImport()['next']['deleted_temp_owners']),

    'vfs temp uri shm filecontrol current source next134 persistent shm first range ok' => static fn (TestRunner $t) => $t->same('ok', $persistentShm()['events'][2]['status']),
    'vfs temp uri shm filecontrol current source next134 persistent shm range locks' => static fn (TestRunner $t) => $t->same(['read1', 'read2'], $persistentShm()['events'][2]['locks']),
    'vfs temp uri shm filecontrol current source next134 persistent shm overlapping shared ok' => static fn (TestRunner $t) => $t->same('ok', $persistentShm()['events'][3]['status']),
    'vfs temp uri shm filecontrol current source next134 persistent shm read2 owners' => static fn (TestRunner $t) => $t->same(['wp-reader-a', 'wp-reader-b'], $persistentShm()['events'][3]['owner_locks']['read2']),
    'vfs temp uri shm filecontrol current source next134 persistent main chunk bump' => static fn (TestRunner $t) => $t->same(2, $persistentShm()['events'][5]['source_generation']),
    'vfs temp uri shm filecontrol current source next134 persistent shm stale after main write' => static fn (TestRunner $t) => $t->same(true, $persistentShm()['events'][7]['stale_current_source']),
    'vfs temp uri shm filecontrol current source next134 persistent final shm locks three' => static fn (TestRunner $t) => $t->same(3, $persistentShm()['next']['shm_lock_count']),
    'vfs temp uri shm filecontrol current source next134 persistent final connections two' => static fn (TestRunner $t) => $t->same(2, $persistentShm()['next']['persistent_shm_connection_count']),

    'vfs temp uri shm filecontrol current source next134 readonly temp reserve ignored' => static fn (TestRunner $t) => $t->same('ignored', $readonlyTemp()['events'][1]['status']),
    'vfs temp uri shm filecontrol current source next134 readonly temp reserve reason' => static fn (TestRunner $t) => $t->same('readonly handle ignores mutating file-control', $readonlyTemp()['events'][1]['reason']),
    'vfs temp uri shm filecontrol current source next134 readonly temp mmap ok' => static fn (TestRunner $t) => $t->same('ok', $readonlyTemp()['events'][2]['status']),
    'vfs temp uri shm filecontrol current source next134 readonly temp shm lock blocked temp reason' => static fn (TestRunner $t) => $t->same('temporary database handles do not use persistent SHM byte-range locks', $readonlyTemp()['events'][4]['reason']),
    'vfs temp uri shm filecontrol current source next134 close only temp shm deleted' => static fn (TestRunner $t) => $t->same(['temp:temp-shm:1'], $closeOnlyTempShm()['next']['deleted_temp_owners']),

    'vfs temp uri shm filecontrol current source next134 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsShmFileControlLockCurrentSourcePlan::planTempUriShmFileControl([])),
    'vfs temp uri shm filecontrol current source next134 rejects bad uri authority' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $invalid(['open(temp, file://example.com/tmp/wp.sqlite?mode=memory)'])),
    'vfs temp uri shm filecontrol current source next134 rejects bad shm range' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $invalid(['open(shm)', ['op' => 'shmlock', 'source' => 'shm', 'lock' => 'read4', 'span' => 2, 'mode' => 'shared']])),
    'vfs temp uri shm filecontrol current source next134 rejects bad connection' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $invalid(['open(temp-shm)', ['op' => 'shmlock', 'source' => 'temp-shm', 'lock' => 'read0', 'span' => 1, 'mode' => 'shared', 'connection' => '../bad']])),
];
