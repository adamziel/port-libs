<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next167.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = implode('', [
    $page('next167 dirty schema after copied import'),
    $page('next167 dirty wp_options root after copied import'),
    $page('next167 dirty active_plugins after copied import'),
    $page('next167 dirty autoload index after copied import'),
    $page('next167 dirty cron option after copied import'),
]);
$hot = [
    2 => $page('next167 hot journal clean wp_options root'),
    4 => $page('next167 hot journal clean autoload index'),
];
$savepointBefore = [
    3 => $page('next167 savepoint before active_plugins retry'),
    5 => $page('next167 savepoint before cron retry'),
];

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

$currentWalBytes = $makeWalBytes([
    [1, 0, 'next167 current wal schema draft'],
    [2, 5, 'next167 current wal wp_options commit'],
    [4, 0, 'next167 current wal autoload draft'],
    [5, 5, 'next167 current wal cron commit'],
], 167, 0x16700101, 0x16700102);
$nextWalBytes = $makeWalBytes([
    [3, 0, 'next167 next wal active_plugins retry draft'],
    [5, 5, 'next167 next wal cron commit'],
], 168, 0x16800101, 0x16800102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);

$readers = [
    ['name' => 'wp-current-schema', 'source_id' => 'bootstrap', 'epoch' => 1],
];
$bootstrap = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planCheckpointSourceTransition(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next167',
    $hot,
    $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('next167 current wal schema draft'), 'source_id' => 'bootstrap', 'epoch' => 1]],
    [1, 2, 3, 4, 5],
    [
        ['name' => 'bootstrap-current', 'source_id' => 'bootstrap', 'epoch' => 1],
        ['name' => 'bootstrap-stale', 'source_id' => 'old-bootstrap', 'epoch' => 1],
    ],
    null,
    null,
    null,
    'restart',
    4,
    167
);
$currentToken = $bootstrap['current_source_token'];
$nextToken = $bootstrap['next_source_token'];

