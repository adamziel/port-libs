<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next168.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = [
    1 => $page('next168 dirty schema after crashed import'),
    2 => $page('next168 dirty wp_options root after crashed import'),
    3 => $page('next168 dirty active_plugins after crashed import'),
    4 => $page('next168 dirty autoload index after crashed import'),
    5 => $page('next168 dirty cron option after crashed import'),
];
$hot = [
    2 => $page('next168 hot journal clean wp_options root'),
    4 => $page('next168 hot journal clean autoload index'),
];
$savepointBefore = [
    3 => $page('next168 savepoint before active_plugins retry'),
    5 => $page('next168 savepoint before cron retry'),
];
$databaseBytes = implode('', $database);

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
    [1, 0, 'next168 current wal schema draft'],
    [2, 5, 'next168 current wal wp_options commit'],
    [4, 0, 'next168 current wal autoload draft'],
    [5, 5, 'next168 current wal cron commit'],
], 168, 0x16800101, 0x16800102);
$nextWalBytes = $makeWalBytes([
    [3, 0, 'next168 next wal active_plugins retry draft'],
    [5, 5, 'next168 next wal cron commit'],
], 169, 0x16900101, 0x16900102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);

$checkpointPages = [1, 2, 3, 4, 5];
$bootstrap = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerCacheCheckpointPlan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next168',
    $hot,
    $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('next168 current wal schema draft'), 'source_id' => 'bootstrap', 'epoch' => 1]],
    $checkpointPages,
    'restart',
    4,
    168
);
$currentToken = $bootstrap['current_source_token'];
$nextToken = $bootstrap['next_source_token'];
$truncateBootstrap = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerCacheCheckpointPlan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next168',
    $hot,
    $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('next168 dirty schema after crashed import'), 'source_id' => 'bootstrap', 'epoch' => 1]],
    [1, 2],
    'truncate',
    0,
    168
);
$truncateToken = $truncateBootstrap['current_source_token'];

