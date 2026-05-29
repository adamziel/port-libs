<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerSavepointHotJournalCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databasePath = '/wp-content/database/wp.sqlite';

$dirty = [
    1 => $page('next88 dirty sqlite header from crashed import'),
    2 => $page('next88 dirty wp_options root from crashed import'),
    3 => $page('next88 dirty active_plugins from crashed import'),
    4 => $page('next88 dirty transient timeout from crashed import'),
];
$clean = [
    1 => $page('next88 clean sqlite header before crashed import'),
    2 => $page('next88 clean wp_options root before crashed import'),
    3 => $page('next88 clean active_plugins before crashed import'),
    4 => $page('next88 clean transient before crashed import'),
];
$databaseBytes = implode('', $dirty);

$plan = static fn (): array => SQLitePagerSavepointHotJournalCurrentSourceNextPlan::plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next88',
    [2 => $clean[2], 4 => $clean[4]],
    [2 => $dirty[2], 3 => $dirty[3], 4 => $dirty[4]],
    [
        2 => $page('next88 current write option root'),
        4 => $page('next88 current write transient timeout'),
    ],
    [
        3 => $page('next88 retry write active plugins'),
        5 => $page('next88 retry append new option'),
    ],
    11,
    false,
    true,
    true,
);

$blocked = static fn (): array => SQLitePagerSavepointHotJournalCurrentSourceNextPlan::plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next88',
    [2 => $clean[2]],
    [2 => $dirty[2]],
    [2 => $page('next88 current write option root')],
    [3 => $page('next88 retry active plugins')],
    11,
    false,
    true,
    false,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'hot_journal_recovered_savepoint_current_source_next'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'hot_journal_recovery_precedes_current_savepoint_retry'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $databasePath . '-journal'],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'savepoint' => [static fn (): mixed => $plan()['savepoint'], 'plugin-import-next88'],
    'current epoch' => [static fn (): mixed => $plan()['current_source_epoch'], 11],
    'next epoch' => [static fn (): mixed => $plan()['next_source_epoch'], 12],
    'hot recovered' => [static fn (): mixed => $plan()['hot_recovered'], true],
    'reserved lock false' => [static fn (): mixed => $plan()['reserved_lock'], false],
    'super required' => [static fn (): mixed => $plan()['super_journal_required'], true],
    'super exists' => [static fn (): mixed => $plan()['super_journal_exists'], true],
    'current source verified' => [static fn (): mixed => $plan()['current_source_verified'], true],
    'current source pages sorted' => [static fn (): mixed => $plan()['current_source_page_numbers'], [2, 3, 4]],
    'current source page two prefix' => [static fn (): mixed => $plan()['current_source_prefixes'][2], 'next88 dirty wp_options root from crashed import'],
    'current source page three prefix' => [static fn (): mixed => $plan()['current_source_prefixes'][3], 'next88 dirty active_plugins from crashed import'],
    'current source page four prefix' => [static fn (): mixed => $plan()['current_source_prefixes'][4], 'next88 dirty transient timeout from crashed impo'],
    'hot page numbers' => [static fn (): mixed => $plan()['hot_journal_page_numbers'], [2, 4]],
    'hot page two prefix' => [static fn (): mixed => $plan()['hot_journal_prefixes'][2], 'next88 clean wp_options root before crashed impo'],
    'hot page four prefix' => [static fn (): mixed => $plan()['hot_journal_prefixes'][4], 'next88 clean transient before crashed import'],
    'captured page numbers' => [static fn (): mixed => $plan()['savepoint_captured_page_numbers'], [2, 4]],
    'captured page two source' => [static fn (): mixed => $plan()['savepoint_captured_sources'][2], 'hot-journal'],
    'captured page four source' => [static fn (): mixed => $plan()['savepoint_captured_sources'][4], 'hot-journal'],
    'rollback restored pages' => [static fn (): mixed => $plan()['rollback_restored_page_numbers'], [2, 4]],
    'rolled back page two clean' => [static fn (): mixed => str_contains($plan()['rolled_back_database_bytes'], 'next88 clean wp_options root before crashed import'), true],
    'rolled back page three still dirty' => [static fn (): mixed => str_contains($plan()['rolled_back_database_bytes'], 'next88 dirty active_plugins from crashed import'), true],
    'rolled back page four clean' => [static fn (): mixed => str_contains($plan()['rolled_back_database_bytes'], 'next88 clean transient before crashed import'), true],
    'next written page numbers' => [static fn (): mixed => $plan()['next_written_page_numbers'], [3, 5]],
    'next first captured page' => [static fn (): mixed => $plan()['next_captured_pages'][0]['page_number'], 3],
    'next first captured source database' => [static fn (): mixed => $plan()['next_captured_pages'][0]['source'], 'database'],
    'next first captured epoch current' => [static fn (): mixed => $plan()['next_captured_pages'][0]['epoch'], 11],
    'next first not before image' => [static fn (): mixed => $plan()['next_captured_pages'][0]['matches_current_savepoint_before_image'], false],
    'next first not zero fill' => [static fn (): mixed => $plan()['next_captured_pages'][0]['zero_filled_short_read'], false],
    'next second captured page' => [static fn (): mixed => $plan()['next_captured_pages'][1]['page_number'], 5],
    'next second zero fill' => [static fn (): mixed => $plan()['next_captured_pages'][1]['zero_filled_short_read'], true],
    'next second source zero fill' => [static fn (): mixed => $plan()['next_captured_pages'][1]['source'], 'zero-fill'],
    'next second epoch next' => [static fn (): mixed => $plan()['next_captured_pages'][1]['epoch'], 12],
    'final page numbers include append' => [static fn (): mixed => $plan()['final_page_numbers'], [1, 2, 3, 4, 5]],
    'final page one source database' => [static fn (): mixed => $plan()['final_sources'][1], 'database'],
    'final page two restored before image' => [static fn (): mixed => $plan()['final_sources'][2], 'savepoint-rollback-before-image'],
    'final page three next write' => [static fn (): mixed => $plan()['final_sources'][3], 'next-savepoint-write'],
    'final page four restored before image' => [static fn (): mixed => $plan()['final_sources'][4], 'savepoint-rollback-before-image'],
    'final page five next write' => [static fn (): mixed => $plan()['final_sources'][5], 'next-savepoint-write'],
    'payload hot journal exists' => [static fn (): mixed => isset($plan()['payloads'][$databasePath . '#hot-journal']), true],
    'payload rollback exists' => [static fn (): mixed => isset($plan()['payloads'][$databasePath . '#savepoint-rollback']), true],
    'hot payload contains clean root' => [static fn (): mixed => str_contains($plan()['payloads'][$databasePath . '#hot-journal'], 'clean wp_options root'), true],
    'hot payload excludes dirty root' => [static fn (): mixed => str_contains($plan()['payloads'][$databasePath . '#hot-journal'], 'dirty wp_options root'), false],
    'rollback payload contains clean transient' => [static fn (): mixed => str_contains($plan()['payloads'][$databasePath . '#savepoint-rollback'], 'clean transient before crashed'), true],
    'operation count' => [static fn (): mixed => count($plan()['operations']), 12],
    'operation first restores hot journal' => [static fn (): mixed => $plan()['operations'][0]['reason'], 'restore_hot_journal_before_savepoint_current_source'],
    'operation second deletes journal' => [static fn (): mixed => $plan()['operations'][1]['reason'], 'delete_hot_journal_before_retry_savepoint'],
    'operation captures page two' => [static fn (): mixed => $plan()['operations'][2]['op'], 'capture_savepoint_before_image'],
    'operation writes current page two' => [static fn (): mixed => $plan()['operations'][3]['op'], 'write_current_savepoint_page'],
    'operation restores page two' => [static fn (): mixed => $plan()['operations'][6]['op'], 'restore_savepoint_before_image'],
    'operation captures next retry' => [static fn (): mixed => $plan()['operations'][8]['op'], 'capture_next_savepoint_before_image'],
    'operation writes next retry' => [static fn (): mixed => $plan()['operations'][9]['op'], 'write_next_savepoint_page'],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-savepoint-hot-journal-current-source-next88', $plan()['dependencies'], true), true],
    'dependency hot journal' => [static fn (): mixed => in_array('sqlite-hot-journal-recovery', $plan()['dependencies'], true), true],
    'dependency savepoint rollback' => [static fn (): mixed => in_array('sqlite-savepoint-page-image-rollback', $plan()['dependencies'], true), true],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'hot_journal_blocked_savepoint_current_source_preserved'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'missing_super_journal_blocks_hot_journal_recovery'],
    'blocked hot recovered false' => [static fn (): mixed => $blocked()['hot_recovered'], false],
    'blocked first capture database' => [static fn (): mixed => $blocked()['savepoint_captured_sources'][2], 'database'],
    'blocked no hot payload' => [static fn (): mixed => isset($blocked()['payloads'][$databasePath . '#hot-journal']), false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager savepoint hot journal current source next88 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => SQLitePagerSavepointHotJournalCurrentSourceNextPlan::plan('', $databaseBytes, $pageSize, 's', [1 => $clean[1]], [1 => $dirty[1]], [1 => $clean[1]], [1 => $dirty[1]]),
    'bad page size rejected' => static fn () => SQLitePagerSavepointHotJournalCurrentSourceNextPlan::plan($databasePath, $databaseBytes, 500, 's', [1 => $clean[1]], [1 => $dirty[1]], [1 => $clean[1]], [1 => $dirty[1]]),
    'unaligned database rejected' => static fn () => SQLitePagerSavepointHotJournalCurrentSourceNextPlan::plan($databasePath, $databaseBytes . 'x', $pageSize, 's', [1 => $clean[1]], [1 => $dirty[1]], [1 => $clean[1]], [1 => $dirty[1]]),
    'empty savepoint rejected' => static fn () => SQLitePagerSavepointHotJournalCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $pageSize, '', [1 => $clean[1]], [1 => $dirty[1]], [1 => $clean[1]], [1 => $dirty[1]]),
    'empty hot journal rejected' => static fn () => SQLitePagerSavepointHotJournalCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $pageSize, 's', [], [1 => $dirty[1]], [1 => $clean[1]], [1 => $dirty[1]]),
    'empty source rejected' => static fn () => SQLitePagerSavepointHotJournalCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $pageSize, 's', [1 => $clean[1]], [], [1 => $clean[1]], [1 => $dirty[1]]),
    'empty current write rejected' => static fn () => SQLitePagerSavepointHotJournalCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $pageSize, 's', [1 => $clean[1]], [1 => $dirty[1]], [], [1 => $dirty[1]]),
    'empty next write rejected' => static fn () => SQLitePagerSavepointHotJournalCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $pageSize, 's', [1 => $clean[1]], [1 => $dirty[1]], [1 => $clean[1]], []),
    'zero epoch rejected' => static fn () => SQLitePagerSavepointHotJournalCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $pageSize, 's', [1 => $clean[1]], [1 => $dirty[1]], [1 => $clean[1]], [1 => $dirty[1]], 0),
    'zero hot page rejected' => static fn () => SQLitePagerSavepointHotJournalCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $pageSize, 's', [0 => $clean[1]], [1 => $dirty[1]], [1 => $clean[1]], [1 => $dirty[1]]),
    'short hot page rejected' => static fn () => SQLitePagerSavepointHotJournalCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $pageSize, 's', [1 => 'short'], [1 => $dirty[1]], [1 => $clean[1]], [1 => $dirty[1]]),
    'stale source page rejected' => static fn () => SQLitePagerSavepointHotJournalCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $pageSize, 's', [1 => $clean[1]], [1 => $clean[1]], [1 => $clean[1]], [1 => $dirty[1]]),
];

foreach ($throws as $name => $callback) {
    $tests['pager savepoint hot journal current source next88 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
