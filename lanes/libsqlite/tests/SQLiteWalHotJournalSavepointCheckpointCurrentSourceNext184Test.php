<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next184.sqlite';
$walPath = $databasePath . '-wal';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$makeWal = static function (array $frames, int $checkpoint, int $salt1, int $salt2) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
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

$currentWalBytes = $makeWal([
    [1, 0, 'next184 current schema draft'],
    [2, 4, 'next184 current options commit'],
    [3, 0, 'next184 current plugin draft'],
], 184, 0x18400101, 0x18400102);
$nextWalBytes = $makeWal([
    [2, 0, 'next184 next options retry draft'],
    [3, 4, 'next184 next plugin retry commit'],
], 185, 0x18500101, 0x18500102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);
$reopen = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next181',
    'database_path' => $databasePath,
    'wal_path' => $walPath,
    'can_reopen_publish' => true,
    'wal_checksums_validated' => true,
    'wal_checkpoint_sequence' => 185,
    'wal_frame_count' => 2,
    'reopen_digest' => hash('sha256', 'next184-reopen'),
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next181'],
];
$plan = static fn (
    ?array $input = null,
    ?SQLiteWal $current = null,
    ?string $currentBytes = null,
    ?SQLiteWal $next = null,
    ?string $nextBytes = null,
    array $readerPages = [1, 2, 3]
): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next184Plan(
    $input ?? $reopen,
    $current ?? $currentWal,
    $currentBytes ?? $currentWalBytes,
    $next ?? $nextWal,
    $nextBytes ?? $nextWalBytes,
    $readerPages
);

