<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerHotJournalSavepointStatementCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/wp-content/database/wp-next97.sqlite';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$dirty = [
    1 => $page('next97 dirty sqlite header after plugin crash'),
    2 => $page('next97 dirty wp_options root after plugin crash'),
    3 => $page('next97 dirty active_plugins after plugin crash'),
    4 => $page('next97 dirty transient timeout after plugin crash'),
    5 => $page('next97 dirty autoload index after plugin crash'),
];
$clean = [
    1 => $page('next97 clean sqlite header before plugin crash'),
    2 => $page('next97 clean wp_options root before plugin crash'),
    4 => $page('next97 clean transient timeout before plugin crash'),
];
$databaseBytes = implode('', $dirty);
$savepointWrite = [
    2 => $page('next97 savepoint rewrites wp_options root'),
    5 => $page('next97 savepoint rewrites autoload index'),
];
$statementBefore = [
    3 => $dirty[3],
    5 => $savepointWrite[5],
];
$statementWrite = [
    3 => $page('next97 failed statement writes active_plugins'),
    5 => $page('next97 failed statement writes autoload index'),
];
$nextBefore = [
    2 => $savepointWrite[2],
    3 => $dirty[3],
    6 => str_repeat("\0", $pageSize),
];
$nextWrite = [
    2 => $page('next97 retry statement keeps root update'),
    3 => $page('next97 retry statement writes active_plugins'),
    6 => $page('next97 retry statement appends option page'),
];

$plan = static fn (
    array $hot = null,
    array $source = null,
    array $savepoint = null,
    array $statementBeforeInput = null,
    array $statementWriteInput = null,
    array $nextBeforeInput = null,
    array $nextWriteInput = null,
    bool $reservedLock = false,
    bool $superRequired = true,
    bool $superExists = true,
    string $path = null,
    string $bytes = null,
    int $size = null,
): array => SQLitePagerHotJournalSavepointStatementCurrentSourceNextPlan::plan(
    $path ?? $databasePath,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    'plugin-batch-next97',
    'insert-active-plugin-next97',
    'retry-active-plugin-next97',
    $hot ?? $clean,
    $source ?? $dirty,
    $savepoint ?? $savepointWrite,
    $statementBeforeInput ?? $statementBefore,
    $statementWriteInput ?? $statementWrite,
    $nextBeforeInput ?? $nextBefore,
    $nextWriteInput ?? $nextWrite,
    $reservedLock,
    $superRequired,
    $superExists,
);

