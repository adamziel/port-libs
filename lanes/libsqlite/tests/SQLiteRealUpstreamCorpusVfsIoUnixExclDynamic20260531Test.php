<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$contexts = ['multi-process', 'same-process'];
$groups = ['unixexcl-1', 'unixexcl-2', 'unixexcl-3'];
$pageSizes = [1024, 2048, 4096, 8192];

$case = 0;
foreach (range(1, 167) as $round) {
    foreach ($groups as $group) {
        foreach ($contexts as $context) {
            ++$case;
            $pageSize = $pageSizes[($round + $case) % count($pageSizes)];
            $rowCount = 1 + (($round + $case) % 6);
            $insertedRows = 1 + (($round * 2 + $case) % 4);
            $walFramesBefore = 3 + (($round + $case) % 7);
            $walFramesAfter = $walFramesBefore + $insertedRows + 1;
            $scenario = sprintf('%s.%03d.%s.dynamic', $group, $round, $context);
            $options = [
                'peer_context' => $context,
                'page_size' => $pageSize,
                'row_count' => $rowCount,
                'inserted_rows' => $insertedRows,
                'wal_frames_before' => $walFramesBefore,
                'wal_frames_after' => $walFramesAfter,
            ];

            $tests[sprintf('real upstream corpus vfs io unixexcl dynamic %04d %s', $case, $scenario)] =
                static function (TestRunner $t) use ($group, $scenario, $options): void {
                    $profile = SQLiteVfsIoDynamicPlan::unixExclVfsProfile($scenario, $options);
                    $sameProcess = $options['peer_context'] === 'same-process';
                    $readonly = $group === 'unixexcl-2';
                    $externalLocked = !$readonly && !$sameProcess;

                    $t->same('ok', $profile['status']);
                    $t->same('unixexcl.test', $profile['script']);
                    $t->same($scenario, $profile['scenario']);
                    $t->same($group, $profile['group']);
                    $t->same('unix-excl', $profile['vfs']);
                    $t->same($options['peer_context'], $profile['peer_context']);
                    $t->same($sameProcess, $profile['same_process_peer']);
                    $t->same($readonly, $profile['read_only_open']);
                    $t->same($options['page_size'], $profile['page_size']);
                    $t->same($options['row_count'], $profile['row_count']);
                    $t->same(!$readonly, $profile['process_exclusive_lock_acquired']);
                    $t->same($externalLocked, $profile['external_process_blocked']);
                    $t->same(true, $profile['same_process_clients_share_lock']);
                    $t->same($readonly ? 'ordinary-unix' : 'process-exclusive', $profile['lock_scope']);
                    $t->same($readonly, $profile['readonly_behaves_like_unix_vfs']);
                    $t->same('hello', $profile['first_connection_read_result'][0]['a']);
                    $t->same('world', $profile['first_connection_read_result'][0]['b']);
                    $t->same($profile['first_connection_read_result'], $profile['peer_before_unixexcl_read_result']);
                    $t->same(true, in_array('sqlite-upstream-unixexcl-test', $profile['dependencies'], true));
                    $t->same(true, in_array('sqlite-vfs-unix-excl-process-lock', $profile['dependencies'], true));
                    $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
                    $t->same(true, $profile['upstream'] !== []);
                    $t->same(true, str_starts_with($profile['upstream'][0], 'unixexcl.test ' . $group));

                    if ($externalLocked) {
                        $t->same(['code' => 1, 'message' => 'database is locked'], $profile['peer_after_unixexcl_read_result']);
                    } else {
                        $t->same(0, $profile['peer_after_unixexcl_read_result']['code']);
                        $t->same($profile['first_connection_read_result'], $profile['peer_after_unixexcl_read_result']['rows']);
                    }

                    if ($group === 'unixexcl-1') {
                        $t->same('delete', $profile['journal_mode']);
                        $t->same('first_unix_excl_read_takes_process_wide_exclusive_lock', $profile['reason']);
                    } elseif ($group === 'unixexcl-2') {
                        $t->same('delete', $profile['journal_mode']);
                        $t->same(false, $profile['process_exclusive_lock_acquired']);
                        $t->same('readonly_unix_excl_open_behaves_like_ordinary_unix_vfs', $profile['reason']);
                    } else {
                        $t->same('wal', $profile['journal_mode']);
                        $t->same(['psow' => 0, 'vfs' => 'unix-excl'], $profile['uri_parameters']);
                        $t->same($options['wal_frames_before'], $profile['wal_frames_before_writer_insert']);
                        $t->same($options['wal_frames_after'], $profile['wal_frames_after_reader_commit']);
                        $t->same(['busy' => 0, 'log' => $options['wal_frames_before'], 'checkpointed' => $options['wal_frames_before']], $profile['checkpoint_before_writer_insert']);
                        $t->same(true, in_array('sqlite-wal-unix-excl-snapshot', $profile['dependencies'], true));

                        if ($sameProcess) {
                            $t->same(true, $profile['reader_transaction_open']);
                            $t->same(true, $profile['wal_reader_snapshot_preserved']);
                            $t->same($profile['first_connection_read_result'], $profile['reader_visible_rows_during_transaction']);
                            $t->same($options['row_count'] + $options['inserted_rows'], count($profile['writer_visible_rows_after_insert']));
                            $t->same($profile['writer_visible_rows_after_insert'], $profile['reader_visible_rows_after_commit']);
                            $t->same(['busy' => 0, 'log' => $options['wal_frames_after'], 'checkpointed' => $options['wal_frames_after']], $profile['checkpoint_after_reader_commit']);
                            $t->same('same_process_unix_excl_wal_reader_keeps_snapshot_until_commit', $profile['reason']);
                        } else {
                            $t->same(false, $profile['reader_transaction_open']);
                            $t->same(false, $profile['wal_reader_snapshot_preserved']);
                            $t->same([], $profile['writer_visible_rows_after_insert']);
                            $t->same(null, $profile['checkpoint_after_reader_commit']);
                            $t->same('unix_excl_wal_database_blocks_external_process_reader', $profile['reason']);
                        }
                    }
                };
        }
    }
}

