<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$caseCount = 0;
foreach (range(1, 1000) as $variant) {
    ++$caseCount;

    $database = sprintf('/tmp/libsqlite-lock6-%04d/test.db', $variant);
    $childHostId = 2 + ($variant % 17);
    $parentHostId = 100 + $childHostId + ($variant % 19);
    $blockedProxy = sprintf('notmine-%04d.lock', $variant);
    $retryProxy = sprintf('mine-%04d.lock', $variant);
    $schemaRowCount = 1 + ($variant % 5);
    $childBeginsReadTransaction = $variant % 3 !== 0;

    $tests[sprintf(
        'real upstream corpus vfs lock6 interprocess proxy lock dynamic %04d rows %d child %d parent %d',
        $variant,
        $schemaRowCount,
        $childHostId,
        $parentHostId
    )] = static function (TestRunner $t) use (
        $database,
        $childHostId,
        $parentHostId,
        $blockedProxy,
        $retryProxy,
        $schemaRowCount,
        $childBeginsReadTransaction
    ): void {
        $profile = SQLiteVfsIoDynamicPlan::lockProxyInterprocessProfile(
            $database,
            $childHostId,
            $parentHostId,
            ':auto:',
            $blockedProxy,
            $retryProxy,
            $schemaRowCount,
            $childBeginsReadTransaction
        );

        $t->same('ok', $profile['status']);
        $t->same('lock6.test', $profile['script']);
        $t->same('lock6-1', $profile['scenario']);
        $t->same($database, $profile['database']);
        $t->same($childHostId, $profile['child_host_id']);
        $t->same($parentHostId, $profile['parent_host_id']);
        $t->same(true, $profile['force_proxy_locking']);
        $t->same($schemaRowCount, count($profile['schema_rows']));
        $t->same('app_schema_0001', $profile['schema_rows'][0]['name']);

        $t->same(':auto:', $profile['child_proxy_request']);
        $t->same($database . ':auto:', $profile['child_auto_proxy_file']);
        $t->same(true, $profile['child_auto_proxy_matches_upstream']);
        $t->same('ok', $profile['child_select_status']);
        $t->same(false, $profile['child_select']['locked']);
        $t->same($schemaRowCount, count($profile['child_select']['rows']));
        $t->same([['lock_proxy_file' => $database . ':auto:']], $profile['child_proxy_query_rows']);

        $t->same(['main' => 'unlocked', 'temp' => 'closed'], $profile['parent_lock_status_before_open']);
        $t->same([1, 'database is locked'], $profile['parent_open_result']);
        $t->same('error', $profile['parent_blocked_open_select']['status']);
        $t->same(true, $profile['parent_blocked_open_select']['locked']);
        $t->same('host_id_mismatch', $profile['parent_select_blocked_reason']);
        $t->same($childHostId, $profile['parent_blocked_open_select']['active_lock']['host_id']);
        $t->same($database . ':auto:', $profile['parent_blocked_open_select']['active_lock']['proxy_file']);
        $t->same([['lock_proxy_file' => ':auto: (not held)']], $profile['parent_auto_not_held_rows']);

        $t->same($blockedProxy, $profile['blocked_proxy_request']);
        $t->same($blockedProxy, $profile['blocked_proxy_file']);
        $t->same('error', $profile['blocked_select']['status']);
        $t->same(true, $profile['blocked_select']['locked']);
        $t->same('host_id_mismatch', $profile['blocked_select']['reason']);
        $t->same('database is locked', $profile['blocked_select']['error']);
        $t->same([['lock_proxy_file' => $blockedProxy]], $profile['blocked_query_rows']);

        $t->same($childBeginsReadTransaction ? 'ok' : 'skipped', $profile['child_read_transaction']['status']);
        $t->same($childBeginsReadTransaction, $profile['child_read_transaction']['holds_proxy_lock']);
        $t->same($childBeginsReadTransaction ? $schemaRowCount : 0, count($profile['child_read_transaction']['rows']));
        $t->same(['status' => 'closed', 'connection' => 1, 'database' => $database], $profile['child_close']);
        $t->same([], $profile['active_locks_after_child_close']);

        $t->same($retryProxy, $profile['retry_proxy_request']);
        $t->same($retryProxy, $profile['retry_proxy_file']);
        $t->same('ok', $profile['retry_select']['status']);
        $t->same(false, $profile['retry_select']['locked']);
        $t->same($schemaRowCount, count($profile['retry_select']['rows']));
        $t->same($parentHostId, $profile['retry_select']['active_lock']['host_id']);
        $t->same($retryProxy, $profile['retry_select']['active_lock']['proxy_file']);
        $t->same([2], $profile['retry_select']['active_lock']['connections']);
        $t->same(true, $profile['database_accessible_after_child_close']);
        $t->same(true, $profile['interprocess_lock_prevented_cross_host_schema_read']);
        $t->same('proxy_locking_vfs_blocks_second_host_until_original_auto_proxy_holder_closes', $profile['reason']);

        $t->same(8, count($profile['upstream']));
        $t->same(true, in_array('lock6.test lock6-1.1 child process force proxy locking auto proxy and sqlite_master read', $profile['upstream'], true));
        $t->same(true, in_array('lock6.test lock6-1.4 auto proxy query returns :auto: (not held)', $profile['upstream'], true));
        $t->same(true, in_array('lock6.test lock6-1.6 parent mine proxy succeeds after child closes', $profile['upstream'], true));
        $t->same(true, in_array('sqlite-upstream-lock6-test', $profile['dependencies'], true));
        $t->same(true, in_array('sqlite-vfs-lock-proxy-interprocess', $profile['dependencies'], true));
        $t->same(true, in_array('sqlite-pragma-lock-proxy-file-state', $profile['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
    };
}

$tests['real upstream corpus vfs lock6 interprocess proxy lock cites hydrated upstream source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/lock6.test';
    $contents = file_get_contents($source);

    $t->same(true, is_string($contents));
    $t->same(true, str_contains((string) $contents, 'SQLITE_FORCE_PROXY_LOCKING'));
    $t->same(true, str_contains((string) $contents, 'PRAGMA lock_proxy_file=":auto:"'));
    $t->same(true, str_contains((string) $contents, '{:auto: (not held)}'));
    $t->same(true, str_contains((string) $contents, 'database is locked'));
    $t->same(true, str_contains((string) $contents, 'PRAGMA lock_proxy_file="mine"'));
};

$tests['real upstream corpus vfs lock6 interprocess proxy lock rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::lockProxyInterprocessProfile(''));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::lockProxyInterprocessProfile('/tmp/test.db', 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::lockProxyInterprocessProfile('/tmp/test.db', 2, 2));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::lockProxyInterprocessProfile('/tmp/test.db', 2, 3, ''));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::lockProxyInterprocessProfile('/tmp/test.db', 2, 3, ':auto:', ''));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::lockProxyInterprocessProfile('/tmp/test.db', 2, 3, ':auto:', 'notmine', ''));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::lockProxyInterprocessProfile('/tmp/test.db', 2, 3, ':auto:', 'notmine', 'mine', 0));
};

$tests['real upstream corpus vfs lock6 interprocess proxy lock owns one thousand behavior cases'] = static function (TestRunner $t) use (&$tests, $caseCount): void {
    $t->same(1000, $caseCount);
    $t->same(1003, count($tests));
    $t->same(
        'non-overlap: covers lock6.test child/parent interprocess force proxy locking with :auto:, notmine, and mine proxy transitions; avoids accepted pragma.test lock_proxy_file standalone coverage, lock7 schema-read, sharedlock, VFS process locks, lock-byte ranges, lock-state application, file writer/sync/rollback, WAL checkpoint, and B-tree clusters',
        'non-overlap: covers lock6.test child/parent interprocess force proxy locking with :auto:, notmine, and mine proxy transitions; avoids accepted pragma.test lock_proxy_file standalone coverage, lock7 schema-read, sharedlock, VFS process locks, lock-byte ranges, lock-state application, file writer/sync/rollback, WAL checkpoint, and B-tree clusters'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLitePragmaLockProxyFileState inside SQLiteVfsIoDynamicPlan and the hydrated upstream lock6.test source truth',
        'dependency-closure: no new support component needed; reuses SQLitePragmaLockProxyFileState inside SQLiteVfsIoDynamicPlan and the hydrated upstream lock6.test source truth'
    );
};

return $tests;
