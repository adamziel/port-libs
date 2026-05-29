<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalChecksumSaltRecoveryCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next106 base schema page')
    . $page('next106 base wp_options autoload page')
    . $page('next106 base plugin setting page')
    . $page('next106 base transient page');

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

$oldSalt1 = 0x10610001;
$oldSalt2 = 0x10620002;
$newSalt1 = 0x10630003;
$newSalt2 = 0x10640004;
$otherSalt1 = 0x10650005;
$otherSalt2 = 0x10660006;

$currentWal = $makeWal(106, $oldSalt1, $oldSalt2, [
    [1, 0, 'next106 current schema draft', null, null],
    [2, 4, 'next106 current active_plugins commit', null, null],
    [3, 0, 'next106 current stale uncommitted plugin draft', null, null],
]);
$nextCleanWal = $makeWal(107, $newSalt1, $newSalt2, [
    [2, 0, 'next106 restarted active_plugins draft', null, null],
    [3, 4, 'next106 restarted plugin settings commit', null, null],
]);
$oldTail = substr($currentWal, 32 + (2 * (24 + $pageSize)));
$nextWithOldSaltTail = $nextCleanWal . $oldTail;
$nextWithChecksumTail = substr_replace($nextCleanWal, 'X', 32 + 24 + 64, 1);
$sameSaltNextWal = $makeWal(108, $oldSalt1, $oldSalt2, [
    [2, 4, 'next106 same salt active_plugins commit', null, null],
]);
$headerOnlyNextWal = $makeWal(109, $otherSalt1, $otherSalt2, []);

$plan = static fn (): array => SQLiteWalChecksumSaltRecoveryCurrentSourceNextPlan::currentSourceNext($currentWal, $nextWithOldSaltTail, $databaseBytes, [1, 2, 3, 4], $pageSize);
$clean = static fn (): array => SQLiteWalChecksumSaltRecoveryCurrentSourceNextPlan::currentSourceNext($currentWal, $nextCleanWal, $databaseBytes, [2, 3], $pageSize);
$checksumTail = static fn (): array => SQLiteWalChecksumSaltRecoveryCurrentSourceNextPlan::currentSourceNext($currentWal, $nextWithChecksumTail, $databaseBytes, [2, 3], $pageSize);
$sameSalt = static fn (): array => SQLiteWalChecksumSaltRecoveryCurrentSourceNextPlan::currentSourceNext($currentWal, $sameSaltNextWal, $databaseBytes, [2], $pageSize);
$headerOnly = static fn (): array => SQLiteWalChecksumSaltRecoveryCurrentSourceNextPlan::currentSourceNext($currentWal, $headerOnlyNextWal, $databaseBytes, [2, 3], $pageSize);

