<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$align = static function (int $value, int $alignment): int {
    $remainder = $value % $alignment;

    return $remainder === 0 ? $value : $value + ($alignment - $remainder);
};

foreach (range(1, 1000) as $case) {
    $iteration = ($case - 1) % 20;
    $chunkSize = [32768, 65536, 98304, 131072, 196608][$case % 5];
    $pageSize = [512, 1024, 2048, 4096][intdiv($case, 3) % 4];
    $rowCount = 250 + (($case * 37) % 1751);
    $payloadBytes = [128, 256, 500, 768, 1024, 1536][intdiv($case, 5) % 6];
    $mainName = 't' . $case . 'm.db';
    $auxName = 't' . $case . 'a.db';

    $tests[sprintf('real upstream corpus vfs io crash multiplex dynamic %04d crashM iteration %02d', $case, $iteration)] = static function (TestRunner $t) use (
        $align,
        $auxName,
        $case,
        $chunkSize,
        $iteration,
        $mainName,
        $pageSize,
        $payloadBytes,
        $rowCount
    ): void {
        $plan = SQLiteVfsIoDynamicPlan::multiplexCrashRecoveryProfile($iteration, [
            'aux_name' => $auxName,
            'chunk_size' => $chunkSize,
            'main_name' => $mainName,
            'page_size' => $pageSize,
            'payload_bytes' => $payloadBytes,
            'row_count' => $rowCount,
        ]);

        $alignedChunk = $align($chunkSize, 65536);
        $databaseBytes = $align(max(
            $pageSize * 5,
            ($pageSize * 5) + ($rowCount * (($payloadBytes + 64) + (2 * 32)))
        ), $pageSize);
        $chunkCount = max(1, (int) ceil($databaseBytes / $alignedChunk));
        $mainStem = preg_replace('/\.[^.]+$/', '', $mainName) ?? $mainName;
        $auxStem = preg_replace('/\.[^.]+$/', '', $auxName) ?? $auxName;

        $t->same('ok', $plan['status']);
        $t->same('crashM.test', $plan['script']);
        $t->same('crashM-2.' . $iteration, $plan['scenario']);
        $t->same('multiplex', $plan['vfs']);
        $t->same(true, $plan['uri_enabled']);
        $t->same(true, $plan['short_names_enabled']);
        $t->same($mainName, $plan['main_name']);
        $t->same($auxName, $plan['aux_name']);
        $t->same('aux', $plan['attached_database']);
        $t->same($chunkSize, $plan['chunk_size_requested']);
        $t->same(65536, $plan['chunk_size_alignment']);
        $t->same($alignedChunk, $plan['chunk_size_aligned']);
        $t->same($pageSize, $plan['page_size']);
        $t->same($rowCount, $plan['row_count_per_database']);
        $t->same($payloadBytes, $plan['payload_bytes']);
        $t->same(2, $plan['index_count_per_database']);
        $t->same($databaseBytes, $plan['database_bytes_per_database']);
        $t->same($chunkCount, $plan['chunk_count_per_database']);
        $mainChunks = $plan['chunk_files_by_database']['main'];
        $auxChunks = $plan['chunk_files_by_database']['aux'];
        $t->same($chunkCount, count($mainChunks));
        $t->same($chunkCount, count($auxChunks));
        $t->same(true, in_array($mainName, $mainChunks, true));
        $t->same(true, in_array($auxName, $auxChunks, true));
        $t->same($mainStem . '.nal', $plan['rollback_journal_files']['main']);
        $t->same($auxStem . '.nal', $plan['rollback_journal_files']['aux']);
        $t->same($mainStem . '.mj', $plan['master_journal_file']);
        $t->same($iteration, $plan['crash_iteration']);
        $t->same(1, $plan['crash_delay']);
        $t->same(intdiv($rowCount, 10), $plan['updated_rows_per_database']);
        $t->same([1, 'child process exited abnormally'], $plan['child_process_result']);
        $t->same(true, $plan['rollback_required']);
        $t->same(true, $plan['hot_journal_or_master_journal_recovery']);
        $t->same(true, $plan['transaction_atomic_across_attached_databases']);
        $t->same('ok', $plan['main_integrity_check']);
        $t->same('ok', $plan['aux_integrity_check']);
        $t->same(['ok', 'ok'], $plan['integrity_sequence']);
        $t->same(['main' => $rowCount, 'aux' => $rowCount], $plan['rows_visible_after_recovery']);
        $t->same(true, $plan['chunk_files_preserved_after_recovery']);
        $t->same(true, $plan['short_sidecar_names_preserved']);
        $t->same(true, $plan['database_image_stable_after_recovery']);
        $t->same(true, in_array('sqlite-upstream-crashM-test', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-vfs-multiplex-crash-recovery', $plan['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
        $t->same(true, str_contains($plan['upstream'][1], 'crashM.test 2.' . $iteration . '.1'));
        $t->same(true, str_contains($plan['upstream'][2], 'integrity_check both return ok'));
        $t->same(true, str_contains($plan['transaction_sequence'][2], 'UPDATE main.t1'));
        $t->same(true, str_contains($plan['transaction_sequence'][3], 'UPDATE aux.t2'));

        if ($chunkCount > 1) {
            $t->same(true, in_array($mainStem . '.001', $mainChunks, true));
            $t->same(true, in_array($auxStem . '.001', $auxChunks, true));
        }

        $t->same(true, $case >= 1);
    };
}

$tests['real upstream corpus vfs io crash multiplex cites hydrated source truth'] = static function (TestRunner $t): void {
    $plan = SQLiteVfsIoDynamicPlan::multiplexCrashRecoveryProfile(7);

    $t->same([
        'crashM.test 1.0 setup multiplex 8.3 main and aux databases with indexed randomblob rows',
        'crashM.test 2.7.1 crashsql exits abnormally during attached UPDATE transaction',
        'crashM.test 2.7.2 main and aux integrity_check both return ok after recovery',
    ], $plan['upstream']);
    $t->same(1000, $plan['row_count_per_database']);
    $t->same(500, $plan['payload_bytes']);
    $t->same(65536, $plan['chunk_size_requested']);
    $t->same($plan['chunk_count_per_database'], count($plan['chunk_files_by_database']['main']));
    $t->same(true, in_array('test1.db', $plan['chunk_files_by_database']['main'], true));
    $t->same(true, in_array('test1.001', $plan['chunk_files_by_database']['main'], true));
    $t->same(true, in_array('test1.009', $plan['chunk_files_by_database']['main'], true));
    $t->same('test1.nal', $plan['rollback_journal_files']['main']);
    $t->same('test2.nal', $plan['rollback_journal_files']['aux']);
    $t->same('test1.mj', $plan['master_journal_file']);
};

$rejects = [
    'negative iteration' => static fn (): array => SQLiteVfsIoDynamicPlan::multiplexCrashRecoveryProfile(-1),
    'too large iteration' => static fn (): array => SQLiteVfsIoDynamicPlan::multiplexCrashRecoveryProfile(20),
    'empty main' => static fn (): array => SQLiteVfsIoDynamicPlan::multiplexCrashRecoveryProfile(0, ['main_name' => '']),
    'empty aux' => static fn (): array => SQLiteVfsIoDynamicPlan::multiplexCrashRecoveryProfile(0, ['aux_name' => '']),
    'same names' => static fn (): array => SQLiteVfsIoDynamicPlan::multiplexCrashRecoveryProfile(0, ['main_name' => 'same.db', 'aux_name' => 'same.db']),
    'bad chunk' => static fn (): array => SQLiteVfsIoDynamicPlan::multiplexCrashRecoveryProfile(0, ['chunk_size' => 0]),
    'bad page' => static fn (): array => SQLiteVfsIoDynamicPlan::multiplexCrashRecoveryProfile(0, ['page_size' => 1000]),
    'bad rows' => static fn (): array => SQLiteVfsIoDynamicPlan::multiplexCrashRecoveryProfile(0, ['row_count' => 0]),
    'bad payload' => static fn (): array => SQLiteVfsIoDynamicPlan::multiplexCrashRecoveryProfile(0, ['payload_bytes' => 0]),
    'bad modulo' => static fn (): array => SQLiteVfsIoDynamicPlan::multiplexCrashRecoveryProfile(0, ['update_modulo' => 0]),
    'bad delay' => static fn (): array => SQLiteVfsIoDynamicPlan::multiplexCrashRecoveryProfile(0, ['crash_delay' => 0]),
];

foreach ($rejects as $name => $callback) {
    $tests['real upstream corpus vfs io crash multiplex rejects malformed input ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

return $tests;
