<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('next236 checkpoint database image');
$statementPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next233',
    'statement_admission_allowed' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next236.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next236.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next236.sqlite-wal',
    'source_token' => 'wp-next236-current-source',
    'next_writer_generation' => 236,
    'schema_cookie' => 432,
    'database_digest' => $databaseDigest,
    'admitted_statement_names' => ['select-schema', 'select-options', 'select-option-name-index'],
    'operation_names' => ['admit_prepared_statement_current_source_next233'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next233'],
];

$finalizer = static function (string $name, string $statement, array $override = []) use ($statementPlan, $databaseDigest): array {
    return array_replace([
        'name' => $name,
        'statement_name' => $statement,
        'source_token' => $statementPlan['source_token'],
        'generation' => $statementPlan['next_writer_generation'],
        'schema_cookie' => $statementPlan['schema_cookie'],
        'database_digest' => $databaseDigest,
        'sqlite_done_seen' => true,
        'reset_called' => true,
        'reader_lease_released' => true,
        'wal_hook_receipt' => true,
        'autocheckpoint_receipt' => true,
    ], $override);
};

$finalizers = [
    $finalizer('schema-finalizer', 'select-schema'),
    $finalizer('options-finalizer', 'select-options'),
    $finalizer('index-finalizer', 'select-option-name-index'),
];

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next236FinalizeForNextWriter($statementPlan, $finalizers, 237);
$blockedFinalizer = static fn (array $override): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next236FinalizeForNextWriter(
    $statementPlan,
    [
        $finalizers[0],
        $finalizer('options-blocked-finalizer', 'select-options', $override),
        $finalizers[2],
    ],
    237
);
$missingStatement = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next236FinalizeForNextWriter(
    $statementPlan,
    [$finalizers[0], $finalizers[1]],
    237
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next236'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'finalized_statements_release_checkpoint_current_source_to_next_writer'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next233'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next236.sqlite'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next236.sqlite-journal'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next236.sqlite-wal'],
    'source token' => [static fn (): mixed => $plan()['source_token'], 'wp-next236-current-source'],
    'current generation' => [static fn (): mixed => $plan()['current_writer_generation'], 236],
    'next generation' => [static fn (): mixed => $plan()['next_writer_generation'], 237],
    'schema cookie' => [static fn (): mixed => $plan()['schema_cookie'], 432],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'expected statements' => [static fn (): mixed => $plan()['expected_statement_names'], ['select-schema', 'select-options', 'select-option-name-index']],
    'finalized statements' => [static fn (): mixed => $plan()['finalized_statement_names'], ['select-option-name-index', 'select-options', 'select-schema']],
    'missing statements empty' => [static fn (): mixed => $plan()['missing_statement_names'], []],
    'admitted finalizers' => [static fn (): mixed => $plan()['admitted_finalizer_names'], ['schema-finalizer', 'options-finalizer', 'index-finalizer']],
    'blocked finalizers empty' => [static fn (): mixed => $plan()['blocked_finalizer_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_finalizer_reasons'], []],
    'next writer allowed' => [static fn (): mixed => $plan()['next_writer_allowed'], true],
    'writer action' => [static fn (): mixed => $plan()['writer_action'], 'open_next_wal_writer_generation_237'],
    'reader action' => [static fn (): mixed => $plan()['reader_action'], 'release_checkpoint_reader_leases'],
    'wal hook action' => [static fn (): mixed => $plan()['wal_hook_action'], 'publish_wal_hook_checkpoint_summary'],
    'autocheckpoint action' => [static fn (): mixed => $plan()['autocheckpoint_action'], 'permit_autocheckpoint_after_next_writer'],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next233_statements_admitted', 'all_admitted_statements_finalized', 'all_finalizers_current_and_clean']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'row count' => [static fn (): mixed => count($plan()['finalizer_rows']), 3],
    'row reason' => [static fn (): mixed => $plan()['finalizer_rows'][1]['finalizer_reason'], 'finalizer_releases_checkpoint_current_source'],
    'row statement' => [static fn (): mixed => $plan()['finalizer_rows'][1]['statement_name'], 'select-options'],
    'row sqlite done' => [static fn (): mixed => $plan()['finalizer_rows'][1]['sqlite_done_seen'], true],
    'row reset' => [static fn (): mixed => $plan()['finalizer_rows'][1]['reset_called'], true],
    'row reader lease' => [static fn (): mixed => $plan()['finalizer_rows'][1]['reader_lease_released'], true],
    'row wal hook' => [static fn (): mixed => $plan()['finalizer_rows'][1]['wal_hook_receipt'], true],
    'row autocheckpoint' => [static fn (): mixed => $plan()['finalizer_rows'][1]['autocheckpoint_receipt'], true],
    'handoff digest length' => [static fn (): mixed => strlen($plan()['handoff_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('admit_prepared_statement_current_source_next233', $plan()['operation_names'], true), true],
    'operation added admit' => [static fn (): mixed => in_array('admit_next_wal_writer_after_checkpoint_finalizers_next236', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next233', $plan()['dependencies'], true), true],
    'dependency next236' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next236', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-checkpoint-finalizer-before-next-writer', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat checkpoint reset admission'), true],
    'unknown statement blocked' => [static fn (): mixed => $blockedFinalizer(['statement_name' => 'old-statement'])['blocked_finalizer_reasons'], ['finalizer_statement_not_admitted']],
    'stale token blocked' => [static fn (): mixed => $blockedFinalizer(['source_token' => 'old-source'])['blocked_finalizer_reasons'], ['finalizer_source_token_mismatch']],
    'stale generation blocked' => [static fn (): mixed => $blockedFinalizer(['generation' => 235])['blocked_finalizer_reasons'], ['finalizer_generation_mismatch']],
    'stale schema blocked' => [static fn (): mixed => $blockedFinalizer(['schema_cookie' => 431])['blocked_finalizer_reasons'], ['finalizer_schema_cookie_mismatch']],
    'stale digest blocked' => [static fn (): mixed => $blockedFinalizer(['database_digest' => $hash('stale database')])['blocked_finalizer_reasons'], ['finalizer_database_digest_mismatch']],
    'missing done blocked' => [static fn (): mixed => $blockedFinalizer(['sqlite_done_seen' => false])['blocked_finalizer_reasons'], ['finalizer_missing_sqlite_done']],
    'missing reset blocked' => [static fn (): mixed => $blockedFinalizer(['reset_called' => false])['blocked_finalizer_reasons'], ['finalizer_missing_reset']],
    'reader lease blocked' => [static fn (): mixed => $blockedFinalizer(['reader_lease_released' => false])['blocked_finalizer_reasons'], ['finalizer_reader_lease_not_released']],
    'wal hook blocked' => [static fn (): mixed => $blockedFinalizer(['wal_hook_receipt' => false])['blocked_finalizer_reasons'], ['finalizer_wal_hook_receipt_missing']],
    'autocheckpoint blocked' => [static fn (): mixed => $blockedFinalizer(['autocheckpoint_receipt' => false])['blocked_finalizer_reasons'], ['finalizer_autocheckpoint_receipt_missing']],
    'hot journal blocked' => [static fn (): mixed => $blockedFinalizer(['hot_journal_present' => true])['blocked_finalizer_reasons'], ['finalizer_hot_journal_still_visible']],
    'savepoint blocked' => [static fn (): mixed => $blockedFinalizer(['savepoint_depth' => 1])['blocked_finalizer_reasons'], ['finalizer_savepoint_scope_open']],
    'dirty cache blocked' => [static fn (): mixed => $blockedFinalizer(['dirty_reader_cache' => true])['blocked_finalizer_reasons'], ['finalizer_dirty_reader_cache']],
    'combined blocked reasons' => [static fn (): mixed => $blockedFinalizer(['source_token' => 'old-source', 'wal_hook_receipt' => false, 'dirty_reader_cache' => true])['blocked_finalizer_reasons'], ['finalizer_source_token_mismatch', 'finalizer_wal_hook_receipt_missing', 'finalizer_dirty_reader_cache']],
    'blocked status' => [static fn (): mixed => $blockedFinalizer(['source_token' => 'old-source'])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next236'],
    'blocked reason' => [static fn (): mixed => $blockedFinalizer(['source_token' => 'old-source'])['reason'], 'finalized_statements_hold_checkpoint_current_source_before_next_writer'],
    'blocked action' => [static fn (): mixed => $blockedFinalizer(['source_token' => 'old-source'])['writer_action'], 'hold_next_wal_writer_until_statement_finalizers'],
    'blocked reader action' => [static fn (): mixed => $blockedFinalizer(['source_token' => 'old-source'])['reader_action'], 'retain_checkpoint_reader_leases'],
    'blocked wal hook action' => [static fn (): mixed => $blockedFinalizer(['source_token' => 'old-source'])['wal_hook_action'], 'defer_wal_hook_until_clean_finalizers'],
    'blocked autocheckpoint action' => [static fn (): mixed => $blockedFinalizer(['source_token' => 'old-source'])['autocheckpoint_action'], 'suppress_autocheckpoint_before_finalizers'],
    'blocked guards' => [static fn (): mixed => $blockedFinalizer(['source_token' => 'old-source'])['blocked_guard_names'], ['all_admitted_statements_finalized', 'all_finalizers_current_and_clean']],
    'missing statement status' => [static fn (): mixed => $missingStatement()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next236'],
    'missing statement list' => [static fn (): mixed => $missingStatement()['missing_statement_names'], ['select-option-name-index']],
    'missing statement guard' => [static fn (): mixed => $missingStatement()['blocked_guard_names'], ['all_admitted_statements_finalized']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next236 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next236FinalizeForNextWriter(array_replace($statementPlan, ['status' => 'bad']), $finalizers, 237),
    'not admitted rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next236FinalizeForNextWriter(array_replace($statementPlan, ['statement_admission_allowed' => false]), $finalizers, 237),
    'empty finalizers rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next236FinalizeForNextWriter($statementPlan, [], 237),
    'bad next generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next236FinalizeForNextWriter($statementPlan, $finalizers, 0),
    'same generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next236FinalizeForNextWriter($statementPlan, $finalizers, 236),
    'bad source token rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next236FinalizeForNextWriter(array_replace($statementPlan, ['source_token' => 'bad token']), $finalizers, 237),
    'bad current generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next236FinalizeForNextWriter(array_replace($statementPlan, ['next_writer_generation' => 0]), $finalizers, 237),
    'bad schema cookie rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next236FinalizeForNextWriter(array_replace($statementPlan, ['schema_cookie' => 0]), $finalizers, 237),
    'bad database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next236FinalizeForNextWriter(array_replace($statementPlan, ['database_digest' => 'short']), $finalizers, 237),
    'bad admitted statements rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next236FinalizeForNextWriter(array_replace($statementPlan, ['admitted_statement_names' => []]), $finalizers, 237),
    'bad finalizer name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next236FinalizeForNextWriter($statementPlan, [array_replace($finalizers[0], ['name' => 'bad name'])], 237),
    'bad statement name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next236FinalizeForNextWriter($statementPlan, [array_replace($finalizers[0], ['statement_name' => 'bad name'])], 237),
    'bad finalizer generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next236FinalizeForNextWriter($statementPlan, [array_replace($finalizers[0], ['generation' => 0])], 237),
    'bad finalizer digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next236FinalizeForNextWriter($statementPlan, [array_replace($finalizers[0], ['database_digest' => 'short'])], 237),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next236 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
