<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointReaderTruncateSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next128 schema base')
    . $page('next128 option base')
    . $page('next128 autoload base')
    . $page('next128 transient base')
    . $page('next128 plugin base');

$makeWal = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x12812801;
    $salt2 = 0x12812802;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 128, $salt1, $salt2);
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
    [1, 0, 'next128 retained schema draft'],
    [2, 5, 'next128 retained siteurl commit'],
    [3, 0, 'next128 discarded autoload draft'],
    [4, 5, 'next128 discarded transient commit'],
    [2, 5, 'next128 discarded option retry'],
    [5, 5, 'next128 discarded plugin tail'],
];
$walBytes = $makeWal($frames);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next128');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('theme-batch-next128');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4, true);
    $stack->recordWalFrameWrite(5, 2, true);
    $stack->recordWalFrameWrite(6, 5, true);

    return $stack;
};

$plan = static function (?int $reader = 2, array $pages = [1, 2, 3, 4, 5]) use ($makeStack, $wal, $walBytes, $databaseBytes): array {
    return SQLiteWalCheckpointReaderTruncateSavepointCurrentSourceNextPlan::plan(
        $makeStack(),
        'theme-batch-next128',
        $wal,
        $walBytes,
        $databaseBytes,
        $pages,
        $reader
    );
};

$pinned = static fn (): array => $plan();
$releasedOnly = static fn (): array => $plan(null);
$baseReader = static fn (): array => $plan(0);
$single = static fn (): array => $plan(2, [2]);

