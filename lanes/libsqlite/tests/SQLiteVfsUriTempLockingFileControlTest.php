<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsUriTempLockingFileControlPlan;

$runVfsUriTempFileControl = static fn (array $ops, array $options = []): array => SQLiteVfsUriTempLockingFileControlPlan::run($ops, $options);

$tempImport = static function () use ($runVfsUriTempFileControl): array {
    static $result = null;
    if ($result === null) {
        $result = $runVfsUriTempFileControl([
            'open(main, file://localhost/srv/www/wp-content/database/wp.sqlite?mode=rw&cache=shared)',
            ['op' => 'filecontrol', 'control' => 'reserve_bytes', 'value' => 32],
            'open(temp, file:/srv/www/wp-content/database/wp-temp-import.sqlite?mode=memory&cache=private)',
            'file_control(persist_wal, on)',
            'file_control(locking_mode, exclusive)',
            'lock(shared, wp-import)',
            'lock(reserved, wp-cron)',
            'lock(exclusive, wp-import)',
            'source(main)',
            'file_control(persist_wal, on)',
            'close(temp)',
            'open(temp, file:/srv/www/wp-content/database/wp-temp-import.sqlite?mode=memory&cache=private)',
            'source(temp)',
            'lock(shared, wp-cron)',
        ]);
    }

    return $result;
};

$readonly = static fn (): array => $runVfsUriTempFileControl([
    'open(archive, file:/srv/www/wp-content/database/archive.sqlite?mode=ro)',
    'file_control(reserve_bytes, 64)',
    'lock(shared, wp-reader)',
    'lock(reserved, wp-reader)',
]);

$nolock = static fn (): array => $runVfsUriTempFileControl([
    'open(temp, file:/srv/www/wp-content/database/nolock.sqlite?mode=memory&nolock=1)',
    'lock(shared, wp-import)',
]);

$persistent = static fn (): array => $runVfsUriTempFileControl([
    'open(main, file:/srv/www/wp-content/database/persist.sqlite?mode=rw)',
    'file_control(chunk_size, 8192)',
    'lock(reserved, wp-admin)',
    'close(main)',
    'open(main, file:/srv/www/wp-content/database/persist.sqlite?mode=rw)',
], [
    'persistent_controls' => [
        '/srv/www/wp-content/database/persist.sqlite' => ['persist_wal' => true],
    ],
]);