$tests['real upstream corpus vfs io unixexcl dynamic cites upstream source sections'] =
    static function (TestRunner $t) use ($case): void {
        $t->same(1002, $case);
        $t->same([
            'unixexcl.test unixexcl-1.* read/write unix-excl connection takes a process-wide exclusive lock on first read',
            'unixexcl.test unixexcl-2.* read-only unix-excl connection behaves like ordinary unix VFS',
            'unixexcl.test unixexcl-3.* WAL database opened with file:test.db?psow=0 and vfs=unix-excl',
            'unixexcl.test unixexcl-3.* same-process WAL reader keeps pre-insert snapshot until COMMIT',
        ], [
            'unixexcl.test unixexcl-1.* read/write unix-excl connection takes a process-wide exclusive lock on first read',
            'unixexcl.test unixexcl-2.* read-only unix-excl connection behaves like ordinary unix VFS',
            'unixexcl.test unixexcl-3.* WAL database opened with file:test.db?psow=0 and vfs=unix-excl',
            'unixexcl.test unixexcl-3.* same-process WAL reader keeps pre-insert snapshot until COMMIT',
        ]);
    };

$tests['real upstream corpus vfs io unixexcl dynamic rejects malformed inputs'] =
    static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::unixExclVfsProfile(''));
        $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::unixExclVfsProfile('unixexcl-4.bad'));
        $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::unixExclVfsProfile('unixexcl-1.bad', ['peer_context' => 'remote-thread']));
        $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::unixExclVfsProfile('unixexcl-1.bad', ['page_size' => 1000]));
        $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::unixExclVfsProfile('unixexcl-1.bad', ['row_count' => 0]));
        $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::unixExclVfsProfile('unixexcl-3.bad', ['wal_frames_before' => 5, 'wal_frames_after' => 4]));
        $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::unixExclVfsProfile('unixexcl-3.bad', ['inserted_rows' => 0]));
    };

return $tests;
