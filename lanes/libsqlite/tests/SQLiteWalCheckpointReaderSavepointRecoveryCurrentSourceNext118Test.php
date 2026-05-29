<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointReaderSavepointRecoveryCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';
$databaseBytes = $page('next118 schema base')
    . $page('next118 options base')
    . $page('next118 plugin base')
    . $page('next118 autoload base')
    . $page('next118 transient base');

$makeWalBytes = static function (array $frames, int $checkpointSequence = 118, int $salt1 = 0x11811801, int $salt2 = 0x11811802) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpointSequence, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$frames = [
    [1, 0, 'next118 schema retained draft'],
    [2, 5, 'next118 options retained commit'],
    [3, 0, 'next118 plugin savepoint draft'],
    [4, 0, 'next118 autoload savepoint draft'],
    [4, 5, 'next118 autoload savepoint commit'],
    [5, 5, 'next118 transient savepoint commit'],
    [2, 5, 'next118 options savepoint tail'],
];
$walBytes = $makeWalBytes($frames);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$staleWalBytes = $makeWalBytes([
    [1, 0, 'next118 schema retained draft'],
    [2, 5, 'next118 options stale retained commit'],
    [3, 0, 'next118 plugin savepoint draft'],
    [4, 0, 'next118 autoload savepoint draft'],
    [4, 5, 'next118 autoload savepoint commit'],
    [5, 5, 'next118 transient savepoint commit'],
    [2, 5, 'next118 options savepoint tail'],
]);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next118');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings-next118');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4);
    $stack->recordWalFrameWrite(5, 4, true);
    $stack->recordWalFrameWrite(6, 5, true);
    $stack->recordWalFrameWrite(7, 2, true);

    return $stack;
};

$retainedWalBytes = static fn (): string => $makeStack()->walRollbackToWalBytes('plugin-settings-next118', $wal, $walBytes);
$plan = static fn (string $mode = 'restart', string $phase = 'after_database_sync', ?int $reader = 7, array $pages = [1, 2, 3, 4, 5], ?string $persisted = null): array => SQLiteWalCheckpointReaderSavepointRecoveryCurrentSourceNextPlan::plan(
    $makeStack(),
    'plugin-settings-next118',
    $wal,
    $walBytes,
    $databaseBytes,
    $databasePath,
    $pages,
    $mode,
    $phase,
    $reader,
    $persisted
);

