<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerStatementJournalWalSavepointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/wp-content/database/wp-next112.sqlite';
$walPath = $databasePath . '-wal';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$current = [
    1 => $page('next112 current sqlite header'),
    2 => $page('next112 savepoint wp_options root current'),
    3 => $page('next112 failed active_plugins option wal frame'),
    4 => $page('next112 savepoint autoload index current'),
    5 => $page('next112 failed plugin index wal frame'),
    6 => $page('next112 current untouched comments page'),
];
$databaseBytes = implode('', $current);
$savepointFrames = [
    ['frame' => 11, 'page_number' => 2, 'image' => $current[2]],
    ['frame' => 12, 'page_number' => 4, 'image' => $current[4]],
];
$statementBefore = [
    3 => $page('next112 before failed active_plugins option'),
    5 => $page('next112 before failed plugin index'),
];
$statementFrames = [
    ['frame' => 13, 'page_number' => 3, 'image' => $current[3]],
    ['frame' => 14, 'page_number' => 5, 'image' => $current[5], 'commit_frame' => true],
];
$nextBefore = [
    2 => $current[2],
    3 => $statementBefore[3],
    5 => $statementBefore[5],
    7 => str_repeat("\0", $pageSize),
];
$nextFrames = [
    ['frame' => 13, 'page_number' => 2, 'image' => $page('next112 retry keeps savepoint root')],
    ['frame' => 14, 'page_number' => 3, 'image' => $page('next112 retry active_plugins option')],
    ['frame' => 15, 'page_number' => 5, 'image' => $page('next112 retry plugin index')],
    ['frame' => 16, 'page_number' => 7, 'image' => $page('next112 retry overflow leaf append'), 'commit_frame' => true],
];

