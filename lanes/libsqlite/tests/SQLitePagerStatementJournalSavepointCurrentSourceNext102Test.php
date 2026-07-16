<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerStatementJournalSavepointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/wp-content/database/wp-next102.sqlite';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$current = [
    1 => $page('next102 current sqlite header'),
    2 => $page('next102 savepoint wp_options root before failure'),
    3 => $page('next102 failed statement active_plugins option'),
    4 => $page('next102 savepoint autoload index before failure'),
    5 => $page('next102 failed statement plugin index'),
    6 => $page('next102 current untouched comments page'),
];
$databaseBytes = implode('', $current);
$savepointBefore = [
    2 => $page('next102 before savepoint wp_options root'),
    4 => $page('next102 before savepoint autoload index'),
];
$statementBefore = [
    3 => $page('next102 before failed active_plugins option'),
    5 => $page('next102 before failed plugin index'),
];
$statementWrites = [
    3 => $current[3],
    5 => $current[5],
];
$nextBefore = [
    2 => $current[2],
    3 => $statementBefore[3],
    5 => $statementBefore[5],
    7 => str_repeat("\0", $pageSize),
];
$nextWrites = [
    2 => $page('next102 retry keeps savepoint root'),
    3 => $page('next102 retry active_plugins option'),
    5 => $page('next102 retry plugin index'),
    7 => $page('next102 retry overflow leaf append'),
];