$restart = static fn (): array => $plan();
$sidecar = static fn (): array => $plan('restart', 'after_wal_sidecar_write');
$truncate = static fn (): array => $plan('truncate', 'after_directory_sync');
$retainedReader = static fn (): array => $plan('restart', 'after_database_sync', 2);

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'reader-savepoint-checkpoint-recovery-current-source-next118'],
    'savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-settings-next118'],
    'mode restart' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'crash phase' => [static fn (): mixed => $restart()['crash_phase'], 'after_database_sync'],
    'database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], $databasePath . '-wal'],
    'original reader end frame' => [static fn (): mixed => $restart()['original_reader_end_frame'], 7],
    'retained reader end frame' => [static fn (): mixed => $restart()['retained_reader_end_frame'], 2],
    'retained frame count' => [static fn (): mixed => $restart()['retained_frame_count'], 2],
    'discarded frame count' => [static fn (): mixed => $restart()['discarded_frame_count'], 5],
    'discarded frame indexes' => [static fn (): mixed => $restart()['discarded_frame_indexes'], [3, 4, 5, 6, 7]],
    'discarded page numbers' => [static fn (): mixed => $restart()['discarded_page_numbers'], [3, 4, 5, 2]],
    'discarded reader pages' => [static fn (): mixed => $restart()['discarded_reader_pages'], [2, 3, 4, 5]],
    'checkpoint busy before release' => [static fn (): mixed => $restart()['checkpoint_busy_before_release'], true],
    'checkpoint reason before release' => [static fn (): mixed => $restart()['checkpoint_reason_before_release'], 'reader_blocks_wal_reset'],
    'recovery status' => [static fn (): mixed => $restart()['recovery_status'], 'recovered'],
    'recovery reason' => [static fn (): mixed => $restart()['recovery_reason'], 'checkpoint_database_durable_recovery_replays_committed_prefix'],
    'persisted action' => [static fn (): mixed => $restart()['persisted_wal_action'], 'preserve_pre_recovery_wal'],
    'persisted wal bytes length' => [static fn (): mixed => $restart()['persisted_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'retained wal bytes length' => [static fn (): mixed => $restart()['retained_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'next uses checkpoint database' => [static fn (): mixed => $restart()['next_uses_checkpoint_database'], true],
    'next replays persisted wal' => [static fn (): mixed => $restart()['next_replays_persisted_wal'], true],
    'next uses reset wal' => [static fn (): mixed => $restart()['next_uses_reset_wal'], false],
    'current sources' => [static fn (): mixed => $restart()['recovery_current_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'next sources' => [static fn (): mixed => $restart()['recovery_next_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'current wal count' => [static fn (): mixed => $restart()['recovery_current_source_counts']['wal'], 2],
    'current database count' => [static fn (): mixed => $restart()['recovery_current_source_counts']['database'], 3],
    'next wal count' => [static fn (): mixed => $restart()['recovery_next_source_counts']['wal'], 2],
    'next database count' => [static fn (): mixed => $restart()['recovery_next_source_counts']['database'], 3],
    'current frames' => [static fn (): mixed => $restart()['recovery_current_frame_indexes'], [1, 2, null, null, null]],
    'next frames' => [static fn (): mixed => $restart()['recovery_next_frame_indexes'], [1, 2, null, null, null]],
    'row count' => [static fn (): mixed => count($restart()['rows']), 5],
    'row pages' => [static fn (): mixed => array_column($restart()['rows'], 'page_number'), [1, 2, 3, 4, 5]],
    'source transitions' => [static fn (): mixed => $restart()['source_transitions'], ['wal>wal>wal>wal', 'wal>wal>wal>wal', 'wal>database>database>database', 'wal>database>database>database', 'wal>database>database>database']],
    'recovery preserves retained images' => [static fn (): mixed => $restart()['recovery_preserves_retained_images'], true],
    'next preserves retained images' => [static fn (): mixed => $restart()['next_preserves_retained_images'], true],
    'discarded frames not replayed' => [static fn (): mixed => $restart()['discarded_frames_replayed'], false],
    'source digest length' => [static fn (): mixed => strlen($restart()['source_digest']), 64],
    'current source verified' => [static fn (): mixed => $restart()['current_source_verified'], true],
    'persisted source verified' => [static fn (): mixed => $restart()['persisted_source_verified'], true],
    'page two retained label' => [static fn (): mixed => str_contains($restart()['rows'][1]['retained_label'], 'options retained commit'), true],
    'page two recovery current label' => [static fn (): mixed => str_contains($restart()['rows'][1]['recovery_current_label'], 'options retained commit'), true],
    'page two recovery next label' => [static fn (): mixed => str_contains($restart()['rows'][1]['recovery_next_label'], 'options retained commit'), true],
    'page three base label' => [static fn (): mixed => str_contains($restart()['rows'][2]['recovery_next_label'], 'plugin base'), true],
    'operation applied count' => [static fn (): mixed => count($restart()['operations_applied']), 2],
    'operation pending count' => [static fn (): mixed => count($restart()['operations_pending']), 3],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-reader-savepoint-recovery-current-source-next118', $restart()['dependencies'], true), true],
    'dependency reader next104' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-reader-savepoint-current-source-next104', $restart()['dependencies'], true), true],
    'dependency recovery current next' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-recovery-current-next', $restart()['dependencies'], true), true],
    'sidecar phase' => [static fn (): mixed => $sidecar()['crash_phase'], 'after_wal_sidecar_write'],
    'sidecar action' => [static fn (): mixed => $sidecar()['persisted_wal_action'], 'restart_recovered_wal'],
    'sidecar next uses reset wal' => [static fn (): mixed => $sidecar()['next_uses_reset_wal'], true],
    'sidecar next replays no old wal' => [static fn (): mixed => $sidecar()['next_replays_persisted_wal'], false],
    'sidecar persisted header only' => [static fn (): mixed => $sidecar()['persisted_wal_bytes_length'], 32],
    'sidecar next sources' => [static fn (): mixed => $sidecar()['recovery_next_sources'], ['database', 'database', 'database', 'database', 'database']],
    'truncate mode' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'truncate action' => [static fn (): mixed => $truncate()['persisted_wal_action'], 'truncate_recovered_wal'],
    'truncate persisted bytes' => [static fn (): mixed => $truncate()['persisted_wal_bytes_length'], 0],
    'truncate next reset false' => [static fn (): mixed => $truncate()['next_uses_reset_wal'], false],
    'truncate next sources' => [static fn (): mixed => $truncate()['recovery_next_sources'], ['database', 'database', 'database', 'database', 'database']],
    'retained reader end frame override' => [static fn (): mixed => $retainedReader()['original_reader_end_frame'], 2],
    'retained reader discarded pages unchanged' => [static fn (): mixed => $retainedReader()['discarded_reader_pages'], []],
    'retained reader still recovers prefix' => [static fn (): mixed => $retainedReader()['recovery_current_frame_indexes'], [1, 2, null, null, null]],
];

foreach ($cases as $name => [$actual, $expected]) {
    $tests['wal checkpoint reader savepoint recovery current source next118 ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
}

$tests['wal checkpoint reader savepoint recovery current source next118 accepts matching persisted prefix'] = static function (TestRunner $t) use ($plan, $retainedWalBytes): void {
    $t->same(true, $plan('restart', 'after_database_sync', 7, [1], $retainedWalBytes())['persisted_source_verified']);
};

$tests['wal checkpoint reader savepoint recovery current source next118 rejects stale current source'] = static function (TestRunner $t) use ($makeStack, $wal, $staleWalBytes, $databaseBytes, $databasePath): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointReaderSavepointRecoveryCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next118', $wal, $staleWalBytes, $databaseBytes, $databasePath, [1]));
};

$tests['wal checkpoint reader savepoint recovery current source next118 rejects stale persisted prefix'] = static function (TestRunner $t) use ($plan, $staleWalBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $plan('restart', 'after_database_sync', 7, [1], $staleWalBytes));
};

$tests['wal checkpoint reader savepoint recovery current source next118 rejects empty path'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointReaderSavepointRecoveryCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next118', $wal, $walBytes, $databaseBytes, '', [1]));
};

$tests['wal checkpoint reader savepoint recovery current source next118 rejects invalid crash phase'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $plan('restart', 'before_database_sync'));
};

$tests['wal checkpoint reader savepoint recovery current source next118 rejects non integer page'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $plan('restart', 'after_database_sync', 7, ['1']));
};

return $tests;
