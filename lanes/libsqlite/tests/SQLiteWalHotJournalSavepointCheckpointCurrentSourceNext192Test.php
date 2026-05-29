<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next192.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$pageDigest = static fn (string $image): string => hash('sha256', $image);
$makeWalBytes = static function (array $frames, int $checkpoint, int $salt1, int $salt2) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$preDatabase = $page('next192 dirty schema before checkpoint')
    . $page('next192 dirty wp_options before checkpoint')
    . $page('next192 dirty active_plugins before savepoint')
    . $page('next192 dirty cron before checkpoint')
    . $page('next192 clean usermeta base page');
$currentWalBytes = $makeWalBytes([
    [1, 0, 'next192 current schema frame'],
    [2, 5, 'next192 current wp_options committed'],
    [4, 5, 'next192 current cron committed'],
], 192, 0x19200101, 0x19200102);
$nextWalBytes = $makeWalBytes([
    [3, 0, 'next192 next active_plugins draft'],
    [4, 5, 'next192 next cron commit'],
], 193, 0x19300101, 0x19300102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);
$currentSalt = [$currentWal->header->salt1, $currentWal->header->salt2];
$nextSalt = [$nextWal->header->salt1, $nextWal->header->salt2];
$checkpointedDatabase = $page('next192 current schema frame')
    . $page('next192 current wp_options committed')
    . $page('next192 dirty active_plugins before savepoint')
    . $page('next192 current cron committed')
    . $page('next192 clean usermeta base page');
$staleCheckpointedDatabase = $page('next192 current schema frame')
    . $page('next192 dirty wp_options before checkpoint')
    . $page('next192 dirty active_plugins before savepoint')
    . $page('next192 current cron committed')
    . $page('next192 clean usermeta base page');

