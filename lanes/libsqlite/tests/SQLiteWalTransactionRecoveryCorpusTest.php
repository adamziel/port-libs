<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$databaseBytes = str_pad('wp-options-schema-before-wal', $pageSize, '.') . str_pad('wp-options-data-before-wal', $pageSize, '.');
$salt1 = 0x51515151;
$salt2 = 0x61616161;

$header = static function (int $checkpoint = 0) use ($pageSize, $salt1, $salt2): array {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($prefix, false);

    return [$prefix . pack('N*', $checksum[0], $checksum[1]), $checksum];
};

$append = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $label) use ($pageSize, $salt1, $salt2): string {
    $pageImage = str_pad($label, $pageSize, "\0");
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $pageImage, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $pageImage;
};

$build = static function (array $frames, ?callable $mutate = null) use ($header, $append): string {
    [$bytes, $seed] = $header(3);
    foreach ($frames as $frame) {
        $bytes = $append($bytes, $seed, $frame[0], $frame[1], $frame[2]);
    }

    return $mutate === null ? $bytes : $mutate($bytes);
};

$validCommitted = $build([
    [1, 0, 'schema committed page one older'],
    [2, 2, 'data committed page two'],
]);
$validTail = $build([
    [1, 0, 'schema committed before plugin'],
    [2, 2, 'autoload index committed before plugin'],
    [3, 0, 'plugin option draft uncommitted'],
    [4, 0, 'single option tail uncommitted'],
]);
$corruptAfterCommit = $build([
    [1, 0, 'schema committed before corrupt tail'],
    [2, 2, 'data committed before corrupt tail'],
    [3, 0, 'draft frame checksum will corrupt'],
], static fn (string $bytes): string => substr_replace($bytes, 'X', 32 + ((24 + 512) * 2) + 40, 1));
$validTailBeforeCorrupt = $build([
    [1, 0, 'schema committed before mixed tail'],
    [2, 2, 'data committed before mixed tail'],
    [3, 0, 'valid draft before corrupt frame'],
    [4, 0, 'corrupt draft after valid tail'],
], static fn (string $bytes): string => substr_replace($bytes, 'Y', 32 + ((24 + 512) * 3) + 80, 1));
$noCommit = $build([
    [1, 0, 'schema draft without commit'],
    [2, 0, 'data draft without commit'],
]);
$truncatedTail = substr($validTail, 0, -100);
$badHeader = substr_replace($validCommitted, "\0\0\0\0", 24, 4);

