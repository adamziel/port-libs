<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicCorpusPlan;

$tests = [];

$tests['real upstream corpus pager wal file permission cites hydrated wal2 section'] = static function (TestRunner $t): void {
    $upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test';
    $source = (string) file_get_contents($upstream);

    $t->same(true, is_file($upstream));
    $t->same(true, str_contains($source, 'do_test wal2-13.$tn.2'));
    $t->same(true, str_contains($source, 'do_test wal2-13.$tn.3'));
    $t->same(true, str_contains($source, 'do_test wal2-13.$tn.4'));
    $t->same(true, str_contains($source, 'foreach {tn db_perm wal_perm shm_perm can_open can_read can_write}'));
};

$rows = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::wal2FilePermissionRows();

$tests['real upstream corpus pager wal file permission row count'] = static function (TestRunner $t) use ($rows): void {
    $t->same(1050, count($rows));
    $t->same(7, count(array_unique(array_column($rows, 'test_number'))));
    $t->same('wal2.test wal2-13.2 dynamic permission row 001', $rows[0]['upstream']);
    $t->same('wal2.test wal2-13.9 dynamic permission row 150', $rows[1049]['upstream']);
};

foreach ($rows as $case => $row) {
    $tests[sprintf('real upstream corpus pager wal file permission %04d upstream matrix', $case)] = static function (TestRunner $t) use ($row): void {
        $t->same('wal2.test', $row['script']);
        $t->same('wal2-13.* database/wal/shm open permission matrix', $row['section']);
        $t->same(true, str_starts_with($row['upstream'], 'wal2.test wal2-13.'));
        $t->same(true, $row['rowid'] >= 1 && $row['rowid'] <= 150);
        $t->same(true, in_array($row['test_number'], [2, 3, 4, 5, 7, 8, 9], true));
        $t->same(3, count($row['permission_triplet']));
        $t->same($row['database_permission'], $row['permission_triplet'][0]);
        $t->same($row['wal_permission'], $row['permission_triplet'][1]);
        $t->same($row['shm_permission'], $row['permission_triplet'][2]);
        $t->same(true, preg_match('/^00[064]{3}$/', $row['database_permission']) === 1 || $row['database_permission'] === '00000');
        $t->same(true, preg_match('/^00[064]{3}$/', $row['wal_permission']) === 1 || $row['wal_permission'] === '00000');
        $t->same(true, preg_match('/^00[064]{3}$/', $row['shm_permission']) === 1 || $row['shm_permission'] === '00000');
    };

    $tests[sprintf('real upstream corpus pager wal file permission %04d open read write result', $case)] = static function (TestRunner $t) use ($row): void {
        $t->same($row['can_open'] ? 0 : 1, $row['open_result'][0]);
        $t->same($row['can_open'] ? 'ok' : 'unable to open database file', $row['open_result'][1]);
        $t->same($row['can_read'] ? 0 : 1, $row['read_result'][0]);
        $t->same($row['can_write'] ? 0 : 1, $row['write_result'][0]);
        $t->same($row['can_open'], $row['database_permission'] !== '00000');
        $t->same($row['can_read'], $row['can_open'] && $row['wal_permission'] !== '00000' && $row['shm_permission'] !== '00000');
        $t->same($row['can_write'], $row['can_read'] && $row['database_permission'] === '00644' && $row['wal_permission'] === '00644' && $row['shm_permission'] === '00644');

        if ($row['can_read']) {
            $t->same(['3.14', '2.72'], $row['initial_row']);
            $t->same(['3.14', '2.72', $row['payload']], $row['read_result'][1]);
        } else {
            $t->same('unable to open database file', $row['read_result'][1]);
        }

        if (!$row['can_read']) {
            $t->same('unable to open database file', $row['write_result'][1]);
        } elseif (!$row['can_write']) {
            $t->same('attempt to write a readonly database', $row['write_result'][1]);
        } else {
            $t->same([], $row['write_result'][1]);
        }
    };

    $tests[sprintf('real upstream corpus pager wal file permission %04d payload provenance', $case)] = static function (TestRunner $t) use ($row): void {
        $t->same('wal', $row['journal_mode']);
        $t->same([true, true], $row['sidecar_files_exist']);
        $t->same($row['payload_digest'], hash('sha256', $row['payload']));
        $t->same(true, str_starts_with($row['payload'], 'setting-'));
        $t->same(true, str_contains($row['payload'], $row['database_permission']));
        $t->same(true, str_contains($row['payload'], $row['wal_permission']));
        $t->same(true, str_contains($row['payload'], $row['shm_permission']));
        $t->same(true, in_array('real-upstream-corpus-wal2', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-file-permission-open-matrix', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-readonly-write-rejection', $row['dependencies'], true));
    };
}

return $tests;
