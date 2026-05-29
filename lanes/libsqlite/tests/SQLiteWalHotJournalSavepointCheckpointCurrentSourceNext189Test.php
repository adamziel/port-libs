<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next189.sqlite';
$journalPath = $databasePath . '-journal';
$walPath = $databasePath . '-wal';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databasePages = [
    1 => $page('next189 checkpoint schema page'),
    2 => $page('next189 checkpoint options page'),
    3 => $page('next189 checkpoint active plugins page'),
    4 => $page('next189 checkpoint rewrite rules page'),
];
$dirtyDatabase = implode('', [
    $page('next189 dirty schema after plugin import'),
    $page('next189 dirty options after plugin import'),
    $page('next189 dirty active plugins after plugin import'),
    $page('next189 dirty rewrite rules after plugin import'),
]);
$cleanPages = [
    1 => $databasePages[1],
    2 => $databasePages[2],
    4 => $databasePages[4],
];

$makeJournal = static function (array $pages, int $nonce = 0x18918901) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, max(array_keys($pages)), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$makeWal = static function (array $frames, int $checkpoint = 189) use ($pageSize, $page): string {
    $salt1 = 0x18918901;
    $salt2 = 0x18918902;
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

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next189');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-batch-next189');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4, true);

    return $stack;
};

$journalBytes = $makeJournal($cleanPages);
$walBytes = $makeWal([
    [1, 0, 'next189 retained schema draft before checkpoint'],
    [2, 4, 'next189 retained wp_options commit before checkpoint'],
    [3, 0, 'next189 discarded active_plugins savepoint draft'],
    [4, 4, 'next189 discarded rewrite_rules savepoint commit'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$completed = [
    'publish_hot_journal_savepoint_current_checkpoint_database_next165',
    'trim_database_after_current_checkpoint_publish_next165',
    'preserve_retained_wal_for_pinned_reader_next165',
    'sync_current_checkpoint_before_reader_release_next165',
];

$base = static fn (array $completedRows = [], ?array $files = null): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next174Plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $makeStack(),
    'plugin-batch-next189',
    $wal,
    $walBytes,
    [1, 2, 3, 4],
    $completedRows,
    $files ?? []
);

$payloadBytesFrom = static function (array $resume) use ($databasePath, $journalPath, $walPath, $journalBytes): array {
    $payloads = $resume['base_plan']['base_plan']['payloads'];
    $databaseKey = $resume['base_plan']['released_database_synced']
        ? $databasePath . '#next165-released-checkpoint'
        : $databasePath . '#next165-current-checkpoint';
    $walKey = $resume['base_plan']['released_database_synced']
        ? $walPath . '#next165-released-reader'
        : $walPath . '#next165-current-reader';

    return [
        $databasePath => (string) $payloads[$databaseKey],
        $journalPath => $journalBytes,
        $walPath => (string) $payloads[$walKey],
    ];
};

$filesFrom = static function (array $completedRows) use ($base, $databasePath, $journalPath, $walPath, $journalBytes, $payloadBytesFrom): array {
    $probe = $base($completedRows, []);
    $payloads = $payloadBytesFrom($probe);

    return [
        $databasePath => $payloads[$databasePath],
        $journalPath => $probe['file_rows'][1]['required'] ? $journalBytes : null,
        $walPath => $payloads[$walPath],
    ];
};

$matching = static fn (array $completedRows = []): array => $base($completedRows, $filesFrom($completedRows));
$apply = static function (array $resume, array $files) use ($payloadBytesFrom): array {
    return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next180Apply(
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next177Plan($resume),
        $files,
        $payloadBytesFrom($resume)
    );
};

$applyResult = $apply($matching($completed), $filesFrom($completed));
$verify186 = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next186VerifyWalSource(
    $applyResult,
    $applyResult['files'],
    189,
    512,
    [],
    189
);
$freshToken = $verify186()['reader_source_token'];
$plan = static fn (int $endFrame = 2, array $pages = [1, 2, 3], array $tokens = [], ?array $dbPages = null, ?array $files = null): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next189ReaderSnapshotPlan(
    $applyResult,
    $files ?? $applyResult['files'],
    189,
    512,
    $endFrame,
    $pages,
    $tokens,
    $dbPages ?? $databasePages,
    189
);

$missingWalFiles = $applyResult['files'];
$missingWalFiles[$walPath] = null;
$missingFallbackPages = $databasePages;
unset($missingFallbackPages[3]);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next189'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'reader_snapshot_frames_are_bounded_by_retained_committed_wal_source'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next186'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $journalPath],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $walPath],
    'epoch' => [static fn (): mixed => $plan()['reader_epoch'], 189],
    'reader end frame' => [static fn (): mixed => $plan()['reader_end_frame'], 2],
    'last commit frame' => [static fn (): mixed => $plan()['last_commit_frame'], 2],
    'last commit page count' => [static fn (): mixed => $plan()['last_commit_page_count'], 4],
    'uncommitted tail' => [static fn (): mixed => $plan()['uncommitted_tail_frames'], []],
    'has no tail' => [static fn (): mixed => $plan()['has_uncommitted_tail'], false],
    'reader pages' => [static fn (): mixed => $plan()['reader_page_numbers'], [1, 2, 3]],
    'reader sources' => [static fn (): mixed => $plan()['reader_sources'], ['retained-wal', 'retained-wal', 'checkpoint-database']],
    'frame indexes' => [static fn (): mixed => $plan()['reader_frame_indexes'], [1, 2, null]],
    'reader row count' => [static fn (): mixed => count($plan()['reader_rows']), 3],
    'schema row source' => [static fn (): mixed => $plan()['reader_rows'][0]['source'], 'retained-wal'],
    'options row frame' => [static fn (): mixed => $plan()['reader_rows'][1]['frame_index'], 2],
    'database fallback row' => [static fn (): mixed => $plan()['reader_rows'][2]['has_database_fallback'], true],
    'database fallback digest' => [static fn (): mixed => $plan()['reader_rows'][2]['image_sha256'], hash('sha256', $databasePages[3])],
    'page four fallback source' => [static fn (): mixed => $plan(2, [4])['reader_sources'], ['checkpoint-database']],
    'page four fallback digest' => [static fn (): mixed => $plan(2, [4])['reader_rows'][0]['image_sha256'], hash('sha256', $databasePages[4])],
    'zero frame database sources' => [static fn (): mixed => $plan(0, [1, 2, 3])['reader_sources'], ['checkpoint-database', 'checkpoint-database', 'checkpoint-database']],
    'zero frame has no retained frames' => [static fn (): mixed => $plan(0, [1, 2, 3])['reader_frame_indexes'], [null, null, null]],
    'missing fallback pages empty' => [static fn (): mixed => $plan()['missing_database_fallback_pages'], []],
    'source token' => [static fn (): mixed => $plan()['reader_source_token'], $freshToken],
    'source token prefix' => [static fn (): mixed => str_starts_with((string) $plan()['reader_source_token'], 'wal-hot-journal-savepoint-checkpoint-next186:wal-source:'), true],
    'fresh token admitted' => [static fn (): mixed => $plan(2, [1, 2], [$freshToken])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next189'],
    'base blocked empty' => [static fn (): mixed => $plan()['base_blocked_reasons'], []],
    'blocked empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'snapshot digest stable' => [static fn (): mixed => $plan()['snapshot_digest'], $plan()['snapshot_digest']],
    'snapshot digest length' => [static fn (): mixed => strlen($plan()['snapshot_digest']), 64],
    'snapshot digest changes by page set' => [static fn (): mixed => $plan(2, [1, 2])['snapshot_digest'] !== $plan(2, [1, 3])['snapshot_digest'], true],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next189', $plan()['dependencies'], true), true],
    'retained frame marker' => [static fn (): mixed => in_array('sqlite-wal-reader-snapshot-retained-commit-frame-admission', $plan()['dependencies'], true), true],
    'inherits next186 marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next186', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat WAL header token validation'), true],
    'end frame beyond commit blocked' => [static fn (): mixed => $plan(3)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next189'],
    'end frame beyond commit reason' => [static fn (): mixed => $plan(3)['blocked_reasons'], ['reader_end_frame_extends_past_last_committed_frame']],
    'wrong checkpoint blocked' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next189ReaderSnapshotPlan($applyResult, $applyResult['files'], 190, 512, 2, [1], [], $databasePages, 189)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next189'],
    'wrong checkpoint reasons' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next189ReaderSnapshotPlan($applyResult, $applyResult['files'], 190, 512, 2, [1], [], $databasePages, 189)['blocked_reasons'], ['wal_checkpoint_sequence_drift_after_hot_journal_apply', 'next186_retained_wal_source_required']],
    'stale token blocked' => [static fn (): mixed => $plan(2, [1], ['wal-hot-journal-savepoint-checkpoint-next186:wal-source:stale'])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next189'],
    'stale token propagated' => [static fn (): mixed => $plan(2, [1], ['wal-hot-journal-savepoint-checkpoint-next186:wal-source:stale'])['blocked_reasons'], ['wal_source_reader_cache_token_predates_verified_header', 'next186_retained_wal_source_required']],
    'missing wal blocked' => [static fn (): mixed => $plan(2, [1], [], $databasePages, $missingWalFiles)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next189'],
    'missing wal reasons' => [static fn (): mixed => $plan(2, [1], [], $databasePages, $missingWalFiles)['blocked_reasons'], ['next183_current_source_admission_required', 'retained_wal_payload_missing_after_hot_journal_apply', 'next186_retained_wal_source_required', 'retained_wal_payload_missing_for_reader_snapshot']],
    'missing database fallback blocked' => [static fn (): mixed => $plan(2, [3], [], $missingFallbackPages)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next189'],
    'missing database fallback page' => [static fn (): mixed => $plan(2, [3], [], $missingFallbackPages)['missing_database_fallback_pages'], [3]],
    'missing database fallback reason' => [static fn (): mixed => $plan(2, [3], [], $missingFallbackPages)['blocked_reasons'], ['checkpoint_database_fallback_missing_for_reader_page']],
    'page two retained even with fallback' => [static fn (): mixed => $plan(2, [2], [], [2 => $page('unused fallback')])['reader_rows'][0]['source'], 'retained-wal'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next189 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'negative reader frame rejected' => static function () use ($applyResult): void {
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next189ReaderSnapshotPlan($applyResult, $applyResult['files'], 189, 512, -1, [1]);
    },
    'empty reader pages rejected' => static function () use ($applyResult): void {
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next189ReaderSnapshotPlan($applyResult, $applyResult['files'], 189, 512, 2, []);
    },
    'bad reader page rejected' => static function () use ($applyResult): void {
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next189ReaderSnapshotPlan($applyResult, $applyResult['files'], 189, 512, 2, [0]);
    },
    'bad database fallback rejected' => static function () use ($applyResult): void {
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next189ReaderSnapshotPlan($applyResult, $applyResult['files'], 189, 512, 2, [1], [], [1 => 42]);
    },
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next189 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
