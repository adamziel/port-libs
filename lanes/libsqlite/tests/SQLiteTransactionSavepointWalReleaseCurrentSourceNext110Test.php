<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$makeWalBytes = static function (int $checkpoint, int $salt1, int $salt2, array $frames) use ($pageSize): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as [$pageNumber, $commit, $image]) {
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$currentWalBytes = $makeWalBytes(110, 0x11011011, 0x22022022, [
    [1, 0, $page('schema draft before plugin release next110')],
    [2, 4, $page('active plugins committed before plugin release next110')],
    [3, 0, $page('plugin options draft inside release next110')],
    [3, 4, $page('plugin options committed inside release next110')],
    [4, 0, $page('transient draft nested release next110')],
    [4, 4, $page('transient committed nested release next110')],
]);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$staleWalBytes = $makeWalBytes(110, 0x11011012, 0x22022022, [
    [1, 0, $page('schema draft stale release next110')],
]);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('application-import');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-batch');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 3, true);
    $stack->savepoint('transient-batch');
    $stack->recordWalFrameWrite(5, 4);
    $stack->recordWalFrameWrite(6, 4, true);

    return $stack;
};

$releaseNext = static fn (): array => $makeStack()->releaseCurrentWalSourceAndAppendFrame(
    'plugin-batch',
    $currentWal,
    $currentWalBytes,
    5,
    true
);

$releaseDraftNext = static fn (): array => $makeStack()->releaseCurrentWalSourceAndAppendFrame(
    'plugin-batch',
    $currentWal,
    $currentWalBytes,
    6,
    false
);

