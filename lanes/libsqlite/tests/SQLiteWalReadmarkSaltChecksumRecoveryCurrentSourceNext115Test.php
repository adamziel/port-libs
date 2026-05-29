<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalReadmarkSaltChecksumRecoveryCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next115 base schema')
    . $page('next115 base active plugins')
    . $page('next115 base autoload')
    . $page('next115 base plugin settings')
    . $page('next115 base cron');

$makeWal = static function (int $checkpoint, int $salt1, int $salt2, array $frames, ?callable $mutate = null) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as [$pageNumber, $commitPageCount, $label, $frameSalt1, $frameSalt2]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $frameSalt1 ?? $salt1, $frameSalt2 ?? $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $mutate === null ? $bytes : $mutate($bytes);
};

$makeShm = static function (
    int $salt1,
    int $salt2,
    int $change,
    int $mxFrame,
    int $databasePageCount,
    array $readMarks,
    array $readLocks,
    int $backfill,
    int $attempted,
    bool $matchingHeaderCopy = true
) use ($pageSize): SQLiteShmIndex {
    $pageSizeField = (1 << 24) | $pageSize;
    $header = pack(
        'V*',
        3007000,
        $backfill,
        $change,
        $pageSizeField,
        $mxFrame,
        $databasePageCount,
        0x11510001,
        0x11510002,
        $salt1,
        $salt2,
        0x11510003,
        0x11510004
    );
    $headerCopy = $matchingHeaderCopy ? $header : substr_replace($header, pack('V', $change + 1), 8, 4);
    $marks = array_map(static fn (?int $frame): int => $frame ?? 0xffffffff, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);

    return SQLiteShmIndex::parse($header . $headerCopy . $checkpoint);
};

$oldSalt1 = 0x11510011;
$oldSalt2 = 0x11510022;
$newSalt1 = 0x11520011;
$newSalt2 = 0x11520022;

$currentWal = $makeWal(115, $oldSalt1, $oldSalt2, [
    [2, 0, 'next115 current active draft', null, null],
    [3, 5, 'next115 current autoload commit', null, null],
    [4, 0, 'next115 current plugin stale tail', null, null],
]);
$nextCleanWal = $makeWal(116, $newSalt1, $newSalt2, [
    [2, 0, 'next115 next active draft', null, null],
    [4, 5, 'next115 next plugin commit', null, null],
    [5, 0, 'next115 next cron draft', null, null],
    [2, 5, 'next115 next active commit', null, null],
]);
$nextWalWithOldSaltTail = $nextCleanWal . substr($currentWal, 32 + (2 * (24 + $pageSize)));
$currentShm = $makeShm($oldSalt1, $oldSalt2, 115, 3, 5, [0, 2, 3, null, null], [false, true, true, false, false], 1, 2);
$staleNextShm = $makeShm($oldSalt1, $oldSalt2, 115, 3, 5, [0, 2, null, null, null], [false, true, false, false, false], 1, 2);
$freshNextShm = $makeShm($newSalt1, $newSalt2, 116, 4, 5, [0, 2, 4, null, null], [false, true, true, false, false], 1, 3);
$staleHeaderNextShm = $makeShm($newSalt1, $newSalt2, 116, 4, 5, [0, 2, 4, null, null], [false, true, true, false, false], 1, 3, false);
$sameSaltNextWal = $makeWal(117, $oldSalt1, $oldSalt2, [
    [2, 5, 'next115 same salt active commit', null, null],
]);
$sameSaltShm = $makeShm($oldSalt1, $oldSalt2, 117, 1, 5, [0, 1, null, null, null], [false, true, false, false, false], 0, 1);

