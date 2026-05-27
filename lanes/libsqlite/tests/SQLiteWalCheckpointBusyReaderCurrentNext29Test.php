<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$salt1 = 0x29292929;
$salt2 = 0x71717171;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('db page 1 schema baseline')
    . $page('db page 2 option baseline')
    . $page('db page 3 index baseline');

$makeWal = static function (array $frames, int $checkpointSequence = 29) use ($pageSize, $salt1, $salt2): string {
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpointSequence, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as [$pageNumber, $commitPageCount, $image]) {
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$walBytes = $makeWal([
    [2, 0, $page('frame 1 option draft visible to old reader')],
    [3, 3, $page('frame 2 autoload index committed')],
    [2, 0, $page('frame 3 option later draft')],
    [2, 3, $page('frame 4 option final committed')],
]);
$wal = SQLiteWal::parse($walBytes, null, true);

$fullPinned = static fn (): array => $wal->checkpointBusyReaderCurrentNext($databaseBytes, [1, 2, 3], 'full', 2);
$restartPinned = static fn (): array => $wal->checkpointBusyReaderCurrentNext($databaseBytes, [1, 2, 3], 'restart', 4);
$passivePinned = static fn (): array => $wal->checkpointBusyReaderCurrentNext($databaseBytes, [2, 3], 'passive', 2);

$cases = [
    'full pinned status busy' => [static fn (): mixed => $fullPinned()['status'], 'busy'],
    'full pinned busy flag' => [static fn (): mixed => $fullPinned()['busy'], true],
    'full pinned mode' => [static fn (): mixed => $fullPinned()['mode'], 'full'],
    'full pinned reason' => [static fn (): mixed => $fullPinned()['reason'], 'reader_blocks_checkpoint_completion'],
    'full pinned reader frame' => [static fn (): mixed => $fullPinned()['reader_end_frame'], 2],
    'full pinned current frame' => [static fn (): mixed => $fullPinned()['current_reader_end_frame'], 2],
    'full pinned next frame uses whole wal' => [static fn (): mixed => $fullPinned()['next_reader_end_frame'], 4],
    'full pinned wal preserved' => [static fn (): mixed => $fullPinned()['wal_action'], 'preserve_wal'],
    'full pinned checkpoint count' => [static fn (): mixed => $fullPinned()['checkpoint']['checkpointed_frame_count'], 1],
    'full pinned remaining committed count' => [static fn (): mixed => $fullPinned()['checkpoint']['remaining_committed_frame_count'], 1],
    'full pinned total committable count' => [static fn (): mixed => $fullPinned()['checkpoint']['total_committable_frame_count'], 2],
    'full pinned cannot reset' => [static fn (): mixed => $fullPinned()['checkpoint']['can_reset'], false],
    'full pinned cannot truncate' => [static fn (): mixed => $fullPinned()['checkpoint']['can_truncate'], false],
    'full pinned keeps wal bytes' => [static fn (): mixed => $fullPinned()['checkpoint']['wal_bytes_length'], strlen($walBytes)],
    'full pinned checkpoint database grows to three pages' => [static fn (): mixed => $fullPinned()['checkpoint']['final_database_bytes'], 3 * $pageSize],
    'full pinned current reader sources' => [static fn (): mixed => $fullPinned()['current_reader_sources'], ['database', 'wal', 'wal']],
    'full pinned next reader sources' => [static fn (): mixed => $fullPinned()['next_reader_sources'], ['database', 'wal', 'wal']],
    'full pinned current frame indexes' => [static fn (): mixed => $fullPinned()['current_reader_frame_indexes'], [null, 1, 2]],
    'full pinned next frame indexes' => [static fn (): mixed => $fullPinned()['next_reader_frame_indexes'], [null, 4, 2]],
    'full pinned current page two old draft' => [static fn (): mixed => str_contains($fullPinned()['current_reader'][1]['image'], 'old reader'), true],
    'full pinned next page two final commit' => [static fn (): mixed => str_contains($fullPinned()['next_reader'][1]['image'], 'final committed'), true],
    'full pinned current page three committed' => [static fn (): mixed => str_contains($fullPinned()['current_reader'][2]['image'], 'autoload index committed'), true],
    'full pinned next page three preserved wal' => [static fn (): mixed => str_contains($fullPinned()['next_reader'][2]['image'], 'autoload index committed'), true],
    'full pinned no current errors' => [static fn (): mixed => $fullPinned()['current_reader_errors'], []],
    'full pinned no next errors' => [static fn (): mixed => $fullPinned()['next_reader_errors'], []],
    'full pinned next uses checkpoint db' => [static fn (): mixed => $fullPinned()['next_uses_checkpoint_database'], true],
    'full pinned next uses preserved wal' => [static fn (): mixed => $fullPinned()['next_uses_preserved_wal'], true],
    'full pinned dependency' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-busy-reader-current-next', $fullPinned()['dependencies'], true), true],
    'full pinned reader dependency' => [static fn (): mixed => in_array('wal-reader-current-next-visibility', $fullPinned()['dependencies'], true), true],
    'full pinned checkpoint dependency' => [static fn (): mixed => in_array('sqlite-wal-checkpoint', $fullPinned()['dependencies'], true), true],
    'passive pinned status ready' => [static fn (): mixed => $passivePinned()['status'], 'ready'],
    'passive pinned busy false' => [static fn (): mixed => $passivePinned()['busy'], false],
    'passive pinned reason' => [static fn (): mixed => $passivePinned()['reason'], 'reader_limited_passive_checkpoint'],
    'passive pinned wal preserved' => [static fn (): mixed => $passivePinned()['wal_action'], 'preserve_wal'],
    'passive pinned current sources' => [static fn (): mixed => $passivePinned()['current_reader_sources'], ['wal', 'wal']],
    'passive pinned next sources' => [static fn (): mixed => $passivePinned()['next_reader_sources'], ['wal', 'wal']],
    'passive pinned current frames' => [static fn (): mixed => $passivePinned()['current_reader_frame_indexes'], [1, 2]],
    'passive pinned next frames' => [static fn (): mixed => $passivePinned()['next_reader_frame_indexes'], [4, 2]],
    'restart last-reader status busy' => [static fn (): mixed => $restartPinned()['status'], 'busy'],
    'restart last-reader reason' => [static fn (): mixed => $restartPinned()['reason'], 'reader_blocks_wal_reset'],
    'restart last-reader checkpointed all' => [static fn (): mixed => $restartPinned()['checkpoint']['checkpointed_frame_count'], 2],
    'restart last-reader remaining zero' => [static fn (): mixed => $restartPinned()['checkpoint']['remaining_committed_frame_count'], 0],
    'restart last-reader preserves wal' => [static fn (): mixed => $restartPinned()['wal_action'], 'preserve_wal'],
    'restart last-reader current frames' => [static fn (): mixed => $restartPinned()['current_reader_frame_indexes'], [null, 4, 2]],
    'restart last-reader next frames' => [static fn (): mixed => $restartPinned()['next_reader_frame_indexes'], [null, 4, 2]],
    'restart last-reader current page two final' => [static fn (): mixed => str_contains($restartPinned()['current_reader'][1]['image'], 'final committed'), true],
    'restart last-reader next page two final' => [static fn (): mixed => str_contains($restartPinned()['next_reader'][1]['image'], 'final committed'), true],
    'restart last-reader next uses checkpoint db' => [static fn (): mixed => $restartPinned()['next_uses_checkpoint_database'], true],
    'restart last-reader header not advanced' => [static fn (): mixed => $restartPinned()['checkpoint']['wal_header']['checkpoint_sequence'], 29],
    'restart last-reader wal length preserved' => [static fn (): mixed => $restartPinned()['checkpoint']['wal_bytes_length'], strlen($walBytes)],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal checkpoint busy reader current next29 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal checkpoint busy reader current next29 rejects empty pages'] = static function (TestRunner $t) use ($wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointBusyReaderCurrentNext($databaseBytes, [], 'full', 1));
};

$tests['wal checkpoint busy reader current next29 rejects non integer page'] = static function (TestRunner $t) use ($wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointBusyReaderCurrentNext($databaseBytes, ['2'], 'full', 1));
};

$tests['wal checkpoint busy reader current next29 rejects zero reader frame'] = static function (TestRunner $t) use ($wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointBusyReaderCurrentNext($databaseBytes, [2], 'full', 0));
};

$tests['wal checkpoint busy reader current next29 rejects unsupported mode'] = static function (TestRunner $t) use ($wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointBusyReaderCurrentNext($databaseBytes, [2], 'invalid', 1));
};

return $tests;
