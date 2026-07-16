<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('wp_options base schema page') . $page('wp_options base value page');
$salt1 = 0x6b6c6d6e;
$salt2 = 0x7b7c7d7e;

$header = static function (int $checkpoint = 21) use ($pageSize, $salt1, $salt2): array {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($prefix, false);

    return [$prefix . pack('N*', $checksum[0], $checksum[1]), $checksum];
};

$append = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $label, ?int $frameSalt1 = null) use ($pageSize, $salt1, $salt2): string {
    $image = str_pad($label, $pageSize, '.');
    $prefix = pack('N*', $pageNumber, $commit, $frameSalt1 ?? $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($prefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $prefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$build = static function (array $frames, ?callable $mutate = null) use ($header, $append): string {
    [$bytes, $seed] = $header();
    foreach ($frames as $frame) {
        $bytes = $append($bytes, $seed, $frame[0], $frame[1], $frame[2], $frame[3] ?? null);
    }

    return $mutate === null ? $bytes : $mutate($bytes);
};

$mixedTail = $build([
    [1, 0, 'current schema frame before plugin import'],
    [2, 2, 'committed wp_options value before plugin import'],
    [3, 0, 'valid draft plugin setting after commit'],
    [4, 0, 'corrupt draft plugin tail after valid draft'],
], static fn (string $bytes): string => substr_replace($bytes, 'Z', 32 + ((24 + 512) * 3) + 96, 1));

$committedCorruptTail = $build([
    [1, 0, 'current schema frame before corrupt commit'],
    [2, 2, 'committed wp_options value before corrupt commit'],
    [2, 2, 'corrupt committed replacement must not appear'],
], static fn (string $bytes): string => substr_replace($bytes, 'Y', 32 + ((24 + 512) * 2) + 80, 1));

[$oneFrame, $oneSeed] = $header();
$oneFrame = $append($oneFrame, $oneSeed, 1, 1, 'single committed page before salt mismatch');
$saltMismatch = $append($oneFrame, $oneSeed, 2, 2, 'salt mismatched next frame', $salt1 ^ 0x01010101);
$truncated = $oneFrame . substr(pack('N*', 2, 2, $salt1, $salt2, 0, 0) . $page('truncated next frame'), 0, 121);
$badHeader = substr_replace($oneFrame, pack('N', 0), 24, 4);

$cases = [
    'mixed tail status' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [1, 2, 3])['status'],
    'mixed tail reason' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [1, 2, 3])['reason'],
    'mixed tail valid frame count' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [1, 2, 3])['valid_frame_count'],
    'mixed tail committed frame count' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [1, 2, 3])['committed_frame_count'],
    'mixed tail first invalid frame' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [1, 2, 3])['first_invalid_frame'],
    'mixed tail current end frame' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [1, 2, 3])['current_reader_end_frame'],
    'mixed tail next end frame' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [1, 2, 3])['next_reader_end_frame'],
    'mixed tail discarded valid draft count' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [1, 2, 3])['discarded_valid_tail_frame_count'],
    'mixed tail discarded corrupt count' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [1, 2, 3])['discarded_corrupt_tail_frame_count'],
    'mixed tail current sources' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [1, 2, 3])['current_reader_sources'],
    'mixed tail next sources' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [1, 2, 3])['next_reader_sources'],
    'mixed tail current frame indexes' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [1, 2, 3])['current_reader_frame_indexes'],
    'mixed tail next frame indexes' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [1, 2, 3])['next_reader_frame_indexes'],
    'mixed tail hides draft page from current reader' => static fn (): mixed => str_contains(implode("\n", SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [1, 2, 3])['current_reader_errors']), 'beyond the committed database size'),
    'mixed tail hides draft page from next reader' => static fn (): mixed => str_contains(implode("\n", SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [1, 2, 3])['next_reader_errors']), 'beyond the committed database size'),
    'mixed tail images remain stable for visible pages' => static function () use ($mixedTail, $databaseBytes): mixed {
        $boundary = SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [1, 2]);

        return $boundary['images_match'];
    },
    'mixed tail uses checkpoint database for next reader' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [1, 2])['next_uses_checkpoint_database'],
    'mixed tail current image contains committed value' => static fn (): mixed => str_contains((string) SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [2])['current_reader'][0]['image'], 'committed wp_options value'),
    'mixed tail next image contains committed value' => static fn (): mixed => str_contains((string) SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [2])['next_reader'][0]['image'], 'committed wp_options value'),
    'mixed tail next image excludes corrupt draft' => static fn (): mixed => !str_contains((string) SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [2])['next_reader'][0]['image'], 'corrupt draft'),
    'mixed tail dependency marker' => static fn (): mixed => in_array('sqlite-wal-corrupt-recovery-current-next-boundary', SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [1])['dependencies'], true),
    'mixed tail preserves transaction dependency marker' => static fn (): mixed => in_array('sqlite-wal-transaction-recovery-boundary', SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [1])['dependencies'], true),
    'mixed tail total frame slots' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [1])['total_frame_slots'],
    'mixed tail visible page error counts' => static function () use ($mixedTail, $databaseBytes): mixed {
        $boundary = SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [1, 2]);

        return [count($boundary['current_reader_errors']), count($boundary['next_reader_errors'])];
    },
    'mixed tail committed offset before valid offset' => static function () use ($mixedTail, $databaseBytes): mixed {
        $boundary = SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [1]);

        return $boundary['committed_end_offset'] < $boundary['recovery_end_offset'];
    },
    'committed corrupt tail reason' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($committedCorruptTail, $databaseBytes, [1, 2])['reason'],
    'committed corrupt tail valid frames' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($committedCorruptTail, $databaseBytes, [1, 2])['valid_frame_count'],
    'committed corrupt tail committed frames' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($committedCorruptTail, $databaseBytes, [1, 2])['committed_frame_count'],
    'committed corrupt tail has no valid draft discard' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($committedCorruptTail, $databaseBytes, [1, 2])['discarded_valid_tail_frame_count'],
    'committed corrupt tail discards one corrupt frame' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($committedCorruptTail, $databaseBytes, [1, 2])['discarded_corrupt_tail_frame_count'],
    'committed corrupt tail current value' => static fn (): mixed => str_contains((string) SQLiteWal::corruptRecoveryCurrentNextBoundary($committedCorruptTail, $databaseBytes, [2])['current_reader'][0]['image'], 'before corrupt commit'),
    'committed corrupt tail next value' => static fn (): mixed => str_contains((string) SQLiteWal::corruptRecoveryCurrentNextBoundary($committedCorruptTail, $databaseBytes, [2])['next_reader'][0]['image'], 'before corrupt commit'),
    'committed corrupt tail excludes replacement' => static fn (): mixed => !str_contains((string) SQLiteWal::corruptRecoveryCurrentNextBoundary($committedCorruptTail, $databaseBytes, [2])['next_reader'][0]['image'], 'replacement must not appear'),
    'committed corrupt tail images stable' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($committedCorruptTail, $databaseBytes, [1, 2])['images_match'],
    'committed corrupt tail checkpoint database used' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($committedCorruptTail, $databaseBytes, [1, 2])['next_uses_checkpoint_database'],
    'salt mismatch reason' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($saltMismatch, $databaseBytes, [1, 2])['reason'],
    'salt mismatch current sources' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($saltMismatch, $databaseBytes, [1, 2])['current_reader_sources'],
    'salt mismatch next sources' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($saltMismatch, $databaseBytes, [1, 2])['next_reader_sources'],
    'salt mismatch first invalid' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($saltMismatch, $databaseBytes, [1, 2])['first_invalid_frame'],
    'truncated tail reason' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($truncated, $databaseBytes, [1, 2])['reason'],
    'truncated tail total slots' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($truncated, $databaseBytes, [1, 2])['total_frame_slots'],
    'truncated tail first invalid' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($truncated, $databaseBytes, [1, 2])['first_invalid_frame'],
    'bad header status' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($badHeader, $databaseBytes, [1])['status'],
    'bad header reason' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($badHeader, $databaseBytes, [1])['reason'],
    'bad header current source falls back to database' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($badHeader, $databaseBytes, [1])['current_reader_sources'],
    'bad header next source falls back to database' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($badHeader, $databaseBytes, [1])['next_reader_sources'],
    'bad header no checkpoint database' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($badHeader, $databaseBytes, [1])['next_uses_checkpoint_database'],
    'rejects empty page list' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, []),
    'rejects non integer page' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, $databaseBytes, [1, '2']),
    'rejects unaligned database image' => static fn (): mixed => SQLiteWal::corruptRecoveryCurrentNextBoundary($mixedTail, substr($databaseBytes, 1), [1]),
];