$rebuilt = static fn (): array => SQLiteWalReadmarkSaltChecksumRecoveryCurrentSourceNextPlan::currentSourceNext(
    $currentWal,
    $currentShm,
    $nextWalWithOldSaltTail,
    $staleNextShm,
    $databaseBytes,
    [1, 2, 3, 4, 5],
    $pageSize
);
$preserved = static fn (): array => SQLiteWalReadmarkSaltChecksumRecoveryCurrentSourceNextPlan::currentSourceNext(
    $currentWal,
    $currentShm,
    $nextCleanWal,
    $freshNextShm,
    $databaseBytes,
    [2, 3, 4, 5],
    $pageSize
);
$staleHeader = static fn (): array => SQLiteWalReadmarkSaltChecksumRecoveryCurrentSourceNextPlan::currentSourceNext(
    $currentWal,
    $currentShm,
    $nextCleanWal,
    $staleHeaderNextShm,
    $databaseBytes,
    [2, 4],
    $pageSize
);
$sameSalt = static fn (): array => SQLiteWalReadmarkSaltChecksumRecoveryCurrentSourceNextPlan::currentSourceNext(
    $currentWal,
    $currentShm,
    $sameSaltNextWal,
    $sameSaltShm,
    $databaseBytes,
    [2, 3],
    $pageSize
);

