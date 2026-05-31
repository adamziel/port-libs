<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$cases = [];
foreach ([1024, 2048, 4096, 8192] as $pageSize) {
    foreach ([3, 5, 12, 20, 37] as $seedRows) {
        foreach ([1, 2, 3, 5] as $insertRows) {
            foreach (['normal', 'full'] as $syncMode) {
                foreach ([false, true] as $sequential) {
                    $flags = $sequential ? ['sequential'] : [];
                    $name = sprintf(
                        'walvfs-1 sequential header sync page%d seed%d insert%d %s %s',
                        $pageSize,
                        $seedRows,
                        $insertRows,
                        $syncMode,
                        $sequential ? 'sequential' : 'plain'
                    );
                    $cases[$name] = [$flags, $syncMode, $seedRows, $insertRows, $pageSize];
                }
            }
        }
    }
}

foreach ($cases as $name => [$flags, $syncMode, $seedRows, $insertRows, $pageSize]) {
    $tests["real upstream vfs io wal sequential dynamic {$name}"] = static function (TestRunner $t) use ($flags, $syncMode, $seedRows, $insertRows, $pageSize): void {
        $plan = SQLiteVfsIoDynamicPlan::walSequentialHeaderSyncProfile($flags, $syncMode, $seedRows, $insertRows, $pageSize);
        $sequential = in_array('sequential', $flags, true);
        $expectedSyncCount = $syncMode === 'normal'
            ? ($sequential ? 0 : 1)
            : ($sequential ? $insertRows : $insertRows + 1);

        $t->same('ok', $plan['status']);
        $t->same('walvfs.test', $plan['script']);
        $t->same(['walvfs.test 1.0', 'walvfs.test 1.1', 'walvfs.test 1.2', 'walvfs.test 1.3'], $plan['upstream']);
        $t->same('wal', $plan['journal_mode']);
        $t->same($syncMode, $plan['sync_mode']);
        $t->same($flags, $plan['device_flags']);
        $t->same($pageSize, $plan['page_size']);
        $t->same($seedRows, $plan['seed_rows']);
        $t->same($insertRows, $plan['post_checkpoint_insert_rows']);
        $t->same($sequential, $plan['sequential_device']);
        $t->same($seedRows + 2, $plan['wal_frames_after_seed']);
        $t->same(['busy' => 0, 'log' => $seedRows + 2, 'checkpointed' => $seedRows + 2], $plan['checkpoint_result']);
        $t->same($insertRows, $plan['post_checkpoint_wal_frames']);
        $t->same(!$sequential, $plan['wal_header_synced_immediately']);
        $t->same($syncMode === 'full', $plan['wal_frame_content_synced']);
        $t->same($expectedSyncCount, $plan['post_checkpoint_wal_sync_count']);
        $t->same($sequential, $plan['wal_header_sync_deferred']);
        $t->same($seedRows + $insertRows, $plan['reader_rows_after_insert']);
        $t->same(
            $sequential
                ? 'sequential_wal_device_defers_header_sync_after_checkpoint'
                : 'non_sequential_wal_device_syncs_header_after_checkpoint',
            $plan['reason']
        );
        $t->same(['upstream-walvfs-sequential-header-sync', 'vfs-io-dynamic-real-corpus'], $plan['dependencies']);
    };
}

return $tests;