$cases = [
    'released savepoint name' => [static fn (): mixed => $releaseNext()['released_savepoint'], 'plugin-batch'],
    'page size' => [static fn (): mixed => $releaseNext()['page_size'], 512],
    'current source verified' => [static fn (): mixed => $releaseNext()['current_source_verified'], true],
    'wal frame count' => [static fn (): mixed => $releaseNext()['current_wal_frame_count'], 6],
    'checkpoint sequence' => [static fn (): mixed => $releaseNext()['current_wal_checkpoint_sequence'], 110],
    'salt one' => [static fn (): mixed => $releaseNext()['current_wal_salt1'], 0x11011011],
    'salt two' => [static fn (): mixed => $releaseNext()['current_wal_salt2'], 0x22022022],
    'names before release' => [static fn (): mixed => $releaseNext()['names_before_release'], ['application-import', 'plugin-batch', 'transient-batch']],
    'names after release' => [static fn (): mixed => $releaseNext()['names_after_release'], ['application-import']],
    'release plan savepoint' => [static fn (): mixed => $releaseNext()['release_plan']['savepoint'], 'plugin-batch'],
    'release found index' => [static fn (): mixed => $releaseNext()['release_plan']['found_index'], 1],
    'release frame names' => [static fn (): mixed => $releaseNext()['release_plan']['released_frame_names'], ['plugin-batch', 'transient-batch']],
    'release merged page numbers' => [static fn (): mixed => $releaseNext()['release_plan']['merged_page_numbers'], [3, 4]],
    'release target is not transaction' => [static fn (): mixed => $releaseNext()['release_plan']['target_is_transaction'], false],
    'release result depth' => [static fn (): mixed => $releaseNext()['release_plan']['result_depth'], 1],
    'release transaction active' => [static fn (): mixed => $releaseNext()['release_plan']['transaction_active_after'], true],
    'pending pages after release' => [static fn (): mixed => $releaseNext()['pending_page_numbers_after_release'], [1, 2, 3, 4]],
    'pending frames after release' => [static fn (): mixed => $releaseNext()['pending_wal_frame_indexes_after_release'], [1, 2, 3, 4, 5, 6]],
    'next wal start frame' => [static fn (): mixed => $releaseNext()['next_wal_start_frame'], 6],
    'next wal frame index' => [static fn (): mixed => $releaseNext()['next_wal_frame_index'], 7],
    'next page number' => [static fn (): mixed => $releaseNext()['next_page_number'], 5],
    'next commit frame' => [static fn (): mixed => $releaseNext()['next_commit_frame'], true],
    'pending pages after next' => [static fn (): mixed => $releaseNext()['pending_page_numbers_after_next'], [1, 2, 3, 4, 5]],
    'pending frames after next' => [static fn (): mixed => $releaseNext()['pending_wal_frame_indexes_after_next'], [1, 2, 3, 4, 5, 6, 7]],
    'released savepoint inactive' => [static fn (): mixed => $releaseNext()['released_savepoint_active_after'], false],
    'transaction still active' => [static fn (): mixed => $releaseNext()['transaction_active_after'], true],
    'next frame follows released frame prefix' => [static fn (): mixed => $releaseNext()['next_wal_frame_index'] === $releaseNext()['next_wal_start_frame'] + 1, true],
    'release does not drop outer schema frame' => [static fn (): mixed => in_array(1, $releaseNext()['pending_wal_frame_indexes_after_release'], true), true],
    'release does not drop outer commit frame' => [static fn (): mixed => in_array(2, $releaseNext()['pending_wal_frame_indexes_after_release'], true), true],
    'release retains plugin savepoint commit frame' => [static fn (): mixed => in_array(4, $releaseNext()['pending_wal_frame_indexes_after_release'], true), true],
    'release retains nested transient commit frame' => [static fn (): mixed => in_array(6, $releaseNext()['pending_wal_frame_indexes_after_release'], true), true],
    'next committed page becomes pending' => [static fn (): mixed => in_array(5, $releaseNext()['pending_page_numbers_after_next'], true), true],
    'released savepoint absent from names' => [static fn (): mixed => in_array('plugin-batch', $releaseNext()['names_after_release'], true), false],
    'outer transaction remains sole name' => [static fn (): mixed => count($releaseNext()['names_after_release']), 1],
    'draft next page' => [static fn (): mixed => $releaseDraftNext()['next_page_number'], 6],
    'draft next commit flag' => [static fn (): mixed => $releaseDraftNext()['next_commit_frame'], false],
    'draft next pending pages' => [static fn (): mixed => $releaseDraftNext()['pending_page_numbers_after_next'], [1, 2, 3, 4, 6]],
    'draft next pending frames' => [static fn (): mixed => $releaseDraftNext()['pending_wal_frame_indexes_after_next'], [1, 2, 3, 4, 5, 6, 7]],
    'dependency release current source' => [static fn (): mixed => in_array('sqlite-savepoint-release-current-wal-source-next110', $releaseNext()['dependencies'], true), true],
    'dependency next frame' => [static fn (): mixed => in_array('sqlite-wal-release-current-source-next-frame', $releaseNext()['dependencies'], true), true],
    'dependency application import' => [static fn (): mixed => in_array('application-import-release-savepoint-wal-current-source', $releaseNext()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['transaction savepoint wal release current source next110 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['transaction savepoint wal release current source next110 rejects stale wal bytes'] = static function (TestRunner $t) use ($makeStack, $currentWal, $staleWalBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $makeStack()->releaseCurrentWalSourceAndAppendFrame('plugin-batch', $currentWal, $staleWalBytes, 5, true));
};

$tests['transaction savepoint wal release current source next110 rejects empty wal bytes'] = static function (TestRunner $t) use ($makeStack, $currentWal): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $makeStack()->releaseCurrentWalSourceAndAppendFrame('plugin-batch', $currentWal, '', 5, true));
};

$tests['transaction savepoint wal release current source next110 rejects missing savepoint'] = static function (TestRunner $t) use ($makeStack, $currentWal, $currentWalBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $makeStack()->releaseCurrentWalSourceAndAppendFrame('missing', $currentWal, $currentWalBytes, 5, true));
};

$tests['transaction savepoint wal release current source next110 rejects invalid next page'] = static function (TestRunner $t) use ($makeStack, $currentWal, $currentWalBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $makeStack()->releaseCurrentWalSourceAndAppendFrame('plugin-batch', $currentWal, $currentWalBytes, 0, true));
};

return $tests;
