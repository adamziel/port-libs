<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next130 schema base')
    . $page('next130 option base')
    . $page('next130 autoload base')
    . $page('next130 transient base')
    . $page('next130 plugin base');

$makeWal = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x13013001;
    $salt2 = 0x13013002;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 130, $salt1, $salt2);
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
    [1, 0, 'next130 retained schema draft'],
    [2, 5, 'next130 retained siteurl commit'],
    [3, 0, 'next130 discarded autoload draft'],
    [4, 5, 'next130 discarded transient commit'],
    [2, 5, 'next130 discarded option retry'],
    [5, 5, 'next130 discarded plugin tail'],
];
$walBytes = $makeWal($frames);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next130');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-batch-next130');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4, true);
    $stack->recordWalFrameWrite(5, 2, true);
    $stack->recordWalFrameWrite(6, 5, true);

    return $stack;
};

$plan = static function (?int $reader = 2, array $pages = [1, 2, 3, 4, 5]) use ($makeStack, $wal, $walBytes, $databaseBytes): array {
    return SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan::readerCheckpointSavepointTruncateAfterRollbackPlan(
        $makeStack(),
        'plugin-batch-next130',
        $wal,
        $walBytes,
        $databaseBytes,
        $pages,
        $reader
    );
};

$pinned = static fn (): array => $plan();
$baseReader = static fn (): array => $plan(0);
$single = static fn (): array => $plan(2, [2]);

