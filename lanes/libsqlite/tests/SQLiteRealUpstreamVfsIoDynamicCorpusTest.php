<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;

$tests = [];

$traffic = [
    'io.test io-2.2 normal insert writes two database pages' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-2.2', 1024, 1)['database_writes'], 2],
    'io.test io-2.2 normal insert creates rollback journal' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-2.2', 1024, 1)['journal_created'], true],
    'io.test io-2.2 normal insert syncs directory journal pages header and database' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-2.2', 1024, 1)['sync_targets'], ['directory', 'rollback_journal_pages', 'rollback_journal_header', 'database']],
    'io.test io-2.2 normal insert records four syncs' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-2.2', 1024, 1)['syncs'], 4],
    'io.test io-2.3 atomic insert keeps database write count' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-2.3', 1024, 1, 0, ['atomic'])['database_writes'], 2],
    'io.test io-2.3 atomic insert avoids journal creation' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-2.3', 1024, 1, 0, ['atomic'])['journal_created'], false],
    'io.test io-2.3 atomic insert uses one database sync' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-2.3', 1024, 1, 0, ['atomic'])['sync_targets'], ['database']],
    'io.test io-2.3 atomic insert reason names optimization' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-2.3', 1024, 1, 0, ['atomic'])['reason'], 'atomic_write_avoids_rollback_journal'],
    'io.test io-2.5 multi page transaction disables atomic shortcut' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-2.5', 1024, 2, 0, ['atomic'])['atomic_write'], false],
    'io.test io-2.5 multi page transaction creates journal' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-2.5', 1024, 2, 0, ['atomic'])['journal_created'], true],
    'io.test io-2.5 multi page transaction writes three database pages' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-2.5', 1024, 2, 0, ['atomic'])['database_writes'], 3],
    'io.test io-2.6 appended page defers journal until commit' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-2.6', 1024, 1, 1, ['atomic'])['journal_deferred_until_commit'], true],
    'io.test io-2.6 appended page reports deferred reason' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-2.6', 1024, 1, 1, ['atomic'])['reason'], 'journal_deferred_until_commit_boundary'],
    'io.test io-2.7 multi file commit forces journal despite atomic' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-2.7', 1024, 1, 0, ['atomic'], 512, 'full', true)['journal_created'], true],
    'io.test io-2.7 multi file commit is not atomic write' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-2.7', 1024, 1, 0, ['atomic'], 512, 'full', true)['atomic_write'], false],
    'io.test io-2.8 rollback before journal creation remains deferred' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-2.8', 1024, 1, 1, ['atomic'])['journal_created'], true],
    'io.test io-2.9 sector larger than page disables atomic' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-2.9', 1024, 1, 0, ['atomic'], 2048)['atomic_write'], false],
    'io.test io-2.9 sector not larger allows atomic' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-2.9', 2048, 1, 0, ['atomic'], 2048)['atomic_write'], true],
    'io.test io-2.10 atomic1k does not cover 2k page' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-2.10', 2048, 1, 0, ['atomic1k'])['atomic_write'], false],
    'io.test io-2.10 atomic2k covers 2k page' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-2.10', 2048, 1, 0, ['atomic2k'])['atomic_write'], true],
    'io.test io-2.11 exclusive locking keeps atomic path' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-2.11', 2048, 1, 0, ['atomic2k'], 512, 'full', false, true)['reason'], 'atomic_write_under_exclusive_lock'],
    'io.test io-3.2 sequential spill performs no precommit syncs' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-3.2', 1024, 30, 0, ['sequential'], 0, 'full', false, false, true)['cache_spill_syncs'], 0],
    'io.test io-3.3 sequential commit performs one database sync' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-3.3', 1024, 30, 0, ['sequential'], 0, 'full', false, false, true)['commit_syncs'], 1],
    'io.test io-3.3 sequential omits rollback journal sync targets' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-3.3', 1024, 30, 0, ['sequential'], 0)['sync_targets'], ['directory', 'database']],
    'io.test io-4.1 safe append uses three sync targets' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-4.1', 1024, 1, 0, ['safe_append'])['sync_targets'], ['directory', 'rollback_journal_pages', 'database']],
    'io.test io-4.1 safe append reports three syncs' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-4.1', 1024, 1, 0, ['safe_append'])['syncs'], 3],
    'io.test io-4.2 safe append writes nrec sentinel' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-4.2', 1024, 1, 0, ['safe_append'])['journal_header_nrec'], 0xffffffff],
    'io.test io-4.3 safe append keeps one journal header write for many pages' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-4.3', 1024, 41, 0, ['safe_append'])['journal_writes'], 1],
    'io.test io-4.3 non safe append writes extra journal headers for many pages' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-4.3', 1024, 41)['journal_writes'], 3],
    'io.test io-5 atomic default page size is 8192' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-5', 512, 0, 0, ['atomic'])['default_page_size'], 8192],
    'io.test io-5 atomic512 default page size is 1024' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-5', 512, 0, 0, ['atomic512'])['default_page_size'], 1024],
    'io.test io-5 atomic2k raises small page to 2048' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-5', 512, 0, 0, ['atomic2k'])['default_page_size'], 2048],
    'io.test io-5 atomic2k preserves 4096 page' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-5', 4096, 0, 0, ['atomic2k'])['default_page_size'], 4096],
    'io.test io-5 atomic64k preserves minimum 1024 page' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-5', 512, 0, 0, ['atomic64k'])['default_page_size'], 1024],
    'io.test io-6 atomic path leaves cache usable without journal' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-6', 1024, 1, 0, ['atomic'])['journal_created'], false],
    'walvfs.test 1.1 normal wal append syncs wal once' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('walvfs-1.1', 1024, 1, 0, [], 512, 'normal')['syncs'], 4],
    'walvfs.test 1.3 sequential wal append skips immediate wal sync' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('walvfs-1.3', 1024, 1, 0, ['sequential'], 512, 'normal')['sync_targets'], ['directory', 'database']],
    'walvfs.test 1.3 sequential wal append reports sequential reason' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('walvfs-1.3', 1024, 1, 0, ['sequential'], 512, 'normal')['reason'], 'sequential_device_defers_journal_sync_until_commit'],
    'io.test generic synchronous off skips sync targets' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-sync-off', 1024, 1, 0, [], 512, 'off')['sync_targets'], []],
    'io.test generic no dirty pages remains read-only plan' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::transaction('io-read-only', 1024, 0)['reason'], 'read_only_or_no_dirty_pages'],
    'io.test generic dependencies cite upstream io test' => [static fn (): mixed => in_array('sqlite-upstream-io-test', SQLiteVfsIoTrafficPlan::transaction('io-deps', 1024, 1)['dependencies'], true), true],
];