$cases = [
    'valid status' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($validCommitted, $databaseBytes)['status'],
    'valid reason' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($validCommitted, $databaseBytes)['reason'],
    'valid frame count' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($validCommitted, $databaseBytes)['valid_frame_count'],
    'valid committed frame count' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($validCommitted, $databaseBytes)['committed_frame_count'],
    'valid committed bytes length' => static fn (): mixed => strlen(SQLiteWal::transactionRecoveryBoundary($validCommitted, $databaseBytes)['committed_wal_bytes']),
    'valid recovery offset equals committed offset' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($validCommitted, $databaseBytes)['recovery_end_offset'] === SQLiteWal::transactionRecoveryBoundary($validCommitted, $databaseBytes)['committed_end_offset'],
    'valid last commit frame' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($validCommitted, $databaseBytes)['last_commit_frame'],
    'valid checkpoint page count' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($validCommitted, $databaseBytes)['checkpoint_database_page_count'],
    'valid checkpoint contains page two' => static fn (): mixed => str_contains(SQLiteWal::transactionRecoveryBoundary($validCommitted, $databaseBytes)['checkpoint_database_bytes'], 'data committed page two'),
    'valid dependencies' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($validCommitted, $databaseBytes)['dependencies'],
    'valid tail status' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($validTail, $databaseBytes)['status'],
    'valid tail reason' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($validTail, $databaseBytes)['reason'],
    'valid tail valid frames' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($validTail, $databaseBytes)['valid_frame_count'],
    'valid tail committed frames' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($validTail, $databaseBytes)['committed_frame_count'],
    'valid tail discarded valid frames' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($validTail, $databaseBytes)['discarded_valid_tail_frame_count'],
    'valid tail discarded corrupt frames' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($validTail, $databaseBytes)['discarded_corrupt_tail_frame_count'],
    'valid tail committed bytes shorter' => static fn (): mixed => strlen(SQLiteWal::transactionRecoveryBoundary($validTail, $databaseBytes)['committed_wal_bytes']) < strlen(SQLiteWal::transactionRecoveryBoundary($validTail, $databaseBytes)['valid_wal_bytes']),
    'valid tail committed wal frame count' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($validTail, $databaseBytes)['committed_wal']->frameCount(),
    'valid tail committed wal uncommitted count' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($validTail, $databaseBytes)['committed_wal']->uncommittedFrameCount(),
    'valid tail checkpoint excludes draft' => static fn (): mixed => str_contains(SQLiteWal::transactionRecoveryBoundary($validTail, $databaseBytes)['checkpoint_database_bytes'], 'plugin option draft uncommitted'),
    'corrupt after commit status' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($corruptAfterCommit, $databaseBytes)['status'],
    'corrupt after commit reason' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($corruptAfterCommit, $databaseBytes)['reason'],
    'corrupt after commit first invalid' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($corruptAfterCommit, $databaseBytes)['first_invalid_frame'],
    'corrupt after commit valid frames' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($corruptAfterCommit, $databaseBytes)['valid_frame_count'],
    'corrupt after commit committed frames' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($corruptAfterCommit, $databaseBytes)['committed_frame_count'],
    'corrupt after commit discarded corrupt frames' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($corruptAfterCommit, $databaseBytes)['discarded_corrupt_tail_frame_count'],
    'corrupt after commit committed bytes length' => static fn (): mixed => strlen(SQLiteWal::transactionRecoveryBoundary($corruptAfterCommit, $databaseBytes)['committed_wal_bytes']),
    'corrupt after commit can checkpoint' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($corruptAfterCommit, $databaseBytes)['can_checkpoint'],
    'corrupt after commit checkpoint contains data' => static fn (): mixed => str_contains(SQLiteWal::transactionRecoveryBoundary($corruptAfterCommit, $databaseBytes)['checkpoint_database_bytes'], 'data committed before corrupt tail'),
    'corrupt after commit checkpoint excludes corrupt draft' => static fn (): mixed => str_contains(SQLiteWal::transactionRecoveryBoundary($corruptAfterCommit, $databaseBytes)['checkpoint_database_bytes'], 'draft frame checksum will corrupt'),
    'mixed tail reason' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($validTailBeforeCorrupt, $databaseBytes)['reason'],
    'mixed tail valid frames' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($validTailBeforeCorrupt, $databaseBytes)['valid_frame_count'],
    'mixed tail committed frames' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($validTailBeforeCorrupt, $databaseBytes)['committed_frame_count'],
    'mixed tail first invalid' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($validTailBeforeCorrupt, $databaseBytes)['first_invalid_frame'],
    'mixed tail discarded valid frames' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($validTailBeforeCorrupt, $databaseBytes)['discarded_valid_tail_frame_count'],
    'mixed tail discarded corrupt frames' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($validTailBeforeCorrupt, $databaseBytes)['discarded_corrupt_tail_frame_count'],
    'mixed tail valid bytes contain draft' => static fn (): mixed => str_contains(SQLiteWal::transactionRecoveryBoundary($validTailBeforeCorrupt, $databaseBytes)['valid_wal_bytes'], 'valid draft before corrupt frame'),
    'mixed tail committed bytes omit draft' => static fn (): mixed => str_contains(SQLiteWal::transactionRecoveryBoundary($validTailBeforeCorrupt, $databaseBytes)['committed_wal_bytes'], 'valid draft before corrupt frame'),
    'mixed tail checkpoint excludes draft' => static fn (): mixed => str_contains(SQLiteWal::transactionRecoveryBoundary($validTailBeforeCorrupt, $databaseBytes)['checkpoint_database_bytes'], 'valid draft before corrupt frame'),
    'mixed tail committed wal frame count' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($validTailBeforeCorrupt, $databaseBytes)['committed_wal']->frameCount(),
    'no commit status' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($noCommit, $databaseBytes)['status'],
    'no commit reason' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($noCommit, $databaseBytes)['reason'],
    'no commit committed frames' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($noCommit, $databaseBytes)['committed_frame_count'],
    'no commit discarded valid frames' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($noCommit, $databaseBytes)['discarded_valid_tail_frame_count'],
    'no commit checkpoint absent' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($noCommit, $databaseBytes)['checkpoint_database_bytes'],
    'no commit can checkpoint' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($noCommit, $databaseBytes)['can_checkpoint'],
    'no commit committed bytes is header only' => static fn (): mixed => strlen(SQLiteWal::transactionRecoveryBoundary($noCommit, $databaseBytes)['committed_wal_bytes']),
    'truncated tail reason' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($truncatedTail, $databaseBytes)['reason'],
    'truncated tail first invalid' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($truncatedTail, $databaseBytes)['first_invalid_frame'],
    'truncated tail total slots' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($truncatedTail, $databaseBytes)['total_frame_slots'],
    'truncated tail valid frames' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($truncatedTail, $databaseBytes)['valid_frame_count'],
    'truncated tail committed frames' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($truncatedTail, $databaseBytes)['committed_frame_count'],
    'truncated tail committed offset' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($truncatedTail, $databaseBytes)['committed_end_offset'],
    'truncated tail recovery offset' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($truncatedTail, $databaseBytes)['recovery_end_offset'],
    'truncated tail committed wal parseable' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($truncatedTail, $databaseBytes)['committed_wal']->checksumsValidated,
    'bad header reason' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($badHeader, $databaseBytes)['reason'],
    'bad header status' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($badHeader, $databaseBytes)['status'],
    'bad header valid frames' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($badHeader, $databaseBytes)['valid_frame_count'],
    'bad header committed frames' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($badHeader, $databaseBytes)['committed_frame_count'],
    'bad header first invalid frame' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($badHeader, $databaseBytes)['first_invalid_frame'],
    'bad header committed bytes length' => static fn (): mixed => strlen(SQLiteWal::transactionRecoveryBoundary($badHeader, $databaseBytes)['committed_wal_bytes']),
    'bad header discarded corrupt slots' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($badHeader, $databaseBytes)['discarded_corrupt_tail_frame_count'],
    'bad header can checkpoint' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($badHeader, $databaseBytes)['can_checkpoint'],
    'bad header committed wal frame count' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($badHeader, $databaseBytes)['committed_wal']->frameCount(),
    'bad header committed wal validated flag' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($badHeader, $databaseBytes)['committed_wal']->checksumsValidated,
    'bad header checkpoint absent' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($badHeader, $databaseBytes)['checkpoint_database_page_count'],
];