$cases = [
    'status reports current source salt recovery' => [static fn (): mixed => $plan()['status'], 'current_source_salt_recovered_next106'],
    'reason reports stale salt tail' => [static fn (): mixed => $plan()['reason'], 'next_restarted_wal_discarded_stale_current_source_salt_tail'],
    'salt changed true' => [static fn (): mixed => $plan()['salt_changed'], true],
    'current status recovers committed prefix' => [static fn (): mixed => $plan()['current_source']['status'], 'recovered_committed_prefix'],
    'current reason uncommitted tail' => [static fn (): mixed => $plan()['current_source']['reason'], 'uncommitted_valid_tail_after_last_commit'],
    'current salt pair' => [static fn (): mixed => $plan()['current_source']['salt'], [$oldSalt1, $oldSalt2]],
    'current checkpoint sequence' => [static fn (): mixed => $plan()['current_source']['checkpoint_sequence'], 106],
    'current valid frame count' => [static fn (): mixed => $plan()['current_source']['valid_frame_count'], 3],
    'current committed frame count' => [static fn (): mixed => $plan()['current_source']['committed_frame_count'], 2],
    'current recovery end offset includes uncommitted frame' => [static fn (): mixed => $plan()['current_source']['recovery_end_offset'], 32 + (3 * (24 + $pageSize))],
    'current committed end offset stops at commit' => [static fn (): mixed => $plan()['current_source']['committed_end_offset'], 32 + (2 * (24 + $pageSize))],
    'next database starts from current checkpoint' => [static fn (): mixed => $plan()['current_source']['database_bytes_source'], 'current_checkpoint_database'],
    'next status recovers committed prefix' => [static fn (): mixed => $plan()['next_source']['status'], 'recovered_committed_prefix'],
    'next reason corrupt tail after prefix' => [static fn (): mixed => $plan()['next_source']['reason'], 'corrupt_tail_after_committed_prefix'],
    'next salt pair' => [static fn (): mixed => $plan()['next_source']['salt'], [$newSalt1, $newSalt2]],
    'next checkpoint sequence' => [static fn (): mixed => $plan()['next_source']['checkpoint_sequence'], 107],
    'next valid frame count' => [static fn (): mixed => $plan()['next_source']['valid_frame_count'], 2],
    'next committed frame count' => [static fn (): mixed => $plan()['next_source']['committed_frame_count'], 2],
    'next total frame slots includes old tail' => [static fn (): mixed => $plan()['next_source']['total_frame_slots'], 3],
    'next first invalid frame is old tail' => [static fn (): mixed => $plan()['next_source']['first_invalid_frame'], 3],
    'next discarded corrupt tail count' => [static fn (): mixed => $plan()['next_source']['discarded_corrupt_tail_frame_count'], 1],
    'next discarded valid tail count zero' => [static fn (): mixed => $plan()['next_source']['discarded_valid_tail_frame_count'], 0],
    'next recovery end offset trims old tail' => [static fn (): mixed => $plan()['next_source']['recovery_end_offset'], 32 + (2 * (24 + $pageSize))],
    'next committed end offset matches recovery offset' => [static fn (): mixed => $plan()['next_source']['committed_end_offset'], 32 + (2 * (24 + $pageSize))],
    'next started from current checkpoint true' => [static fn (): mixed => $plan()['next_source']['started_from_current_checkpoint'], true],
    'next checkpointed database true' => [static fn (): mixed => $plan()['next_source']['checkpointed_database'], true],
    'stale salt tail count' => [static fn (): mixed => $plan()['stale_salt_tail_frame_count'], 1],
    'stale salt tail frame index' => [static fn (): mixed => $plan()['stale_salt_tail_frames'][0]['frame_index'], 3],
    'stale salt tail offset' => [static fn (): mixed => $plan()['stale_salt_tail_frames'][0]['offset'], 32 + (2 * (24 + $pageSize))],
    'stale salt tail frame salt' => [static fn (): mixed => $plan()['stale_salt_tail_frames'][0]['frame_salt'], [$oldSalt1, $oldSalt2]],
    'stale salt tail expected salt' => [static fn (): mixed => $plan()['stale_salt_tail_frames'][0]['expected_salt'], [$newSalt1, $newSalt2]],
    'stale salt tail reason' => [static fn (): mixed => $plan()['stale_salt_tail_frames'][0]['reason'], 'stale_current_source_salt'],
    'current reader sources' => [static fn (): mixed => $plan()['current_reader_sources'], ['wal', 'wal', 'database', 'database']],
    'next reader sources' => [static fn (): mixed => $plan()['next_reader_sources'], ['database', 'wal', 'wal', 'database']],
    'current reader frame indexes' => [static fn (): mixed => $plan()['current_reader_frame_indexes'], [1, 2, null, null]],
    'next reader frame indexes' => [static fn (): mixed => $plan()['next_reader_frame_indexes'], [null, 1, 2, null]],
    'current reader errors empty' => [static fn (): mixed => $plan()['current_reader_errors'], []],
    'next reader errors empty' => [static fn (): mixed => $plan()['next_reader_errors'], []],
    'current page two image from current wal' => [static fn (): mixed => str_contains((string) $plan()['current_reader'][1]['image'], 'current active_plugins commit'), true],
    'next page two image from restarted wal' => [static fn (): mixed => str_contains((string) $plan()['next_reader'][1]['image'], 'restarted active_plugins draft'), true],
    'next page three image from restarted commit' => [static fn (): mixed => str_contains((string) $plan()['next_reader'][2]['image'], 'restarted plugin settings commit'), true],
    'next page three excludes current uncommitted draft' => [static fn (): mixed => !str_contains((string) $plan()['next_reader'][2]['image'], 'current stale uncommitted'), true],
    'images changed across current next' => [static fn (): mixed => $plan()['images_changed'], true],
    'first operation recovers current source' => [static fn (): mixed => $plan()['operations'][0]['reason'], 'recover_current_wal_committed_prefix_before_next_source'],
    'second operation discards stale salt' => [static fn (): mixed => $plan()['operations'][1]['reason'], 'discard_stale_salt_tail_after_restart'],
    'third operation compares four pages' => [static fn (): mixed => $plan()['operations'][2]['page_count'], 4],
    'dependency marker exists' => [static fn (): mixed => in_array('sqlite-wal-checksum-salt-recovery-current-source-next106', $plan()['dependencies'], true), true],
    'transaction dependency preserved' => [static fn (): mixed => in_array('sqlite-wal-transaction-recovery-boundary', $plan()['dependencies'], true), true],
    'clean restart reason' => [static fn (): mixed => $clean()['reason'], 'next_restarted_wal_new_salt_from_current_source'],
    'clean restart stale salt count zero' => [static fn (): mixed => $clean()['stale_salt_tail_frame_count'], 0],
    'clean restart operation recovers next prefix' => [static fn (): mixed => $clean()['operations'][1]['reason'], 'recover_next_wal_committed_prefix'],
    'clean restart next sources' => [static fn (): mixed => $clean()['next_reader_sources'], ['wal', 'wal']],
    'checksum tail reason' => [static fn (): mixed => $checksumTail()['reason'], 'next_restarted_wal_discarded_corrupt_tail'],
    'checksum tail stale salt count zero' => [static fn (): mixed => $checksumTail()['stale_salt_tail_frame_count'], 0],
    'checksum tail next valid frame count zero' => [static fn (): mixed => $checksumTail()['next_source']['valid_frame_count'], 0],
    'checksum tail next sources fall back to current checkpoint' => [static fn (): mixed => $checksumTail()['next_reader_sources'], ['database', 'database']],
    'same salt status' => [static fn (): mixed => $sameSalt()['status'], 'current_source_same_salt_next106'],
    'same salt reason' => [static fn (): mixed => $sameSalt()['reason'], 'wal_salt_unchanged_current_source'],
    'same salt changed false' => [static fn (): mixed => $sameSalt()['salt_changed'], false],
    'same salt next frame index' => [static fn (): mixed => $sameSalt()['next_reader_frame_indexes'], [1]],
    'header only next uses current checkpoint' => [static fn (): mixed => $headerOnly()['next_source']['committed_frame_count'], 0],
    'header only next reads current checkpoint page two' => [static fn (): mixed => str_contains((string) $headerOnly()['next_reader'][0]['image'], 'current active_plugins commit'), true],
    'header only next reads base page three' => [static fn (): mixed => str_contains((string) $headerOnly()['next_reader'][1]['image'], 'base plugin setting page'), true],
    'rejects empty page list' => [static fn (): mixed => SQLiteWalChecksumSaltRecoveryCurrentSourceNextPlan::currentSourceNext($currentWal, $nextCleanWal, $databaseBytes, [], $pageSize), InvalidArgumentException::class],
    'rejects zero page' => [static fn (): mixed => SQLiteWalChecksumSaltRecoveryCurrentSourceNextPlan::currentSourceNext($currentWal, $nextCleanWal, $databaseBytes, [0], $pageSize), InvalidArgumentException::class],
    'rejects non integer page' => [static fn (): mixed => SQLiteWalChecksumSaltRecoveryCurrentSourceNextPlan::currentSourceNext($currentWal, $nextCleanWal, $databaseBytes, [1, '2'], $pageSize), InvalidArgumentException::class],
    'rejects bad current wal magic' => [static fn (): mixed => SQLiteWalChecksumSaltRecoveryCurrentSourceNextPlan::currentSourceNext(substr_replace($currentWal, pack('N', 0), 0, 4), $nextCleanWal, $databaseBytes, [1], $pageSize), InvalidArgumentException::class],
];

foreach ($cases as $name => [$case, $expected]) {
    $tests['wal checksum salt recovery current source next106 ' . $name] = static function (TestRunner $t) use ($case, $expected): void {
        if (is_string($expected) && class_exists($expected)) {
            $t->throws($expected, $case);

            return;
        }

        $t->same($expected, $case());
    };
}

return $tests;