$plan = static fn (
    array $source = null,
    array $savepoint = null,
    array $statementBeforeInput = null,
    array $statementWriteInput = null,
    array $nextBeforeInput = null,
    array $nextWriteInput = null,
    bool $release = true,
    string $path = null,
    string $bytes = null,
    int $size = null,
    string $savepointName = 'plugin-batch-next102',
    string $statementName = 'insert-active-plugin-next102',
    string $nextName = 'retry-active-plugin-next102',
): array => SQLitePagerStatementJournalSavepointCurrentSourceNextPlan::plan(
    $path ?? $databasePath,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $savepointName,
    $statementName,
    $nextName,
    $source ?? $current,
    $savepoint ?? $savepointBefore,
    $statementBeforeInput ?? $statementBefore,
    $statementWriteInput ?? $statementWrites,
    $nextBeforeInput ?? $nextBefore,
    $nextWriteInput ?? $nextWrites,
    $release,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager_statement_journal_savepoint_current_source_next102'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'statement_journal_rollback_keeps_active_savepoint_current_source_for_retry'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'savepoint name' => [static fn (): mixed => $plan()['savepoint'], 'plugin-batch-next102'],
    'statement name' => [static fn (): mixed => $plan()['statement'], 'insert-active-plugin-next102'],
    'next statement name' => [static fn (): mixed => $plan()['next_statement'], 'retry-active-plugin-next102'],
    'release flag true' => [static fn (): mixed => $plan()['release_savepoint_after_retry'], true],
    'current source verified' => [static fn (): mixed => $plan()['current_source_verified'], true],
    'current source pages sorted' => [static fn (): mixed => $plan()['current_source_page_numbers'], [1, 2, 3, 4, 5, 6]],
    'savepoint before pages' => [static fn (): mixed => $plan()['savepoint_before_page_numbers'], [2, 4]],
    'statement write pages' => [static fn (): mixed => $plan()['statement_write_page_numbers'], [3, 5]],
    'statement restored pages' => [static fn (): mixed => $plan()['statement_restored_page_numbers'], [3, 5]],
    'next statement pages' => [static fn (): mixed => $plan()['next_statement_page_numbers'], [2, 3, 5, 7]],
    'release merged pages' => [static fn (): mixed => $plan()['release_merged_page_numbers'], [2, 3, 4, 5, 7]],
    'current prefix page three failed' => [static fn (): mixed => $plan()['current_source_prefixes'][3], 'next102 failed statement active_plugins option'],
    'current prefix page five failed' => [static fn (): mixed => $plan()['current_source_prefixes'][5], 'next102 failed statement plugin index'],
    'savepoint before root prefix' => [static fn (): mixed => $plan()['savepoint_before_prefixes'][2], 'next102 before savepoint wp_options root'],
    'statement rollback page three prefix' => [static fn (): mixed => $plan()['statement_rollback_prefixes'][3], 'next102 before failed active_plugins option'],
    'statement rollback page five prefix' => [static fn (): mixed => $plan()['statement_rollback_prefixes'][5], 'next102 before failed plugin index'],
    'next before page two keeps savepoint root' => [static fn (): mixed => $plan()['next_statement_before_prefixes'][2], 'next102 savepoint wp_options root before failure'],
    'next before page seven is empty' => [static fn (): mixed => $plan()['next_statement_before_prefixes'][7], ''],
    'rollback bytes restore active plugin before image' => [static fn (): mixed => str_contains($plan()['statement_rollback_database_bytes'], 'next102 before failed active_plugins option'), true],
    'rollback bytes restore plugin index before image' => [static fn (): mixed => str_contains($plan()['statement_rollback_database_bytes'], 'next102 before failed plugin index'), true],
    'rollback bytes exclude failed active plugin write' => [static fn (): mixed => str_contains($plan()['statement_rollback_database_bytes'], 'next102 failed statement active_plugins option'), false],
    'rollback bytes keep savepoint root' => [static fn (): mixed => str_contains($plan()['statement_rollback_database_bytes'], 'next102 savepoint wp_options root before failure'), true],
    'rollback bytes keep untouched comments page' => [static fn (): mixed => str_contains($plan()['statement_rollback_database_bytes'], 'next102 current untouched comments page'), true],
    'final bytes include retry root' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next102 retry keeps savepoint root'), true],
    'final bytes include retry option' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next102 retry active_plugins option'), true],
    'final bytes include retry index' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next102 retry plugin index'), true],
    'final bytes include append' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next102 retry overflow leaf append'), true],
    'final bytes exclude failed option' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next102 failed statement active_plugins option'), false],
    'final bytes exclude failed index' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next102 failed statement plugin index'), false],
    'final bytes keep untouched comments' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next102 current untouched comments page'), true],
    'final prefix page one current' => [static fn (): mixed => $plan()['final_prefixes'][1], 'next102 current sqlite header'],
    'final prefix page two next' => [static fn (): mixed => $plan()['final_prefixes'][2], 'next102 retry keeps savepoint root'],
    'final prefix page three next' => [static fn (): mixed => $plan()['final_prefixes'][3], 'next102 retry active_plugins option'],
    'final prefix page four savepoint current' => [static fn (): mixed => $plan()['final_prefixes'][4], 'next102 savepoint autoload index before failure'],
    'final prefix page five next' => [static fn (): mixed => $plan()['final_prefixes'][5], 'next102 retry plugin index'],
    'final prefix page six current' => [static fn (): mixed => $plan()['final_prefixes'][6], 'next102 current untouched comments page'],
    'final prefix page seven appended' => [static fn (): mixed => $plan()['final_prefixes'][7], 'next102 retry overflow leaf append'],
    'final source page one current' => [static fn (): mixed => $plan()['final_sources'][1], 'current-database'],
    'final source page two next' => [static fn (): mixed => $plan()['final_sources'][2], 'next-statement-write'],
    'final source page three next' => [static fn (): mixed => $plan()['final_sources'][3], 'next-statement-write'],
    'final source page four current' => [static fn (): mixed => $plan()['final_sources'][4], 'current-database'],
    'final source page five next' => [static fn (): mixed => $plan()['final_sources'][5], 'next-statement-write'],
    'final source page seven next' => [static fn (): mixed => $plan()['final_sources'][7], 'next-statement-write'],
    'operation count with release' => [static fn (): mixed => count($plan()['operations']), 13],
    'operation first verify' => [static fn (): mixed => $plan()['operations'][0]['op'], 'verify_failed_statement_page'],
    'operation second verify page five' => [static fn (): mixed => $plan()['operations'][1]['page_number'], 5],
    'operation restore statement page three' => [static fn (): mixed => $plan()['operations'][2]['reason'], 'rollback_failed_statement_inside_active_savepoint'],
    'operation restore statement page five' => [static fn (): mixed => $plan()['operations'][3]['page_number'], 5],
    'operation capture retry root' => [static fn (): mixed => $plan()['operations'][4]['op'], 'capture_next_statement_before_image'],
    'operation capture retry empty append' => [static fn (): mixed => $plan()['operations'][7]['page_number'], 7],
    'operation retry write root' => [static fn (): mixed => $plan()['operations'][8]['reason'], 'retry_statement_uses_statement_rollback_current_source'],
    'operation retry write append' => [static fn (): mixed => $plan()['operations'][11]['page_number'], 7],
    'operation release savepoint' => [static fn (): mixed => $plan()['operations'][12]['op'], 'release_savepoint'],
    'operation release merge pages' => [static fn (): mixed => $plan()['operations'][12]['merged_page_numbers'], [2, 3, 4, 5, 7]],
    'dependency slice' => [static fn (): mixed => in_array('sqlite-pager-statement-journal-savepoint-current-source-next102', $plan()['dependencies'], true), true],
    'dependency source guard' => [static fn (): mixed => in_array('sqlite-statement-journal-current-source-guard', $plan()['dependencies'], true), true],
    'dependency retry release' => [static fn (): mixed => in_array('sqlite-savepoint-release-after-statement-retry', $plan()['dependencies'], true), true],
    'no release operation count' => [static fn (): mixed => count($plan(null, null, null, null, null, null, false)['operations']), 12],
    'no release merged pages empty' => [static fn (): mixed => $plan(null, null, null, null, null, null, false)['release_merged_page_numbers'], []],
    'no release flag false' => [static fn (): mixed => $plan(null, null, null, null, null, null, false)['release_savepoint_after_retry'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager statement journal savepoint current source next102 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty path rejected' => static fn () => $plan(null, null, null, null, null, null, true, ''),
    'empty bytes rejected' => static fn () => $plan(null, null, null, null, null, null, true, null, ''),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, null, true, null, null, 500),
    'unaligned bytes rejected' => static fn () => $plan(null, null, null, null, null, null, true, null, $databaseBytes . 'x'),
    'empty savepoint name rejected' => static fn () => $plan(null, null, null, null, null, null, true, null, null, null, ''),
    'empty statement name rejected' => static fn () => $plan(null, null, null, null, null, null, true, null, null, null, 'sp', ''),
    'empty next name rejected' => static fn () => $plan(null, null, null, null, null, null, true, null, null, null, 'sp', 'stmt', ''),
    'empty source rejected' => static fn () => $plan([]),
    'empty savepoint before rejected' => static fn () => $plan(null, []),
    'empty statement before rejected' => static fn () => $plan(null, null, []),
    'empty statement writes rejected' => static fn () => $plan(null, null, null, []),
    'empty next before rejected' => static fn () => $plan(null, null, null, null, []),
    'empty next writes rejected' => static fn () => $plan(null, null, null, null, null, []),
    'zero source page rejected' => static fn () => $plan([0 => $current[1]]),
    'short source page rejected' => static fn () => $plan([1 => 'short']),
    'stale source page rejected' => static fn () => $plan([3 => $statementBefore[3]]),
    'statement write outside source rejected' => static fn () => $plan([1 => $current[1], 2 => $current[2]], null, null, [3 => $current[3]]),
    'statement write mismatch rejected' => static fn () => $plan([3 => $current[3]], null, null, [3 => $page('next102 mismatched failed write')]),
    'next before stale rejected' => static fn () => $plan(null, null, null, null, [3 => $current[3]]),
    'next write missing before rejected' => static fn () => $plan(null, null, null, null, [2 => $current[2]], [3 => $nextWrites[3]]),
];

foreach ($throws as $name => $callback) {
    $tests['pager statement journal savepoint current source next102 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