$expected = [
    'valid status' => 'valid',
    'valid reason' => 'all_frames_valid',
    'valid frame count' => 2,
    'valid committed frame count' => 2,
    'valid committed bytes length' => 1104,
    'valid recovery offset equals committed offset' => true,
    'valid last commit frame' => 2,
    'valid checkpoint page count' => 2,
    'valid checkpoint contains page two' => true,
    'valid dependencies' => ['sqlite-wal-checksum-recovery-boundary', 'sqlite-wal-transaction-recovery-boundary'],
    'valid tail status' => 'recovered_committed_prefix',
    'valid tail reason' => 'uncommitted_valid_tail_after_last_commit',
    'valid tail valid frames' => 4,
    'valid tail committed frames' => 2,
    'valid tail discarded valid frames' => 2,
    'valid tail discarded corrupt frames' => 0,
    'valid tail committed bytes shorter' => true,
    'valid tail committed wal frame count' => 2,
    'valid tail committed wal uncommitted count' => 0,
    'valid tail checkpoint excludes draft' => false,
    'corrupt after commit status' => 'recovered_committed_prefix',
    'corrupt after commit reason' => 'corrupt_tail_after_committed_prefix',
    'corrupt after commit first invalid' => 3,
    'corrupt after commit valid frames' => 2,
    'corrupt after commit committed frames' => 2,
    'corrupt after commit discarded corrupt frames' => 1,
    'corrupt after commit committed bytes length' => 1104,
    'corrupt after commit can checkpoint' => true,
    'corrupt after commit checkpoint contains data' => true,
    'corrupt after commit checkpoint excludes corrupt draft' => false,
    'mixed tail reason' => 'uncommitted_valid_tail_before_corrupt_frame',
    'mixed tail valid frames' => 3,
    'mixed tail committed frames' => 2,
    'mixed tail first invalid' => 4,
    'mixed tail discarded valid frames' => 1,
    'mixed tail discarded corrupt frames' => 1,
    'mixed tail valid bytes contain draft' => true,
    'mixed tail committed bytes omit draft' => false,
    'mixed tail checkpoint excludes draft' => false,
    'mixed tail committed wal frame count' => 2,
    'no commit status' => 'recovered_committed_prefix',
    'no commit reason' => 'no_committed_transaction_in_valid_prefix',
    'no commit committed frames' => 0,
    'no commit discarded valid frames' => 2,
    'no commit checkpoint absent' => null,
    'no commit can checkpoint' => false,
    'no commit committed bytes is header only' => 32,
    'truncated tail reason' => 'uncommitted_valid_tail_before_corrupt_frame',
    'truncated tail first invalid' => 4,
    'truncated tail total slots' => 4,
    'truncated tail valid frames' => 3,
    'truncated tail committed frames' => 2,
    'truncated tail committed offset' => 1104,
    'truncated tail recovery offset' => 1640,
    'truncated tail committed wal parseable' => true,
    'bad header reason' => 'header_checksum_mismatch',
    'bad header status' => 'corrupt',
    'bad header valid frames' => 0,
    'bad header committed frames' => 0,
    'bad header first invalid frame' => 0,
    'bad header committed bytes length' => 32,
    'bad header discarded corrupt slots' => 0,
    'bad header can checkpoint' => false,
    'bad header committed wal frame count' => 0,
    'bad header committed wal validated flag' => true,
    'bad header checkpoint absent' => null,
];

foreach ($cases as $name => $callback) {
    $tests['wal transaction recovery corpus ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