$expected = [
    'mixed tail status' => 'recovered_committed_prefix',
    'mixed tail reason' => 'uncommitted_valid_tail_before_corrupt_frame',
    'mixed tail valid frame count' => 3,
    'mixed tail committed frame count' => 2,
    'mixed tail first invalid frame' => 4,
    'mixed tail current end frame' => 3,
    'mixed tail next end frame' => 2,
    'mixed tail discarded valid draft count' => 1,
    'mixed tail discarded corrupt count' => 1,
    'mixed tail current sources' => ['wal', 'wal', 'missing'],
    'mixed tail next sources' => ['wal', 'wal', 'missing'],
    'mixed tail current frame indexes' => [1, 2, null],
    'mixed tail next frame indexes' => [1, 2, null],
    'mixed tail hides draft page from current reader' => true,
    'mixed tail hides draft page from next reader' => true,
    'mixed tail images remain stable for visible pages' => true,
    'mixed tail uses checkpoint database for next reader' => true,
    'mixed tail current image contains committed value' => true,
    'mixed tail next image contains committed value' => true,
    'mixed tail next image excludes corrupt draft' => true,
    'mixed tail dependency marker' => true,
    'mixed tail preserves transaction dependency marker' => true,
    'mixed tail total frame slots' => 4,
    'mixed tail visible page error counts' => [0, 0],
    'mixed tail committed offset before valid offset' => true,
    'committed corrupt tail reason' => 'corrupt_tail_after_committed_prefix',
    'committed corrupt tail valid frames' => 2,
    'committed corrupt tail committed frames' => 2,
    'committed corrupt tail has no valid draft discard' => 0,
    'committed corrupt tail discards one corrupt frame' => 1,
    'committed corrupt tail current value' => true,
    'committed corrupt tail next value' => true,
    'committed corrupt tail excludes replacement' => true,
    'committed corrupt tail images stable' => true,
    'committed corrupt tail checkpoint database used' => true,
    'salt mismatch reason' => 'corrupt_tail_after_committed_prefix',
    'salt mismatch current sources' => ['wal', 'missing'],
    'salt mismatch next sources' => ['wal', 'missing'],
    'salt mismatch first invalid' => 2,
    'truncated tail reason' => 'corrupt_tail_after_committed_prefix',
    'truncated tail total slots' => 2,
    'truncated tail first invalid' => 2,
    'bad header status' => 'corrupt',
    'bad header reason' => 'header_checksum_mismatch',
    'bad header current source falls back to database' => ['database'],
    'bad header next source falls back to database' => ['database'],
    'bad header no checkpoint database' => false,
];

foreach ($cases as $name => $case) {
    $tests["wal corrupt boundary current next21 {$name}"] = static function (TestRunner $t) use ($name, $case, $expected): void {
        if (str_starts_with($name, 'rejects ')) {
            $t->throws(InvalidArgumentException::class, $case);

            return;
        }

        $t->same($expected[$name], $case());
    };
}

return $tests;