$blocked = static fn (): array => $plan(null, null, null, null, null, null, null, false, true, false);
$reserved = static fn (): array => $plan(null, null, null, null, null, null, null, true, false, false);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager_hot_journal_savepoint_statement_current_source_next97'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'hot_journal_recovery_precedes_savepoint_statement_retry'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $databasePath . '-journal'],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'savepoint' => [static fn (): mixed => $plan()['savepoint'], 'plugin-batch-next97'],
    'statement' => [static fn (): mixed => $plan()['statement'], 'insert-active-plugin-next97'],
    'next statement' => [static fn (): mixed => $plan()['next_statement'], 'retry-active-plugin-next97'],
    'hot recovered' => [static fn (): mixed => $plan()['hot_recovered'], true],
    'reserved false' => [static fn (): mixed => $plan()['reserved_lock'], false],
    'super required' => [static fn (): mixed => $plan()['super_journal_required'], true],
    'super exists' => [static fn (): mixed => $plan()['super_journal_exists'], true],
    'current source verified' => [static fn (): mixed => $plan()['current_source_verified'], true],
    'hot page numbers' => [static fn (): mixed => $plan()['hot_journal_page_numbers'], [1, 2, 4]],
    'current source page numbers' => [static fn (): mixed => $plan()['current_source_page_numbers'], [1, 2, 3, 4, 5]],
    'savepoint captured pages' => [static fn (): mixed => $plan()['savepoint_captured_page_numbers'], [2, 5]],
    'statement restored pages' => [static fn (): mixed => $plan()['statement_restored_page_numbers'], [3, 5]],
    'next statement pages' => [static fn (): mixed => $plan()['next_statement_page_numbers'], [2, 3, 6]],
    'current source prefix page two' => [static fn (): mixed => $plan()['current_source_prefixes'][2], 'next97 dirty wp_options root after plugin crash'],
    'hot prefix page two' => [static fn (): mixed => $plan()['hot_journal_prefixes'][2], 'next97 clean wp_options root before plugin crash'],
    'failed bytes include failed active plugin' => [static fn (): mixed => str_contains($plan()['failed_statement_database_bytes'], 'next97 failed statement writes active_plugins'), true],
    'failed bytes include failed autoload' => [static fn (): mixed => str_contains($plan()['failed_statement_database_bytes'], 'next97 failed statement writes autoload index'), true],
    'rollback bytes restore active plugin dirty source' => [static fn (): mixed => str_contains($plan()['statement_rollback_database_bytes'], 'next97 dirty active_plugins after plugin crash'), true],
    'rollback bytes restore savepoint autoload' => [static fn (): mixed => str_contains($plan()['statement_rollback_database_bytes'], 'next97 savepoint rewrites autoload index'), true],
    'rollback bytes exclude failed active plugin' => [static fn (): mixed => str_contains($plan()['statement_rollback_database_bytes'], 'next97 failed statement writes active_plugins'), false],
    'rollback bytes keep savepoint root' => [static fn (): mixed => str_contains($plan()['statement_rollback_database_bytes'], 'next97 savepoint rewrites wp_options root'), true],
    'final bytes include retry root' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next97 retry statement keeps root update'), true],
    'final bytes include retry active plugin' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next97 retry statement writes active_plugins'), true],
    'final bytes include appended option' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next97 retry statement appends option page'), true],
    'final bytes exclude clean root' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next97 clean wp_options root before plugin crash'), false],
    'final bytes exclude failed autoload' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next97 failed statement writes autoload index'), false],
    'statement rollback prefix page three' => [static fn (): mixed => $plan()['statement_rollback_prefixes'][3], 'next97 dirty active_plugins after plugin crash'],
    'statement rollback prefix page five' => [static fn (): mixed => $plan()['statement_rollback_prefixes'][5], 'next97 savepoint rewrites autoload index'],
    'final page numbers include append' => [static fn (): mixed => $plan()['final_page_numbers'], [1, 2, 3, 4, 5, 6]],
    'final source page one hot' => [static fn (): mixed => $plan()['final_sources'][1], 'hot-journal'],
    'final source page two next' => [static fn (): mixed => $plan()['final_sources'][2], 'next-statement-write'],
    'final source page three next' => [static fn (): mixed => $plan()['final_sources'][3], 'next-statement-write'],
    'final source page four hot' => [static fn (): mixed => $plan()['final_sources'][4], 'hot-journal'],
    'final source page five statement rollback' => [static fn (): mixed => $plan()['final_sources'][5], 'statement-rollback-before-image'],
    'final source page six next' => [static fn (): mixed => $plan()['final_sources'][6], 'next-statement-write'],
    'payload hot exists' => [static fn (): mixed => isset($plan()['payloads'][$databasePath . '#hot-journal-next97']), true],
    'payload failed exists' => [static fn (): mixed => isset($plan()['payloads'][$databasePath . '#failed-statement-next97']), true],
    'payload rollback exists' => [static fn (): mixed => isset($plan()['payloads'][$databasePath . '#statement-rollback-next97']), true],
    'payload next exists' => [static fn (): mixed => isset($plan()['payloads'][$databasePath . '#next-statement-next97']), true],
    'payload hot contains clean transient' => [static fn (): mixed => str_contains($plan()['payloads'][$databasePath . '#hot-journal-next97'], 'next97 clean transient timeout'), true],
    'payload rollback excludes failed statement' => [static fn (): mixed => str_contains($plan()['payloads'][$databasePath . '#statement-rollback-next97'], 'failed statement writes active'), false],
    'operation count' => [static fn (): mixed => count($plan()['operations']), 19],
    'operation first hot restore' => [static fn (): mixed => $plan()['operations'][0]['reason'], 'restore_hot_journal_before_savepoint_statement_current_source'],
    'operation second deletes journal' => [static fn (): mixed => $plan()['operations'][1]['reason'], 'delete_hot_journal_before_savepoint_statement_retry'],
    'operation captures savepoint page two' => [static fn (): mixed => $plan()['operations'][2]['op'], 'capture_savepoint_before_image'],
    'operation writes savepoint page two' => [static fn (): mixed => $plan()['operations'][3]['reason'], 'write_current_savepoint_page_after_hot_journal'],
    'operation captures statement page three' => [static fn (): mixed => $plan()['operations'][6]['op'], 'capture_statement_before_image'],
    'operation failed statement write' => [static fn (): mixed => $plan()['operations'][8]['reason'], 'write_failed_statement_page_under_savepoint'],
    'operation statement rollback' => [static fn (): mixed => $plan()['operations'][10]['reason'], 'rollback_failed_statement_before_retry'],
    'operation next capture' => [static fn (): mixed => $plan()['operations'][12]['op'], 'capture_next_statement_before_image'],
    'operation retry write' => [static fn (): mixed => $plan()['operations'][15]['reason'], 'retry_statement_uses_statement_rollback_current_source'],
    'operation final sync' => [static fn (): mixed => $plan()['operations'][18]['reason'], 'sync_retry_statement_after_hot_journal_statement_recovery'],
    'dependency slice' => [static fn (): mixed => in_array('sqlite-pager-hot-journal-savepoint-statement-current-source-next97', $plan()['dependencies'], true), true],
    'dependency statement rollback' => [static fn (): mixed => in_array('sqlite-statement-journal-rollback-current-source', $plan()['dependencies'], true), true],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'pager_hot_journal_savepoint_statement_current_source_blocked_next97'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'missing_super_journal_preserves_hot_journal_before_statement_retry'],
    'blocked hot recovered false' => [static fn (): mixed => $blocked()['hot_recovered'], false],
    'blocked final page one remains dirty' => [static fn (): mixed => str_contains($blocked()['final_database_bytes'], 'next97 dirty sqlite header after plugin crash'), true],
    'reserved reason' => [static fn (): mixed => $reserved()['reason'], 'reserved_lock_preserves_hot_journal_before_statement_retry'],
    'reserved hot recovered false' => [static fn (): mixed => $reserved()['hot_recovered'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager hot journal savepoint statement current source next97 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty path rejected' => static fn () => $plan(null, null, null, null, null, null, null, false, true, true, ''),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, null, null, false, true, true, null, null, 500),
    'unaligned database rejected' => static fn () => $plan(null, null, null, null, null, null, null, false, true, true, null, $databaseBytes . 'x'),
    'empty hot rejected' => static fn () => $plan([]),
    'empty source rejected' => static fn () => $plan(null, []),
    'empty savepoint writes rejected' => static fn () => $plan(null, null, []),
    'empty statement before rejected' => static fn () => $plan(null, null, null, []),
    'empty statement write rejected' => static fn () => $plan(null, null, null, null, []),
    'empty next before rejected' => static fn () => $plan(null, null, null, null, null, []),
    'empty next write rejected' => static fn () => $plan(null, null, null, null, null, null, []),
    'zero hot page rejected' => static fn () => $plan([0 => $clean[1]]),
    'short source page rejected' => static fn () => $plan(null, [1 => 'short']),
    'stale source rejected' => static fn () => $plan(null, [1 => $clean[1]]),
    'stale statement before rejected' => static fn () => $plan(null, null, null, [3 => $clean[1]]),
    'stale next before rejected' => static fn () => $plan(null, null, null, null, null, [3 => $clean[3] ?? $clean[1]]),
];

foreach ($throws as $name => $callback) {
    $tests['pager hot journal savepoint statement current source next97 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
