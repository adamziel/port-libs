<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalReaderSavepointRestartCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next121 schema base')
    . $page('next121 option active_plugins base')
    . $page('next121 autoload index base')
    . $page('next121 plugin settings base')
    . $page('next121 transient cache base');

$makeWalBytes = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x12112101;
    $salt2 = 0x12112102;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 121, $salt1, $salt2);
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

$staleFrames = [
    [1, 0, 'next121 schema retained draft'],
    [2, 5, 'next121 active retained commit'],
    [4, 0, 'next121 plugin stale reader draft'],
    [5, 5, 'next121 transient stale commit'],
    [2, 5, 'next121 active stale tail commit'],
];
$restartedFrames = [
    [1, 0, 'next121 schema retained draft'],
    [2, 5, 'next121 active retained commit'],
    [3, 0, 'next121 autoload restarted writer draft'],
    [4, 5, 'next121 plugin restarted writer commit'],
    [2, 5, 'next121 active restarted writer tail'],
];

$walBytes = $makeWalBytes($staleFrames);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$restartedWalBytes = $makeWalBytes($restartedFrames);
$retainedReaderWalBytes = $makeWalBytes(array_slice($staleFrames, 0, 2));

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-options-import-next121');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings-next121');
    $stack->recordWalFrameWrite(3, 4);
    $stack->recordWalFrameWrite(4, 5, true);
    $stack->recordWalFrameWrite(5, 2, true);

    return $stack;
};

$plan = static function (?int $readerEndFrame = 5, ?string $readerBytes = null, array $pages = [1, 2, 3, 4, 5]) use ($makeStack, $wal, $walBytes, $restartedWalBytes, $databaseBytes): array {
    return SQLiteWalReaderSavepointRestartCurrentSourceNextPlan::plan(
        $makeStack(),
        'plugin-settings-next121',
        $wal,
        $walBytes,
        $readerBytes ?? $walBytes,
        $restartedWalBytes,
        $databaseBytes,
        $pages,
        $readerEndFrame
    );
};