$cache = [
    1 => ['image' => $page('next167 current wal schema draft'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'label' => 'schema cache current'],
    2 => ['image' => $page('next167 current wal wp_options commit'), 'source_id' => 'old-token', 'epoch' => $currentToken['epoch'], 'label' => 'wp_options stale token'],
    3 => ['image' => $savepointBefore[3], 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'] - 1, 'label' => 'active_plugins stale epoch'],
    4 => ['image' => $page('next167 stale autoload cache'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'label' => 'autoload stale image'],
    5 => ['image' => $page('next167 current wal cron commit'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'dirty' => true, 'label' => 'cron dirty cache'],
];
$readers = [
    ['name' => 'wp-current-schema', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
    ['name' => 'wp-pinned-options', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'pinned' => true],
    ['name' => 'wp-stale-token', 'source_id' => 'old-token', 'epoch' => $currentToken['epoch']],
    ['name' => 'wp-stale-epoch', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'] - 1],
    ['name' => 'wp-next-reader', 'source_id' => $nextToken['id'], 'epoch' => $nextToken['epoch']],
    ['name' => 'wp-dirty-reader', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'dirty' => true],
    ['name' => 'wp-closed-reader', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'closed' => true],
];

$plan = static fn (
    ?array $expectedCurrent = null,
    ?array $expectedNext = null,
    ?string $expectedFingerprint = null,
    ?array $readerRows = null,
    ?array $cacheRows = null,
): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planCheckpointSourceTransition(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next167',
    $hot,
    $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    $cacheRows ?? $cache,
    [1, 2, 3, 4, 5],
    $readerRows ?? $readers,
    $expectedCurrent,
    $expectedNext,
    $expectedFingerprint,
    'restart',
    4,
    167
);
$ok = static fn (): array => $plan($currentToken, $nextToken);
$fingerprint = $ok()['publication_fingerprint'];
$staleCurrent = static fn (): array => $plan(['id' => 'stale-current-source', 'epoch' => $currentToken['epoch']], $nextToken, $fingerprint);
$staleNext = static fn (): array => $plan($currentToken, ['id' => 'stale-next-source', 'epoch' => $nextToken['epoch']], $fingerprint);
$staleFingerprint = static fn (): array => $plan($currentToken, $nextToken, str_repeat('0', 64));
$allStale = static fn (): array => $plan(['id' => 'stale-current-source', 'epoch' => 1], ['id' => 'stale-next-source', 'epoch' => 1], str_repeat('f', 64));

$cases = [
    'status' => [static fn (): mixed => $ok()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next167'],
    'reason' => [static fn (): mixed => $ok()['reason'], 'current_source_publication_guard_admits_checkpoint_after_hot_journal_savepoint'],
    'base status' => [static fn (): mixed => $ok()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next164'],
    'database path' => [static fn (): mixed => $ok()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $ok()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $ok()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $ok()['page_size'], 512],
    'savepoint' => [static fn (): mixed => $ok()['savepoint'], 'plugin-import-next167'],
    'mode' => [static fn (): mixed => $ok()['mode'], 'restart'],
    'reader frame' => [static fn (): mixed => $ok()['reader_end_frame'], 4],
    'current token' => [static fn (): mixed => $ok()['current_source_token'], $currentToken],
    'next token' => [static fn (): mixed => $ok()['next_source_token'], $nextToken],
    'fingerprint length' => [static fn (): mixed => strlen($ok()['publication_fingerprint']), 64],
    'expected fingerprint copied' => [static fn (): mixed => $ok()['expected_publication_fingerprint'], $ok()['publication_fingerprint']],
    'guard names' => [static fn (): mixed => $ok()['publication_guard_names'], ['current_token', 'next_token', 'publication_fingerprint', 'reader_admission']],
    'guard matches' => [static fn (): mixed => $ok()['publication_guard_matches'], [true, true, true, true]],
    'guard reasons' => [static fn (): mixed => $ok()['publication_guard_reasons'], [
        'checkpoint_current_source_token_matches_prepared_statement',
        'next_wal_source_token_matches_retry_generation',
        'hot_journal_savepoint_checkpoint_inputs_match_current_source',
        'checkpoint_publication_keeps_only_current_source_readers',
    ]],
    'stale guard count zero' => [static fn (): mixed => $ok()['stale_guard_count'], 0],
    'stale guard names empty' => [static fn (): mixed => $ok()['stale_guard_names'], []],
    'admitted readers' => [static fn (): mixed => $ok()['admitted_reader_names'], ['wp-current-schema']],
    'reopen readers' => [static fn (): mixed => $ok()['reopen_reader_names'], ['wp-pinned-options', 'wp-stale-token', 'wp-stale-epoch', 'wp-next-reader', 'wp-dirty-reader', 'wp-closed-reader']],
    'reader reopen count' => [static fn (): mixed => $ok()['reader_reopen_count'], 6],
    'reader reasons' => [static fn (): mixed => $ok()['reader_admission_reasons'], [
        'reader_matches_checkpoint_current_source_token',
        'pinned_reader_must_reopen_after_cache_rebase',
        'reader_source_token_predates_checkpoint_current_source',
        'reader_epoch_predates_checkpoint_current_source',
        'reader_already_reopened_on_next_wal_source',
        'reader_dirty_after_failed_savepoint',
        'reader_closed_before_checkpoint_publish',
    ]],
    'retained cache pages' => [static fn (): mixed => $ok()['retained_cache_page_numbers'], [1]],
    'invalidated cache pages' => [static fn (): mixed => $ok()['invalidated_cache_page_numbers'], [2, 3, 4, 5]],
    'operation appended guard' => [static fn (): mixed => end($ok()['operation_names']), 'publish_guarded_current_source_next167'],
    'base dependency present' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next164', $ok()['dependencies'], true), true],
    'next167 dependency present' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next167', $ok()['dependencies'], true), true],
    'fingerprint dependency present' => [static fn (): mixed => in_array('sqlite-wal-current-source-publication-fingerprint', $ok()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($ok()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($ok()['non_overlap'], 'does not repeat next164'), true],
    'stale current status' => [static fn (): mixed => $staleCurrent()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next167'],
    'stale current reason' => [static fn (): mixed => $staleCurrent()['reason'], 'current_source_publication_guard_detected_stale_checkpoint_inputs'],
    'stale current guard count' => [static fn (): mixed => $staleCurrent()['stale_guard_count'], 1],
    'stale current guard name' => [static fn (): mixed => $staleCurrent()['stale_guard_names'], ['current_token']],
    'stale current match vector' => [static fn (): mixed => $staleCurrent()['publication_guard_matches'], [false, true, true, true]],
    'stale next status' => [static fn (): mixed => $staleNext()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next167'],
    'stale next guard name' => [static fn (): mixed => $staleNext()['stale_guard_names'], ['next_token']],
    'stale next match vector' => [static fn (): mixed => $staleNext()['publication_guard_matches'], [true, false, true, true]],
    'stale fingerprint status' => [static fn (): mixed => $staleFingerprint()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next167'],
    'stale fingerprint guard name' => [static fn (): mixed => $staleFingerprint()['stale_guard_names'], ['publication_fingerprint']],
    'stale fingerprint expected' => [static fn (): mixed => $staleFingerprint()['expected_publication_fingerprint'], str_repeat('0', 64)],
    'stale fingerprint actual still current' => [static fn (): mixed => $staleFingerprint()['publication_fingerprint'], $fingerprint],
    'all stale status' => [static fn (): mixed => $allStale()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next167'],
    'all stale count' => [static fn (): mixed => $allStale()['stale_guard_count'], 3],
    'all stale names' => [static fn (): mixed => $allStale()['stale_guard_names'], ['current_token', 'next_token', 'publication_fingerprint']],
    'single reader blocked' => [static fn (): mixed => $plan($currentToken, $nextToken, null, [['name' => 'only-current', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']]])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next167'],
    'single reader guard' => [static fn (): mixed => $plan($currentToken, $nextToken, null, [['name' => 'only-current', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']]])['stale_guard_names'], ['reader_admission']],
    'clean cache admits second reader' => [static fn (): mixed => $plan($currentToken, $nextToken, null, [
        ['name' => 'current-a', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        ['name' => 'current-b', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        ['name' => 'old', 'source_id' => 'old-token', 'epoch' => $currentToken['epoch']],
    ])['admitted_reader_names'], ['current-a', 'current-b']],
    'current guard expected field' => [static fn (): mixed => $ok()['publication_guard_rows'][0]['expected'], $currentToken['id']],
    'next guard expected field' => [static fn (): mixed => $ok()['publication_guard_rows'][1]['expected'], $nextToken['id']],
    'fingerprint guard actual field' => [static fn (): mixed => $ok()['publication_guard_rows'][2]['actual'], $fingerprint],
    'reader guard actual field' => [static fn (): mixed => $ok()['publication_guard_rows'][3]['actual'], 'mixed-admit-reopen'],
    'base plan current token retained' => [static fn (): mixed => $ok()['base_plan']['current_source_token'], $currentToken],
    'base plan next token retained' => [static fn (): mixed => $ok()['base_plan']['next_source_token'], $nextToken],
    'base plan current durable action' => [static fn (): mixed => $ok()['base_plan']['current_durable']['wal_action'], 'preserve_wal'],
    'base plan next durable action' => [static fn (): mixed => $ok()['base_plan']['next_durable']['wal_action'], 'preserve_wal'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next167 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad expected current token rejected' => static fn () => $plan(['id' => '', 'epoch' => 1], $nextToken),
    'bad expected next token rejected' => static fn () => $plan($currentToken, ['id' => 'next', 'epoch' => 0]),
    'mutated current wal bytes rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planCheckpointSourceTransition($databasePath, $databaseBytes, $pageSize, 'plugin-import-next167', $hot, $savepointBefore, $currentWal, substr_replace($currentWalBytes, 'x', -1), $nextWal, $nextWalBytes, $cache, [1, 2, 3, 4, 5], $readers),
    'mutated next wal bytes rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planCheckpointSourceTransition($databasePath, $databaseBytes, $pageSize, 'plugin-import-next167', $hot, $savepointBefore, $currentWal, $currentWalBytes, $nextWal, substr_replace($nextWalBytes, 'x', -1), $cache, [1, 2, 3, 4, 5], $readers),
    'bad hot page key rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planCheckpointSourceTransition($databasePath, $databaseBytes, $pageSize, 'plugin-import-next167', [0 => $hot[2]], $savepointBefore, $currentWal, $currentWalBytes, $nextWal, $nextWalBytes, $cache, [1, 2, 3, 4, 5], $readers),
    'empty readers rejected' => static fn () => $plan($currentToken, $nextToken, null, []),
    'bad reader source rejected' => static fn () => $plan($currentToken, $nextToken, null, [['name' => 'bad', 'source_id' => '', 'epoch' => 1]]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next167 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