$cases = [
    'status' => [static fn (): mixed => $pinned()['status'], 'wal-reader-checkpoint-savepoint-truncate-current-source-next130'],
    'savepoint' => [static fn (): mixed => $pinned()['savepoint'], 'plugin-batch-next130'],
    'mode' => [static fn (): mixed => $pinned()['mode'], 'truncate'],
    'page size' => [static fn (): mixed => $pinned()['page_size'], 512],
    'current reader end frame' => [static fn (): mixed => $pinned()['current_reader_end_frame'], 2],
    'original frame count' => [static fn (): mixed => $pinned()['original_frame_count'], 6],
    'retained frame count' => [static fn (): mixed => $pinned()['retained_frame_count'], 2],
    'discarded frame count' => [static fn (): mixed => $pinned()['discarded_frame_count'], 4],
    'discarded indexes' => [static fn (): mixed => $pinned()['discarded_frame_indexes'], [3, 4, 5, 6]],
    'discarded pages' => [static fn (): mixed => $pinned()['discarded_page_numbers'], [3, 4, 2, 5]],
    'retained wal bytes' => [static fn (): mixed => $pinned()['retained_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'retained sha length' => [static fn (): mixed => strlen($pinned()['retained_wal_sha256']), 64],
    'current checkpoint busy' => [static fn (): mixed => $pinned()['current_checkpoint_busy'], true],
    'current checkpoint reason' => [static fn (): mixed => $pinned()['current_checkpoint_reason'], 'reader_blocks_wal_reset'],
    'current wal action' => [static fn (): mixed => $pinned()['current_wal_action'], 'preserve_wal'],
    'current wal bytes length' => [static fn (): mixed => $pinned()['current_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'next checkpoint ready' => [static fn (): mixed => $pinned()['next_checkpoint_busy'], false],
    'next checkpoint reason' => [static fn (): mixed => $pinned()['next_checkpoint_reason'], 'truncate_checkpoint_can_reset_and_truncate_wal'],
    'next wal action' => [static fn (): mixed => $pinned()['next_wal_action'], 'truncate_wal'],
    'next wal bytes length' => [static fn (): mixed => $pinned()['next_wal_bytes_length'], 0],
    'next database bytes length' => [static fn (): mixed => $pinned()['next_database_bytes_length'], strlen($databaseBytes)],
    'next database sha length' => [static fn (): mixed => strlen($pinned()['next_database_sha256']), 64],
    'wal sidecar removed' => [static fn (): mixed => $pinned()['wal_sidecar_removed_for_next_open'], true],
    'next open reader frame' => [static fn (): mixed => $pinned()['next_open_reader_frame'], 0],
    'current sources' => [static fn (): mixed => $pinned()['current_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'current after checkpoint sources' => [static fn (): mixed => $pinned()['current_after_checkpoint_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'next open sources' => [static fn (): mixed => $pinned()['next_open_sources'], ['database', 'database', 'database', 'database', 'database']],
    'current wal count' => [static fn (): mixed => $pinned()['current_source_counts']['wal'], 2],
    'current database count' => [static fn (): mixed => $pinned()['current_source_counts']['database'], 3],
    'after wal count' => [static fn (): mixed => $pinned()['current_after_checkpoint_source_counts']['wal'], 2],
    'next database count' => [static fn (): mixed => $pinned()['next_open_source_counts']['database'], 5],
    'row count' => [static fn (): mixed => count($pinned()['rows']), 5],
    'row pages' => [static fn (): mixed => array_column($pinned()['rows'], 'page_number'), [1, 2, 3, 4, 5]],
    'current frames' => [static fn (): mixed => array_column($pinned()['rows'], 'current_frame'), [1, 2, null, null, null]],
    'after frames' => [static fn (): mixed => array_column($pinned()['rows'], 'current_after_checkpoint_frame'), [1, 2, null, null, null]],
    'next frames' => [static fn (): mixed => array_column($pinned()['rows'], 'next_open_frame'), [null, null, null, null, null]],
    'source transitions' => [static fn (): mixed => $pinned()['source_transitions'], ['wal>wal>database', 'wal>wal>database', 'database>database>database', 'database>database>database', 'database>database>database']],
    'row one current label' => [static fn (): mixed => $pinned()['rows'][0]['current_label'], 'next130 retained schema draft'],
    'row two current label' => [static fn (): mixed => $pinned()['rows'][1]['current_label'], 'next130 retained siteurl commit'],
    'row two next label' => [static fn (): mixed => $pinned()['rows'][1]['next_open_label'], 'next130 retained siteurl commit'],
    'row three next label' => [static fn (): mixed => $pinned()['rows'][2]['next_open_label'], 'next130 autoload base'],
    'row five next label' => [static fn (): mixed => $pinned()['rows'][4]['next_open_label'], 'next130 plugin base'],
    'current reader preserved' => [static fn (): mixed => $pinned()['current_reader_preserved_images'], true],
    'next open preserved' => [static fn (): mixed => $pinned()['next_open_preserved_images'], true],
    'next open uses database' => [static fn (): mixed => $pinned()['next_open_uses_checkpoint_database'], true],
    'reader release unblocked truncate' => [static fn (): mixed => $pinned()['reader_release_unblocked_truncate'], true],
    'source digest length' => [static fn (): mixed => strlen($pinned()['source_digest']), 64],
    'base reader current sources' => [static fn (): mixed => $baseReader()['current_sources'], ['database', 'database', 'database', 'database', 'database']],
    'base reader checkpoint reason' => [static fn (): mixed => $baseReader()['current_checkpoint_reason'], 'reader_blocks_checkpoint_completion'],
    'single page next label' => [static fn (): mixed => $single()['rows'][0]['next_open_label'], 'next130 retained siteurl commit'],
    'single page transition' => [static fn (): mixed => $single()['source_transitions'], ['wal>wal>database']],
    'dependency next130' => [static fn (): mixed => in_array('sqlite-wal-reader-checkpoint-savepoint-truncate-current-source-next130', $pinned()['dependencies'], true), true],
    'dependency truncation' => [static fn (): mixed => in_array('sqlite-savepoint-wal-prefix-truncation', $pinned()['dependencies'], true), true],
    'dependency next open' => [static fn (): mixed => in_array('sqlite-wal-truncate-next-open-reader', $pinned()['dependencies'], true), true],
    'dependency durable sidecar' => [static fn (): mixed => in_array('durable-sidecar-write', $pinned()['dependencies'], true), true],
];

foreach ($cases as $name => [$actual, $expected]) {
    $tests['wal reader checkpoint savepoint truncate current source next130 ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
}

$tests['wal reader checkpoint savepoint truncate current source next130 rejects empty savepoint'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan::readerCheckpointSavepointTruncateAfterRollbackPlan($makeStack(), '', $wal, $walBytes, $databaseBytes, [1]));
};

$tests['wal reader checkpoint savepoint truncate current source next130 rejects empty wal bytes'] = static function (TestRunner $t) use ($makeStack, $wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan::readerCheckpointSavepointTruncateAfterRollbackPlan($makeStack(), 'plugin-batch-next130', $wal, '', $databaseBytes, [1]));
};

$tests['wal reader checkpoint savepoint truncate current source next130 rejects empty database bytes'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan::readerCheckpointSavepointTruncateAfterRollbackPlan($makeStack(), 'plugin-batch-next130', $wal, $walBytes, '', [1]));
};

$tests['wal reader checkpoint savepoint truncate current source next130 rejects empty pages'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan::readerCheckpointSavepointTruncateAfterRollbackPlan($makeStack(), 'plugin-batch-next130', $wal, $walBytes, $databaseBytes, []));
};

$tests['wal reader checkpoint savepoint truncate current source next130 rejects source mismatch'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $bad = substr_replace($walBytes, 'x', 1200, 1);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan::readerCheckpointSavepointTruncateAfterRollbackPlan($makeStack(), 'plugin-batch-next130', $wal, $bad, $databaseBytes, [1]));
};

$tests['wal reader checkpoint savepoint truncate current source next130 rejects non integer page'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan::readerCheckpointSavepointTruncateAfterRollbackPlan($makeStack(), 'plugin-batch-next130', $wal, $walBytes, $databaseBytes, ['1']));
};

$tests['wal reader checkpoint savepoint truncate current source next130 rejects reader outside retained range'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan::readerCheckpointSavepointTruncateAfterRollbackPlan($makeStack(), 'plugin-batch-next130', $wal, $walBytes, $databaseBytes, [1], 3));
};

$tests['wal reader checkpoint savepoint truncate current source next130 rejects missing savepoint'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(Throwable::class, static fn (): mixed => SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan::readerCheckpointSavepointTruncateAfterRollbackPlan($makeStack(), 'missing-next130', $wal, $walBytes, $databaseBytes, [1]));
};

return $tests;
