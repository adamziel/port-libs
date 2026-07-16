<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('wp_options page one base') . $page('wp_options page two base') . $page('wp_options page three base');

$makeWal = static function (int $checkpoint, int $salt1, int $salt2, array $frames, ?callable $mutate = null) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as $frame) {
        $image = $page($frame[2]);
        $frameSalt1 = $frame[3] ?? $salt1;
        $frameSalt2 = $frame[4] ?? $salt2;
        $framePrefix = pack('N*', $frame[0], $frame[1], $frameSalt1, $frameSalt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $mutate === null ? $bytes : $mutate($bytes);
};

$oldSalt1 = 0x70110001;
$oldSalt2 = 0x70220002;
$newSalt1 = 0x70110002;
$newSalt2 = 0x70330003;

$currentWal = $makeWal(70, $oldSalt1, $oldSalt2, [
    [1, 0, 'current wal schema frame'],
    [2, 3, 'current active_plugins committed'],
    [3, 0, 'current draft transient ignored'],
]);
$nextWalClean = $makeWal(71, $newSalt1, $newSalt2, [
    [2, 0, 'next restarted active_plugins draft'],
    [3, 3, 'next restarted transient commit'],
]);
$nextWalWithOldTail = $nextWalClean . substr($currentWal, 32 + (24 + $pageSize));
$sameSaltNextWal = $makeWal(72, $oldSalt1, $oldSalt2, [
    [2, 3, 'same salt next active_plugins'],
]);
$nextWalHeaderOnly = $makeWal(73, $newSalt1 + 7, $newSalt2 + 7, []);
$nextWalBadChecksum = substr_replace($nextWalClean, 'X', 32 + 24 + 40, 1);

$saltRecovered = static fn (): array => SQLiteWal::checksumSaltRecoveryCurrentNext($currentWal, $nextWalWithOldTail, $databaseBytes, [1, 2, 3], $pageSize);
$cleanRestart = static fn (): array => SQLiteWal::checksumSaltRecoveryCurrentNext($currentWal, $nextWalClean, $databaseBytes, [2, 3], $pageSize);
$sameSalt = static fn (): array => SQLiteWal::checksumSaltRecoveryCurrentNext($currentWal, $sameSaltNextWal, $databaseBytes, [2], $pageSize);
$headerOnly = static fn (): array => SQLiteWal::checksumSaltRecoveryCurrentNext($currentWal, $nextWalHeaderOnly, $databaseBytes, [2, 3], $pageSize);
$badChecksum = static fn (): array => SQLiteWal::checksumSaltRecoveryCurrentNext($currentWal, $nextWalBadChecksum, $databaseBytes, [2, 3], $pageSize);

$cases = [
    'status reports salt recovery' => [static fn (): mixed => $saltRecovered()['status'], 'salt-recovered-current-next'],
    'reason reports stale old salt tail' => [static fn (): mixed => $saltRecovered()['reason'], 'next_wal_restarted_and_ignored_stale_salt_tail'],
    'salt changed is true' => [static fn (): mixed => $saltRecovered()['salt_changed'], true],
    'current salt pair' => [static fn (): mixed => $saltRecovered()['current_salt'], [$oldSalt1, $oldSalt2]],
    'next salt pair' => [static fn (): mixed => $saltRecovered()['next_salt'], [$newSalt1, $newSalt2]],
    'current valid frames keep draft prefix' => [static fn (): mixed => $saltRecovered()['current_valid_frame_count'], 3],
    'current committed frames stop at commit' => [static fn (): mixed => $saltRecovered()['current_committed_frame_count'], 2],
    'next valid frames stop before old tail' => [static fn (): mixed => $saltRecovered()['next_valid_frame_count'], 2],
    'next committed frames include restart commit' => [static fn (): mixed => $saltRecovered()['next_committed_frame_count'], 2],
    'next discards old salt tail' => [static fn (): mixed => $saltRecovered()['next_discarded_corrupt_tail_frame_count'], 2],
    'current reader end frame uses committed prefix' => [static fn (): mixed => $saltRecovered()['current_reader_end_frame'], 2],
    'next reader end frame uses new committed prefix' => [static fn (): mixed => $saltRecovered()['next_reader_end_frame'], 2],
    'current reader sources' => [static fn (): mixed => $saltRecovered()['current_reader_sources'], ['wal', 'wal', 'database']],
    'next reader sources' => [static fn (): mixed => $saltRecovered()['next_reader_sources'], ['database', 'wal', 'wal']],
    'current frame indexes' => [static fn (): mixed => $saltRecovered()['current_reader_frame_indexes'], [1, 2, null]],
    'next frame indexes' => [static fn (): mixed => $saltRecovered()['next_reader_frame_indexes'], [null, 1, 2]],
    'current reader has no errors' => [static fn (): mixed => $saltRecovered()['current_reader_errors'], []],
    'next reader has no errors' => [static fn (): mixed => $saltRecovered()['next_reader_errors'], []],
    'next uses checkpoint database' => [static fn (): mixed => $saltRecovered()['next_uses_checkpoint_database'], true],
    'visible images changed' => [static fn (): mixed => $saltRecovered()['images_changed'], true],
    'dependency marker exists' => [static fn (): mixed => in_array('sqlite-wal-checksum-salt-recovery-current-next70', $saltRecovered()['dependencies'], true), true],
    'transaction dependency preserved' => [static fn (): mixed => in_array('sqlite-wal-transaction-recovery-boundary', $saltRecovered()['dependencies'], true), true],
    'next reason is corrupt tail after prefix' => [static fn (): mixed => $saltRecovered()['next']['reason'], 'corrupt_tail_after_committed_prefix'],
    'current reason is uncommitted tail' => [static fn (): mixed => $saltRecovered()['current']['reason'], 'uncommitted_valid_tail_after_last_commit'],
    'current page two contains active plugins' => [static fn (): mixed => str_contains((string) $saltRecovered()['current_reader'][1]['image'], 'current active_plugins committed'), true],
    'next page two contains restarted draft' => [static fn (): mixed => str_contains((string) $saltRecovered()['next_reader'][1]['image'], 'next restarted active_plugins draft'), true],
    'next page two excludes old committed value' => [static fn (): mixed => !str_contains((string) $saltRecovered()['next_reader'][1]['image'], 'current active_plugins committed'), true],
    'next page three contains transient commit' => [static fn (): mixed => str_contains((string) $saltRecovered()['next_reader'][2]['image'], 'next restarted transient commit'), true],
    'clean restart reason has no stale tail' => [static fn (): mixed => $cleanRestart()['reason'], 'next_wal_restarted_with_new_salt'],
    'clean restart discards zero corrupt tail' => [static fn (): mixed => $cleanRestart()['next_discarded_corrupt_tail_frame_count'], 0],
    'clean restart next valid frame count' => [static fn (): mixed => $cleanRestart()['next_valid_frame_count'], 2],
    'clean restart current sources' => [static fn (): mixed => $cleanRestart()['current_reader_sources'], ['wal', 'database']],
    'clean restart next sources' => [static fn (): mixed => $cleanRestart()['next_reader_sources'], ['wal', 'wal']],
    'same salt status' => [static fn (): mixed => $sameSalt()['status'], 'same-salt-current-next'],
    'same salt reason' => [static fn (): mixed => $sameSalt()['reason'], 'wal_salt_unchanged'],
    'same salt changed false' => [static fn (): mixed => $sameSalt()['salt_changed'], false],
    'same salt current pair equals next' => [static fn (): mixed => $sameSalt()['current_salt'] === $sameSalt()['next_salt'], true],
    'same salt next source is wal' => [static fn (): mixed => $sameSalt()['next_reader_sources'], ['wal']],
    'same salt next image changes value' => [static fn (): mixed => str_contains((string) $sameSalt()['next_reader'][0]['image'], 'same salt next active_plugins'), true],
    'header only next has zero committed frames' => [static fn (): mixed => $headerOnly()['next_committed_frame_count'], 0],
    'header only next reads checkpoint database page two' => [static fn (): mixed => $headerOnly()['next_reader_sources'], ['database', 'database']],
    'header only next image inherits current checkpoint' => [static fn (): mixed => str_contains((string) $headerOnly()['next_reader'][0]['image'], 'current active_plugins committed'), true],
    'header only next image keeps base page three' => [static fn (): mixed => str_contains((string) $headerOnly()['next_reader'][1]['image'], 'wp_options page three base'), true],
    'bad checksum next status still recovers current next' => [static fn (): mixed => $badChecksum()['status'], 'salt-recovered-current-next'],
    'bad checksum next reason reports stale tail' => [static fn (): mixed => $badChecksum()['reason'], 'next_wal_restarted_and_ignored_stale_salt_tail'],
    'bad checksum next valid frames zero' => [static fn (): mixed => $badChecksum()['next_valid_frame_count'], 0],
    'bad checksum next committed frames zero' => [static fn (): mixed => $badChecksum()['next_committed_frame_count'], 0],
    'bad checksum next discards corrupt frame' => [static fn (): mixed => $badChecksum()['next_discarded_corrupt_tail_frame_count'], 2],
    'bad checksum next reads checkpoint database' => [static fn (): mixed => $badChecksum()['next_reader_sources'], ['database', 'database']],
    'bad checksum excludes corrupted replacement' => [static fn (): mixed => !str_contains((string) $badChecksum()['next_reader'][0]['image'], 'next restarted active_plugins draft'), true],
    'rejects empty page list' => [static fn (): mixed => SQLiteWal::checksumSaltRecoveryCurrentNext($currentWal, $nextWalClean, $databaseBytes, [], $pageSize), InvalidArgumentException::class],
    'rejects non integer page' => [static fn (): mixed => SQLiteWal::checksumSaltRecoveryCurrentNext($currentWal, $nextWalClean, $databaseBytes, [1, '2'], $pageSize), InvalidArgumentException::class],
    'rejects invalid current wal magic' => [static fn (): mixed => SQLiteWal::checksumSaltRecoveryCurrentNext(substr_replace($currentWal, pack('N', 0), 0, 4), $nextWalClean, $databaseBytes, [1], $pageSize), InvalidArgumentException::class],
];

foreach ($cases as $name => [$case, $expected]) {
    $tests['wal checksum salt recovery current next70 ' . $name] = static function (TestRunner $t) use ($case, $expected): void {
        if (is_string($expected) && class_exists($expected)) {
            $t->throws($expected, $case);

            return;
        }

        $t->same($expected, $case());
    };
}

return $tests;
