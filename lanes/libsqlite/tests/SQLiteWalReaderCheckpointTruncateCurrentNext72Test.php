<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('db72-page-1-schema-before') . $page('db72-page-2-option-before') . $page('db72-page-3-index-before') . $page('db72-page-4-meta-before') . $page('db72-page-5-unused-before');
$salt1 = 0x72112233;
$salt2 = 0x72556677;

$makeWal = static function (array $frames) use ($pageSize, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 72, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as [$pageNumber, $commitPageCount, $image]) {
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$wal = SQLiteWal::parse($makeWal([
    [2, 0, $page('wal72-frame-1-siteurl-old')],
    [3, 5, $page('wal72-frame-2-index-first-commit')],
    [2, 0, $page('wal72-frame-3-siteurl-current')],
    [4, 0, $page('wal72-frame-4-plugin-current')],
    [5, 0, $page('wal72-frame-5-meta-current')],
    [3, 5, $page('wal72-frame-6-index-final-commit')],
]), null, true);

$plan = static fn (): array => $wal->checkpointTruncateCurrentNext($databaseBytes, [1, 2, 3, 4, 5]);
$partial = static fn (): array => $wal->checkpointTruncateCurrentNext($databaseBytes, [2, 3, 4], 2);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'truncate-checkpoint-drained-reader-next-database'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'truncate_checkpoint_can_reset_and_truncate_wal'],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'truncate_wal'],
    'wal bytes length' => [static fn (): mixed => $plan()['wal_bytes_length'], 0],
    'current reader frame' => [static fn (): mixed => $plan()['current_reader_end_frame'], 6],
    'next reader frame' => [static fn (): mixed => $plan()['next_reader_end_frame'], 0],
    'database page count' => [static fn (): mixed => $plan()['database_page_count'], 5],
    'final database bytes' => [static fn (): mixed => $plan()['final_database_bytes'], 2560],
    'checkpointed frame count' => [static fn (): mixed => $plan()['checkpointed_frame_count'], 4],
    'committable frame count' => [static fn (): mixed => $plan()['total_committable_frame_count'], 4],
    'remaining committed frames' => [static fn (): mixed => $plan()['remaining_committed_frame_count'], 0],
    'uncommitted frame count' => [static fn (): mixed => $plan()['uncommitted_frame_count'], 0],
    'current sources' => [static fn (): mixed => $plan()['current_sources'], ['database', 'wal', 'wal', 'wal', 'wal']],
    'next sources' => [static fn (): mixed => $plan()['next_sources'], ['database', 'database', 'database', 'database', 'database']],
    'current frame indexes' => [static fn (): mixed => $plan()['current_frame_indexes'], [null, 3, 6, 4, 5]],
    'next frame indexes' => [static fn (): mixed => $plan()['next_frame_indexes'], [null, null, null, null, null]],
    'current errors' => [static fn (): mixed => $plan()['current_errors'], []],
    'next errors' => [static fn (): mixed => $plan()['next_errors'], []],
    'images match' => [static fn (): mixed => $plan()['images_match'], true],
    'next database changed' => [static fn (): mixed => $plan()['next_uses_checkpoint_database'], true],
    'checkpoint not busy' => [static fn (): mixed => $plan()['checkpoint']['busy'], false],
    'checkpoint can truncate' => [static fn (): mixed => $plan()['checkpoint']['can_truncate'], true],
    'checkpoint can reset' => [static fn (): mixed => $plan()['checkpoint']['can_reset'], true],
    'checkpoint mode' => [static fn (): mixed => $plan()['checkpoint']['mode'], 'truncate'],
    'checkpoint wal header removed' => [static fn (): mixed => $plan()['checkpoint']['wal_header'], null],
    'checkpoint dependencies include sidecar' => [static fn (): mixed => in_array('durable-sidecar-write', $plan()['dependencies'], true), true],
    'checkpoint dependencies include slice' => [static fn (): mixed => in_array('sqlite-wal-reader-checkpoint-truncate-current-next72', $plan()['dependencies'], true), true],
    'checkpoint dependencies include drained reader' => [static fn (): mixed => in_array('sqlite-wal-drained-reader-truncate-boundary', $plan()['dependencies'], true), true],
    'page one current database image' => [static fn (): mixed => str_contains($plan()['current_reader'][0]['image'], 'schema-before'), true],
    'page one next database image' => [static fn (): mixed => str_contains($plan()['next_reader'][0]['image'], 'schema-before'), true],
    'page two current wal image' => [static fn (): mixed => str_contains($plan()['current_reader'][1]['image'], 'siteurl-current'), true],
    'page two next checkpoint image' => [static fn (): mixed => str_contains($plan()['next_reader'][1]['image'], 'siteurl-current'), true],
    'page three current wal image' => [static fn (): mixed => str_contains($plan()['current_reader'][2]['image'], 'index-final'), true],
    'page three next checkpoint image' => [static fn (): mixed => str_contains($plan()['next_reader'][2]['image'], 'index-final'), true],
    'page four current wal image' => [static fn (): mixed => str_contains($plan()['current_reader'][3]['image'], 'plugin-current'), true],
    'page four next checkpoint image' => [static fn (): mixed => str_contains($plan()['next_reader'][3]['image'], 'plugin-current'), true],
    'page five current wal image' => [static fn (): mixed => str_contains($plan()['current_reader'][4]['image'], 'meta-current'), true],
    'page five next checkpoint image' => [static fn (): mixed => str_contains($plan()['next_reader'][4]['image'], 'meta-current'), true],
    'checkpoint plan last commit' => [static fn (): mixed => $plan()['checkpoint_plan']['last_commit_frame'], 6],
    'checkpoint plan final bytes' => [static fn (): mixed => $plan()['checkpoint_plan']['final_database_bytes'], 2560],
    'checkpoint plan applied flags' => [static fn (): mixed => array_column($plan()['checkpoint_plan']['frames'], 'applied'), [false, false, true, true, true, true]],
    'checkpoint plan reasons' => [static fn (): mixed => array_column($plan()['checkpoint_plan']['frames'], 'reason'), ['superseded_by_later_committed_frame', 'superseded_by_later_committed_frame', 'checkpointed_to_database', 'checkpointed_to_database', 'checkpointed_to_database', 'checkpointed_to_database']],
    'partial current frame' => [static fn (): mixed => $partial()['current_reader_end_frame'], 2],
    'partial current sources' => [static fn (): mixed => $partial()['current_sources'], ['wal', 'wal', 'database']],
    'partial current frame indexes' => [static fn (): mixed => $partial()['current_frame_indexes'], [1, 2, null]],
    'partial next sources' => [static fn (): mixed => $partial()['next_sources'], ['database', 'database', 'database']],
    'partial images do not match' => [static fn (): mixed => $partial()['images_match'], false],
    'partial checkpoint still truncates' => [static fn (): mixed => $partial()['wal_action'], 'truncate_wal'],
    'partial page two old image' => [static fn (): mixed => str_contains($partial()['current_reader'][0]['image'], 'siteurl-old'), true],
    'partial page two next image' => [static fn (): mixed => str_contains($partial()['next_reader'][0]['image'], 'siteurl-current'), true],
    'partial page four current database image' => [static fn (): mixed => str_contains($partial()['current_reader'][2]['image'], 'meta-before'), true],
    'partial page four next wal checkpoint image' => [static fn (): mixed => str_contains($partial()['next_reader'][2]['image'], 'plugin-current'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal reader checkpoint truncate current next72 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal reader checkpoint truncate current next72 rejects empty page list'] = static function (TestRunner $t) use ($wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointTruncateCurrentNext($databaseBytes, []));
};

$tests['wal reader checkpoint truncate current next72 rejects non integer page'] = static function (TestRunner $t) use ($wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointTruncateCurrentNext($databaseBytes, [2, '3']));
};

$tests['wal reader checkpoint truncate current next72 rejects negative reader frame'] = static function (TestRunner $t) use ($wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointTruncateCurrentNext($databaseBytes, [2], -1));
};

$tests['wal reader checkpoint truncate current next72 rejects reader frame beyond wal'] = static function (TestRunner $t) use ($wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $wal->checkpointTruncateCurrentNext($databaseBytes, [2], 7));
};

return $tests;