$bootstrap = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next188Plan(
    $databasePath,
    $preDatabase,
    $pageSize,
    'plugin-import-next192',
    [2 => $page('next192 hot journal clean wp_options')],
    [3 => $page('next192 savepoint before active_plugins')],
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('next192 current schema frame'), 'source_id' => 'bootstrap', 'epoch' => 1]],
    [1, 2, 3, 4, 5],
    [['name' => 'bootstrap-current', 'source_id' => 'bootstrap', 'epoch' => 1]],
    [
        ['name' => 'bootstrap-current', 'source_id' => 'bootstrap', 'epoch' => 1, 'schema_cookie' => 92, 'root_pages' => [5], 'observed_checkpoint_sequence' => 192, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 920, 'observed_schema_cookie' => 92],
        ['name' => 'bootstrap-stale', 'source_id' => 'bootstrap', 'epoch' => 1, 'schema_cookie' => 92, 'root_pages' => [5], 'observed_checkpoint_sequence' => 191, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 919, 'observed_schema_cookie' => 92],
    ],
    [
        ['name' => 'bootstrap-reader-current', 'source_id' => 'bootstrap', 'epoch' => 1, 'observed_checkpoint_sequence' => 192, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 920, 'observed_schema_cookie' => 92],
        ['name' => 'bootstrap-reader-stale', 'source_id' => 'old-bootstrap', 'epoch' => 1, 'observed_checkpoint_sequence' => 191, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 919, 'observed_schema_cookie' => 92],
    ],
    92,
    93,
    920,
    921,
    null,
    null,
    'restart',
    3,
    192
);
$currentToken = $bootstrap['current_source_token'];
$basePlan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next188Plan(
    $databasePath,
    $preDatabase,
    $pageSize,
    'plugin-import-next192',
    [2 => $page('next192 hot journal clean wp_options')],
    [3 => $page('next192 savepoint before active_plugins')],
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('next192 current schema frame'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']]],
    [1, 2, 3, 4, 5],
    [
        ['name' => 'base-current-reader', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        ['name' => 'base-stale-reader', 'source_id' => 'old-token', 'epoch' => $currentToken['epoch']],
    ],
    [
        ['name' => 'select-current-hook', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 92, 'root_pages' => [5], 'observed_checkpoint_sequence' => 192, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 920, 'observed_schema_cookie' => 92],
        ['name' => 'select-stale-hook', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 92, 'root_pages' => [5], 'observed_checkpoint_sequence' => 192, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 919, 'observed_schema_cookie' => 92],
        ['name' => 'select-stale-generation-before-hook', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 92, 'root_pages' => [5], 'observed_checkpoint_sequence' => 191, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 920, 'observed_schema_cookie' => 92],
        ['name' => 'select-hot-root-before-hook', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 92, 'root_pages' => [2], 'observed_checkpoint_sequence' => 192, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 920, 'observed_schema_cookie' => 92],
    ],
    [
        ['name' => 'reader-current-hook', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'observed_checkpoint_sequence' => 192, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 920, 'observed_schema_cookie' => 92],
        ['name' => 'reader-stale-hook', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'observed_checkpoint_sequence' => 192, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 919, 'observed_schema_cookie' => 92],
        ['name' => 'reader-stale-generation-before-hook', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'observed_checkpoint_sequence' => 191, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 920, 'observed_schema_cookie' => 92],
        ['name' => 'reader-old-token-before-hook', 'source_id' => 'old-token', 'epoch' => $currentToken['epoch'], 'observed_checkpoint_sequence' => 192, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 920, 'observed_schema_cookie' => 92],
    ],
    92,
    93,
    920,
    921,
    $currentToken,
    $bootstrap['next_source_token'],
    'restart',
    3,
    192
);
$digests = [
    1 => $pageDigest($page('next192 current schema frame')),
    2 => $pageDigest($page('next192 current wp_options committed')),
    4 => $pageDigest($page('next192 current cron committed')),
];
$oldDigests = [
    1 => $pageDigest($page('next192 current schema frame')),
    2 => $pageDigest($page('next192 dirty wp_options before checkpoint')),
    4 => $pageDigest($page('next192 dirty cron before checkpoint')),
];
$statements = [
    ['name' => 'select-options-current-pages', 'root_pages' => [1, 2], 'observed_page_digests' => [1 => $digests[1], 2 => $digests[2]]],
    ['name' => 'select-options-stale-page', 'root_pages' => [2], 'observed_page_digests' => [2 => $oldDigests[2]]],
    ['name' => 'select-cron-stale-page', 'root_pages' => [4], 'observed_page_digests' => [4 => $oldDigests[4]]],
    ['name' => 'select-uncheckpointed-usermeta', 'root_pages' => [5], 'observed_page_digests' => [5 => $pageDigest($page('next192 clean usermeta base page'))]],
    ['name' => 'select-dirty-before-publication', 'root_pages' => [1], 'observed_page_digests' => [1 => $digests[1]], 'dirty' => true],
];
$readers = [
    ['name' => 'reader-current-pages', 'pinned_pages' => [1, 4], 'observed_page_digests' => [1 => $digests[1], 4 => $digests[4]]],
    ['name' => 'reader-stale-options', 'pinned_pages' => [2], 'observed_page_digests' => [2 => $oldDigests[2]]],
    ['name' => 'reader-uncheckpointed', 'pinned_pages' => [5], 'observed_page_digests' => [5 => $pageDigest($page('next192 clean usermeta base page'))]],
    ['name' => 'reader-closed', 'pinned_pages' => [1], 'observed_page_digests' => [1 => $digests[1]], 'closed' => true],
];

$plan = static fn (?string $databaseBytes = null, ?array $statementRows = null, ?array $readerRows = null, array $pages = [1, 2, 4]): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next192Plan(
    $basePlan,
    $preDatabase,
    $databaseBytes ?? $checkpointedDatabase,
    $currentWal,
    $pages,
    $statementRows ?? $statements,
    $readerRows ?? $readers
);
$ok = static fn (): array => $plan();
$staleDatabase = static fn (): array => $plan($staleCheckpointedDatabase);
$allCurrent = static fn (): array => $plan(null, [$statements[0]], [$readers[0]]);

$cases = [
    'status' => [static fn (): mixed => $ok()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next192'],
    'reason' => [static fn (): mixed => $ok()['reason'], 'checkpointed_database_pages_match_committed_wal_frames_before_current_source_reuse'],
    'base status' => [static fn (): mixed => $ok()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next188'],
    'database path' => [static fn (): mixed => $ok()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $ok()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $ok()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $ok()['page_size'], 512],
    'checkpoint pages' => [static fn (): mixed => $ok()['checkpoint_pages'], [1, 2, 4]],
    'page row count' => [static fn (): mixed => count($ok()['checkpoint_page_rows']), 3],
    'page one source' => [static fn (): mixed => $ok()['checkpoint_page_rows'][0]['source'], 'wal'],
    'page one frame' => [static fn (): mixed => $ok()['checkpoint_page_rows'][0]['frame_index'], 1],
    'page two frame' => [static fn (): mixed => $ok()['checkpoint_page_rows'][1]['frame_index'], 2],
    'page four frame' => [static fn (): mixed => $ok()['checkpoint_page_rows'][2]['frame_index'], 3],
    'page one matches' => [static fn (): mixed => $ok()['checkpoint_page_rows'][0]['matches'], true],
    'page two matches' => [static fn (): mixed => $ok()['checkpoint_page_rows'][1]['matches'], true],
    'page four matches' => [static fn (): mixed => $ok()['checkpoint_page_rows'][2]['matches'], true],
    'page two prefix' => [static fn (): mixed => $ok()['checkpoint_page_rows'][1]['actual_prefix'], 'next192 current wp_options committed'],
    'digest map count' => [static fn (): mixed => count($ok()['checkpoint_page_digests']), 3],
    'digest page two' => [static fn (): mixed => $ok()['checkpoint_page_digests'][2], $digests[2]],
    'mismatches empty' => [static fn (): mixed => $ok()['mismatched_checkpoint_pages'], []],
    'admitted statements' => [static fn (): mixed => $ok()['admitted_statement_names'], ['select-options-current-pages']],
    'reprepare statements' => [static fn (): mixed => $ok()['reprepare_statement_names'], ['select-options-stale-page', 'select-cron-stale-page', 'select-uncheckpointed-usermeta', 'select-dirty-before-publication']],
    'admitted readers' => [static fn (): mixed => $ok()['admitted_reader_names'], ['reader-current-pages']],
    'reopen readers' => [static fn (): mixed => $ok()['reopen_reader_names'], ['reader-stale-options', 'reader-uncheckpointed', 'reader-closed']],
    'statement row count' => [static fn (): mixed => count($ok()['statement_rows']), 5],
    'reader row count' => [static fn (): mixed => count($ok()['reader_rows']), 4],
    'statement current reason' => [static fn (): mixed => $ok()['statement_rows'][0]['digest_reason'], 'statement_checkpoint_page_images_match_current_source'],
    'statement stale reason' => [static fn (): mixed => $ok()['statement_rows'][1]['digest_reason'], 'statement_observed_page_digest_predates_checkpoint'],
    'statement cron stale reason' => [static fn (): mixed => $ok()['statement_rows'][2]['digest_reason'], 'statement_observed_page_digest_predates_checkpoint'],
    'statement uncheckpointed reason' => [static fn (): mixed => $ok()['statement_rows'][3]['digest_reason'], 'statement_page_not_in_checkpoint_publication'],
    'statement dirty reason' => [static fn (): mixed => $ok()['statement_rows'][4]['digest_reason'], 'statement_closed_or_dirty_before_checkpoint_publication'],
    'reader current reason' => [static fn (): mixed => $ok()['reader_rows'][0]['digest_reason'], 'reader_checkpoint_page_images_match_current_source'],
    'reader stale reason' => [static fn (): mixed => $ok()['reader_rows'][1]['digest_reason'], 'reader_observed_page_digest_predates_checkpoint'],
    'reader uncheckpointed reason' => [static fn (): mixed => $ok()['reader_rows'][2]['digest_reason'], 'reader_page_not_in_checkpoint_publication'],
    'reader closed reason' => [static fn (): mixed => $ok()['reader_rows'][3]['digest_reason'], 'reader_closed_or_dirty_before_checkpoint_publication'],
    'statement page matches' => [static fn (): mixed => $ok()['statement_rows'][0]['page_rows'][1]['matches'], true],
    'stale page mismatch false' => [static fn (): mixed => $ok()['statement_rows'][1]['page_rows'][0]['matches'], false],
    'reader page rows preserved' => [static fn (): mixed => count($ok()['reader_rows'][0]['page_rows']), 2],
    'guard names' => [static fn (): mixed => $ok()['guard_names'], ['base_commit_hook_current_source', 'checkpoint_pages_materialized', 'statement_digest_mix', 'reader_digest_mix']],
    'guard matches' => [static fn (): mixed => $ok()['guard_matches'], [true, true, true, true]],
    'stale guards empty' => [static fn (): mixed => $ok()['stale_guard_names'], []],
    'operation verify' => [static fn (): mixed => in_array('verify_checkpoint_page_images_current_source_next192', $ok()['operation_names'], true), true],
    'operation admit' => [static fn (): mixed => in_array('admit_checkpoint_page_digest_current_source_next192', $ok()['operation_names'], true), true],
    'operation reprepare' => [static fn (): mixed => in_array('reprepare_checkpoint_page_digest_current_source_next192', $ok()['operation_names'], true), true],
    'operation retain reader' => [static fn (): mixed => in_array('retain_reader_checkpoint_page_digest_next192', $ok()['operation_names'], true), true],
    'operation reopen reader' => [static fn (): mixed => in_array('reopen_reader_checkpoint_page_digest_next192', $ok()['operation_names'], true), true],
    'operation final' => [static fn (): mixed => end($ok()['operation_names']), 'publish_checkpoint_page_image_current_source_next192'],
    'digest length' => [static fn (): mixed => strlen($ok()['page_image_digest']), 64],
    'dependency next192' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next192', $ok()['dependencies'], true), true],
    'dependency image publication' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-page-image-publication', $ok()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($ok()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($ok()['non_overlap'], 'does not repeat next188 hook checks'), true],
    'stale database blocked' => [static fn (): mixed => $staleDatabase()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next192'],
    'stale database page' => [static fn (): mixed => $staleDatabase()['mismatched_checkpoint_pages'], [2]],
    'stale database guard' => [static fn (): mixed => $staleDatabase()['stale_guard_names'], ['checkpoint_pages_materialized', 'statement_digest_mix']],
    'stale database statement reason' => [static fn (): mixed => $staleDatabase()['statement_rows'][0]['digest_reason'], 'statement_checkpoint_page_image_not_materialized'],
    'all current blocked' => [static fn (): mixed => $allCurrent()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next192'],
    'all current stale guards' => [static fn (): mixed => $allCurrent()['stale_guard_names'], ['statement_digest_mix', 'reader_digest_mix']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next192 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next192Plan(['status' => 'bad'], $preDatabase, $checkpointedDatabase, $currentWal, [1], $statements, $readers),
    'missing pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next192Plan($basePlan, $preDatabase, $checkpointedDatabase, $currentWal, [], $statements, $readers),
    'missing statements rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next192Plan($basePlan, $preDatabase, $checkpointedDatabase, $currentWal, [1], [], $readers),
    'bad page rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next192Plan($basePlan, $preDatabase, $checkpointedDatabase, $currentWal, [0], $statements, $readers),
    'missing statement digest rejected' => static fn () => $plan(null, [['name' => 'bad', 'root_pages' => [1]]], $readers),
    'malformed reader digest rejected' => static fn () => $plan(null, $statements, [['name' => 'bad-reader', 'pinned_pages' => [1], 'observed_page_digests' => [1 => 'short']]]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next192 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