$cases = [
    'rebuilt status' => [static fn (): mixed => $rebuilt()['status'], 'readmark_salt_rebuilt_next115'],
    'rebuilt reason' => [static fn (): mixed => $rebuilt()['reason'], 'next_generation_shm_salt_rebuilt_after_checksum_recovery'],
    'rebuilt salt changed' => [static fn (): mixed => $rebuilt()['salt_changed'], true],
    'current source status' => [static fn (): mixed => $rebuilt()['current_source']['status'], 'recovered_committed_prefix'],
    'current source reason' => [static fn (): mixed => $rebuilt()['current_source']['reason'], 'uncommitted_valid_tail_after_last_commit'],
    'current source salt' => [static fn (): mixed => $rebuilt()['current_source']['salt'], [$oldSalt1, $oldSalt2]],
    'current committed frame count' => [static fn (): mixed => $rebuilt()['current_source']['committed_frame_count'], 2],
    'current valid frame count' => [static fn (): mixed => $rebuilt()['current_source']['valid_frame_count'], 3],
    'current committed end offset' => [static fn (): mixed => $rebuilt()['current_source']['committed_end_offset'], 32 + (2 * (24 + $pageSize))],
    'next source status' => [static fn (): mixed => $rebuilt()['next_source']['status'], 'recovered_committed_prefix'],
    'next source reason' => [static fn (): mixed => $rebuilt()['next_source']['reason'], 'corrupt_tail_after_committed_prefix'],
    'next source salt' => [static fn (): mixed => $rebuilt()['next_source']['salt'], [$newSalt1, $newSalt2]],
    'next committed frame count' => [static fn (): mixed => $rebuilt()['next_source']['committed_frame_count'], 4],
    'next valid frame count' => [static fn (): mixed => $rebuilt()['next_source']['valid_frame_count'], 4],
    'next total frame slots includes old salt tail' => [static fn (): mixed => $rebuilt()['next_source']['total_frame_slots'], 5],
    'next first invalid frame old salt tail' => [static fn (): mixed => $rebuilt()['next_source']['first_invalid_frame'], 5],
    'next discarded corrupt tail count' => [static fn (): mixed => $rebuilt()['next_source']['discarded_corrupt_tail_frame_count'], 1],
    'next started from current checkpoint' => [static fn (): mixed => $rebuilt()['next_source']['started_from_current_checkpoint'], true],
    'current readmark recovery preserves slot one' => [static fn (): mixed => $rebuilt()['current_source']['readmark_recovery']['preserved_slots'], [1]],
    'current readmark recovery discards stale tail slot' => [static fn (): mixed => $rebuilt()['current_source']['readmark_recovery']['discarded_slots'], [0, 2]],
    'current readmark recovery reason' => [static fn (): mixed => $rebuilt()['current_source']['readmark_recovery']['reason'], 'read_marks_recovered_from_matching_wal'],
    'next readmark recovery rebuilds' => [static fn (): mixed => $rebuilt()['next_source']['readmark_recovery']['status'], 'rebuilt'],
    'next readmark recovery reason' => [static fn (): mixed => $rebuilt()['next_source']['readmark_recovery']['reason'], 'shm_salt_mismatch_rebuilt_from_wal'],
    'next readmark salt mismatch false' => [static fn (): mixed => $rebuilt()['next_source']['readmark_recovery']['salt_matches_wal'], false],
    'current reader end frame pinned at two' => [static fn (): mixed => $rebuilt()['current_reader_end_frame'], 2],
    'next reader end frame falls to latest committed after rebuild' => [static fn (): mixed => $rebuilt()['next_reader_end_frame'], 4],
    'latest next reader end frame' => [static fn (): mixed => $rebuilt()['latest_next_reader_end_frame'], 4],
    'current preserved slots summary' => [static fn (): mixed => $rebuilt()['current_preserved_slots'], [1]],
    'next preserved slots empty' => [static fn (): mixed => $rebuilt()['next_preserved_slots'], []],
    'next discarded slots include stale salt reader' => [static fn (): mixed => $rebuilt()['next_discarded_slots'], [0, 1]],
    'next rebuilt flag' => [static fn (): mixed => $rebuilt()['next_rebuilt_for_salt'], true],
    'current next read marks' => [static fn (): mixed => $rebuilt()['current_next_read_marks'], [null, 2, null, null, null]],
    'next generation read marks reset' => [static fn (): mixed => $rebuilt()['next_generation_read_marks'], [0, null, null, null, null]],
    'current reader sources' => [static fn (): mixed => $rebuilt()['current_reader_sources'], ['database', 'wal', 'wal', 'database', 'database']],
    'next reader sources' => [static fn (): mixed => $rebuilt()['next_reader_sources'], ['database', 'wal', 'database', 'wal', 'wal']],
    'latest next reader sources' => [static fn (): mixed => $rebuilt()['latest_next_reader_sources'], ['database', 'wal', 'database', 'wal', 'wal']],
    'current reader frames' => [static fn (): mixed => $rebuilt()['current_reader_frame_indexes'], [null, 1, 2, null, null]],
    'next reader frames' => [static fn (): mixed => $rebuilt()['next_reader_frame_indexes'], [null, 4, null, 2, 3]],
    'latest next reader frames' => [static fn (): mixed => $rebuilt()['latest_next_reader_frame_indexes'], [null, 4, null, 2, 3]],
    'current reader errors empty' => [static fn (): mixed => $rebuilt()['current_reader_errors'], []],
    'next reader errors empty' => [static fn (): mixed => $rebuilt()['next_reader_errors'], []],
    'latest next errors empty' => [static fn (): mixed => $rebuilt()['latest_next_reader_errors'], []],
    'current page two keeps old active draft' => [static fn (): mixed => str_contains((string) $rebuilt()['current_reader'][1]['image'], 'current active draft'), true],
    'next page two advances to active commit' => [static fn (): mixed => str_contains((string) $rebuilt()['next_reader'][1]['image'], 'next active commit'), true],
    'next page four uses plugin commit' => [static fn (): mixed => str_contains((string) $rebuilt()['next_reader'][3]['image'], 'next plugin commit'), true],
    'next page five sees cron draft before commit frame four' => [static fn (): mixed => str_contains((string) $rebuilt()['next_reader'][4]['image'], 'next cron draft'), true],
    'current reader differs from latest next' => [static fn (): mixed => $rebuilt()['current_reader_keeps_recovered_snapshot'], true],
    'next rebuilt to latest flag' => [static fn (): mixed => $rebuilt()['next_reader_rebuilt_to_database_or_latest'], true],
    'operation one recovers current' => [static fn (): mixed => $rebuilt()['operations'][0]['reason'], 'recover_current_wal_before_preserving_readmarks'],
    'operation two preserves current slot' => [static fn (): mixed => $rebuilt()['operations'][1]['preserved_slots'], [1]],
    'operation three recovers restarted wal' => [static fn (): mixed => $rebuilt()['operations'][2]['reason'], 'recover_restarted_wal_before_next_readmarks'],
    'operation four names salt mismatch' => [static fn (): mixed => $rebuilt()['operations'][3]['reason'], 'shm_salt_mismatch_rebuilt_from_wal'],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-readmark-salt-checksum-recovery-current-source-next115', $rebuilt()['dependencies'], true), true],
    'dependency shm recovery' => [static fn (): mixed => in_array('wal-shm-readmark-recovery', $rebuilt()['dependencies'], true), true],
    'dependency transaction boundary' => [static fn (): mixed => in_array('sqlite-wal-transaction-recovery-boundary', $rebuilt()['dependencies'], true), true],
    'preserved status' => [static fn (): mixed => $preserved()['status'], 'readmark_salt_recovered_next115'],
    'preserved reason' => [static fn (): mixed => $preserved()['reason'], 'current_and_next_generation_readmarks_preserved'],
    'preserved next slots' => [static fn (): mixed => $preserved()['next_preserved_slots'], [1, 2]],
    'preserved next reader end oldest frame' => [static fn (): mixed => $preserved()['next_reader_end_frame'], 2],
    'preserved next reader sources stay pinned' => [static fn (): mixed => $preserved()['next_reader_sources'], ['wal', 'database', 'wal', 'database']],
    'preserved latest next reader sources advance' => [static fn (): mixed => $preserved()['latest_next_reader_sources'], ['wal', 'database', 'wal', 'wal']],
    'preserved next reader frame indexes' => [static fn (): mixed => $preserved()['next_reader_frame_indexes'], [1, null, 2, null]],
    'preserved latest next frame indexes' => [static fn (): mixed => $preserved()['latest_next_reader_frame_indexes'], [4, null, 2, 3]],
    'stale header next rebuilds' => [static fn (): mixed => $staleHeader()['next_source']['readmark_recovery']['reason'], 'stale_shm_header_copy_rebuilt_from_wal'],
    'stale header status recovered not salt rebuilt' => [static fn (): mixed => $staleHeader()['status'], 'readmark_salt_recovered_next115'],
    'same salt reason' => [static fn (): mixed => $sameSalt()['reason'], 'readmark_recovery_same_salt'],
    'same salt changed false' => [static fn (): mixed => $sameSalt()['salt_changed'], false],
    'same salt next reader frame one' => [static fn (): mixed => $sameSalt()['next_reader_frame_indexes'], [1, null]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal readmark salt checksum recovery current source next115 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal readmark salt checksum recovery current source next115 rejects empty pages'] = static function (TestRunner $t) use ($currentWal, $currentShm, $nextCleanWal, $freshNextShm, $databaseBytes, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReadmarkSaltChecksumRecoveryCurrentSourceNextPlan::currentSourceNext($currentWal, $currentShm, $nextCleanWal, $freshNextShm, $databaseBytes, [], $pageSize));
};

$tests['wal readmark salt checksum recovery current source next115 rejects bad page'] = static function (TestRunner $t) use ($currentWal, $currentShm, $nextCleanWal, $freshNextShm, $databaseBytes, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReadmarkSaltChecksumRecoveryCurrentSourceNextPlan::currentSourceNext($currentWal, $currentShm, $nextCleanWal, $freshNextShm, $databaseBytes, [0], $pageSize));
};

$tests['wal readmark salt checksum recovery current source next115 rejects non integer page'] = static function (TestRunner $t) use ($currentWal, $currentShm, $nextCleanWal, $freshNextShm, $databaseBytes, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReadmarkSaltChecksumRecoveryCurrentSourceNextPlan::currentSourceNext($currentWal, $currentShm, $nextCleanWal, $freshNextShm, $databaseBytes, [1, '2'], $pageSize));
};

$tests['wal readmark salt checksum recovery current source next115 rejects corrupt current header'] = static function (TestRunner $t) use ($currentWal, $currentShm, $nextCleanWal, $freshNextShm, $databaseBytes, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReadmarkSaltChecksumRecoveryCurrentSourceNextPlan::currentSourceNext(substr_replace($currentWal, pack('N', 0), 0, 4), $currentShm, $nextCleanWal, $freshNextShm, $databaseBytes, [1], $pageSize));
};

return $tests;
