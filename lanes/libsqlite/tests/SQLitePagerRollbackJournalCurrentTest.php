<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerRollbackJournalCurrentPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$nonce = 0x20260529;
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$currentPages = [
    1 => $page('wp_options schema current before rollback journal import'),
    2 => $page('wp_options active_plugins current before rollback journal import'),
    3 => $page('wp_options autoload index current before rollback journal import'),
];
$dirtyPages = [
    2 => $page('wp_options active_plugins after rollback journal import'),
    3 => $page('wp_options autoload index after rollback journal import'),
];
$databaseBytes = implode('', $currentPages);

$journalBytes = static function (array $pages, ?int $initialPageCount = null, ?int $journalPageSize = null, int $nonceValue = 0x20260529) use ($sectorSize, $pageSize): string {
    $journalPageSize ??= $pageSize;
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('NNNNN', count($pages), $nonceValue, $initialPageCount ?? 3, $sectorSize, $journalPageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonceValue));
    }

    return $bytes;
};

$plan = static fn (
    ?array $journalPages = null,
    ?array $dirtyInput = null,
    bool $journalSynced = true,
    bool $reservedLock = false,
    ?string $databaseInput = null,
    ?string $journalInput = null,
): array => SQLitePagerRollbackJournalCurrentPlan::admitCurrentJournal(
    '/srv/www/wp-content/database/.ht.sqlite',
    $databaseInput ?? $databaseBytes,
    $journalInput ?? $journalBytes($journalPages ?? [2 => $currentPages[2], 3 => $currentPages[3]]),
    $dirtyInput ?? $dirtyPages,
    512,
    $journalSynced,
    $reservedLock
);