foreach ($traffic as $name => [$callback, $expected]) {
    $tests['real upstream corpus vfs io dynamic ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$errors = [
    'ioerr.test ioerr-1 read past eof is suppressed' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::ioErrorBoundary('ioerr.test', 'ioerr-1', 'read', 8, true)['detected'], false],
    'ioerr.test ioerr-1 read past eof reason' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::ioErrorBoundary('ioerr.test', 'ioerr-1', 'read', 8, true)['reason'], 'pager_suppresses_read_past_eof_ioerr'],
    'ioerr.test ioerr-2 vacuum write error requests checksum check' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::ioErrorBoundary('ioerr.test', 'ioerr-2', 'write', 9, false, false, true)['checksum_check'], true],
    'ioerr.test ioerr-2 vacuum write error rolls back' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::ioErrorBoundary('ioerr.test', 'ioerr-2', 'write', 9, false, false, true)['rollback_required'], true],
    'ioerr.test ioerr-3 delete update write propagates' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::ioErrorBoundary('ioerr.test', 'ioerr-3', 'write', 12)['detected'], true],
    'ioerr.test ioerr-4 overflow record header read propagates' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::ioErrorBoundary('ioerr.test', 'ioerr-4', 'read', 3)['refcount_check'], true],
    'ioerr.test ioerr-5 multifile commit sync error rolls back' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::ioErrorBoundary('ioerr.test', 'ioerr-5', 'sync', 17)['rollback_required'], true],
    'ioerr.test ioerr-7 hot journal rollback error leaves journal' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::ioErrorBoundary('ioerr.test', 'ioerr-7', 'write', 2, false, true)['hot_journal_left'], true],
    'ioerr.test ioerr-8 short field read error propagates' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::ioErrorBoundary('ioerr.test', 'ioerr-8', 'read', 4)['detected'], true],
    'ioerr.test ioerr-9 master journal name read error propagates' => [static fn (): mixed => SQLiteVfsIoTrafficPlan::ioErrorBoundary('ioerr.test', 'ioerr-9', 'read', 5)['reason'], 'io_error_propagates_to_pager_boundary'],
    'ioerr.test dependency marker records pager boundary' => [static fn (): mixed => in_array('sqlite-pager-io-error-boundary', SQLiteVfsIoTrafficPlan::ioErrorBoundary('ioerr.test', 'ioerr-9', 'read', 5)['dependencies'], true), true],
];

foreach ($errors as $name => [$callback, $expected]) {
    $tests['real upstream corpus vfs io dynamic ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['real upstream corpus vfs io dynamic rejects empty scenario'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::transaction('', 1024, 1));
};
$tests['real upstream corpus vfs io dynamic rejects non power page size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::transaction('bad-page', 1000, 1));
};
$tests['real upstream corpus vfs io dynamic rejects bad device flag'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::transaction('bad-flag', 1024, 1, 0, ['not-a-vfs-flag']));
};
$tests['real upstream corpus vfs io dynamic rejects negative page count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::transaction('bad-count', 1024, -1));
};
$tests['real upstream corpus vfs io dynamic rejects empty ioerr operation'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::ioErrorBoundary('ioerr.test', 'ioerr-1', '', 1));
};
$tests['real upstream corpus vfs io dynamic rejects zero ioerr index'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::ioErrorBoundary('ioerr.test', 'ioerr-1', 'read', 0));
};

return $tests;
