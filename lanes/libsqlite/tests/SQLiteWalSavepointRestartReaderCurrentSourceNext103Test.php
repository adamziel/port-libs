<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next103 schema base')
    . $page('next103 options base')
    . $page('next103 plugin base')
    . $page('next103 autoload base');

$makeWalBytes = static function (int $checkpoint, int $salt1, int $salt2, array $labels) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($labels as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$labels = [
    [1, 0, 'next103 retained schema draft'],
    [2, 4, 'next103 retained options commit'],
    [3, 0, 'next103 discarded plugin draft'],
    [4, 4, 'next103 discarded autoload commit'],
    [2, 4, 'next103 discarded options retry draft'],
];
$walBytes = $makeWalBytes(103, 0x10310310, 0x3030103, $labels);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$mutatedLabels = $labels;
$mutatedLabels[1] = [2, 4, 'next103 retained options stale source'];
$mutatedWalBytes = $makeWalBytes(103, 0x10310310, 0x3030103, $mutatedLabels);
$shortWalBytes = substr($walBytes, 0, 32 + (4 * (24 + $pageSize)));

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-retry');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4, true);
    $stack->recordWalFrameWrite(5, 2);

    return $stack;
};

$transactions = [
    [
        'pages' => [
            2 => $page('next103 retry active plugins committed'),
            3 => $page('next103 retry plugin settings committed'),
            4 => $page('next103 retry autoload index committed'),
        ],
        'database_page_count' => 4,
        'commit' => true,
    ],
];

$plan = static fn (string $mode = 'restart', bool $syncWal = true, bool $syncDirectory = true): array => SQLiteWalSavepointCheckpointPlan::savepointRestartAppendReaderCurrentSourceNext(
    $makeStack(),
    'plugin-retry',
    $wal,
    $walBytes,
    $databaseBytes,
    $databasePath,
    $transactions,
    [1, 2, 3, 4],
    $mode,
    5,
    $syncWal,
    $syncDirectory
);

