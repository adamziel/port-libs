<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$pageDigests = [
    1 => $hash('next229 schema page after checkpoint'),
    2 => $hash('next229 wp_options page after checkpoint'),
    3 => $hash('next229 autoload index after checkpoint'),
];
$databaseDigest = $hash('next229 checkpoint database');
$oldWalDigest = $hash('next229 previous wal before reset');
$newWalDigest = $hash('next229 restarted wal after reset');
$publication = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next224',
    'publication_allowed' => true,
    'checkpoint_reset_visible' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next229.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next229.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next229.sqlite-wal',
    'source_token' => 'wp-next229-current-source',
    'next_writer_generation' => 229,
    'database_digest' => $databaseDigest,
    'previous_wal_digest' => $oldWalDigest,
    'operation_names' => ['publish_checkpoint_reset_current_source_next224'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next224'],
];

$handle = static function (string $name, array $pages, array $override = []) use ($publication, $databaseDigest, $newWalDigest): array {
    return array_replace([
        'name' => $name,
        'source_token' => $publication['source_token'],
        'generation' => $publication['next_writer_generation'],
        'database_digest' => $databaseDigest,
        'wal_digest' => $newWalDigest,
        'page_digests' => $pages,
        'lock_receipt' => true,
        'sync_receipt' => true,
    ], $override);
};

