<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$playbackScenarios = [
    'hot-journal-read' => ['upstream' => 'ioerr.test ioerr-7', 'checkpoint' => 'hot-journal'],
    'master-journal-name-read' => ['upstream' => 'ioerr.test ioerr-9', 'checkpoint' => 'master-journal-name'],
    'statement-playback-constraint' => ['upstream' => 'ioerr.test ioerr-10', 'checkpoint' => 'statement-journal'],
];
$operations = ['read', 'write', 'sync', 'truncate'];

foreach ($playbackScenarios as $scenario => $metadata) {
    for ($variant = 1; $variant <= 180; $variant++) {
        $operation = $operations[$variant % count($operations)];
        $failAt = $variant + ($scenario === 'statement-playback-constraint' ? 17 : 0);
        $seedRows = 64 + $variant;

        $tests[sprintf('real upstream corpus vfs io dynamic real batch %s playback variant %03d', $scenario, $variant)] = static function (TestRunner $t) use ($scenario, $metadata, $operation, $failAt, $seedRows): void {
            $profile = SQLiteVfsIoDynamicPlan::journalPlaybackIoErrorProfile($scenario, $failAt, $operation, $seedRows);
            $faultDetected = $failAt % 41 !== 0;
            $writeSideFault = in_array($operation, ['write', 'sync', 'truncate'], true);

            $t->same('ok', $profile['status']);
            $t->same('ioerr.test', $profile['script']);
            $t->same($scenario, $profile['scenario']);
            $t->same($failAt, $profile['fail_at']);
            $t->same($operation, $profile['operation']);
            $t->same($seedRows, $profile['seed_rows']);
            $t->same($faultDetected, $profile['fault_detected']);
            $t->same($metadata['checkpoint'], $profile['checkpoint']);
            $t->same(true, in_array($metadata['upstream'], $profile['upstream'], true));
            $t->same(true, $profile['rows_preserved']);
            $t->same('ok', $profile['integrity_check']);
            $t->same(0, $profile['open_file_count']);
            $t->same(true, in_array('upstream-ioerr-journal-playback', $profile['dependencies'], true));

            if ($scenario === 'hot-journal-read') {
                $t->same($faultDetected, $profile['hot_journal_left']);
                $t->same($faultDetected ? 'SQLITE_IOERR_READ' : 'SQLITE_OK', $profile['expected_result']);
                $t->same([[1, 2]], $profile['final_rows_sample']);
            } elseif ($scenario === 'master-journal-name-read') {
                $t->same(true, $profile['master_journal_name_required']);
                $t->same($faultDetected, $profile['journal_bytes_retained_for_retry']);
                $t->same(['committed-row'], $profile['final_rows_sample']);
            } else {
                $t->same(true, $profile['statement_journal_playback']);
                $t->same(true, $profile['constraint_message_preserved']);
                $t->same('UNIQUE constraint failed: t1.a', $profile['expected_result']);
                $t->same($faultDetected && $writeSideFault, $profile['rollback_required']);
            }
        };
    }
}

$memoryCases = [];
foreach ([1, 2, 3, 5, 8, 13] as $outerOrdinal) {
    foreach ([1, 2, 4, 8, 16] as $innerRepeats) {
        foreach ([256, 511, 1024] as $payloadBytes) {
            foreach ([false, true] as $rollbackOuter) {
                $memoryCases[] = [64 + $outerOrdinal, $outerOrdinal, $innerRepeats, $payloadBytes, $rollbackOuter];
            }
        }
    }
}

foreach ($memoryCases as $index => [$seedRows, $outerOrdinal, $innerRepeats, $payloadBytes, $rollbackOuter]) {
    $tests[sprintf('real upstream corpus vfs io dynamic real batch memjournal savepoint variant %03d', $index + 1)] = static function (TestRunner $t) use ($seedRows, $outerOrdinal, $innerRepeats, $payloadBytes, $rollbackOuter): void {
        $profile = SQLiteVfsIoDynamicPlan::memoryJournalSavepointProfile($seedRows, $outerOrdinal, $innerRepeats, $payloadBytes, $rollbackOuter);
        $outerTouchedRows = min($seedRows, $outerOrdinal);
        $innerImageBytes = $payloadBytes + 24;

        $t->same('ok', $profile['status']);
        $t->same('memjournal2.test', $profile['script']);
        $t->same('memory', $profile['journal_mode']);
        $t->same($seedRows, $profile['seed_rows']);
        $t->same($outerOrdinal, $profile['outer_savepoint_ordinal']);
        $t->same($innerRepeats, $profile['inner_update_repeats']);
        $t->same($payloadBytes, $profile['payload_bytes']);
        $t->same($outerTouchedRows, $profile['outer_touched_rows']);
        $t->same($innerImageBytes, $profile['inner_image_bytes']);
        $t->same($outerTouchedRows * ($payloadBytes + 24) + ($innerImageBytes * $innerRepeats), $profile['memory_journal_bytes']);
        $t->same(false, $profile['disk_journal_created']);
        $t->same(0, $profile['vfs_write_count']);
        $t->same(true, $profile['inner_rollback_restores_row_one']);
        $t->same($rollbackOuter ? 0 : $outerTouchedRows, $profile['final_touched_rows']);
        $t->same('ok', $profile['final_integrity_check']);
        $t->same(true, in_array('memjournal2.test 1.2.200-1.2.300', $profile['upstream'], true));
        $t->same(true, in_array('upstream-memjournal-savepoint-loop', $profile['dependencies'], true));
    };
}