return [
    'vfs uri temp locking filecontrol current source dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-uri-temp-locking-filecontrol', $tempImport()['dependencies'], true)),
    'vfs uri temp locking filecontrol current source final status ok' => static fn (TestRunner $t) => $t->same('ok', $tempImport()['status']),
    'vfs uri temp locking filecontrol current source main opens persistent' => static fn (TestRunner $t) => $t->same(false, $tempImport()['events'][0]['temporary']),
    'vfs uri temp locking filecontrol current source main owner decoded' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp.sqlite', $tempImport()['events'][0]['owner']),
    'vfs uri temp locking filecontrol current source main not delete on close' => static fn (TestRunner $t) => $t->same(false, $tempImport()['events'][0]['delete_on_close']),
    'vfs uri temp locking filecontrol current source main reserve persistent' => static fn (TestRunner $t) => $t->same(true, $tempImport()['events'][1]['persistent']),
    'vfs uri temp locking filecontrol current source main reserve stored' => static fn (TestRunner $t) => $t->same(32, $tempImport()['events'][1]['next']['persistent_controls']['/srv/www/wp-content/database/wp.sqlite']['reserve_bytes']),
    'vfs uri temp locking filecontrol current source temp opens as temporary' => static fn (TestRunner $t) => $t->same(true, $tempImport()['events'][2]['temporary']),
    'vfs uri temp locking filecontrol current source temp delete on close' => static fn (TestRunner $t) => $t->same(true, $tempImport()['events'][2]['delete_on_close']),
    'vfs uri temp locking filecontrol current source temp owner is handle scoped' => static fn (TestRunner $t) => $t->same('temp:temp:2', $tempImport()['events'][2]['owner']),
    'vfs uri temp locking filecontrol current source temp path decoded' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp-temp-import.sqlite', $tempImport()['events'][2]['path']),
    'vfs uri temp locking filecontrol current source temp current source selected' => static fn (TestRunner $t) => $t->same('temp', $tempImport()['events'][2]['next']['current_source']),
    'vfs uri temp locking filecontrol current source temp persist wal ignored' => static fn (TestRunner $t) => $t->same('ignored', $tempImport()['events'][3]['status']),
    'vfs uri temp locking filecontrol current source temp persist reason' => static fn (TestRunner $t) => $t->same('temporary database handles do not persist WAL state', $tempImport()['events'][3]['reason']),
    'vfs uri temp locking filecontrol current source temp persist not stored' => static fn (TestRunner $t) => $t->same(false, array_key_exists('temp:temp:2', $tempImport()['events'][3]['next']['persistent_controls'])),
    'vfs uri temp locking filecontrol current source locking mode ok' => static fn (TestRunner $t) => $t->same('ok', $tempImport()['events'][4]['status']),
    'vfs uri temp locking filecontrol current source locking mode changed' => static fn (TestRunner $t) => $t->same(true, $tempImport()['events'][4]['changed']),
    'vfs uri temp locking filecontrol current source locking mode exclusive visible' => static fn (TestRunner $t) => $t->same('exclusive', $tempImport()['events'][4]['next']['handles']['vfs-uri-temp-filecontrol-2']['locking_mode']),
    'vfs uri temp locking filecontrol current source exclusive lock recorded' => static fn (TestRunner $t) => $t->same('locking_mode', $tempImport()['events'][4]['next']['handles']['vfs-uri-temp-filecontrol-2']['locks']['exclusive']),
    'vfs uri temp locking filecontrol current source shared blocked by exclusive mode' => static fn (TestRunner $t) => $t->same('busy', $tempImport()['events'][5]['status']),
    'vfs uri temp locking filecontrol current source shared blocking mode owner' => static fn (TestRunner $t) => $t->same(['locking_mode:exclusive'], $tempImport()['events'][5]['blocking']),
    'vfs uri temp locking filecontrol current source reserved blocked by exclusive mode' => static fn (TestRunner $t) => $t->same('busy', $tempImport()['events'][6]['status']),
    'vfs uri temp locking filecontrol current source exclusive by same import ok' => static fn (TestRunner $t) => $t->same('ok', $tempImport()['events'][7]['status']),
    'vfs uri temp locking filecontrol current source source main selected' => static fn (TestRunner $t) => $t->same('main', $tempImport()['events'][8]['next']['current_source']),
    'vfs uri temp locking filecontrol current source main persist wal ok' => static fn (TestRunner $t) => $t->same('ok', $tempImport()['events'][9]['status']),
    'vfs uri temp locking filecontrol current source main persist wal stored' => static fn (TestRunner $t) => $t->same(true, $tempImport()['events'][9]['next']['persistent_controls']['/srv/www/wp-content/database/wp.sqlite']['persist_wal']),
    'vfs uri temp locking filecontrol current source close temp deleted' => static fn (TestRunner $t) => $t->same(true, $tempImport()['events'][10]['deleted_temp']),
    'vfs uri temp locking filecontrol current source close temp owner listed' => static fn (TestRunner $t) => $t->same(['temp:temp:2'], $tempImport()['events'][10]['next']['deleted_temp_owners']),
    'vfs uri temp locking filecontrol current source close temp removes handle' => static fn (TestRunner $t) => $t->same(false, array_key_exists('vfs-uri-temp-filecontrol-2', $tempImport()['events'][10]['next']['handles'])),
    'vfs uri temp locking filecontrol current source reopen temp new owner' => static fn (TestRunner $t) => $t->same('temp:temp:3', $tempImport()['events'][11]['owner']),
    'vfs uri temp locking filecontrol current source reopen temp does not reuse locks' => static fn (TestRunner $t) => $t->same(false, $tempImport()['events'][11]['reused_locks']),
    'vfs uri temp locking filecontrol current source source temp after reopen' => static fn (TestRunner $t) => $t->same('temp', $tempImport()['events'][12]['next']['current_source']),
    'vfs uri temp locking filecontrol current source reader after reopen ok' => static fn (TestRunner $t) => $t->same('ok', $tempImport()['events'][13]['status']),
    'vfs uri temp locking filecontrol current source final current temp' => static fn (TestRunner $t) => $t->same('temp', $tempImport()['next']['current_source']),
    'vfs uri temp locking filecontrol current source final open count two' => static fn (TestRunner $t) => $t->same(2, $tempImport()['next']['open_count']),
    'vfs uri temp locking filecontrol current source final temp open count one' => static fn (TestRunner $t) => $t->same(1, $tempImport()['next']['temp_open_count']),
    'vfs uri temp locking filecontrol current source final persistent control count one' => static fn (TestRunner $t) => $t->same(1, $tempImport()['next']['persistent_control_count']),
    'vfs uri temp locking filecontrol current source final deleted temp owner retained' => static fn (TestRunner $t) => $t->same(['temp:temp:2'], $tempImport()['next']['deleted_temp_owners']),

    'vfs uri temp locking filecontrol current source readonly reserve ignored' => static fn (TestRunner $t) => $t->same('ignored', $readonly()['events'][1]['status']),
    'vfs uri temp locking filecontrol current source readonly shared ok' => static fn (TestRunner $t) => $t->same('ok', $readonly()['events'][2]['status']),
    'vfs uri temp locking filecontrol current source readonly reserved blocked' => static fn (TestRunner $t) => $t->same('blocked', $readonly()['events'][3]['status']),
    'vfs uri temp locking filecontrol current source readonly reserved reason' => static fn (TestRunner $t) => $t->same('readonly handle cannot take writer locks', $readonly()['events'][3]['reason']),
    'vfs uri temp locking filecontrol current source nolock shared blocked' => static fn (TestRunner $t) => $t->same('blocked', $nolock()['events'][1]['status']),
    'vfs uri temp locking filecontrol current source nolock reason' => static fn (TestRunner $t) => $t->same('nolock VFS disables temp byte-range locking', $nolock()['events'][1]['reason']),

    'vfs uri temp locking filecontrol current source persistent reuses controls' => static fn (TestRunner $t) => $t->same(true, $persistent()['events'][0]['reused_controls']),
    'vfs uri temp locking filecontrol current source persistent chunk stored' => static fn (TestRunner $t) => $t->same(8192, $persistent()['events'][1]['next']['persistent_controls']['/srv/www/wp-content/database/persist.sqlite']['chunk_size']),
    'vfs uri temp locking filecontrol current source persistent lock stored' => static fn (TestRunner $t) => $t->same(1, $persistent()['events'][2]['next']['persistent_locks']['/srv/www/wp-content/database/persist.sqlite']['reserved'] === 'wp-admin' ? 1 : 0),
    'vfs uri temp locking filecontrol current source persistent close not deleted' => static fn (TestRunner $t) => $t->same(false, $persistent()['events'][3]['deleted_temp']),
    'vfs uri temp locking filecontrol current source persistent reopen reuses controls' => static fn (TestRunner $t) => $t->same(true, $persistent()['events'][4]['reused_controls']),
    'vfs uri temp locking filecontrol current source persistent reopen reuses locks' => static fn (TestRunner $t) => $t->same(true, $persistent()['events'][4]['reused_locks']),

    'vfs uri temp locking filecontrol current source rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsUriTempLockingFileControlPlan::run([])),
    'vfs uri temp locking filecontrol current source rejects bad uri authority' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $runVfsUriTempFileControl(['open(temp, file://example.com/tmp/wp.sqlite?mode=memory)'])),
    'vfs uri temp locking filecontrol current source rejects bad locking mode' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $runVfsUriTempFileControl(['open(temp)', 'file_control(locking_mode, reserved)'])),
    'vfs uri temp locking filecontrol current source rejects bad connection' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $runVfsUriTempFileControl(['open(temp)', ['op' => 'lock', 'level' => 'shared', 'connection' => '../bad']])),
    'vfs uri temp locking filecontrol current source rejects bad reserve' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $runVfsUriTempFileControl(['open(temp)', ['op' => 'filecontrol', 'control' => 'reserve_bytes', 'value' => -1]])),
];
