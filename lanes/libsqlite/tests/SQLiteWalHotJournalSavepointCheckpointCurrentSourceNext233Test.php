<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$rootPages = [
    1 => $hash('next233 sqlite_schema root page after checkpoint'),
    2 => $hash('next233 wp_options root page after checkpoint'),
    5 => $hash('next233 option_name index root page after checkpoint'),
];
$databaseDigest = $hash('next233 checkpoint database image');
$handlePlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next229',
    'current_source_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next233.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next233.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next233.sqlite-wal',
    'source_token' => 'wp-next233-current-source',
    'next_writer_generation' => 233,
    'schema_cookie' => 331,
    'database_digest' => $databaseDigest,
    'admitted_handle_names' => ['schema-handle', 'options-handle', 'index-handle'],
    'operation_names' => ['verify_reopened_handles_after_checkpoint_publication_next229'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next229'],
];

$statement = static function (string $name, string $handle, array $pages, array $override = []) use ($handlePlan, $databaseDigest): array {
    return array_replace([
        'name' => $name,
        'handle_name' => $handle,
        'source_token' => $handlePlan['source_token'],
        'generation' => $handlePlan['next_writer_generation'],
        'schema_cookie' => $handlePlan['schema_cookie'],
        'database_digest' => $databaseDigest,
        'root_page_digests' => $pages,
        'schema_reparse_receipt' => true,
        'read_lock_receipt' => true,
    ], $override);
};