$cases = [
    'status' => [static fn (): mixed => $pinned()['status'], 'wal-checkpoint-reader-truncate-savepoint-current-source-next128'],
    'savepoint' => [static fn (): mixed => $pinned()['savepoint'], 'theme-batch-next128'],
    'mode' => [static fn (): mixed => $pinned()['mode'], 'truncate'],
    'page size' => [static fn (): mixed => $pinned()['page_size'], 512],
    'reader end frame' => [static fn (): mixed => $pinned()['reader_end_frame'], 2],
    'original frame count' => [static fn (): mixed => $pinned()['original_frame_count'], 6],
    'retained frame count' => [static fn (): mixed => $pinned()['retained_frame_count'], 2],
    'discarded frame count' => [static fn (): mixed => $pinned()['discarded_frame_count'], 4],
    'truncate to bytes' => [static fn (): mixed => $pinned()['truncate_to_bytes'], 32 + (2 * (24 + $pageSize))],
    'retained wal bytes' => [static fn (): mixed => $pinned()['retained_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'retained sha length' => [static fn (): mixed => strlen($pinned()['retained_wal_sha256']), 64],
    'source frame count' => [static fn (): mixed => $pinned()['current_source']['frame_count'], 2],
    'source checkpoint sequence' => [static fn (): mixed => $pinned()['current_source']['checkpoint_sequence'], 128],
    'source salt one' => [static fn (): mixed => $pinned()['current_source']['salt1'], 0x12812801],
    'source salt two' => [static fn (): mixed => $pinned()['current_source']['salt2'], 0x12812802],
    'source bytes length' => [static fn (): mixed => $pinned()['current_source']['wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'discarded indexes' => [static fn (): mixed => $pinned()['discarded_frame_indexes'], [3, 4, 5, 6]],
    'discarded pages' => [static fn (): mixed => $pinned()['discarded_page_numbers'], [3, 4, 2, 5]],
    'discarded frame names' => [static fn (): mixed => array_column($pinned()['discarded_wal_frames'], 'frame_name'), ['theme-batch-next128', 'theme-batch-next128', 'theme-batch-next128', 'theme-batch-next128']],
    'discarded commit flags' => [static fn (): mixed => array_column($pinned()['discarded_wal_frames'], 'commit_frame'), [false, true, true, true]],
    'pinned checkpoint busy' => [static fn (): mixed => $pinned()['pinned_checkpoint_busy'], true],
    'pinned checkpoint reason' => [static fn (): mixed => $pinned()['pinned_checkpoint_reason'], 'reader_blocks_wal_reset'],
    'pinned wal action' => [static fn (): mixed => $pinned()['pinned_wal_action'], 'preserve_wal'],
    'pinned checkpointed frames' => [static fn (): mixed => $pinned()['pinned_checkpointed_frame_count'], 2],
    'pinned wal bytes length' => [static fn (): mixed => $pinned()['pinned_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'released checkpoint ready' => [static fn (): mixed => $pinned()['released_checkpoint_busy'], false],
    'released checkpoint reason' => [static fn (): mixed => $pinned()['released_checkpoint_reason'], 'truncate_checkpoint_can_reset_and_truncate_wal'],
    'released wal action' => [static fn (): mixed => $pinned()['released_wal_action'], 'truncate_wal'],
    'released checkpointed frames' => [static fn (): mixed => $pinned()['released_checkpointed_frame_count'], 2],
    'released wal bytes length' => [static fn (): mixed => $pinned()['released_wal_bytes_length'], 0],
    'released database bytes length' => [static fn (): mixed => $pinned()['released_database_bytes_length'], strlen($databaseBytes)],
    'current sources' => [static fn (): mixed => $pinned()['current_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'pinned sources' => [static fn (): mixed => $pinned()['pinned_next_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'released sources' => [static fn (): mixed => $pinned()['released_next_sources'], ['database', 'database', 'database', 'database', 'database']],
    'current wal count' => [static fn (): mixed => $pinned()['current_source_counts']['wal'], 2],
    'current database count' => [static fn (): mixed => $pinned()['current_source_counts']['database'], 3],
    'pinned wal count' => [static fn (): mixed => $pinned()['pinned_next_source_counts']['wal'], 2],
    'released database count' => [static fn (): mixed => $pinned()['released_next_source_counts']['database'], 5],
    'row count' => [static fn (): mixed => count($pinned()['rows']), 5],
    'row pages' => [static fn (): mixed => array_column($pinned()['rows'], 'page_number'), [1, 2, 3, 4, 5]],
    'current frames' => [static fn (): mixed => array_column($pinned()['rows'], 'current_frame'), [1, 2, null, null, null]],
    'pinned frames' => [static fn (): mixed => array_column($pinned()['rows'], 'pinned_next_frame'), [1, 2, null, null, null]],
    'released frames' => [static fn (): mixed => array_column($pinned()['rows'], 'released_next_frame'), [null, null, null, null, null]],
    'source transitions' => [static fn (): mixed => $pinned()['source_transitions'], ['wal>wal>database', 'wal>wal>database', 'database>database>database', 'database>database>database', 'database>database>database']],
    'row one current label' => [static fn (): mixed => $pinned()['rows'][0]['current_label'], 'next128 retained schema draft'],
    'row two current label' => [static fn (): mixed => $pinned()['rows'][1]['current_label'], 'next128 retained siteurl commit'],
    'row two released label' => [static fn (): mixed => $pinned()['rows'][1]['released_next_label'], 'next128 retained siteurl commit'],
    'row three current label' => [static fn (): mixed => $pinned()['rows'][2]['current_label'], 'next128 autoload base'],
    'row five released label' => [static fn (): mixed => $pinned()['rows'][4]['released_next_label'], 'next128 plugin base'],
    'pinned preserves images' => [static fn (): mixed => $pinned()['pinned_checkpoint_preserved_images'], true],
    'released preserves images' => [static fn (): mixed => $pinned()['released_checkpoint_preserved_images'], true],
    'reader release unblocked truncate' => [static fn (): mixed => $pinned()['reader_release_unblocked_truncate'], true],
    'released uses checkpoint database' => [static fn (): mixed => $pinned()['released_reader_uses_checkpoint_database'], true],
    'current source verified' => [static fn (): mixed => $pinned()['current_source_verified'], true],
    'source digest length' => [static fn (): mixed => strlen($pinned()['source_digest']), 64],
    'default reader equals retained frame count' => [static fn (): mixed => $releasedOnly()['reader_end_frame'], 2],
    'base reader current sources' => [static fn (): mixed => $baseReader()['current_sources'], ['database', 'database', 'database', 'database', 'database']],
    'base reader pinned reason' => [static fn (): mixed => $baseReader()['pinned_checkpoint_reason'], 'reader_blocks_checkpoint_completion'],
    'single page current label' => [static fn (): mixed => $single()['rows'][0]['current_label'], 'next128 retained siteurl commit'],
    'single page transition' => [static fn (): mixed => $single()['source_transitions'], ['wal>wal>database']],
    'dependency next128' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-reader-truncate-savepoint-current-source-next128', $pinned()['dependencies'], true), true],
    'dependency truncation' => [static fn (): mixed => in_array('sqlite-savepoint-wal-prefix-truncation', $pinned()['dependencies'], true), true],
    'dependency checkpoint' => [static fn (): mixed => in_array('sqlite-wal-durable-checkpoint-result', $pinned()['dependencies'], true), true],
    'dependency durable sidecar' => [static fn (): mixed => in_array('durable-sidecar-write', $pinned()['dependencies'], true), true],
];

foreach ($cases as $name => [$actual, $expected]) {
    $tests['wal checkpoint reader truncate savepoint current source next128 ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
}

$tests['wal checkpoint reader truncate savepoint current source next128 rejects empty savepoint'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointReaderTruncateSavepointCurrentSourceNextPlan::plan($makeStack(), '', $wal, $walBytes, $databaseBytes, [1]));
};

$tests['wal checkpoint reader truncate savepoint current source next128 rejects empty wal bytes'] = static function (TestRunner $t) use ($makeStack, $wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointReaderTruncateSavepointCurrentSourceNextPlan::plan($makeStack(), 'theme-batch-next128', $wal, '', $databaseBytes, [1]));
};

$tests['wal checkpoint reader truncate savepoint current source next128 rejects empty database bytes'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointReaderTruncateSavepointCurrentSourceNextPlan::plan($makeStack(), 'theme-batch-next128', $wal, $walBytes, '', [1]));
};

$tests['wal checkpoint reader truncate savepoint current source next128 rejects empty pages'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointReaderTruncateSavepointCurrentSourceNextPlan::plan($makeStack(), 'theme-batch-next128', $wal, $walBytes, $databaseBytes, []));
};

$tests['wal checkpoint reader truncate savepoint current source next128 rejects source mismatch'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $bad = substr_replace($walBytes, 'x', 1200, 1);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointReaderTruncateSavepointCurrentSourceNextPlan::plan($makeStack(), 'theme-batch-next128', $wal, $bad, $databaseBytes, [1]));
};

$tests['wal checkpoint reader truncate savepoint current source next128 rejects non integer page'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointReaderTruncateSavepointCurrentSourceNextPlan::plan($makeStack(), 'theme-batch-next128', $wal, $walBytes, $databaseBytes, ['1']));
};

$tests['wal checkpoint reader truncate savepoint current source next128 rejects reader outside retained range'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointReaderTruncateSavepointCurrentSourceNextPlan::plan($makeStack(), 'theme-batch-next128', $wal, $walBytes, $databaseBytes, [1], 3));
};

$tests['wal checkpoint reader truncate savepoint current source next128 rejects missing savepoint'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes): void {
    $t->throws(Throwable::class, static fn (): mixed => SQLiteWalCheckpointReaderTruncateSavepointCurrentSourceNextPlan::plan($makeStack(), 'missing-next128', $wal, $walBytes, $databaseBytes, [1]));
};

return $tests;
