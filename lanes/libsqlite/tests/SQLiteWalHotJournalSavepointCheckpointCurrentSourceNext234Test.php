<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$walDigest = $digest('next234 restarted wal after hot journal savepoint checkpoint');
$dbDigest = $digest('next234 checkpointed database image');
$shmDigest = $digest('next234 synced shm index');
$token = ['id' => 'wp-hot-journal-current-source-next234', 'epoch' => 234];
$admission = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next211',
    'database_path' => '/srv/www/wp-content/database/wp-next234.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next234.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next234.sqlite-journal',
    'current_source_token' => $token,
    'checkpoint_frame' => 96,
    'checkpoint_cookie' => 90234,
    'schema_cookie' => 1234,
    'next_source_epoch' => 235,
    'checkpoint_admitted' => true,
    'admitted_reader_names' => ['wp-options-import'],
    'reopen_reader_names' => ['wp-plugin-cache'],
];
$scope = static function (string $name, array $pages) use ($digest): array {
    $pageDigests = [];
    foreach ($pages as $page) {
        $pageDigests[$page] = $digest($name . ':page:' . $page);
    }

    return [
        'name' => $name,
        'savepoint_depth' => 0,
        'released' => true,
        'rollback_generation' => 234,
        'checkpoint_frame' => 96,
        'checkpoint_cookie' => 90234,
        'schema_cookie' => 1234,
        'journal_delete_receipt' => true,
        'wal_reset_frame' => 96,
        'reader_names' => ['wp-options-import'],
        'page_digests' => $pageDigests,
    ];
};
$publishReceipt = static function (string $scopeName, array $pages) use ($digest, $token): array {
    $pageDigests = [];
    foreach ($pages as $page) {
        $pageDigests[$page] = $digest($scopeName . ':page:' . $page);
    }

    return [
        'scope_name' => $scopeName,
        'source_token_id' => $token['id'],
        'source_epoch' => 234,
        'checkpoint_frame' => 96,
        'checkpoint_cookie' => 90234,
        'schema_cookie' => 1234,
        'journal_delete_receipt' => true,
        'page_digests' => $pageDigests,
        'next_source_epoch' => 235,
    ];
};
$finalized = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next219Plan($admission, [
    $scope('wp-options-savepoint', [1, 2]),
    $scope('wp-plugin-savepoint', [3, 4]),
]);
$published = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next227Plan($finalized, [
    $publishReceipt('wp-options-savepoint', [1, 2]),
    $publishReceipt('wp-plugin-savepoint', [3, 4]),
]);
$checksum = static fn (array $readmarks): string =>
    hash('sha256', json_encode([23401, 23402, 96, 96, $readmarks, $walDigest], JSON_THROW_ON_ERROR));
$readmarks = ['wp-options-import' => 96, 'wp-plugin-cache' => 96];
$walIndexReopen = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next231VerifyWalIndexReopen($published, [[
    'name' => 'wp-options-shm-reopen-next234',
    'scope_names' => ['wp-options-savepoint', 'wp-plugin-savepoint'],
    'source_token_id' => $token['id'],
    'source_epoch' => 234,
    'next_source_epoch' => 235,
    'checkpoint_frame' => 96,
    'checkpoint_cookie' => 90234,
    'schema_cookie' => 1234,
    'wal_digest' => $walDigest,
    'salt_1' => 23401,
    'salt_2' => 23402,
    'checksum_digest' => $checksum($readmarks),
    'mx_frame' => 96,
    'backfill_frame' => 96,
    'readmark_frames' => $readmarks,
    'readers_reopened' => true,
    'shm_synced' => true,
]], $walDigest);