$statements = [
    $statement('select-schema-statement', 'schema-handle', [1 => $rootPages[1]]),
    $statement('select-options-statement', 'options-handle', [2 => $rootPages[2]]),
    $statement('select-option-name-index-statement', 'index-handle', [5 => $rootPages[5]]),
];

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next233AdmitStatements($handlePlan, $statements, $rootPages);
$blockedStatement = static fn (array $override, ?array $pages = null): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next233AdmitStatements(
    $handlePlan,
    [
        $statements[0],
        $statement('select-options-blocked', 'options-handle', $pages ?? [2 => $rootPages[2]], $override),
        $statements[2],
    ],
    $rootPages
);
$missingRoot = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next233AdmitStatements(
    $handlePlan,
    [$statements[0], $statements[1]],
    $rootPages
);
$requireAllRoots = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next233AdmitStatements(
    $handlePlan,
    [$statement('select-all-required', 'schema-handle', [1 => $rootPages[1]], ['require_all_root_pages' => true])],
    $rootPages
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next233'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'prepared_statements_reuse_checkpoint_current_source_after_hot_journal_savepoint'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next229'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next233.sqlite'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next233.sqlite-journal'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next233.sqlite-wal'],
    'source token' => [static fn (): mixed => $plan()['source_token'], 'wp-next233-current-source'],
    'generation' => [static fn (): mixed => $plan()['next_writer_generation'], 233],
    'schema cookie' => [static fn (): mixed => $plan()['schema_cookie'], 331],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'expected roots' => [static fn (): mixed => $plan()['expected_root_pages'], [1, 2, 5]],
    'covered roots' => [static fn (): mixed => $plan()['covered_root_pages'], [1, 2, 5]],
    'missing roots empty' => [static fn (): mixed => $plan()['missing_root_pages'], []],
    'admitted statements' => [static fn (): mixed => $plan()['admitted_statement_names'], ['select-schema-statement', 'select-options-statement', 'select-option-name-index-statement']],
    'blocked statements empty' => [static fn (): mixed => $plan()['blocked_statement_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_statement_reasons'], []],
    'admission allowed' => [static fn (): mixed => $plan()['statement_admission_allowed'], true],
    'statement action' => [static fn (): mixed => $plan()['statement_action'], 'reuse_prepared_statements_on_checkpoint_current_source'],
    'pager action' => [static fn (): mixed => $plan()['pager_action'], 'serve_pages_from_reopened_checkpoint_handles'],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next229_handles_admitted', 'all_statement_sources_current', 'all_root_pages_covered']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'row count' => [static fn (): mixed => count($plan()['statement_rows']), 3],
    'row reason' => [static fn (): mixed => $plan()['statement_rows'][1]['statement_reason'], 'statement_matches_checkpoint_current_source'],
    'row handle' => [static fn (): mixed => $plan()['statement_rows'][1]['handle_name'], 'options-handle'],
    'row roots' => [static fn (): mixed => $plan()['statement_rows'][1]['root_pages'], [2]],
    'row receipts' => [static fn (): mixed => [$plan()['statement_rows'][1]['schema_reparse_receipt'], $plan()['statement_rows'][1]['read_lock_receipt']], [true, true]],
    'admission digest length' => [static fn (): mixed => strlen($plan()['admission_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('verify_reopened_handles_after_checkpoint_publication_next229', $plan()['operation_names'], true), true],
    'operation added' => [static fn (): mixed => in_array('admit_prepared_statement_current_source_next233', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next229', $plan()['dependencies'], true), true],
    'dependency added' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next233', $plan()['dependencies'], true), true],
    'application dependency added' => [static fn (): mixed => in_array('application-import-checkpoint-statement-reuse-after-hot-journal', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat checkpoint reset admission'), true],
    'unknown handle blocked' => [static fn (): mixed => $blockedStatement(['handle_name' => 'old-handle'])['blocked_statement_reasons'], ['statement_handle_not_admitted']],
    'stale token blocked' => [static fn (): mixed => $blockedStatement(['source_token' => 'old-source'])['blocked_statement_reasons'], ['statement_source_token_mismatch']],
    'stale generation blocked' => [static fn (): mixed => $blockedStatement(['generation' => 232])['blocked_statement_reasons'], ['statement_generation_mismatch']],
    'stale schema blocked' => [static fn (): mixed => $blockedStatement(['schema_cookie' => 330])['blocked_statement_reasons'], ['statement_schema_cookie_mismatch']],
    'stale database blocked' => [static fn (): mixed => $blockedStatement(['database_digest' => $hash('stale database')])['blocked_statement_reasons'], ['statement_database_digest_mismatch']],
    'root digest mismatch blocked' => [static fn (): mixed => $blockedStatement([], [2 => $hash('stale root page')])['blocked_statement_reasons'], ['statement_root_page_digest_mismatch']],
    'unknown root page blocked' => [static fn (): mixed => $blockedStatement([], [9 => $hash('unknown root page')])['blocked_statement_reasons'], ['statement_root_page_not_checkpointed']],
    'hot journal blocked' => [static fn (): mixed => $blockedStatement(['hot_journal_present' => true])['blocked_statement_reasons'], ['statement_hot_journal_still_visible']],
    'savepoint open blocked' => [static fn (): mixed => $blockedStatement(['savepoint_depth' => 1])['blocked_statement_reasons'], ['statement_savepoint_scope_open']],
    'dirty cache blocked' => [static fn (): mixed => $blockedStatement(['dirty_cache' => true])['blocked_statement_reasons'], ['statement_dirty_cache']],
    'missing reparse blocked' => [static fn (): mixed => $blockedStatement(['schema_reparse_receipt' => false])['blocked_statement_reasons'], ['statement_schema_reparse_receipt_missing']],
    'missing read lock blocked' => [static fn (): mixed => $blockedStatement(['read_lock_receipt' => false])['blocked_statement_reasons'], ['statement_read_lock_receipt_missing']],
    'missing root status blocked' => [static fn (): mixed => $missingRoot()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next233'],
    'missing root pages' => [static fn (): mixed => $missingRoot()['missing_root_pages'], [5]],
    'missing root guard' => [static fn (): mixed => $missingRoot()['blocked_guard_names'], ['all_root_pages_covered']],
    'require all roots blocked' => [static fn (): mixed => $requireAllRoots()['blocked_statement_reasons'], ['statement_missing_required_root_page']],
    'combined reasons unique' => [static fn (): mixed => $blockedStatement(['source_token' => 'old-source', 'hot_journal_present' => true, 'dirty_cache' => true])['blocked_statement_reasons'], ['statement_source_token_mismatch', 'statement_hot_journal_still_visible', 'statement_dirty_cache']],
    'combined blocked guards' => [static fn (): mixed => $blockedStatement(['source_token' => 'old-source'])['blocked_guard_names'], ['all_statement_sources_current', 'all_root_pages_covered']],
    'blocked action' => [static fn (): mixed => $blockedStatement(['source_token' => 'old-source'])['statement_action'], 'expire_prepared_statements_before_next_step'],
    'blocked pager action' => [static fn (): mixed => $blockedStatement(['source_token' => 'old-source'])['pager_action'], 'force_reopen_and_schema_recheck'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next233 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next233AdmitStatements(array_replace($handlePlan, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next229-blocked']), $statements, $rootPages),
    'not admitted rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next233AdmitStatements(array_replace($handlePlan, ['current_source_admitted' => false]), $statements, $rootPages),
    'empty statements rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next233AdmitStatements($handlePlan, [], $rootPages),
    'bad root digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next233AdmitStatements($handlePlan, $statements, [0 => 'bad']),
    'bad source token rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next233AdmitStatements(array_replace($handlePlan, ['source_token' => 'bad token']), $statements, $rootPages),
    'bad generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next233AdmitStatements(array_replace($handlePlan, ['next_writer_generation' => 0]), $statements, $rootPages),
    'bad schema cookie rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next233AdmitStatements(array_replace($handlePlan, ['schema_cookie' => 0]), $statements, $rootPages),
    'bad database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next233AdmitStatements(array_replace($handlePlan, ['database_digest' => 'short']), $statements, $rootPages),
    'bad admitted handles rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next233AdmitStatements(array_replace($handlePlan, ['admitted_handle_names' => []]), $statements, $rootPages),
    'bad statement name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next233AdmitStatements($handlePlan, [array_replace($statements[0], ['name' => 'bad name'])], $rootPages),
    'bad statement generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next233AdmitStatements($handlePlan, [array_replace($statements[0], ['generation' => -1])], $rootPages),
    'bad statement root digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next233AdmitStatements($handlePlan, [array_replace($statements[0], ['root_page_digests' => [0 => 'bad']])], $rootPages),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next233 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
