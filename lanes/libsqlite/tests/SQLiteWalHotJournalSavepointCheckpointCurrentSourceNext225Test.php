<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$token = ['id' => 'wp-next225-current-source', 'epoch' => 225];
$scopeDigest = $hash('next225 finalized savepoint scopes');
$publishPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next219',
    'database_path' => '/srv/www/wp-content/database/wp-next225.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next225.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next225.sqlite-journal',
    'current_source_token' => $token,
    'checkpoint_frame' => 61,
    'checkpoint_cookie' => 22561,
    'schema_cookie' => 22517,
    'next_source_epoch' => 226,
    'savepoint_scope_digest' => $scopeDigest,
    'checkpoint_next_source_published' => true,
    'operation_names' => ['publish_checkpoint_next_source_after_savepoint_finalization_next219'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next219'],
];
$receipt = static function (string $name, string $region, array $override = []) use ($token, $publishPlan, $scopeDigest, $hash): array {
    return array_replace([
        'name' => $name,
        'header_region' => $region,
        'source_id' => $token['id'],
        'source_epoch' => $token['epoch'],
        'checkpoint_frame' => $publishPlan['checkpoint_frame'],
        'checkpoint_cookie' => $publishPlan['checkpoint_cookie'],
        'schema_cookie' => $publishPlan['schema_cookie'],
        'next_source_epoch' => $publishPlan['next_source_epoch'],
        'savepoint_scope_digest' => $scopeDigest,
        'header_digest' => $hash($name . ':' . $region),
        'write_synced' => true,
    ], $override);
};
$receipts = [
    $receipt('database-header-write', 'database-header'),
    $receipt('wal-index-header-write', 'wal-index-header'),
    $receipt('change-counter-write', 'change-counter'),
];
$blockedReceipts = [
    $receipt('wrong-source-id', 'database-header', ['source_id' => 'stale-source']),
    $receipt('wrong-source-epoch', 'database-header', ['source_epoch' => 224]),
    $receipt('wrong-frame', 'database-header', ['checkpoint_frame' => 60]),
    $receipt('wrong-checkpoint-cookie', 'database-header', ['checkpoint_cookie' => 22560]),
    $receipt('wrong-schema-cookie', 'database-header', ['schema_cookie' => 22516]),
    $receipt('wrong-next-epoch', 'database-header', ['next_source_epoch' => 227]),
    $receipt('wrong-scope-digest', 'database-header', ['savepoint_scope_digest' => $hash('old savepoint scope')]),
    $receipt('unsynced-write', 'database-header', ['write_synced' => false]),
    $receipt('hot-journal', 'database-header', ['hot_journal_present' => true]),
    $receipt('stale-header-bytes', 'database-header', ['stale_header_bytes' => true]),
];

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next225Plan($publishPlan, $receipts);
$blocked = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next225Plan($publishPlan, $blockedReceipts);
$badStatus = $publishPlan;
$badStatus['status'] = 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next219';
$notPublished = $publishPlan;
$notPublished['checkpoint_next_source_published'] = false;
$missingRegionReceipts = [
    $receipt('database-header-write', 'database-header'),
    $receipt('change-counter-write', 'change-counter'),
];

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next225'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'database_header_receipts_publish_checkpoint_current_source'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next225.sqlite'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next225.sqlite-wal'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next225.sqlite-journal'],
    'token id' => [static fn (): mixed => $plan()['current_source_token']['id'], 'wp-next225-current-source'],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 61],
    'checkpoint cookie' => [static fn (): mixed => $plan()['checkpoint_cookie'], 22561],
    'schema cookie' => [static fn (): mixed => $plan()['schema_cookie'], 22517],
    'next source epoch' => [static fn (): mixed => $plan()['next_source_epoch'], 226],
    'scope digest' => [static fn (): mixed => $plan()['savepoint_scope_digest'], $scopeDigest],
    'header published' => [static fn (): mixed => $plan()['checkpoint_current_source_header_published'], true],
    'receipt names' => [static fn (): mixed => $plan()['published_receipt_names'], ['database-header-write', 'wal-index-header-write', 'change-counter-write']],
    'blocked names empty' => [static fn (): mixed => $plan()['blocked_receipt_names'], []],
    'receipt count' => [static fn (): mixed => count($plan()['receipt_rows']), 3],
    'first receipt published' => [static fn (): mixed => $plan()['receipt_rows'][0]['published'], true],
    'first receipt reason' => [static fn (): mixed => $plan()['receipt_rows'][0]['receipt_reason'], 'header_receipt_published_current_source'],
    'first receipt transition' => [static fn (): mixed => $plan()['receipt_rows'][0]['receipt_transition'], 'database-header-write>publish-header-current-source:next225'],
    'published regions' => [static fn (): mixed => $plan()['published_header_regions'], ['change-counter', 'database-header', 'wal-index-header']],
    'required regions' => [static fn (): mixed => $plan()['required_header_regions'], ['change-counter', 'database-header', 'wal-index-header']],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next219_checkpoint_source_published', 'database_header_receipts_current', 'required_header_regions_written', 'next_source_epoch_advanced']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'receipt digest length' => [static fn (): mixed => strlen($plan()['header_receipt_digest']), 64],
    'inherited operation' => [static fn (): mixed => $plan()['operation_names'][0], 'publish_checkpoint_next_source_after_savepoint_finalization_next219'],
    'verify operation present' => [static fn (): mixed => in_array('verify_checkpoint_database_header_receipts_next225', $plan()['operation_names'], true), true],
    'publish operation present' => [static fn (): mixed => in_array('publish_database_header_current_source_after_hot_journal_next225', $plan()['operation_names'], true), true],
    'fence operation present' => [static fn (): mixed => in_array('fence_stale_hot_journal_header_after_savepoint_checkpoint_next225', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next219', $plan()['dependencies'], true), true],
    'dependency next225' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next225', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-checkpoint-header-receipts', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next219 savepoint-scope finalization'), true],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next225'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'database_header_receipts_block_checkpoint_current_source'],
    'blocked published flag' => [static fn (): mixed => $blocked()['checkpoint_current_source_header_published'], false],
    'blocked names' => [static fn (): mixed => $blocked()['blocked_receipt_names'], ['wrong-source-id', 'wrong-source-epoch', 'wrong-frame', 'wrong-checkpoint-cookie', 'wrong-schema-cookie', 'wrong-next-epoch', 'wrong-scope-digest', 'unsynced-write', 'hot-journal', 'stale-header-bytes']],
    'wrong source reason' => [static fn (): mixed => $blocked()['receipt_rows'][0]['receipt_reason'], 'header_source_id_mismatch'],
    'wrong epoch reason' => [static fn (): mixed => $blocked()['receipt_rows'][1]['receipt_reason'], 'header_source_epoch_mismatch'],
    'wrong frame reason' => [static fn (): mixed => $blocked()['receipt_rows'][2]['receipt_reason'], 'header_checkpoint_frame_mismatch'],
    'wrong checkpoint cookie reason' => [static fn (): mixed => $blocked()['receipt_rows'][3]['receipt_reason'], 'header_checkpoint_cookie_mismatch'],
    'wrong schema cookie reason' => [static fn (): mixed => $blocked()['receipt_rows'][4]['receipt_reason'], 'header_schema_cookie_mismatch'],
    'wrong next epoch reason' => [static fn (): mixed => $blocked()['receipt_rows'][5]['receipt_reason'], 'header_next_source_epoch_mismatch'],
    'wrong scope reason' => [static fn (): mixed => $blocked()['receipt_rows'][6]['receipt_reason'], 'header_savepoint_scope_digest_mismatch'],
    'unsynced reason' => [static fn (): mixed => $blocked()['receipt_rows'][7]['receipt_reason'], 'header_write_not_synced'],
    'hot journal reason' => [static fn (): mixed => $blocked()['receipt_rows'][8]['receipt_reason'], 'header_hot_journal_still_present'],
    'stale header reason' => [static fn (): mixed => $blocked()['receipt_rows'][9]['receipt_reason'], 'header_stale_bytes_observed'],
    'blocked guard names' => [static fn (): mixed => $blocked()['blocked_guard_names'], ['database_header_receipts_current', 'required_header_regions_written']],
    'bad status guard' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next225Plan($badStatus, $receipts)['blocked_guard_names'], ['next219_checkpoint_source_published']],
    'not published guard' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next225Plan($notPublished, $receipts)['blocked_guard_names'], ['next219_checkpoint_source_published']],
    'missing region guard' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next225Plan($publishPlan, $missingRegionReceipts)['blocked_guard_names'], ['required_header_regions_written']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next225 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing publish field rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next225Plan(['status' => 'x'], $receipts),
    'bad token rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next225Plan(array_merge($publishPlan, ['current_source_token' => ['id' => '', 'epoch' => 0]]), $receipts),
    'bad frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next225Plan(array_merge($publishPlan, ['checkpoint_frame' => 0]), $receipts),
    'bad scope digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next225Plan(array_merge($publishPlan, ['savepoint_scope_digest' => 'short']), $receipts),
    'empty receipts rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next225Plan($publishPlan, []),
    'missing receipt field rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next225Plan($publishPlan, [['name' => 'bad']]),
    'empty receipt name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next225Plan($publishPlan, [$receipt('', 'database-header')]),
    'empty region rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next225Plan($publishPlan, [$receipt('bad', '')]),
    'bad receipt integer rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next225Plan($publishPlan, [$receipt('bad', 'database-header', ['source_epoch' => -1])]),
    'bad header digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next225Plan($publishPlan, [$receipt('bad', 'database-header', ['header_digest' => 'short'])]),
    'bad receipt scope digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next225Plan($publishPlan, [$receipt('bad', 'database-header', ['savepoint_scope_digest' => 'short'])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next225 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