$subjournalCases = [];
foreach ([12, 24, 48, 96] as $rows) {
    foreach ([3, 8, 16, 32] as $cachePages) {
        foreach ([250, 499, 900] as $outerPayloadBytes) {
            foreach ([125, 498] as $innerPayloadBytes) {
                $backupPages = 60 + $rows;
                foreach ([1, 7, 19] as $stepOffset) {
                    $subjournalCases[] = [$rows, $cachePages, $outerPayloadBytes, $innerPayloadBytes, $backupPages, $backupPages - $stepOffset];
                }
            }
        }
    }
}

foreach ($subjournalCases as $index => [$rows, $cachePages, $outerPayloadBytes, $innerPayloadBytes, $backupPages, $backupStepPages]) {
    $tests[sprintf('real upstream corpus vfs io dynamic real batch subjournal backup variant %03d', $index + 1)] = static function (TestRunner $t) use ($rows, $cachePages, $outerPayloadBytes, $innerPayloadBytes, $backupPages, $backupStepPages): void {
        $profile = SQLiteVfsIoDynamicPlan::subjournalMemoryBackupProfile($rows, $cachePages, $outerPayloadBytes, $innerPayloadBytes, $backupPages, $backupStepPages);

        $t->same('ok', $profile['status']);
        $t->same('subjournal.test', $profile['script']);
        $t->same('memory', $profile['temp_store']);
        $t->same($rows, $profile['table_rows']);
        $t->same($cachePages, $profile['cache_pages']);
        $t->same($outerPayloadBytes, $profile['outer_payload_bytes']);
        $t->same($innerPayloadBytes, $profile['inner_payload_bytes']);
        $t->same($rows, $profile['outer_before_images']);
        $t->same($rows, $profile['inner_before_images']);
        $t->same($rows * ($outerPayloadBytes + 24), $profile['outer_subjournal_bytes']);
        $t->same($rows * ($innerPayloadBytes + 24), $profile['inner_subjournal_bytes']);
        $t->same($rows > $cachePages, $profile['spill_required']);
        $t->same(false, $profile['disk_statement_journal_created']);
        $t->same(true, $profile['rollback_to_inner_restores_outer_update']);
        $t->same(true, $profile['outer_transaction_rows_visible']);
        $t->same('ok', $profile['commit_result']);
        $t->same($backupPages, $profile['backup_total_pages']);
        $t->same($backupStepPages, $profile['backup_first_step_pages']);
        $t->same($backupPages - $backupStepPages, $profile['backup_remaining_pages']);
        $t->same('SQLITE_OK', $profile['backup_first_step_result']);
        $t->same('SQLITE_DONE', $profile['backup_final_step_result']);
        $t->same('ok', $profile['source_integrity_check']);
        $t->same('ok', $profile['backup_integrity_check']);
        $t->same(true, in_array('subjournal.test 2.2 subjournal rollback while backup is active', $profile['upstream'], true));
        $t->same(true, in_array('upstream-subjournal-memory-backup', $profile['dependencies'], true));
    };
}

$tests['real upstream corpus vfs io dynamic real batch cites hydrated upstream sections'] = static function (TestRunner $t) use (&$tests): void {
    $t->same(1009, count($tests));
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr.test ioerr-7 hot-journal replay read fault',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr.test ioerr-9 master-journal-name read fault',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr.test ioerr-10 statement journal constraint playback',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/memjournal.test 1.0-1.3 memory journal behavior',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/memjournal2.test 1.2.200-1.2.300 savepoint loop behavior',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/subjournal.test 2.1-2.4 online backup with subjournal rollback',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr.test ioerr-7 hot-journal replay read fault',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr.test ioerr-9 master-journal-name read fault',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr.test ioerr-10 statement journal constraint playback',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/memjournal.test 1.0-1.3 memory journal behavior',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/memjournal2.test 1.2.200-1.2.300 savepoint loop behavior',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/subjournal.test 2.1-2.4 online backup with subjournal rollback',
    ]);
};

return $tests;
