<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('next240 checkpoint database image');
$pageCacheDigest = $hash('next240 clean checkpoint page cache');
$finalizerPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next236',
    'next_writer_allowed' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next240.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next240.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next240.sqlite-wal',
    'source_token' => 'wp-next240-current-source',
    'next_writer_generation' => 240,
    'schema_cookie' => 640,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'wal_index_salt' => ['next240-salt-a', 'next240-salt-b'],
    'wal_index_mx_frame' => 12,
    'checkpoint_frame' => 9,
    'finalized_statement_names' => ['select-schema', 'select-options', 'select-option-index'],
    'operation_names' => ['admit_next_wal_writer_after_checkpoint_finalizers_next236'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next236'],
];

$receipt = static function (string $name, array $statements, array $override = []) use ($finalizerPlan, $databaseDigest, $pageCacheDigest): array {
    return array_replace([
        'name' => $name,
        'source_token' => $finalizerPlan['source_token'],
        'released_generation' => $finalizerPlan['next_writer_generation'],
        'commit_generation' => 241,
        'schema_cookie' => $finalizerPlan['schema_cookie'],
        'database_digest' => $databaseDigest,
        'page_cache_digest' => $pageCacheDigest,
        'wal_index_salt' => $finalizerPlan['wal_index_salt'],
        'wal_index_mx_frame' => $finalizerPlan['wal_index_mx_frame'],
        'checkpoint_frame' => $finalizerPlan['checkpoint_frame'],
        'covered_statement_names' => $statements,
        'dirty_pages' => [1, 2],
        'commit_frames' => [10, 11, 12],
        'commit_mark_seen' => true,
        'writer_lock_released' => true,
        'wal_hook_receipt' => true,
        'autocheckpoint_receipt' => true,
    ], $override);
};