$admitted = $plan();
$cases = [
    'status admitted' => [static fn (): mixed => $admitted['status'], 'pager_rollback_journal_current_admitted'],
    'reason admitted' => [static fn (): mixed => $admitted['reason'], 'rollback_journal_images_match_current_database_pages'],
    'database path' => [static fn (): mixed => $admitted['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $admitted['journal_path'], $databasePath . '-journal'],
    'page size' => [static fn (): mixed => $admitted['page_size'], $pageSize],
    'database page count' => [static fn (): mixed => $admitted['database_page_count'], 3],
    'journal page count' => [static fn (): mixed => $admitted['journal_page_count'], 2],
    'journal initial page count' => [static fn (): mixed => $admitted['journal_initial_database_page_count'], 3],
    'journal synced' => [static fn (): mixed => $admitted['journal_synced'], true],
    'reserved lock false' => [static fn (): mixed => $admitted['database_reserved_lock'], false],
    'admitted pages' => [static fn (): mixed => $admitted['admitted_pages'], [2, 3]],
    'rejected pages empty' => [static fn (): mixed => $admitted['rejected_pages'], []],
    'blocked reasons empty' => [static fn (): mixed => $admitted['blocked_reasons'], []],
    'page checks count' => [static fn (): mixed => count($admitted['page_checks']), 2],
    'page two current prefix' => [static fn (): mixed => $admitted['page_checks'][0]['current_prefix'], 'wp_options active_plugins current before rollback journal import'],
    'page two journal prefix' => [static fn (): mixed => $admitted['page_checks'][0]['journal_prefix'], 'wp_options active_plugins current before rollback journal import'],
    'page two dirty prefix' => [static fn (): mixed => $admitted['page_checks'][0]['dirty_prefix'], 'wp_options active_plugins after rollback journal import'],
    'page two current journal matches' => [static fn (): mixed => $admitted['page_checks'][0]['current_journal_match'], true],
    'page two dirty changes current' => [static fn (): mixed => $admitted['page_checks'][0]['dirty_changes_current'], true],
    'page two admitted' => [static fn (): mixed => $admitted['page_checks'][0]['admitted'], true],
    'page two no reasons' => [static fn (): mixed => $admitted['page_checks'][0]['reasons'], []],
    'current bytes remain old active plugins' => [static fn (): mixed => str_contains($admitted['current_database_bytes'], 'active_plugins current before'), true],
    'current bytes exclude dirty active plugins' => [static fn (): mixed => str_contains($admitted['current_database_bytes'], 'active_plugins after'), false],
    'next bytes include dirty active plugins' => [static fn (): mixed => str_contains($admitted['next_database_bytes'], 'active_plugins after'), true],
    'next bytes include dirty autoload index' => [static fn (): mixed => str_contains($admitted['next_database_bytes'], 'autoload index after'), true],
    'next bytes preserve schema page' => [static fn (): mixed => str_contains($admitted['next_database_bytes'], 'schema current before'), true],
    'operation count' => [static fn (): mixed => count($admitted['operations']), 3],
    'operation zero admission' => [static fn (): mixed => $admitted['operations'][0]['op'], 'admit_rollback_journal_current_source'],
    'operation zero reason' => [static fn (): mixed => $admitted['operations'][0]['reason'], 'all_dirty_pages_have_current_rollback_images'],
    'operation one writes page two' => [static fn (): mixed => $admitted['operations'][1]['reason'], 'write_dirty_page_after_current_journal_admission_2'],
    'operation two writes page three' => [static fn (): mixed => $admitted['operations'][2]['reason'], 'write_dirty_page_after_current_journal_admission_3'],
    'operation one offset' => [static fn (): mixed => $admitted['operations'][1]['offset'], 512],
    'operation two offset' => [static fn (): mixed => $admitted['operations'][2]['offset'], 1024],
    'dependency current' => [static fn (): mixed => in_array('sqlite-pager-rollback-journal-current', $admitted['dependencies'], true), true],
    'dependency checksum' => [static fn (): mixed => in_array('sqlite-rollback-journal-checksum-validation', $admitted['dependencies'], true), true],
    'non overlap note' => [static fn (): mixed => str_contains($admitted['non_overlap'], 'does not repeat rollback commit apply'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager rollback journal current ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$stale = $plan([2 => $page('stale active_plugins image'), 3 => $currentPages[3]]);
$tests['pager rollback journal current blocks stale page image'] = static function (TestRunner $t) use ($stale): void {
    $t->same('pager_rollback_journal_current_blocked', $stale['status']);
    $t->same(['some_dirty_pages_lack_current_rollback_images'], $stale['blocked_reasons']);
    $t->same(['rollback_image_not_from_current_database_page'], $stale['rejected_pages'][2]);
    $t->same(false, $stale['page_checks'][0]['current_journal_match']);
    $t->same($stale['current_database_bytes'], $stale['next_database_bytes']);
    $t->same('preserve_current_reader', $stale['operations'][0]['op']);
};

$missing = $plan([2 => $currentPages[2]]);
$tests['pager rollback journal current blocks missing dirty page journal image'] = static function (TestRunner $t) use ($missing): void {
    $t->same('pager_rollback_journal_current_blocked', $missing['status']);
    $t->same([2], $missing['admitted_pages']);
    $t->same(['missing_current_rollback_image'], $missing['rejected_pages'][3]);
    $t->same(null, $missing['page_checks'][1]['journal_prefix']);
    $t->same(['some_dirty_pages_lack_current_rollback_images'], $missing['blocked_reasons']);
};

$unchanged = $plan(null, [2 => $currentPages[2], 3 => $dirtyPages[3]]);
$tests['pager rollback journal current rejects dirty page matching current image'] = static function (TestRunner $t) use ($unchanged): void {
    $t->same('pager_rollback_journal_current_blocked', $unchanged['status']);
    $t->same(['dirty_page_matches_current_database_page'], $unchanged['rejected_pages'][2]);
    $t->same(false, $unchanged['page_checks'][0]['dirty_changes_current']);
};

$unsynced = $plan(null, null, false);
$tests['pager rollback journal current blocks unsynced journal'] = static function (TestRunner $t) use ($unsynced): void {
    $t->same('pager_rollback_journal_current_blocked', $unsynced['status']);
    $t->same(['rollback_journal_not_synced'], $unsynced['blocked_reasons']);
    $t->same([2, 3], $unsynced['admitted_pages']);
};

$reserved = $plan(null, null, true, true);
$tests['pager rollback journal current blocks reserved lock'] = static function (TestRunner $t) use ($reserved): void {
    $t->same('pager_rollback_journal_current_blocked', $reserved['status']);
    $t->same(['database_reserved_lock_held_by_other_writer'], $reserved['blocked_reasons']);
    $t->same(true, $reserved['database_reserved_lock']);
};

$tests['pager rollback journal current rejects checksum mismatch'] = static function (TestRunner $t) use ($journalBytes, $currentPages, $databaseBytes, $dirtyPages): void {
    $bytes = $journalBytes([2 => $currentPages[2], 3 => $currentPages[3]]);
    $badBytes = substr_replace($bytes, pack('N', 0), -4);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerRollbackJournalCurrentPlan::admitCurrentJournal('/tmp/wp.sqlite', $databaseBytes, $badBytes, $dirtyPages, 512));
};

$tests['pager rollback journal current rejects journal initial page count mismatch'] = static function (TestRunner $t) use ($journalBytes, $currentPages, $databaseBytes, $dirtyPages): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerRollbackJournalCurrentPlan::admitCurrentJournal('/tmp/wp.sqlite', $databaseBytes, $journalBytes([2 => $currentPages[2], 3 => $currentPages[3]], 2), $dirtyPages, 512));
};

$tests['pager rollback journal current rejects journal page size mismatch'] = static function (TestRunner $t) use ($journalBytes, $currentPages, $databaseBytes, $dirtyPages): void {
    $large = str_pad('large page image', 1024, '.', STR_PAD_RIGHT);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerRollbackJournalCurrentPlan::admitCurrentJournal('/tmp/wp.sqlite', $databaseBytes, $journalBytes([2 => $large], 3, 1024), $dirtyPages, 512));
};

$invalidCases = [
    'empty database path' => static fn () => SQLitePagerRollbackJournalCurrentPlan::admitCurrentJournal('', $databaseBytes, $journalBytes([2 => $currentPages[2]]), $dirtyPages, 512),
    'empty database bytes' => static fn () => SQLitePagerRollbackJournalCurrentPlan::admitCurrentJournal('/tmp/wp.sqlite', '', $journalBytes([2 => $currentPages[2]]), $dirtyPages, 512),
    'empty journal bytes' => static fn () => SQLitePagerRollbackJournalCurrentPlan::admitCurrentJournal('/tmp/wp.sqlite', $databaseBytes, '', $dirtyPages, 512),
    'empty dirty pages' => static fn () => SQLitePagerRollbackJournalCurrentPlan::admitCurrentJournal('/tmp/wp.sqlite', $databaseBytes, $journalBytes([2 => $currentPages[2]]), [], 512),
    'bad page size' => static fn () => SQLitePagerRollbackJournalCurrentPlan::admitCurrentJournal('/tmp/wp.sqlite', $databaseBytes, $journalBytes([2 => $currentPages[2]]), $dirtyPages, 768),
    'unaligned database' => static fn () => SQLitePagerRollbackJournalCurrentPlan::admitCurrentJournal('/tmp/wp.sqlite', 'short', $journalBytes([2 => $currentPages[2]]), $dirtyPages, 512),
    'zero dirty page' => static fn () => SQLitePagerRollbackJournalCurrentPlan::admitCurrentJournal('/tmp/wp.sqlite', $databaseBytes, $journalBytes([2 => $currentPages[2]]), [0 => $dirtyPages[2]], 512),
    'short dirty page' => static fn () => SQLitePagerRollbackJournalCurrentPlan::admitCurrentJournal('/tmp/wp.sqlite', $databaseBytes, $journalBytes([2 => $currentPages[2]]), [2 => 'short'], 512),
];

foreach ($invalidCases as $name => $callback) {
    $tests['pager rollback journal current rejects ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

return $tests;