$handles = [
    $handle('wp-schema-reopen', [1 => $pageDigests[1]]),
    $handle('wp-options-reopen', [2 => $pageDigests[2]]),
    $handle('wp-autoload-reopen', [3 => $pageDigests[3]]),
];
$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next229Verify($publication, $handles, $pageDigests);
$blockedHandle = static fn (array $override, ?array $pages = null): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next229Verify(
    $publication,
    [
        $handles[0],
        $handle('wp-options-blocked', $pages ?? [2 => $pageDigests[2]], $override),
        $handles[2],
    ],
    $pageDigests
);
$missingPage = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next229Verify(
    $publication,
    [$handles[0], $handles[1]],
    $pageDigests
);
$requireAllPages = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next229Verify(
    $publication,
    [$handle('wp-single-required', [1 => $pageDigests[1]], ['require_all_pages' => true])],
    $pageDigests
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next229'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'reopened_handles_match_checkpoint_current_source_after_hot_journal_savepoint'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next224'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next229.sqlite'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next229.sqlite-journal'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next229.sqlite-wal'],
    'source token' => [static fn (): mixed => $plan()['source_token'], 'wp-next229-current-source'],
    'writer generation' => [static fn (): mixed => $plan()['next_writer_generation'], 229],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'previous wal digest' => [static fn (): mixed => $plan()['previous_wal_digest'], $oldWalDigest],
    'expected pages' => [static fn (): mixed => $plan()['expected_page_numbers'], [1, 2, 3]],
    'covered pages' => [static fn (): mixed => $plan()['covered_page_numbers'], [1, 2, 3]],
    'missing pages empty' => [static fn (): mixed => $plan()['missing_page_numbers'], []],
    'admitted handle names' => [static fn (): mixed => $plan()['admitted_handle_names'], ['wp-schema-reopen', 'wp-options-reopen', 'wp-autoload-reopen']],
    'blocked handle names empty' => [static fn (): mixed => $plan()['blocked_handle_names'], []],
    'blocked handle reasons empty' => [static fn (): mixed => $plan()['blocked_handle_reasons'], []],
    'current source admitted' => [static fn (): mixed => $plan()['current_source_admitted'], true],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'allow_reopened_handles_to_serve_checkpoint_source'],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'keep_next224_reset_publication_visible'],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next224_publication_visible', 'all_reopened_handles_current', 'checkpoint_pages_covered']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'handle row count' => [static fn (): mixed => count($plan()['handle_rows']), 3],
    'handle reason' => [static fn (): mixed => $plan()['handle_rows'][1]['handle_reason'], 'handle_matches_checkpoint_current_source'],
    'handle page numbers' => [static fn (): mixed => $plan()['handle_rows'][1]['page_numbers'], [2]],
    'handle receipts' => [static fn (): mixed => [$plan()['handle_rows'][1]['lock_receipt'], $plan()['handle_rows'][1]['sync_receipt']], [true, true]],
    'publication digest length' => [static fn (): mixed => strlen($plan()['publication_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('publish_checkpoint_reset_current_source_next224', $plan()['operation_names'], true), true],
    'operation added' => [static fn (): mixed => in_array('admit_checkpoint_current_source_next229', $plan()['operation_names'], true), true],
    'dependency next224 inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next224', $plan()['dependencies'], true), true],
    'dependency next229 added' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next229', $plan()['dependencies'], true), true],
    'wordpress dependency added' => [static fn (): mixed => in_array('wordpress-import-hot-journal-savepoint-checkpoint-reopen', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat reset admission'), true],
    'stale token blocked' => [static fn (): mixed => $blockedHandle(['source_token' => 'old-source'])['blocked_handle_reasons'], ['handle_source_token_mismatch']],
    'stale generation blocked' => [static fn (): mixed => $blockedHandle(['generation' => 228])['blocked_handle_reasons'], ['handle_generation_mismatch']],
    'stale database blocked' => [static fn (): mixed => $blockedHandle(['database_digest' => $hash('stale db')])['blocked_handle_reasons'], ['handle_database_digest_mismatch']],
    'previous wal reuse blocked' => [static fn (): mixed => $blockedHandle(['wal_digest' => $oldWalDigest])['blocked_handle_reasons'], ['handle_reuses_previous_wal_digest']],
    'page digest mismatch blocked' => [static fn (): mixed => $blockedHandle([], [2 => $hash('stale page')])['blocked_handle_reasons'], ['handle_page_digest_mismatch']],
    'unknown page blocked' => [static fn (): mixed => $blockedHandle([], [4 => $hash('unknown page')])['blocked_handle_reasons'], ['handle_page_not_in_checkpoint_set']],
    'hot journal blocked' => [static fn (): mixed => $blockedHandle(['hot_journal_present' => true])['blocked_handle_reasons'], ['handle_hot_journal_still_visible']],
    'savepoint open blocked' => [static fn (): mixed => $blockedHandle(['savepoint_depth' => 1])['blocked_handle_reasons'], ['handle_savepoint_scope_open']],
    'dirty cache blocked' => [static fn (): mixed => $blockedHandle(['dirty_cache' => true])['blocked_handle_reasons'], ['handle_dirty_cache']],
    'missing lock blocked' => [static fn (): mixed => $blockedHandle(['lock_receipt' => false])['blocked_handle_reasons'], ['handle_lock_receipt_missing']],
    'missing sync blocked' => [static fn (): mixed => $blockedHandle(['sync_receipt' => false])['blocked_handle_reasons'], ['handle_sync_receipt_missing']],
    'missing page status blocked' => [static fn (): mixed => $missingPage()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next229'],
    'missing page numbers' => [static fn (): mixed => $missingPage()['missing_page_numbers'], [3]],
    'missing page guard' => [static fn (): mixed => $missingPage()['blocked_guard_names'], ['checkpoint_pages_covered']],
    'require all pages blocked' => [static fn (): mixed => $requireAllPages()['blocked_handle_reasons'], ['handle_missing_required_checkpoint_page']],
    'combined handle reasons unique' => [static fn (): mixed => $blockedHandle(['source_token' => 'old-source', 'hot_journal_present' => true, 'dirty_cache' => true])['blocked_handle_reasons'], ['handle_source_token_mismatch', 'handle_hot_journal_still_visible', 'handle_dirty_cache']],
    'combined blocked guards' => [static fn (): mixed => $blockedHandle(['source_token' => 'old-source'])['blocked_guard_names'], ['all_reopened_handles_current', 'checkpoint_pages_covered']],
    'bad base rejected' => [static function () use ($publication, $handles, $pageDigests): string {
        $bad = $publication;
        $bad['status'] = 'wal-hot-journal-savepoint-checkpoint-current-source-next223';
        try {
            SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next229Verify($bad, $handles, $pageDigests);
        } catch (Throwable $throwable) {
            return $throwable->getMessage();
        }

        return 'no-error';
    }, 'SQLite WAL hot-journal savepoint checkpoint current-source next229 requires a published next224 plan'],
    'not visible rejected' => [static function () use ($publication, $handles, $pageDigests): string {
        $bad = $publication;
        $bad['checkpoint_reset_visible'] = false;
        try {
            SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next229Verify($bad, $handles, $pageDigests);
        } catch (Throwable $throwable) {
            return $throwable->getMessage();
        }

        return 'no-error';
    }, 'SQLite WAL hot-journal savepoint checkpoint current-source next229 requires visible reset publication'],
    'empty handles rejected' => [static function () use ($publication, $pageDigests): string {
        try {
            SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next229Verify($publication, [], $pageDigests);
        } catch (Throwable $throwable) {
            return $throwable->getMessage();
        }

        return 'no-error';
    }, 'SQLite WAL hot-journal savepoint checkpoint current-source next229 requires reopened handles'],
    'bad page digest rejected' => [static function () use ($publication, $handles): string {
        try {
            SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next229Verify($publication, $handles, [0 => 'bad']);
        } catch (Throwable $throwable) {
            return $throwable->getMessage();
        }

        return 'no-error';
    }, 'SQLite WAL hot-journal savepoint checkpoint current-source next229 expected pages page digests must map positive pages to sha256 strings'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next229 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