$plan = static fn (
    array $source = null,
    array $savepointInput = null,
    array $statementBeforeInput = null,
    array $statementInput = null,
    array $nextBeforeInput = null,
    array $nextInput = null,
    bool $release = true,
    string $path = null,
    string $bytes = null,
    int $size = null,
    string $wal = null,
    int $start = 10,
    string $savepointName = 'plugin-batch-next112',
    string $statementName = 'insert-active-plugin-next112',
    string $nextName = 'retry-active-plugin-next112',
): array => SQLitePagerStatementJournalWalSavepointCurrentSourceNextPlan::plan(
    $path ?? $databasePath,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $wal ?? $walPath,
    $start,
    $savepointName,
    $statementName,
    $nextName,
    $source ?? $current,
    $savepointInput ?? $savepointFrames,
    $statementBeforeInput ?? $statementBefore,
    $statementInput ?? $statementFrames,
    $nextBeforeInput ?? $nextBefore,
    $nextInput ?? $nextFrames,
    $release,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager_statement_journal_wal_savepoint_current_source_next112'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'statement_journal_rollback_truncates_failed_wal_frames_before_retry_savepoint_frames'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $walPath],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'wal frame start' => [static fn (): mixed => $plan()['wal_frame_start'], 10],
    'savepoint name' => [static fn (): mixed => $plan()['savepoint'], 'plugin-batch-next112'],
    'statement name' => [static fn (): mixed => $plan()['statement'], 'insert-active-plugin-next112'],
    'next statement name' => [static fn (): mixed => $plan()['next_statement'], 'retry-active-plugin-next112'],
    'release true' => [static fn (): mixed => $plan()['release_savepoint_after_retry'], true],
    'current source verified' => [static fn (): mixed => $plan()['current_source_verified'], true],
    'current source pages sorted' => [static fn (): mixed => $plan()['current_source_page_numbers'], [1, 2, 3, 4, 5, 6]],
    'savepoint frame numbers' => [static fn (): mixed => $plan()['savepoint_wal_frame_numbers'], [11, 12]],
    'statement frame numbers' => [static fn (): mixed => $plan()['statement_wal_frame_numbers'], [13, 14]],
    'discarded frame numbers' => [static fn (): mixed => $plan()['discarded_statement_frame_numbers'], [13, 14]],
    'truncate to retained savepoint frame' => [static fn (): mixed => $plan()['wal_truncate_to_frame'], 12],
    'discarded after retained savepoint frame' => [static fn (): mixed => $plan()['wal_discarded_after_frame'], 12],
    'original frame count' => [static fn (): mixed => $plan()['wal_original_frame_count'], 14],
    'retry frame numbers restart after truncation' => [static fn (): mixed => $plan()['next_statement_wal_frame_numbers'], [13, 14, 15, 16]],
    'statement restored pages' => [static fn (): mixed => $plan()['statement_restored_page_numbers'], [3, 5]],
    'next statement pages' => [static fn (): mixed => $plan()['next_statement_page_numbers'], [2, 3, 5, 7]],
    'release merged pages' => [static fn (): mixed => $plan()['release_merged_page_numbers'], [2, 3, 4, 5, 7]],
    'current prefix page three failed' => [static fn (): mixed => $plan()['current_source_prefixes'][3], 'next112 failed active_plugins option wal frame'],
    'current prefix page five failed' => [static fn (): mixed => $plan()['current_source_prefixes'][5], 'next112 failed plugin index wal frame'],
    'statement before page three' => [static fn (): mixed => $plan()['statement_before_prefixes'][3], 'next112 before failed active_plugins option'],
    'statement before page five' => [static fn (): mixed => $plan()['statement_before_prefixes'][5], 'next112 before failed plugin index'],
    'rollback page three prefix' => [static fn (): mixed => $plan()['statement_rollback_prefixes'][3], 'next112 before failed active_plugins option'],
    'rollback page five prefix' => [static fn (): mixed => $plan()['statement_rollback_prefixes'][5], 'next112 before failed plugin index'],
    'next before root keeps savepoint' => [static fn (): mixed => $plan()['next_statement_before_prefixes'][2], 'next112 savepoint wp_options root current'],
    'next before page seven empty' => [static fn (): mixed => $plan()['next_statement_before_prefixes'][7], ''],
    'reader map page two source' => [static fn (): mixed => $plan()['reader_page_map_after_rollback'][2]['source'], 'current-source'],
    'reader map page three restored' => [static fn (): mixed => $plan()['reader_page_map_after_rollback'][3]['prefix'], 'next112 before failed active_plugins option'],
    'reader map page five restored source' => [static fn (): mixed => $plan()['reader_page_map_after_rollback'][5]['source'], 'statement-journal-before-image'],
    'reader map page seven short read' => [static fn (): mixed => $plan()['reader_page_map_after_rollback'][7]['zero_filled_short_read'], true],
    'rollback bytes restore option before image' => [static fn (): mixed => str_contains($plan()['statement_rollback_database_bytes'], 'next112 before failed active_plugins option'), true],
    'rollback bytes restore index before image' => [static fn (): mixed => str_contains($plan()['statement_rollback_database_bytes'], 'next112 before failed plugin index'), true],
    'rollback bytes exclude failed option frame' => [static fn (): mixed => str_contains($plan()['statement_rollback_database_bytes'], 'next112 failed active_plugins option wal frame'), false],
    'rollback bytes exclude failed index frame' => [static fn (): mixed => str_contains($plan()['statement_rollback_database_bytes'], 'next112 failed plugin index wal frame'), false],
    'rollback bytes keep savepoint root' => [static fn (): mixed => str_contains($plan()['statement_rollback_database_bytes'], 'next112 savepoint wp_options root current'), true],
    'rollback bytes keep untouched comments' => [static fn (): mixed => str_contains($plan()['statement_rollback_database_bytes'], 'next112 current untouched comments page'), true],
    'final bytes include retry root' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next112 retry keeps savepoint root'), true],
    'final bytes include retry option' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next112 retry active_plugins option'), true],
    'final bytes include retry index' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next112 retry plugin index'), true],
    'final bytes include retry append' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next112 retry overflow leaf append'), true],
    'final bytes exclude failed option' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next112 failed active_plugins option wal frame'), false],
    'final bytes exclude failed index' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next112 failed plugin index wal frame'), false],
    'final prefix page one' => [static fn (): mixed => $plan()['final_prefixes'][1], 'next112 current sqlite header'],
    'final prefix page two' => [static fn (): mixed => $plan()['final_prefixes'][2], 'next112 retry keeps savepoint root'],
    'final prefix page three' => [static fn (): mixed => $plan()['final_prefixes'][3], 'next112 retry active_plugins option'],
    'final prefix page four' => [static fn (): mixed => $plan()['final_prefixes'][4], 'next112 savepoint autoload index current'],
    'final prefix page five' => [static fn (): mixed => $plan()['final_prefixes'][5], 'next112 retry plugin index'],
    'final prefix page six' => [static fn (): mixed => $plan()['final_prefixes'][6], 'next112 current untouched comments page'],
    'final prefix page seven' => [static fn (): mixed => $plan()['final_prefixes'][7], 'next112 retry overflow leaf append'],
    'final source page two retry' => [static fn (): mixed => $plan()['final_sources'][2], 'retry-wal-frame'],
    'final source page three retry' => [static fn (): mixed => $plan()['final_sources'][3], 'retry-wal-frame'],
    'final source page four current' => [static fn (): mixed => $plan()['final_sources'][4], 'current-source'],
    'final source page five retry' => [static fn (): mixed => $plan()['final_sources'][5], 'retry-wal-frame'],
    'final source page seven retry' => [static fn (): mixed => $plan()['final_sources'][7], 'retry-wal-frame'],
    'operation count with release' => [static fn (): mixed => count($plan()['operations']), 15],
    'operation retain savepoint first' => [static fn (): mixed => $plan()['operations'][0]['op'], 'retain_savepoint_wal_frame'],
    'operation retain savepoint frame twelve' => [static fn (): mixed => $plan()['operations'][1]['frame'], 12],
    'operation discard failed frame thirteen' => [static fn (): mixed => $plan()['operations'][2]['op'], 'discard_statement_wal_frame'],
    'operation discard failed frame fourteen commit' => [static fn (): mixed => $plan()['operations'][3]['commit_frame'], true],
    'operation restore page three' => [static fn (): mixed => $plan()['operations'][4]['reason'], 'rollback_failed_statement_inside_wal_savepoint'],
    'operation capture retry root' => [static fn (): mixed => $plan()['operations'][6]['op'], 'capture_retry_statement_before_image'],
    'operation capture retry zero append' => [static fn (): mixed => $plan()['operations'][9]['page_number'], 7],
    'operation append retry frame thirteen' => [static fn (): mixed => $plan()['operations'][10]['frame'], 13],
    'operation append retry final commit' => [static fn (): mixed => $plan()['operations'][13]['commit_frame'], true],
    'operation release savepoint' => [static fn (): mixed => $plan()['operations'][14]['op'], 'release_savepoint'],
    'dependency slice' => [static fn (): mixed => in_array('sqlite-pager-statement-journal-wal-savepoint-current-source-next112', $plan()['dependencies'], true), true],
    'dependency wal truncation' => [static fn (): mixed => in_array('sqlite-wal-savepoint-frame-truncation', $plan()['dependencies'], true), true],
    'dependency retry current source' => [static fn (): mixed => in_array('sqlite-retry-wal-frame-current-source', $plan()['dependencies'], true), true],
    'no release operation count' => [static fn (): mixed => count($plan(null, null, null, null, null, null, false)['operations']), 14],
    'no release merged pages empty' => [static fn (): mixed => $plan(null, null, null, null, null, null, false)['release_merged_page_numbers'], []],
    'no release flag false' => [static fn (): mixed => $plan(null, null, null, null, null, null, false)['release_savepoint_after_retry'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager statement journal wal savepoint current source next112 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty path rejected' => static fn () => $plan(null, null, null, null, null, null, true, ''),
    'empty wal path rejected' => static fn () => $plan(null, null, null, null, null, null, true, null, null, null, ''),
    'empty bytes rejected' => static fn () => $plan(null, null, null, null, null, null, true, null, ''),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, null, true, null, null, 500),
    'unaligned bytes rejected' => static fn () => $plan(null, null, null, null, null, null, true, null, $databaseBytes . 'x'),
    'negative wal start rejected' => static fn () => $plan(null, null, null, null, null, null, true, null, null, null, null, -1),
    'empty savepoint name rejected' => static fn () => $plan(null, null, null, null, null, null, true, null, null, null, null, 10, ''),
    'empty statement name rejected' => static fn () => $plan(null, null, null, null, null, null, true, null, null, null, null, 10, 'sp', ''),
    'empty next name rejected' => static fn () => $plan(null, null, null, null, null, null, true, null, null, null, null, 10, 'sp', 'stmt', ''),
    'empty source rejected' => static fn () => $plan([]),
    'empty savepoint frames rejected' => static fn () => $plan(null, []),
    'empty statement before rejected' => static fn () => $plan(null, null, []),
    'empty statement frames rejected' => static fn () => $plan(null, null, null, []),
    'empty next before rejected' => static fn () => $plan(null, null, null, null, []),
    'empty next frames rejected' => static fn () => $plan(null, null, null, null, null, []),
    'zero source page rejected' => static fn () => $plan([0 => $current[1]]),
    'short source page rejected' => static fn () => $plan([1 => 'short']),
    'stale source page rejected' => static fn () => $plan([3 => $statementBefore[3]]),
    'savepoint frame before start rejected' => static fn () => $plan(null, [['frame' => 10, 'page_number' => 2, 'image' => $current[2]]]),
    'savepoint zero page rejected' => static fn () => $plan(null, [['frame' => 11, 'page_number' => 0, 'image' => $current[2]]]),
    'savepoint short image rejected' => static fn () => $plan(null, [['frame' => 11, 'page_number' => 2, 'image' => 'short']]),
    'savepoint frame not current source rejected' => static fn () => $plan(null, [['frame' => 11, 'page_number' => 2, 'image' => $page('next112 stale savepoint frame')]]),
    'statement frame not current source rejected' => static fn () => $plan(null, null, null, [['frame' => 13, 'page_number' => 3, 'image' => $page('next112 stale statement frame')]]),
    'statement frame missing before rejected' => static fn () => $plan(null, null, [3 => $statementBefore[3]], [['frame' => 13, 'page_number' => 5, 'image' => $current[5]]]),
    'retry before stale rejected' => static fn () => $plan(null, null, null, null, [3 => $current[3]]),
    'retry frame sequence rejected' => static fn () => $plan(null, null, null, null, null, [['frame' => 14, 'page_number' => 2, 'image' => $nextFrames[0]['image']]]),
    'retry frame missing before rejected' => static fn () => $plan(null, null, null, null, [2 => $current[2]], [['frame' => 13, 'page_number' => 3, 'image' => $nextFrames[1]['image']]]),
];

foreach ($throws as $name => $callback) {
    $tests['pager statement journal wal savepoint current source next112 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