$restart = static fn (): array => $plan();
$retainedReader = static fn (): array => $plan(2, $retainedReaderWalBytes);
$singlePage = static fn (): array => $plan(5, $walBytes, [2]);

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'reader-savepoint-restart-current-source-next121'],
    'savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-settings-next121'],
    'page size' => [static fn (): mixed => $restart()['page_size'], $pageSize],
    'retained frame count' => [static fn (): mixed => $restart()['retained_frame_count'], 2],
    'discarded frame count' => [static fn (): mixed => $restart()['discarded_frame_count'], 3],
    'discarded frame indexes' => [static fn (): mixed => $restart()['discarded_frame_indexes'], [3, 4, 5]],
    'stale reader end frame' => [static fn (): mixed => $restart()['stale_reader_end_frame'], 5],
    'stale reader frame count' => [static fn (): mixed => $restart()['stale_reader_frame_count'], 5],
    'next writer frame count' => [static fn (): mixed => $restart()['next_writer_frame_count'], 5],
    'next writer first frame' => [static fn (): mixed => $restart()['next_writer_first_frame'], 3],
    'next writer frame indexes' => [static fn (): mixed => $restart()['next_writer_frame_indexes'], [3, 4, 5]],
    'next writer page numbers' => [static fn (): mixed => $restart()['next_writer_page_numbers'], [2, 3, 4]],
    'stale tail frame indexes' => [static fn (): mixed => $restart()['stale_reader_tail_frame_indexes'], [3, 4, 5]],
    'stale tail page numbers' => [static fn (): mixed => $restart()['stale_reader_tail_page_numbers'], [2, 4, 5]],
    'retained bytes length' => [static fn (): mixed => $restart()['retained_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'stale bytes length' => [static fn (): mixed => $restart()['stale_reader_wal_bytes_length'], strlen($walBytes)],
    'restarted bytes length' => [static fn (): mixed => $restart()['restarted_wal_bytes_length'], strlen($restartedWalBytes)],
    'restarted extends prefix' => [static fn (): mixed => $restart()['restarted_extends_retained_prefix'], true],
    'reader source mismatch' => [static fn (): mixed => $restart()['reader_source_matches_current'], false],
    'sha length retained' => [static fn (): mixed => strlen($restart()['retained_wal_sha256']), 64],
    'sha length stale' => [static fn (): mixed => strlen($restart()['stale_reader_wal_sha256']), 64],
    'sha length restarted' => [static fn (): mixed => strlen($restart()['restarted_wal_sha256']), 64],
    'retained and stale sha differ' => [static fn (): mixed => $restart()['retained_wal_sha256'] !== $restart()['stale_reader_wal_sha256'], true],
    'stale and restarted sha differ' => [static fn (): mixed => $restart()['stale_reader_wal_sha256'] !== $restart()['restarted_wal_sha256'], true],
    'stale sources' => [static fn (): mixed => $restart()['stale_reader_sources'], ['wal', 'wal', 'database', 'wal', 'wal']],
    'current sources' => [static fn (): mixed => $restart()['current_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'next sources' => [static fn (): mixed => $restart()['next_sources'], ['wal', 'wal', 'wal', 'wal', 'database']],
    'stale frame indexes' => [static fn (): mixed => $restart()['stale_reader_frame_indexes'], [1, 5, null, 3, 4]],
    'current frame indexes' => [static fn (): mixed => $restart()['current_frame_indexes'], [1, 2, null, null, null]],
    'next frame indexes' => [static fn (): mixed => $restart()['next_frame_indexes'], [1, 5, 3, 4, null]],
    'row count' => [static fn (): mixed => count($restart()['rows']), 5],
    'row pages' => [static fn (): mixed => array_column($restart()['rows'], 'page_number'), [1, 2, 3, 4, 5]],
    'source transitions' => [static fn (): mixed => $restart()['source_transitions'], ['wal>wal>wal', 'wal>wal>wal', 'database>database>wal', 'wal>database>wal', 'wal>database>database']],
    'stale pages ignored' => [static fn (): mixed => $restart()['stale_reader_tail_pages_ignored'], [2, 4, 5]],
    'next replaced stale pages' => [static fn (): mixed => $restart()['next_replaced_stale_tail_pages'], [2, 4]],
    'current source verified' => [static fn (): mixed => $restart()['current_source_verified'], true],
    'next writer restarted after prefix' => [static fn (): mixed => $restart()['next_writer_restarted_after_retained_prefix'], true],
    'next writer uses current source' => [static fn (): mixed => $restart()['next_writer_uses_current_source'], true],
    'digest length' => [static fn (): mixed => strlen($restart()['source_digest']), 64],
    'stale label page two' => [static fn (): mixed => str_contains($restart()['rows'][1]['stale_reader_label'], 'active stale tail'), true],
    'current label page two' => [static fn (): mixed => str_contains($restart()['rows'][1]['current_label'], 'active retained commit'), true],
    'next label page two' => [static fn (): mixed => str_contains($restart()['rows'][1]['next_label'], 'active restarted writer tail'), true],
    'page five next label base' => [static fn (): mixed => str_contains($restart()['rows'][4]['next_label'], 'transient cache base'), true],
    'dependency truncation' => [static fn (): mixed => in_array('sqlite-savepoint-wal-byte-truncation', $restart()['dependencies'], true), true],
    'dependency next121' => [static fn (): mixed => in_array('sqlite-wal-reader-savepoint-restart-current-source-next121', $restart()['dependencies'], true), true],
    'retained reader matches current' => [static fn (): mixed => $retainedReader()['reader_source_matches_current'], true],
    'retained reader no stale tail frames' => [static fn (): mixed => $retainedReader()['stale_reader_tail_frame_indexes'], []],
    'retained reader no ignored pages' => [static fn (): mixed => $retainedReader()['stale_reader_tail_pages_ignored'], []],
    'retained reader still sees next writer' => [static fn (): mixed => $retainedReader()['next_writer_frame_indexes'], [3, 4, 5]],
    'single page ignored' => [static fn (): mixed => $singlePage()['stale_reader_tail_pages_ignored'], [2]],
    'single page replaced' => [static fn (): mixed => $singlePage()['next_replaced_stale_tail_pages'], [2]],
    'single page transition' => [static fn (): mixed => $singlePage()['source_transitions'], ['wal>wal>wal']],
];

foreach ($cases as $name => [$actual, $expected]) {
    $tests['wal reader savepoint restart current source next121 ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
}

$tests['wal reader savepoint restart current source next121 rejects empty savepoint'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $restartedWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderSavepointRestartCurrentSourceNextPlan::plan($makeStack(), '', $wal, $walBytes, $walBytes, $restartedWalBytes, $databaseBytes, [1]));
};

$tests['wal reader savepoint restart current source next121 rejects empty reader bytes'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $restartedWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderSavepointRestartCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next121', $wal, $walBytes, '', $restartedWalBytes, $databaseBytes, [1]));
};

$tests['wal reader savepoint restart current source next121 rejects empty pages'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $restartedWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderSavepointRestartCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next121', $wal, $walBytes, $walBytes, $restartedWalBytes, $databaseBytes, []));
};

$tests['wal reader savepoint restart current source next121 rejects mismatched source bytes'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $restartedWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderSavepointRestartCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next121', $wal, $walBytes . 'x', $walBytes, $restartedWalBytes, $databaseBytes, [1]));
};

$tests['wal reader savepoint restart current source next121 rejects non integer page'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $restartedWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderSavepointRestartCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next121', $wal, $walBytes, $walBytes, $restartedWalBytes, $databaseBytes, ['1']));
};

$tests['wal reader savepoint restart current source next121 rejects negative reader frame'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $restartedWalBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderSavepointRestartCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next121', $wal, $walBytes, $walBytes, $restartedWalBytes, $databaseBytes, [1], -1));
};

$tests['wal reader savepoint restart current source next121 rejects non prefix restarted wal'] = static function (TestRunner $t) use ($makeStack, $wal, $walBytes, $databaseBytes, $makeWalBytes, $staleFrames): void {
    $badRestart = $makeWalBytes([
        $staleFrames[0],
        [3, 5, 'next121 wrong retained second frame'],
        [4, 5, 'next121 writer after wrong prefix'],
    ]);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalReaderSavepointRestartCurrentSourceNextPlan::plan($makeStack(), 'plugin-settings-next121', $wal, $walBytes, $walBytes, $badRestart, $databaseBytes, [1]));
};

return $tests;