$blockedReopen = $reopen;
$blockedReopen['can_reopen_publish'] = false;
$badDigestReopen = $reopen;
$badDigestReopen['wal_checksums_validated'] = false;
$staleSequenceReopen = $reopen;
$staleSequenceReopen['wal_checkpoint_sequence'] = 184;
$staleCountReopen = $reopen;
$staleCountReopen['wal_frame_count'] = 99;
$sameSaltWalBytes = $makeWal([
    [2, 0, 'next184 same salt options retry draft'],
    [3, 4, 'next184 same salt plugin retry commit'],
], 185, 0x18400101, 0x18400102);
$sameSaltWal = SQLiteWal::parse($sameSaltWalBytes, $pageSize, true);
$sameCheckpointWalBytes = $makeWal([
    [2, 0, 'next184 same checkpoint options retry draft'],
    [3, 4, 'next184 same checkpoint plugin retry commit'],
], 184, 0x18600101, 0x18600102);
$sameCheckpointWal = SQLiteWal::parse($sameCheckpointWalBytes, $pageSize, true);
$sameSourceWalBytes = $makeWal([
    [1, 0, 'next184 current schema draft'],
    [2, 4, 'next184 current options commit'],
    [3, 0, 'next184 current plugin draft'],
], 185, 0x18700101, 0x18700102);
$sameSourceWal = SQLiteWal::parse($sameSourceWalBytes, $pageSize, true);
$uncheckedCurrent = SQLiteWal::parse($currentWalBytes, $pageSize, false);
$uncheckedNext = SQLiteWal::parse($nextWalBytes, $pageSize, false);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next184'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'reopened_next_wal_source_is_distinct_before_reader_marks_are_reused'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $walPath],
    'can reuse reader marks' => [static fn (): mixed => $plan()['can_reuse_reader_marks'], true],
    'current checkpoint sequence' => [static fn (): mixed => $plan()['current_checkpoint_sequence'], 184],
    'next checkpoint sequence' => [static fn (): mixed => $plan()['next_checkpoint_sequence'], 185],
    'checkpoint advanced' => [static fn (): mixed => $plan()['checkpoint_sequence_advanced'], true],
    'current salt pair' => [static fn (): mixed => $plan()['current_salt_pair'], [0x18400101, 0x18400102]],
    'next salt pair' => [static fn (): mixed => $plan()['next_salt_pair'], [0x18500101, 0x18500102]],
    'salt rotated' => [static fn (): mixed => $plan()['salt_pair_rotated'], true],
    'current wal sha' => [static fn (): mixed => $plan()['current_wal_sha256'], hash('sha256', $currentWalBytes)],
    'next wal sha' => [static fn (): mixed => $plan()['next_wal_sha256'], hash('sha256', $nextWalBytes)],
    'current frame count' => [static fn (): mixed => $plan()['current_frame_count'], 3],
    'next frame count' => [static fn (): mixed => $plan()['next_frame_count'], 2],
    'current commit frame' => [static fn (): mixed => $plan()['current_commit_frame'], 2],
    'next commit frame' => [static fn (): mixed => $plan()['next_commit_frame'], 2],
    'reader page numbers' => [static fn (): mixed => $plan()['reader_page_numbers'], [1, 2, 3]],
    'reader current sources' => [static fn (): mixed => $plan()['reader_current_sources'], ['current-wal', 'current-wal', 'current-wal']],
    'reader next sources' => [static fn (): mixed => $plan()['reader_next_sources'], ['checkpoint-database', 'next-wal', 'next-wal']],
    'reader row count' => [static fn (): mixed => count($plan()['reader_rows']), 3],
    'reader row page one current frame' => [static fn (): mixed => $plan()['reader_rows'][0]['current_frame'], 1],
    'reader row page one next frame' => [static fn (): mixed => $plan()['reader_rows'][0]['next_frame'], null],
    'reader row page two current frame' => [static fn (): mixed => $plan()['reader_rows'][1]['current_frame'], 2],
    'reader row page two next frame' => [static fn (): mixed => $plan()['reader_rows'][1]['next_frame'], 1],
    'reader row page three next frame' => [static fn (): mixed => $plan()['reader_rows'][2]['next_frame'], 2],
    'reader row page two separated' => [static fn (): mixed => $plan()['reader_rows'][1]['source_separated'], true],
    'all reader pages separated' => [static fn (): mixed => $plan()['all_reader_pages_separated'], true],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'transition digest length' => [static fn (): mixed => strlen($plan()['source_transition_digest']), 64],
    'dependency next181 carried' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next181', $plan()['dependencies'], true), true],
    'dependency next184' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next184', $plan()['dependencies'], true), true],
    'dependency reader mark separation' => [static fn (): mixed => in_array('sqlite-wal-reader-mark-source-separation-after-reopen', $plan()['dependencies'], true), true],
    'application dependency' => [static fn (): mixed => in_array('application-import-retry-wal-salt-checkpoint-fence', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next178'), true],
    'blocked reopen status' => [static fn (): mixed => $plan($blockedReopen)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next184'],
    'blocked reopen reason' => [static fn (): mixed => $plan($blockedReopen)['blocked_reasons'], ['next181_reopen_not_publishable_for_next_wal_source']],
    'blocked reopen checksum reason' => [static fn (): mixed => $plan($badDigestReopen)['blocked_reasons'], ['next181_reopen_not_publishable_for_next_wal_source']],
    'blocked reopen sequence reason' => [static fn (): mixed => $plan($staleSequenceReopen)['blocked_reasons'], ['next181_reopen_not_publishable_for_next_wal_source']],
    'blocked reopen frame count reason' => [static fn (): mixed => $plan($staleCountReopen)['blocked_reasons'], ['next181_reopen_not_publishable_for_next_wal_source']],
    'same salt status' => [static fn (): mixed => $plan($reopen, null, null, $sameSaltWal, $sameSaltWalBytes)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next184'],
    'same salt reasons' => [static fn (): mixed => $plan($reopen, null, null, $sameSaltWal, $sameSaltWalBytes)['blocked_reasons'], ['next_wal_salt_pair_not_rotated', 'reader_pages_not_separated_from_current_wal_source']],
    'same checkpoint status' => [static fn (): mixed => $plan($reopen, null, null, $sameCheckpointWal, $sameCheckpointWalBytes)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next184'],
    'same checkpoint reasons' => [static fn (): mixed => $plan($reopen, null, null, $sameCheckpointWal, $sameCheckpointWalBytes)['blocked_reasons'], ['next181_reopen_not_publishable_for_next_wal_source', 'next_wal_checkpoint_sequence_not_advanced']],
    'same source status' => [static fn (): mixed => $plan($reopen, null, null, $sameSourceWal, $sameSourceWalBytes)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next184'],
    'same source separated false' => [static fn (): mixed => $plan($reopen, null, null, $sameSourceWal, $sameSourceWalBytes)['all_reader_pages_separated'], false],
    'unchecked current reason' => [static fn (): mixed => $plan($reopen, $uncheckedCurrent)['blocked_reasons'], ['current_wal_checksums_not_validated']],
    'unchecked next reason' => [static fn (): mixed => $plan($reopen, null, null, $uncheckedNext)['blocked_reasons'], ['next_wal_checksums_not_validated']],
    'single reader page' => [static fn (): mixed => $plan(null, null, null, null, null, [2])['reader_page_numbers'], [2]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next184 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing reopen key rejected' => static function () use ($plan, $reopen): void {
        $bad = $reopen;
        unset($bad['wal_path']);
        $plan($bad);
    },
    'empty reader pages rejected' => static fn () => $plan(null, null, null, null, null, []),
    'bad reader page rejected' => static fn () => $plan(null, null, null, null, null, [0]),
    'current bytes mismatch rejected' => static fn () => $plan(null, null, 'bad-current'),
    'next bytes mismatch rejected' => static fn () => $plan(null, null, null, null, 'bad-next'),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next184 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