$cache = [
    1 => ['image' => $page('next168 current wal schema draft'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'label' => 'schema cache current'],
    2 => ['image' => $page('next168 current wal wp_options commit'), 'source_id' => 'old-token', 'epoch' => $currentToken['epoch'], 'label' => 'wp_options stale token'],
    3 => ['image' => $savepointBefore[3], 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'] - 1, 'label' => 'active_plugins stale epoch'],
    4 => ['image' => $page('next168 stale autoload cache'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'label' => 'autoload stale image'],
    5 => ['image' => $page('next168 current wal cron commit'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'dirty' => true, 'label' => 'cron dirty cache'],
];
$readers = [
    ['name' => 'wp-current-schema', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
    ['name' => 'wp-pinned-options', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'pinned' => true],
    ['name' => 'wp-stale-token', 'source_id' => 'old-token', 'epoch' => $currentToken['epoch']],
    ['name' => 'wp-next-reader', 'source_id' => $nextToken['id'], 'epoch' => $nextToken['epoch']],
];

$plan = static fn (
    ?array $readerRows = null,
    ?array $cacheRows = null,
    string $mode = 'restart',
    int $readerEndFrame = 4,
    bool $hotJournalExists = true,
    bool $walSidecarExists = true,
    bool $directorySyncRequested = true,
): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next168Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next168',
    $hot,
    $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    $cacheRows ?? $cache,
    $checkpointPages,
    $readerRows ?? $readers,
    $mode,
    $readerEndFrame,
    168,
    $hotJournalExists,
    $walSidecarExists,
    $directorySyncRequested
);

$ready = static fn (): array => $plan();
$noReaderReset = static fn (): array => $plan([
    ['name' => 'wp-current-schema', 'source_id' => $truncateToken['id'], 'epoch' => $truncateToken['epoch']],
    ['name' => 'wp-stale-token', 'source_id' => 'old-token', 'epoch' => $truncateToken['epoch']],
], [
    1 => ['image' => $page('next168 dirty schema after crashed import'), 'source_id' => $truncateToken['id'], 'epoch' => $truncateToken['epoch'], 'label' => 'schema reset cache'],
    2 => ['image' => $page('next168 hot journal clean wp_options root'), 'source_id' => $truncateToken['id'], 'epoch' => $truncateToken['epoch'], 'label' => 'options reset cache'],
], 'truncate', 0);

$cases = [
    'status' => [static fn (): mixed => $ready()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next168'],
    'reason' => [static fn (): mixed => $ready()['reason'], 'hot_journal_savepoint_checkpoint_source_publish_is_crash_safe'],
    'database path' => [static fn (): mixed => $ready()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $ready()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $ready()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $ready()['page_size'], 512],
    'savepoint' => [static fn (): mixed => $ready()['savepoint'], 'plugin-import-next168'],
    'mode' => [static fn (): mixed => $ready()['mode'], 'restart'],
    'reader frame' => [static fn (): mixed => $ready()['reader_end_frame'], 4],
    'base status' => [static fn (): mixed => $ready()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next164'],
    'base reason' => [static fn (): mixed => $ready()['base_reason'], 'checkpoint_current_source_readers_admitted_after_hot_journal_savepoint_rollback'],
    'current token' => [static fn (): mixed => $ready()['checkpoint_current_source_token'], $currentToken],
    'next token' => [static fn (): mixed => $ready()['next_wal_source_token'], $nextToken],
    'epoch order' => [static fn (): mixed => $ready()['source_epoch_order_valid'], true],
    'publish allowed' => [static fn (): mixed => $ready()['publish_allowed'], true],
    'delete hot journal allowed' => [static fn (): mixed => $ready()['delete_hot_journal_allowed'], true],
    'reset wal blocked by reader' => [static fn (): mixed => $ready()['reset_wal_allowed'], false],
    'preserve wal for readers' => [static fn (): mixed => $ready()['preserve_wal_for_readers'], true],
    'requires directory sync' => [static fn (): mixed => $ready()['requires_directory_sync'], true],
    'directory sync requested' => [static fn (): mixed => $ready()['directory_sync_requested'], true],
    'hot journal exists' => [static fn (): mixed => $ready()['hot_journal_exists'], true],
    'wal sidecar exists' => [static fn (): mixed => $ready()['wal_sidecar_exists'], true],
    'blocked reasons empty' => [static fn (): mixed => $ready()['blocked_reasons'], []],
    'admitted readers' => [static fn (): mixed => $ready()['reader_admitted_names'], ['wp-current-schema']],
    'reopen readers' => [static fn (): mixed => $ready()['reader_reopen_names'], ['wp-pinned-options', 'wp-stale-token', 'wp-next-reader']],
    'reader publish count' => [static fn (): mixed => count($ready()['reader_publish_rows']), 4],
    'reader one publish current' => [static fn (): mixed => $ready()['reader_publish_rows'][0]['publish_source'], $currentToken['id']],
    'reader one publish epoch' => [static fn (): mixed => $ready()['reader_publish_rows'][0]['publish_epoch'], $currentToken['epoch']],
    'reader one no reopen' => [static fn (): mixed => $ready()['reader_publish_rows'][0]['needs_reopen'], false],
    'reader two publish next' => [static fn (): mixed => $ready()['reader_publish_rows'][1]['publish_source'], $nextToken['id']],
    'reader two needs reopen' => [static fn (): mixed => $ready()['reader_publish_rows'][1]['needs_reopen'], true],
    'reader three reason' => [static fn (): mixed => $ready()['reader_publish_rows'][2]['reason'], 'reader_source_token_predates_checkpoint_current_source'],
    'reader four reason' => [static fn (): mixed => $ready()['reader_publish_rows'][3]['reason'], 'reader_already_reopened_on_next_wal_source'],
    'reader publish sources' => [static fn (): mixed => $ready()['reader_publish_sources'], [$currentToken['id'], $nextToken['id'], $nextToken['id'], $nextToken['id']]],
    'reader publish epochs' => [static fn (): mixed => $ready()['reader_publish_epochs'], [$currentToken['epoch'], $nextToken['epoch'], $nextToken['epoch'], $nextToken['epoch']]],
    'operation publish' => [static fn (): mixed => $ready()['operation_names'][0], 'publish_checkpoint_current_source_next168'],
    'operation delete journal' => [static fn (): mixed => $ready()['operation_names'][1], 'delete_hot_journal_after_checkpoint_publish_next168'],
    'operation preserve wal' => [static fn (): mixed => $ready()['operation_names'][2], 'preserve_wal_sidecar_for_readers_next168'],
    'operation sync directory' => [static fn (): mixed => $ready()['operation_names'][3], 'sync_checkpoint_directory_after_source_publish_next168'],
    'operation publish next' => [static fn (): mixed => $ready()['operation_names'][4], 'publish_next_wal_generation_after_checkpoint_next168'],
    'operation reason publish' => [static fn (): mixed => $ready()['operations'][0]['reason'], 'checkpoint_source_ready_after_hot_journal_savepoint_reader_gate'],
    'operation reason journal' => [static fn (): mixed => $ready()['operations'][1]['reason'], 'hot_journal_recovery_is_durable_in_checkpoint_source'],
    'operation reason wal' => [static fn (): mixed => $ready()['operations'][2]['reason'], 'wal_frames_remain_visible_or_publish_deferred'],
    'operation directory path' => [static fn (): mixed => $ready()['operations'][3]['path'], dirname($databasePath)],
    'source digest length' => [static fn (): mixed => strlen($ready()['source_digest']), 64],
    'base plan retained cache' => [static fn (): mixed => $ready()['base_plan']['retained_cache_page_numbers'], [1]],
    'base plan invalidated cache' => [static fn (): mixed => $ready()['base_plan']['invalidated_cache_page_numbers'], [2, 3, 4, 5]],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next168', $ready()['dependencies'], true), true],
    'dependency journal delete' => [static fn (): mixed => in_array('sqlite-hot-journal-delete-after-checkpoint-source-publish', $ready()['dependencies'], true), true],
    'dependency generation publish' => [static fn (): mixed => in_array('sqlite-wal-generation-publish-after-savepoint-checkpoint', $ready()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($ready()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($ready()['non_overlap'], 'does not repeat next161'), true],
    'truncate publish status' => [static fn (): mixed => $noReaderReset()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next168'],
    'truncate still preserves wal when durable result keeps frames' => [static fn (): mixed => $noReaderReset()['reset_wal_allowed'], false],
    'truncate preserve wal true' => [static fn (): mixed => $noReaderReset()['preserve_wal_for_readers'], true],
    'truncate preserve operation' => [static fn (): mixed => $noReaderReset()['operation_names'][2], 'preserve_wal_sidecar_for_readers_next168'],
    'missing journal blocks' => [static fn (): mixed => $plan(null, null, 'restart', 4, false)['blocked_reasons'], ['hot_journal_already_missing_before_publish']],
    'missing journal status' => [static fn (): mixed => $plan(null, null, 'restart', 4, false)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next168'],
    'missing journal delete false' => [static fn (): mixed => $plan(null, null, 'restart', 4, false)['delete_hot_journal_allowed'], false],
    'missing wal blocks preserve' => [static fn (): mixed => $plan(null, null, 'restart', 4, true, false)['blocked_reasons'], ['wal_sidecar_missing_for_preserved_reader_snapshot']],
    'missing wal status' => [static fn (): mixed => $plan(null, null, 'restart', 4, true, false)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next168'],
    'directory sync block' => [static fn (): mixed => $plan(null, null, 'restart', 4, true, true, false)['blocked_reasons'], ['directory_sync_required_for_hot_journal_checkpoint_publish']],
    'directory sync operation defer' => [static fn (): mixed => $plan(null, null, 'restart', 4, true, true, false)['operation_names'][3], 'defer_directory_sync_after_source_publish_next168'],
    'base admission blocks publish' => [static fn (): mixed => $plan([['name' => 'only-current', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']]])['blocked_reasons'], ['reader_admission_not_ready']],
    'base admission blocks operation' => [static fn (): mixed => $plan([['name' => 'only-current', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']]])['operation_names'][0], 'defer_checkpoint_current_source_publish_next168'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next168 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty readers rejected by base' => static fn () => $plan([]),
    'empty reader name rejected by base' => static fn () => $plan([['name' => '', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']]]),
    'bad mode rejected by base' => static fn () => $plan(null, null, 'passive'),
    'reader frame outside wal rejected by base' => static fn () => $plan(null, null, 'restart', 5),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next168 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