$restart = static fn (): array => $plan('restart');
$truncate = static fn (): array => $plan('truncate');
$unsynced = static fn (): array => $plan('restart', false, false);

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'savepoint-restart-append-current-source-next103'],
    'savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-retry'],
    'mode restart' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], $databasePath . '-wal'],
    'original reader end frame' => [static fn (): mixed => $restart()['original_reader_end_frame'], 5],
    'current reader end frame clamps retained' => [static fn (): mixed => $restart()['current_reader_end_frame'], 2],
    'released next reader end frame' => [static fn (): mixed => $restart()['released_next_reader_end_frame'], 0],
    'next reader end frame' => [static fn (): mixed => $restart()['next_reader_end_frame'], 3],
    'retained frame count' => [static fn (): mixed => $restart()['retained_frame_count'], 2],
    'discarded frame count' => [static fn (): mixed => $restart()['discarded_frame_count'], 3],
    'current source verified' => [static fn (): mixed => $restart()['current_source_verified'], true],
    'current source checkpoint' => [static fn (): mixed => $restart()['current_source']['checkpoint_sequence'], 103],
    'current source frame count' => [static fn (): mixed => $restart()['current_source']['frame_count'], 5],
    'current source byte length' => [static fn (): mixed => $restart()['current_source']['wal_bytes_length'], strlen($walBytes)],
    'retained source frame count' => [static fn (): mixed => $restart()['retained_source']['frame_count'], 2],
    'released source kind' => [static fn (): mixed => $restart()['released_source']['kind'], 'restart_wal'],
    'released source checkpoint advanced' => [static fn (): mixed => $restart()['released_source']['checkpoint_sequence'], 104],
    'next source checkpoint advanced' => [static fn (): mixed => $restart()['next_source']['checkpoint_sequence'], 104],
    'next source frame count' => [static fn (): mixed => $restart()['next_source']['frame_count'], 3],
    'append committed transaction count' => [static fn (): mixed => $restart()['append']['committed_transaction_count'], 1],
    'append operation count synced' => [static fn (): mixed => count($restart()['append']['operations']), 3],
    'append last commit frame' => [static fn (): mixed => $restart()['append']['last_commit_frame'], 3],
    'current sources' => [static fn (): mixed => $restart()['current_sources'], ['wal', 'wal', 'database', 'database']],
    'released next sources' => [static fn (): mixed => $restart()['released_next_sources'], ['database', 'database', 'database', 'database']],
    'next sources' => [static fn (): mixed => $restart()['next_sources'], ['database', 'wal', 'wal', 'wal']],
    'current source counts wal' => [static fn (): mixed => $restart()['current_source_counts']['wal'], 2],
    'next source counts wal' => [static fn (): mixed => $restart()['next_source_counts']['wal'], 3],
    'source transitions' => [static fn (): mixed => $restart()['source_transitions'], ['wal>wal>database>database', 'wal>wal>database>wal', 'wal>database>database>wal', 'wal>database>database>wal']],
    'rolled back page numbers' => [static fn (): mixed => $restart()['rolled_back_page_numbers'], [2, 3, 4]],
    'rolled back frame indexes' => [static fn (): mixed => $restart()['rolled_back_frame_indexes'], [3, 4, 5]],
    'reader release unblocked checkpoint' => [static fn (): mixed => $restart()['reader_release_unblocked_checkpoint'], true],
    'next uses restarted generation' => [static fn (): mixed => $restart()['next_uses_restarted_generation'], true],
    'next uses appended wal' => [static fn (): mixed => $restart()['next_uses_appended_wal'], true],
    'current reader preserved through release' => [static fn (): mixed => $restart()['current_reader_preserved'], true],
    'images differ after retry append' => [static fn (): mixed => $restart()['images_match'], false],
    'row count' => [static fn (): mixed => count($restart()['current_source_rows']), 4],
    'row page numbers' => [static fn (): mixed => array_column($restart()['current_source_rows'], 'page_number'), [1, 2, 3, 4]],
    'row next frames' => [static fn (): mixed => array_column($restart()['current_source_rows'], 'next_frame'), [null, 1, 2, 3]],
    'row current to next changed' => [static fn (): mixed => array_column($restart()['current_source_rows'], 'current_to_next_changed'), [false, true, true, true]],
    'row released to next changed' => [static fn (): mixed => array_column($restart()['current_source_rows'], 'released_to_next_changed'), [false, true, true, true]],
    'next label page two' => [static fn (): mixed => str_contains($restart()['current_source_rows'][1]['next_label'], 'retry active plugins committed'), true],
    'frame source rows count' => [static fn (): mixed => count($restart()['frame_source_rows']), 5],
    'commit frame indexes' => [static fn (): mixed => $restart()['commit_frame_indexes'], [2, 4, 5]],
    'frame source offsets' => [static fn (): mixed => array_column($restart()['frame_source_rows'], 'source_offset'), [32, 568, 1104, 1640, 2176]],
    'source digest length' => [static fn (): mixed => strlen($restart()['source_digest']), 64],
    'yield count' => [static fn (): mixed => $restart()['yield_count'], 26],
    'dependency next103' => [static fn (): mixed => in_array('sqlite-wal-savepoint-restart-reader-current-source-next103', $restart()['dependencies'], true), true],
    'dependency next99 retained' => [static fn (): mixed => in_array('sqlite-wal-savepoint-reader-checkpoint-current-source-next99', $restart()['dependencies'], true), true],
    'dependency append transaction' => [static fn (): mixed => in_array('sqlite-wal-append-transaction', $restart()['dependencies'], true), true],
    'truncate mode' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'truncate released source kind' => [static fn (): mixed => $truncate()['released_source']['kind'], 'truncate_wal'],
    'truncate next source frame count' => [static fn (): mixed => $truncate()['next_source']['frame_count'], 3],
    'truncate next sources' => [static fn (): mixed => $truncate()['next_sources'], ['database', 'wal', 'wal', 'wal']],
    'unsynced append operation count' => [static fn (): mixed => count($unsynced()['append']['operations']), 1],
    'unsynced omits wal sync dependency' => [static fn (): mixed => in_array('vfs-file-write-coordination', $unsynced()['dependencies'], true), true],
];

foreach ($cases as $name => [$actual, $expected]) {
    $tests['wal savepoint restart reader current source next103 ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
}

$tests['wal savepoint restart reader current source next103 rejects empty database path'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::savepointRestartAppendReaderCurrentSourceNext($makeStack(), 'plugin-retry', $wal, $walBytes, $databaseBytes, '', $transactions, [1]));
};

$tests['wal savepoint restart reader current source next103 rejects empty pages'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::savepointRestartAppendReaderCurrentSourceNext($makeStack(), 'plugin-retry', $wal, $walBytes, $databaseBytes, $databasePath, $transactions, []));
};

$tests['wal savepoint restart reader current source next103 rejects invalid mode'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::savepointRestartAppendReaderCurrentSourceNext($makeStack(), 'plugin-retry', $wal, $walBytes, $databaseBytes, $databasePath, $transactions, [1], 'passive'));
};

$tests['wal savepoint restart reader current source next103 rejects non integer page'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::savepointRestartAppendReaderCurrentSourceNext($makeStack(), 'plugin-retry', $wal, $walBytes, $databaseBytes, $databasePath, $transactions, ['1']));
};

$tests['wal savepoint restart reader current source next103 rejects short source'] = static function (TestRunner $t) use ($makeStack, $wal, $shortWalBytes, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::savepointRestartAppendReaderCurrentSourceNext($makeStack(), 'plugin-retry', $wal, $shortWalBytes, $databaseBytes, $databasePath, $transactions, [1]));
};

$tests['wal savepoint restart reader current source next103 rejects mutated source'] = static function (TestRunner $t) use ($makeStack, $wal, $mutatedWalBytes, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::savepointRestartAppendReaderCurrentSourceNext($makeStack(), 'plugin-retry', $wal, $mutatedWalBytes, $databaseBytes, $databasePath, $transactions, [1]));
};

return $tests;