$receipts = [
    $receipt('schema-commit-receipt', ['select-schema'], ['dirty_pages' => [1], 'commit_frames' => [10]]),
    $receipt('options-commit-receipt', ['select-options'], ['dirty_pages' => [2], 'commit_frames' => [11]]),
    $receipt('index-commit-receipt', ['select-option-index'], ['dirty_pages' => [5], 'commit_frames' => [12]]),
];

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next240AdmitAutocheckpointBaseline($finalizerPlan, $receipts, 241);
$blockedReceipt = static fn (array $override): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next240AdmitAutocheckpointBaseline(
    $finalizerPlan,
    [
        $receipts[0],
        $receipt('options-blocked-receipt', ['select-options'], $override),
        $receipts[2],
    ],
    241
);
$missingStatement = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next240AdmitAutocheckpointBaseline(
    $finalizerPlan,
    [$receipts[0], $receipts[1]],
    241
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next240'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'next_writer_commit_admits_autocheckpoint_baseline_after_hot_journal_savepoint_checkpoint'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next236'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next240.sqlite'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next240.sqlite-journal'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next240.sqlite-wal'],
    'source token' => [static fn (): mixed => $plan()['source_token'], 'wp-next240-current-source'],
    'released generation' => [static fn (): mixed => $plan()['released_writer_generation'], 240],
    'commit generation' => [static fn (): mixed => $plan()['commit_generation'], 241],
    'schema cookie' => [static fn (): mixed => $plan()['schema_cookie'], 640],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'page cache digest' => [static fn (): mixed => $plan()['page_cache_digest'], $pageCacheDigest],
    'wal salt' => [static fn (): mixed => $plan()['wal_index_salt'], ['next240-salt-a', 'next240-salt-b']],
    'wal mx frame' => [static fn (): mixed => $plan()['wal_index_mx_frame'], 12],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 9],
    'expected statements' => [static fn (): mixed => $plan()['expected_statement_names'], ['select-option-index', 'select-options', 'select-schema']],
    'covered statements' => [static fn (): mixed => $plan()['covered_statement_names'], ['select-option-index', 'select-options', 'select-schema']],
    'missing statements empty' => [static fn (): mixed => $plan()['missing_statement_names'], []],
    'dirty pages merged' => [static fn (): mixed => $plan()['dirty_pages'], [1, 2, 5]],
    'commit frames merged' => [static fn (): mixed => $plan()['commit_frames'], [10, 11, 12]],
    'admitted receipts' => [static fn (): mixed => $plan()['admitted_receipt_names'], ['schema-commit-receipt', 'options-commit-receipt', 'index-commit-receipt']],
    'blocked receipts empty' => [static fn (): mixed => $plan()['blocked_receipt_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_receipt_reasons'], []],
    'baseline allowed' => [static fn (): mixed => $plan()['autocheckpoint_baseline_allowed'], true],
    'writer action' => [static fn (): mixed => $plan()['writer_action'], 'commit_next_writer_generation_241'],
    'wal index action' => [static fn (): mixed => $plan()['wal_index_action'], 'publish_wal_index_baseline_for_autocheckpoint'],
    'page cache action' => [static fn (): mixed => $plan()['page_cache_action'], 'promote_clean_pages_to_commit_generation_241'],
    'hook action' => [static fn (): mixed => $plan()['hook_action'], 'run_wal_hook_and_autocheckpoint_for_generation_241'],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next236_finalizers_released', 'all_finalized_statements_covered_by_commit_receipts', 'all_commit_receipts_match_checkpoint_current_source']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'row count' => [static fn (): mixed => count($plan()['receipt_rows']), 3],
    'row reason' => [static fn (): mixed => $plan()['receipt_rows'][1]['receipt_reason'], 'receipt_promotes_checkpoint_current_source_to_autocheckpoint_baseline'],
    'row covered statement' => [static fn (): mixed => $plan()['receipt_rows'][1]['covered_statements'], ['select-options']],
    'row dirty pages' => [static fn (): mixed => $plan()['receipt_rows'][1]['dirty_pages'], [2]],
    'row commit frames' => [static fn (): mixed => $plan()['receipt_rows'][1]['commit_frames'], [11]],
    'row commit mark' => [static fn (): mixed => $plan()['receipt_rows'][1]['commit_mark_seen'], true],
    'row lock release' => [static fn (): mixed => $plan()['receipt_rows'][1]['writer_lock_released'], true],
    'row wal hook' => [static fn (): mixed => $plan()['receipt_rows'][1]['wal_hook_receipt'], true],
    'row autocheckpoint' => [static fn (): mixed => $plan()['receipt_rows'][1]['autocheckpoint_receipt'], true],
    'digest length' => [static fn (): mixed => strlen($plan()['baseline_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('admit_next_wal_writer_after_checkpoint_finalizers_next236', $plan()['operation_names'], true), true],
    'operation added' => [static fn (): mixed => in_array('admit_autocheckpoint_baseline_next240', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next236', $plan()['dependencies'], true), true],
    'dependency next240' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next240', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-next-writer-autocheckpoint-after-hot-journal', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat checkpoint publication'), true],
    'stale token blocked' => [static fn (): mixed => $blockedReceipt(['source_token' => 'old-source'])['blocked_receipt_reasons'], ['receipt_source_token_mismatch']],
    'stale released generation blocked' => [static fn (): mixed => $blockedReceipt(['released_generation' => 239])['blocked_receipt_reasons'], ['receipt_released_generation_mismatch']],
    'stale commit generation blocked' => [static fn (): mixed => $blockedReceipt(['commit_generation' => 242])['blocked_receipt_reasons'], ['receipt_commit_generation_mismatch']],
    'stale schema blocked' => [static fn (): mixed => $blockedReceipt(['schema_cookie' => 639])['blocked_receipt_reasons'], ['receipt_schema_cookie_mismatch']],
    'stale database blocked' => [static fn (): mixed => $blockedReceipt(['database_digest' => $hash('stale db')])['blocked_receipt_reasons'], ['receipt_database_digest_mismatch']],
    'stale page cache blocked' => [static fn (): mixed => $blockedReceipt(['page_cache_digest' => $hash('stale cache')])['blocked_receipt_reasons'], ['receipt_page_cache_digest_mismatch']],
    'stale wal salt blocked' => [static fn (): mixed => $blockedReceipt(['wal_index_salt' => ['old-a', 'old-b']])['blocked_receipt_reasons'], ['receipt_wal_index_salt_mismatch']],
    'stale mx frame blocked' => [static fn (): mixed => $blockedReceipt(['wal_index_mx_frame' => 11])['blocked_receipt_reasons'], ['receipt_wal_index_mx_frame_mismatch']],
    'stale checkpoint frame blocked' => [static fn (): mixed => $blockedReceipt(['checkpoint_frame' => 8])['blocked_receipt_reasons'], ['receipt_checkpoint_frame_mismatch']],
    'unknown statement blocked' => [static fn (): mixed => $blockedReceipt(['covered_statement_names' => ['select-options', 'old-statement']])['blocked_receipt_reasons'], ['receipt_statement_not_finalized']],
    'missing commit mark blocked' => [static fn (): mixed => $blockedReceipt(['commit_mark_seen' => false])['blocked_receipt_reasons'], ['receipt_commit_mark_missing']],
    'writer lock blocked' => [static fn (): mixed => $blockedReceipt(['writer_lock_released' => false])['blocked_receipt_reasons'], ['receipt_writer_lock_not_released']],
    'wal hook blocked' => [static fn (): mixed => $blockedReceipt(['wal_hook_receipt' => false])['blocked_receipt_reasons'], ['receipt_wal_hook_missing']],
    'autocheckpoint blocked' => [static fn (): mixed => $blockedReceipt(['autocheckpoint_receipt' => false])['blocked_receipt_reasons'], ['receipt_autocheckpoint_missing']],
    'hot journal blocked' => [static fn (): mixed => $blockedReceipt(['hot_journal_present' => true])['blocked_receipt_reasons'], ['receipt_hot_journal_still_visible']],
    'savepoint blocked' => [static fn (): mixed => $blockedReceipt(['savepoint_open' => true])['blocked_receipt_reasons'], ['receipt_savepoint_still_open']],
    'dirty checkpoint cache blocked' => [static fn (): mixed => $blockedReceipt(['dirty_checkpoint_cache' => true])['blocked_receipt_reasons'], ['receipt_dirty_checkpoint_cache']],
    'combined reasons' => [static fn (): mixed => $blockedReceipt(['source_token' => 'old-source', 'wal_hook_receipt' => false, 'dirty_checkpoint_cache' => true])['blocked_receipt_reasons'], ['receipt_source_token_mismatch', 'receipt_wal_hook_missing', 'receipt_dirty_checkpoint_cache']],
    'blocked status' => [static fn (): mixed => $blockedReceipt(['source_token' => 'old-source'])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next240'],
    'blocked reason' => [static fn (): mixed => $blockedReceipt(['source_token' => 'old-source'])['reason'], 'next_writer_commit_holds_autocheckpoint_baseline_after_hot_journal_savepoint_checkpoint'],
    'blocked writer action' => [static fn (): mixed => $blockedReceipt(['source_token' => 'old-source'])['writer_action'], 'hold_next_writer_commit_for_checkpoint_current_source'],
    'blocked wal index action' => [static fn (): mixed => $blockedReceipt(['source_token' => 'old-source'])['wal_index_action'], 'retain_wal_index_checkpoint_baseline'],
    'blocked page cache action' => [static fn (): mixed => $blockedReceipt(['source_token' => 'old-source'])['page_cache_action'], 'discard_stale_checkpoint_page_cache'],
    'blocked hook action' => [static fn (): mixed => $blockedReceipt(['source_token' => 'old-source'])['hook_action'], 'defer_wal_hook_and_autocheckpoint_until_receipts_match'],
    'blocked guards' => [static fn (): mixed => $blockedReceipt(['source_token' => 'old-source'])['blocked_guard_names'], ['all_finalized_statements_covered_by_commit_receipts', 'all_commit_receipts_match_checkpoint_current_source']],
    'missing statement status' => [static fn (): mixed => $missingStatement()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next240'],
    'missing statement list' => [static fn (): mixed => $missingStatement()['missing_statement_names'], ['select-option-index']],
    'missing statement guard' => [static fn (): mixed => $missingStatement()['blocked_guard_names'], ['all_finalized_statements_covered_by_commit_receipts']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next240 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next240AdmitAutocheckpointBaseline(array_replace($finalizerPlan, ['status' => 'bad']), $receipts, 241),
    'not admitted rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next240AdmitAutocheckpointBaseline(array_replace($finalizerPlan, ['next_writer_allowed' => false]), $receipts, 241),
    'empty receipts rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next240AdmitAutocheckpointBaseline($finalizerPlan, [], 241),
    'same commit generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next240AdmitAutocheckpointBaseline($finalizerPlan, $receipts, 240),
    'bad source token rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next240AdmitAutocheckpointBaseline(array_replace($finalizerPlan, ['source_token' => 'bad token']), $receipts, 241),
    'bad released generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next240AdmitAutocheckpointBaseline(array_replace($finalizerPlan, ['next_writer_generation' => 0]), $receipts, 241),
    'bad schema cookie rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next240AdmitAutocheckpointBaseline(array_replace($finalizerPlan, ['schema_cookie' => 0]), $receipts, 241),
    'bad database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next240AdmitAutocheckpointBaseline(array_replace($finalizerPlan, ['database_digest' => 'short']), $receipts, 241),
    'bad page cache digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next240AdmitAutocheckpointBaseline(array_replace($finalizerPlan, ['page_cache_digest' => 'short']), $receipts, 241),
    'bad wal salt rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next240AdmitAutocheckpointBaseline(array_replace($finalizerPlan, ['wal_index_salt' => ['one']]), $receipts, 241),
    'bad mx frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next240AdmitAutocheckpointBaseline(array_replace($finalizerPlan, ['wal_index_mx_frame' => 0]), $receipts, 241),
    'bad finalized statements rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next240AdmitAutocheckpointBaseline(array_replace($finalizerPlan, ['finalized_statement_names' => []]), $receipts, 241),
    'bad receipt name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next240AdmitAutocheckpointBaseline($finalizerPlan, [array_replace($receipts[0], ['name' => 'bad name'])], 241),
    'bad receipt generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next240AdmitAutocheckpointBaseline($finalizerPlan, [array_replace($receipts[0], ['commit_generation' => 0])], 241),
    'bad receipt digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next240AdmitAutocheckpointBaseline($finalizerPlan, [array_replace($receipts[0], ['database_digest' => 'short'])], 241),
    'bad covered statement rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next240AdmitAutocheckpointBaseline($finalizerPlan, [array_replace($receipts[0], ['covered_statement_names' => ['bad statement']])], 241),
    'bad dirty page rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next240AdmitAutocheckpointBaseline($finalizerPlan, [array_replace($receipts[0], ['dirty_pages' => [0]])], 241),
    'bad commit frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next240AdmitAutocheckpointBaseline($finalizerPlan, [array_replace($receipts[0], ['commit_frames' => ['bad']])], 241),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next240 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