$receipt = static function (array $overrides = []) use ($walDigest, $dbDigest, $shmDigest, $token): array {
    return array_merge([
        'name' => 'wp-options-durable-handoff-next234',
        'scope_names' => ['wp-options-savepoint', 'wp-plugin-savepoint'],
        'source_token_id' => $token['id'],
        'source_epoch' => 234,
        'next_source_epoch' => 235,
        'checkpoint_frame' => 96,
        'checkpoint_cookie' => 90234,
        'schema_cookie' => 1234,
        'wal_digest' => $walDigest,
        'database_digest' => $dbDigest,
        'shm_digest' => $shmDigest,
        'sync_order' => ['database_sync', 'wal_sync', 'shm_sync', 'journal_unlink', 'directory_sync'],
        'database_synced' => true,
        'wal_synced' => true,
        'shm_synced' => true,
        'journal_unlinked' => true,
        'directory_synced' => true,
        'reader_cache_clean' => true,
        'writer_generation' => 235,
    ], $overrides);
};
$receipts = [$receipt()];
$plan = static fn (?array $inputReopen = null, ?array $inputReceipts = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next234VerifyDurableHandoff(
        $inputReopen ?? $walIndexReopen,
        $inputReceipts ?? $receipts
    );

$badReopen = $walIndexReopen;
$badReopen['status'] = 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next231';
$badReopenFlag = $walIndexReopen;
$badReopenFlag['can_reopen_current_source'] = false;
$badReopenMissing = $walIndexReopen;
unset($badReopenMissing['expected_wal_digest']);
$badReopenDigest = $walIndexReopen;
$badReopenDigest['expected_wal_digest'] = 'not-a-digest';

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next234'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'durable_handoff_receipts_match_reopened_wal_checkpoint_current_source'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $admission['database_path']],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $admission['wal_path']],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $admission['journal_path']],
    'source epoch' => [static fn (): mixed => $plan()['source_epoch'], 234],
    'next source epoch' => [static fn (): mixed => $plan()['next_source_epoch'], 235],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 96],
    'checkpoint cookie' => [static fn (): mixed => $plan()['checkpoint_cookie'], 90234],
    'schema cookie' => [static fn (): mixed => $plan()['schema_cookie'], 1234],
    'expected wal digest' => [static fn (): mixed => $plan()['expected_wal_digest'], $walDigest],
    'publishable scopes' => [static fn (): mixed => $plan()['publishable_scope_names'], ['wp-options-savepoint', 'wp-plugin-savepoint']],
    'covered scopes' => [static fn (): mixed => $plan()['covered_scope_names'], ['wp-options-savepoint', 'wp-plugin-savepoint']],
    'missing scopes empty' => [static fn (): mixed => $plan()['missing_scope_names'], []],
    'extra scopes empty' => [static fn (): mixed => $plan()['extra_scope_names'], []],
    'duplicates empty' => [static fn (): mixed => $plan()['duplicate_receipt_names'], []],
    'receipt names' => [static fn (): mixed => $plan()['receipt_names'], ['wp-options-durable-handoff-next234']],
    'durable names' => [static fn (): mixed => $plan()['durable_receipt_names'], ['wp-options-durable-handoff-next234']],
    'blocked receipt names empty' => [static fn (): mixed => $plan()['blocked_receipt_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next231_wal_index_reopen_admitted', 'no_duplicate_durable_handoff_receipts', 'all_publishable_scopes_have_durable_handoff_receipts', 'no_unpublished_scope_durable_handoff_receipts', 'all_receipts_match_reopened_wal_index_source', 'sync_order_respects_sqlite_commit_boundary']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'can serve durable' => [static fn (): mixed => $plan()['can_serve_durable_current_source'], true],
    'digest length' => [static fn (): mixed => strlen($plan()['durable_handoff_digest']), 64],
    'operation verify' => [static fn (): mixed => in_array('verify_durable_handoff_sync_receipts_current_source_next234', $plan()['operation_names'], true), true],
    'operation fence' => [static fn (): mixed => in_array('fence_database_wal_shm_journal_directory_receipts_next234', $plan()['operation_names'], true), true],
    'operation serve' => [static fn (): mixed => in_array('serve_durable_reopened_checkpoint_source_next234', $plan()['operation_names'], true), true],
    'dependency next234' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next234', $plan()['dependencies'], true), true],
    'dependency sync receipts' => [static fn (): mixed => in_array('sqlite-wal-durable-handoff-sync-receipts', $plan()['dependencies'], true), true],
    'dependency application' => [static fn (): mixed => in_array('application-import-durable-wal-current-source-handoff', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next231 readmark reopen checks'), true],
    'row durable' => [static fn (): mixed => $plan()['receipt_rows'][0]['durable'], true],
    'row reason' => [static fn (): mixed => $plan()['receipt_rows'][0]['receipt_reason'], 'durable_handoff_receipt_matches_reopened_checkpoint'],
    'row scopes' => [static fn (): mixed => $plan()['receipt_rows'][0]['scope_names'], ['wp-options-savepoint', 'wp-plugin-savepoint']],
    'row token' => [static fn (): mixed => $plan()['receipt_rows'][0]['source_token_id'], $token['id']],
    'row epoch' => [static fn (): mixed => $plan()['receipt_rows'][0]['source_epoch'], 234],
    'row next epoch' => [static fn (): mixed => $plan()['receipt_rows'][0]['next_source_epoch'], 235],
    'row frame' => [static fn (): mixed => $plan()['receipt_rows'][0]['checkpoint_frame'], 96],
    'row wal digest' => [static fn (): mixed => $plan()['receipt_rows'][0]['wal_digest'], $walDigest],
    'row db digest' => [static fn (): mixed => $plan()['receipt_rows'][0]['database_digest'], $dbDigest],
    'row shm digest' => [static fn (): mixed => $plan()['receipt_rows'][0]['shm_digest'], $shmDigest],
    'row order' => [static fn (): mixed => $plan()['receipt_rows'][0]['sync_order'], ['database_sync', 'wal_sync', 'shm_sync', 'journal_unlink', 'directory_sync']],
    'row writer generation' => [static fn (): mixed => $plan()['receipt_rows'][0]['writer_generation'], 235],
    'row database synced' => [static fn (): mixed => $plan()['receipt_rows'][0]['database_synced'], true],
    'row wal synced' => [static fn (): mixed => $plan()['receipt_rows'][0]['wal_synced'], true],
    'row shm synced' => [static fn (): mixed => $plan()['receipt_rows'][0]['shm_synced'], true],
    'row journal unlinked' => [static fn (): mixed => $plan()['receipt_rows'][0]['journal_unlinked'], true],
    'row directory synced' => [static fn (): mixed => $plan()['receipt_rows'][0]['directory_synced'], true],
    'row reader cache clean' => [static fn (): mixed => $plan()['receipt_rows'][0]['reader_cache_clean'], true],
    'row receipt digest length' => [static fn (): mixed => strlen($plan()['receipt_rows'][0]['receipt_digest']), 64],
    'missing scope status' => [static fn (): mixed => $plan(null, [$receipt(['scope_names' => ['wp-options-savepoint']])])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next234'],
    'missing scope reason' => [static fn (): mixed => $plan(null, [$receipt(['scope_names' => ['wp-options-savepoint']])])['blocked_reasons'], ['durable_handoff_scope_missing']],
    'extra scope reason' => [static fn (): mixed => in_array('durable_handoff_unpublished_scope', $plan(null, [$receipt(['scope_names' => ['wp-options-savepoint', 'wp-plugin-savepoint', 'not-published']])])['blocked_reasons'], true), true],
    'duplicate reason' => [static fn (): mixed => in_array('duplicate_durable_handoff_receipt', $plan(null, [$receipt(), $receipt()])['blocked_reasons'], true), true],
    'bad token reason' => [static fn (): mixed => $plan(null, [$receipt(['source_token_id' => 'stale-token'])])['receipt_rows'][0]['blocked_reasons'], ['durable_handoff_source_token_mismatch']],
    'bad source epoch reason' => [static fn (): mixed => $plan(null, [$receipt(['source_epoch' => 233])])['receipt_rows'][0]['blocked_reasons'], ['durable_handoff_source_epoch_mismatch']],
    'bad next epoch reason' => [static fn (): mixed => $plan(null, [$receipt(['next_source_epoch' => 236])])['receipt_rows'][0]['blocked_reasons'], ['durable_handoff_next_source_epoch_mismatch']],
    'bad frame reason' => [static fn (): mixed => $plan(null, [$receipt(['checkpoint_frame' => 95])])['receipt_rows'][0]['blocked_reasons'], ['durable_handoff_checkpoint_frame_mismatch']],
    'bad cookie reason' => [static fn (): mixed => $plan(null, [$receipt(['checkpoint_cookie' => 1])])['receipt_rows'][0]['blocked_reasons'], ['durable_handoff_checkpoint_cookie_mismatch']],
    'bad schema reason' => [static fn (): mixed => $plan(null, [$receipt(['schema_cookie' => 1])])['receipt_rows'][0]['blocked_reasons'], ['durable_handoff_schema_cookie_mismatch']],
    'bad wal reason' => [static fn (): mixed => $plan(null, [$receipt(['wal_digest' => $digest('stale wal')])])['receipt_rows'][0]['blocked_reasons'], ['durable_handoff_wal_digest_mismatch']],
    'bad writer generation reason' => [static fn (): mixed => $plan(null, [$receipt(['writer_generation' => 234])])['receipt_rows'][0]['blocked_reasons'], ['durable_handoff_writer_generation_mismatch']],
    'missing database sync reason' => [static fn (): mixed => $plan(null, [$receipt(['database_synced' => false])])['receipt_rows'][0]['blocked_reasons'], ['database_synced_missing']],
    'missing wal sync reason' => [static fn (): mixed => $plan(null, [$receipt(['wal_synced' => false])])['receipt_rows'][0]['blocked_reasons'], ['wal_synced_missing']],
    'missing shm sync reason' => [static fn (): mixed => $plan(null, [$receipt(['shm_synced' => false])])['receipt_rows'][0]['blocked_reasons'], ['shm_synced_missing']],
    'missing journal unlink reason' => [static fn (): mixed => $plan(null, [$receipt(['journal_unlinked' => false])])['receipt_rows'][0]['blocked_reasons'], ['journal_unlinked_missing']],
    'missing directory sync reason' => [static fn (): mixed => $plan(null, [$receipt(['directory_synced' => false])])['receipt_rows'][0]['blocked_reasons'], ['directory_synced_missing']],
    'dirty reader cache reason' => [static fn (): mixed => $plan(null, [$receipt(['reader_cache_clean' => false])])['receipt_rows'][0]['blocked_reasons'], ['reader_cache_clean_missing']],
    'bad order reason' => [static fn (): mixed => $plan(null, [$receipt(['sync_order' => ['wal_sync', 'database_sync', 'shm_sync', 'journal_unlink', 'directory_sync']])])['receipt_rows'][0]['blocked_reasons'], ['durable_handoff_sync_order_violation']],
    'bad order guard' => [static fn (): mixed => in_array('sync_order_respects_sqlite_commit_boundary', $plan(null, [$receipt(['sync_order' => ['wal_sync', 'database_sync', 'shm_sync', 'journal_unlink', 'directory_sync']])])['blocked_guard_names'], true), true],
    'blocked operation hold' => [static fn (): mixed => in_array('hold_reopened_checkpoint_source_until_durable_next234', $plan(null, [$receipt(['directory_synced' => false])])['operation_names'], true), true],
    'bad reopen status throws' => [static function () use ($plan, $badReopen): string {
        try {
            $plan($badReopen);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
        return 'no exception';
    }, 'SQLite WAL hot-journal current-source next234 requires an admitted next231 WAL-index reopen plan'],
    'bad reopen flag throws' => [static function () use ($plan, $badReopenFlag): string {
        try {
            $plan($badReopenFlag);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
        return 'no exception';
    }, 'SQLite WAL hot-journal current-source next234 requires an admitted next231 WAL-index reopen plan'],
    'missing reopen key throws' => [static function () use ($plan, $badReopenMissing): string {
        try {
            $plan($badReopenMissing);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
        return 'no exception';
    }, 'SQLite WAL hot-journal current-source next234 missing reopen expected_wal_digest'],
    'bad reopen digest throws' => [static function () use ($plan, $badReopenDigest): string {
        try {
            $plan($badReopenDigest);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
        return 'no exception';
    }, 'SQLite WAL hot-journal current-source next234 expected WAL digest must be a sha256 string'],
    'empty receipts throws' => [static function () use ($plan): string {
        try {
            $plan(null, []);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
        return 'no exception';
    }, 'SQLite WAL hot-journal current-source next234 requires durable handoff sync receipts'],
    'missing receipt key throws' => [static function () use ($plan, $receipt): string {
        $bad = $receipt();
        unset($bad['database_digest']);
        try {
            $plan(null, [$bad]);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
        return 'no exception';
    }, 'SQLite WAL hot-journal current-source next234 missing durable handoff receipt database_digest'],
    'bad receipt digest throws' => [static function () use ($plan, $receipt): string {
        try {
            $plan(null, [$receipt(['database_digest' => 'not-a-digest'])]);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
        return 'no exception';
    }, 'SQLite WAL hot-journal current-source next234 wp-options-durable-handoff-next234 database_digest must be a sha256 string'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next234 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
