<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$pageSize = 1024;
$checkpointFrame = 64;
$walLength = 32 + ($checkpointFrame * (24 + $pageSize));
$walDigest = $digest('next237 durable wal sidecar after hot journal savepoint checkpoint');
$dbDigest = $digest('next237 checkpointed database image');
$shmDigest = $digest('next237 synced shm index');
$token = ['id' => 'wp-hot-journal-current-source-next237', 'epoch' => 237];
$admission = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next211',
    'database_path' => '/srv/www/wp-content/database/wp-next237.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next237.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next237.sqlite-journal',
    'current_source_token' => $token,
    'checkpoint_frame' => $checkpointFrame,
    'checkpoint_cookie' => 90237,
    'schema_cookie' => 1237,
    'next_source_epoch' => 238,
    'checkpoint_admitted' => true,
    'admitted_reader_names' => ['wp-options-import'],
    'reopen_reader_names' => ['wp-plugin-cache'],
];
$scope = static function (string $name, array $pages) use ($digest, $checkpointFrame): array {
    $pageDigests = [];
    foreach ($pages as $page) {
        $pageDigests[$page] = $digest($name . ':page:' . $page);
    }

    return [
        'name' => $name,
        'savepoint_depth' => 0,
        'released' => true,
        'rollback_generation' => 237,
        'checkpoint_frame' => $checkpointFrame,
        'checkpoint_cookie' => 90237,
        'schema_cookie' => 1237,
        'journal_delete_receipt' => true,
        'wal_reset_frame' => $checkpointFrame,
        'reader_names' => ['wp-options-import'],
        'page_digests' => $pageDigests,
    ];
};
$publishReceipt = static function (string $scopeName, array $pages) use ($digest, $token, $checkpointFrame): array {
    $pageDigests = [];
    foreach ($pages as $page) {
        $pageDigests[$page] = $digest($scopeName . ':page:' . $page);
    }

    return [
        'scope_name' => $scopeName,
        'source_token_id' => $token['id'],
        'source_epoch' => 237,
        'checkpoint_frame' => $checkpointFrame,
        'checkpoint_cookie' => 90237,
        'schema_cookie' => 1237,
        'journal_delete_receipt' => true,
        'page_digests' => $pageDigests,
        'next_source_epoch' => 238,
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
$readmarks = ['wp-options-import' => $checkpointFrame, 'wp-plugin-cache' => $checkpointFrame];
$checksum = static fn (array $pins): string =>
    hash('sha256', json_encode([23701, 23702, $checkpointFrame, $checkpointFrame, $pins, $walDigest], JSON_THROW_ON_ERROR));
$walIndexReopen = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next231VerifyWalIndexReopen($published, [[
    'name' => 'wp-options-shm-reopen-next237',
    'scope_names' => ['wp-options-savepoint', 'wp-plugin-savepoint'],
    'source_token_id' => $token['id'],
    'source_epoch' => 237,
    'next_source_epoch' => 238,
    'checkpoint_frame' => $checkpointFrame,
    'checkpoint_cookie' => 90237,
    'schema_cookie' => 1237,
    'wal_digest' => $walDigest,
    'salt_1' => 23701,
    'salt_2' => 23702,
    'checksum_digest' => $checksum($readmarks),
    'mx_frame' => $checkpointFrame,
    'backfill_frame' => $checkpointFrame,
    'readmark_frames' => $readmarks,
    'readers_reopened' => true,
    'shm_synced' => true,
]], $walDigest);
$durableReceipt = static function (array $overrides = []) use ($walDigest, $dbDigest, $shmDigest, $token, $checkpointFrame): array {
    return array_merge([
        'name' => 'wp-options-durable-handoff-next237',
        'scope_names' => ['wp-options-savepoint', 'wp-plugin-savepoint'],
        'source_token_id' => $token['id'],
        'source_epoch' => 237,
        'next_source_epoch' => 238,
        'checkpoint_frame' => $checkpointFrame,
        'checkpoint_cookie' => 90237,
        'schema_cookie' => 1237,
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
        'writer_generation' => 238,
    ], $overrides);
};
$durable = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next234VerifyDurableHandoff($walIndexReopen, [$durableReceipt()]);
$sidecar = static function (array $overrides = []) use ($walDigest, $token, $checkpointFrame, $pageSize, $walLength, $checksum, $readmarks): array {
    return array_merge([
        'name' => 'wp-options-wal-sidecar-next237',
        'source_token_id' => $token['id'],
        'source_epoch' => 237,
        'next_source_epoch' => 238,
        'checkpoint_frame' => $checkpointFrame,
        'checkpoint_cookie' => 90237,
        'schema_cookie' => 1237,
        'wal_digest' => $walDigest,
        'salt_1' => 23701,
        'salt_2' => 23702,
        'page_size' => $pageSize,
        'frame_count' => $checkpointFrame,
        'byte_length' => $walLength,
        'last_commit_frame' => $checkpointFrame,
        'checksum_digest' => $checksum($readmarks),
        'hot_journal_visible' => false,
        'savepoint_depth' => 0,
        'writer_generation' => 238,
        'directory_synced' => true,
    ], $overrides);
};
$pins = ['wp-options-import' => 64, 'wp-plugin-cache' => 63];
$plan = static fn (?array $inputDurable = null, ?array $inputSidecars = null, ?array $inputPins = null, ?int $inputPageSize = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next237VerifySidecarBoundary(
        $inputDurable ?? $durable,
        $inputSidecars ?? [$sidecar()],
        $inputPins ?? $pins,
        $inputPageSize ?? $pageSize
    );

$badDurable = $durable;
$badDurable['status'] = 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next234';
$badDurableFlag = $durable;
$badDurableFlag['can_serve_durable_current_source'] = false;
$badDurableMissing = $durable;
unset($badDurableMissing['expected_wal_digest']);
$badDurableDigest = $durable;
$badDurableDigest['expected_wal_digest'] = 'not-a-digest';

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next237'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'wal_sidecar_boundary_matches_durable_hot_journal_checkpoint_source'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $admission['database_path']],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $admission['wal_path']],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $admission['journal_path']],
    'source epoch' => [static fn (): mixed => $plan()['source_epoch'], 237],
    'next source epoch' => [static fn (): mixed => $plan()['next_source_epoch'], 238],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 64],
    'checkpoint cookie' => [static fn (): mixed => $plan()['checkpoint_cookie'], 90237],
    'schema cookie' => [static fn (): mixed => $plan()['schema_cookie'], 1237],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'expected wal digest' => [static fn (): mixed => $plan()['expected_wal_digest'], $walDigest],
    'expected wal length' => [static fn (): mixed => $plan()['expected_wal_sidecar_length'], $walLength],
    'sidecar names' => [static fn (): mixed => $plan()['sidecar_names'], ['wp-options-wal-sidecar-next237']],
    'admitted sidecars' => [static fn (): mixed => $plan()['admitted_sidecar_names'], ['wp-options-wal-sidecar-next237']],
    'blocked sidecars empty' => [static fn (): mixed => $plan()['blocked_sidecar_names'], []],
    'duplicate sidecars empty' => [static fn (): mixed => $plan()['duplicate_sidecar_names'], []],
    'reader pins' => [static fn (): mixed => $plan()['reader_pin_rows'], [['name' => 'wp-options-import', 'readmark_frame' => 64, 'within_checkpoint' => true], ['name' => 'wp-plugin-cache', 'readmark_frame' => 63, 'within_checkpoint' => true]]],
    'blocked reader pins empty' => [static fn (): mixed => $plan()['blocked_reader_pin_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next234_durable_handoff_admitted', 'wal_sidecar_length_matches_checkpoint_frame', 'no_duplicate_wal_sidecar_receipts', 'reader_pins_do_not_cross_checkpoint_frame', 'at_least_one_sidecar_receipt_admitted']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'can reuse sidecar' => [static fn (): mixed => $plan()['can_reuse_wal_sidecar'], true],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'reuse_durable_wal_sidecar_boundary'],
    'pager action' => [static fn (): mixed => $plan()['pager_action'], 'serve_current_source_after_sidecar_boundary_check'],
    'digest length' => [static fn (): mixed => strlen($plan()['sidecar_boundary_digest']), 64],
    'operation verify' => [static fn (): mixed => in_array('verify_wal_sidecar_boundary_current_source_next237', $plan()['operation_names'], true), true],
    'operation reuse' => [static fn (): mixed => in_array('reuse_checkpoint_wal_sidecar_current_source_next237', $plan()['operation_names'], true), true],
    'dependency next237' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next237', $plan()['dependencies'], true), true],
    'dependency sidecar' => [static fn (): mixed => in_array('sqlite-wal-sidecar-boundary-current-source', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-wal-sidecar-boundary-after-hot-journal', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat durable sync receipt admission'), true],
    'row admitted' => [static fn (): mixed => $plan()['sidecar_rows'][0]['admitted'], true],
    'row reason' => [static fn (): mixed => $plan()['sidecar_rows'][0]['sidecar_reason'], 'wal_sidecar_boundary_matches_checkpoint_current_source'],
    'row token' => [static fn (): mixed => $plan()['sidecar_rows'][0]['source_token_id'], $token['id']],
    'row source epoch' => [static fn (): mixed => $plan()['sidecar_rows'][0]['source_epoch'], 237],
    'row next epoch' => [static fn (): mixed => $plan()['sidecar_rows'][0]['next_source_epoch'], 238],
    'row frame' => [static fn (): mixed => $plan()['sidecar_rows'][0]['checkpoint_frame'], 64],
    'row cookie' => [static fn (): mixed => $plan()['sidecar_rows'][0]['checkpoint_cookie'], 90237],
    'row schema' => [static fn (): mixed => $plan()['sidecar_rows'][0]['schema_cookie'], 1237],
    'row wal digest' => [static fn (): mixed => $plan()['sidecar_rows'][0]['wal_digest'], $walDigest],
    'row salt1' => [static fn (): mixed => $plan()['sidecar_rows'][0]['salt_1'], 23701],
    'row salt2' => [static fn (): mixed => $plan()['sidecar_rows'][0]['salt_2'], 23702],
    'row page size' => [static fn (): mixed => $plan()['sidecar_rows'][0]['page_size'], $pageSize],
    'row frame count' => [static fn (): mixed => $plan()['sidecar_rows'][0]['frame_count'], 64],
    'row byte length' => [static fn (): mixed => $plan()['sidecar_rows'][0]['byte_length'], $walLength],
    'row commit frame' => [static fn (): mixed => $plan()['sidecar_rows'][0]['last_commit_frame'], 64],
    'row hot journal hidden' => [static fn (): mixed => $plan()['sidecar_rows'][0]['hot_journal_visible'], false],
    'row savepoint depth' => [static fn (): mixed => $plan()['sidecar_rows'][0]['savepoint_depth'], 0],
    'row writer generation' => [static fn (): mixed => $plan()['sidecar_rows'][0]['writer_generation'], 238],
    'row directory synced' => [static fn (): mixed => $plan()['sidecar_rows'][0]['directory_synced'], true],
    'bad token reason' => [static fn (): mixed => $plan(null, [$sidecar(['source_token_id' => 'stale-token'])])['sidecar_rows'][0]['blocked_reasons'], ['sidecar_source_token_mismatch']],
    'bad source epoch reason' => [static fn (): mixed => $plan(null, [$sidecar(['source_epoch' => 236])])['sidecar_rows'][0]['blocked_reasons'], ['sidecar_source_epoch_mismatch']],
    'bad next epoch reason' => [static fn (): mixed => $plan(null, [$sidecar(['next_source_epoch' => 239])])['sidecar_rows'][0]['blocked_reasons'], ['sidecar_next_source_epoch_mismatch']],
    'bad frame reason' => [static fn (): mixed => $plan(null, [$sidecar(['checkpoint_frame' => 63])])['sidecar_rows'][0]['blocked_reasons'], ['sidecar_checkpoint_frame_mismatch']],
    'bad frame count reason' => [static fn (): mixed => $plan(null, [$sidecar(['frame_count' => 63])])['sidecar_rows'][0]['blocked_reasons'], ['sidecar_checkpoint_frame_mismatch']],
    'bad cookie reason' => [static fn (): mixed => $plan(null, [$sidecar(['checkpoint_cookie' => 1])])['sidecar_rows'][0]['blocked_reasons'], ['sidecar_checkpoint_cookie_mismatch']],
    'bad schema reason' => [static fn (): mixed => $plan(null, [$sidecar(['schema_cookie' => 1])])['sidecar_rows'][0]['blocked_reasons'], ['sidecar_schema_cookie_mismatch']],
    'bad digest reason' => [static fn (): mixed => $plan(null, [$sidecar(['wal_digest' => $digest('stale wal')])])['sidecar_rows'][0]['blocked_reasons'], ['sidecar_wal_digest_mismatch']],
    'bad page size reason' => [static fn (): mixed => $plan(null, [$sidecar(['page_size' => 512])])['sidecar_rows'][0]['blocked_reasons'], ['sidecar_page_size_mismatch']],
    'bad byte length reason' => [static fn (): mixed => $plan(null, [$sidecar(['byte_length' => $walLength - 1])])['sidecar_rows'][0]['blocked_reasons'], ['sidecar_byte_length_mismatch']],
    'bad commit reason' => [static fn (): mixed => $plan(null, [$sidecar(['last_commit_frame' => 63])])['sidecar_rows'][0]['blocked_reasons'], ['sidecar_last_commit_frame_mismatch']],
    'bad writer reason' => [static fn (): mixed => $plan(null, [$sidecar(['writer_generation' => 237])])['sidecar_rows'][0]['blocked_reasons'], ['sidecar_writer_generation_mismatch']],
    'bad salt reason' => [static fn (): mixed => $plan(null, [$sidecar(['salt_2' => 23701])])['sidecar_rows'][0]['blocked_reasons'], ['sidecar_salt_pair_invalid']],
    'hot journal reason' => [static fn (): mixed => $plan(null, [$sidecar(['hot_journal_visible' => true])])['sidecar_rows'][0]['blocked_reasons'], ['sidecar_hot_journal_visible']],
    'open savepoint reason' => [static fn (): mixed => $plan(null, [$sidecar(['savepoint_depth' => 1])])['sidecar_rows'][0]['blocked_reasons'], ['sidecar_savepoint_scope_open']],
    'missing directory sync reason' => [static fn (): mixed => $plan(null, [$sidecar(['directory_synced' => false])])['sidecar_rows'][0]['blocked_reasons'], ['sidecar_directory_sync_missing']],
    'duplicate reason' => [static fn (): mixed => in_array('duplicate_wal_sidecar_receipt', $plan(null, [$sidecar(), $sidecar()])['blocked_reasons'], true), true],
    'reader pin reason' => [static fn (): mixed => $plan(null, null, ['wp-options-import' => 65])['blocked_reasons'], ['reader_pin_beyond_checkpoint_frame']],
    'reader pin blocked name' => [static fn (): mixed => $plan(null, null, ['wp-options-import' => 65])['blocked_reader_pin_names'], ['wp-options-import']],
    'blocked status' => [static fn (): mixed => $plan(null, [$sidecar(['byte_length' => $walLength - 1])])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next237'],
    'blocked wal action' => [static fn (): mixed => $plan(null, [$sidecar(['byte_length' => $walLength - 1])])['wal_action'], 'truncate_or_reopen_wal_sidecar_before_reuse'],
    'blocked operation' => [static fn (): mixed => in_array('block_checkpoint_wal_sidecar_current_source_next237', $plan(null, [$sidecar(['byte_length' => $walLength - 1])])['operation_names'], true), true],
    'bad durable status throws' => [static function () use ($plan, $badDurable): string {
        try {
            $plan($badDurable);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
        return 'no exception';
    }, 'SQLite WAL hot-journal current-source next237 requires admitted next234 durable handoff'],
    'bad durable flag throws' => [static function () use ($plan, $badDurableFlag): string {
        try {
            $plan($badDurableFlag);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
        return 'no exception';
    }, 'SQLite WAL hot-journal current-source next237 requires admitted next234 durable handoff'],
    'missing durable key throws' => [static function () use ($plan, $badDurableMissing): string {
        try {
            $plan($badDurableMissing);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
        return 'no exception';
    }, 'SQLite WAL hot-journal current-source next237 missing durable expected_wal_digest'],
    'bad durable digest throws' => [static function () use ($plan, $badDurableDigest): string {
        try {
            $plan($badDurableDigest);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
        return 'no exception';
    }, 'SQLite WAL hot-journal current-source next237 expected WAL digest must be a sha256 string'],
    'empty sidecars throws' => [static function () use ($plan): string {
        try {
            $plan(null, []);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
        return 'no exception';
    }, 'SQLite WAL hot-journal current-source next237 requires WAL sidecar receipts'],
    'bad page size throws' => [static function () use ($plan): string {
        try {
            $plan(null, null, null, 0);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
        return 'no exception';
    }, 'SQLite WAL hot-journal current-source next237 page size must be positive'],
    'missing sidecar key throws' => [static function () use ($plan, $sidecar): string {
        $bad = $sidecar();
        unset($bad['byte_length']);
        try {
            $plan(null, [$bad]);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
        return 'no exception';
    }, 'SQLite WAL hot-journal current-source next237 missing WAL sidecar byte_length'],
    'bad sidecar digest throws' => [static function () use ($plan, $sidecar): string {
        try {
            $plan(null, [$sidecar(['checksum_digest' => 'not-a-digest'])]);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
        return 'no exception';
    }, 'SQLite WAL hot-journal current-source next237 wp-options-wal-sidecar-next237 checksum_digest must be a sha256 string'],
    'bad reader pins throws' => [static function () use ($plan): string {
        try {
            $plan(null, null, ['wp-options-import' => -1]);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
        return 'no exception';
    }, 'SQLite WAL hot-journal current-source next237 reader pins must map names to non-negative frames'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next237 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
