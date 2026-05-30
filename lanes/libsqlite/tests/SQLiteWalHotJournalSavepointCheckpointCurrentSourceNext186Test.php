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
$databasePath = '/srv/www/wp-content/database/wp-next186.sqlite';
$journalPath = $databasePath . '-journal';
$walPath = $databasePath . '-wal';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$dirtyDatabase = $page('next186 dirty schema after plugin import')
    . $page('next186 dirty wp_options root after plugin import')
    . $page('next186 dirty active_plugins after plugin import')
    . $page('next186 dirty rewrite_rules after plugin import');
$cleanPages = [
    1 => $page('next186 clean schema before plugin import'),
    2 => $page('next186 clean wp_options root before plugin import'),
    4 => $page('next186 clean rewrite_rules before plugin import'),
];

$makeJournal = static function (array $pages, int $nonce = 0x18618601) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, max(array_keys($pages)), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$makeWal = static function (array $frames, int $checkpoint = 186) use ($pageSize, $page): string {
    $salt1 = 0x18618601;
    $salt2 = 0x18618602;
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
    $stack->beginTransaction('wp-import-next186');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-batch-next186');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4, true);

    return $stack;
};

$journalBytes = $makeJournal($cleanPages);
$walBytes = $makeWal([
    [1, 0, 'next186 retained schema draft before publish'],
    [2, 4, 'next186 retained wp_options commit before publish'],
    [3, 0, 'next186 discarded active_plugins draft'],
    [4, 4, 'next186 discarded rewrite_rules commit'],
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
    'plugin-batch-next186',
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
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::atomicResumeApplyPlan($resume),
        $files,
        $payloadBytesFrom($resume)
    );
};

$applyResult = $apply($matching($completed), $filesFrom($completed));
$verify = static fn (array $tokens = [], int $checkpoint = 186, int $size = 512, int $epoch = 186, ?array $files = null): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next186VerifyWalSource(
    $applyResult,
    $files ?? $applyResult['files'],
    $checkpoint,
    $size,
    $tokens,
    $epoch
);
$freshToken = $verify()['reader_source_token'];

$corruptWalFiles = $applyResult['files'];
$corruptWalFiles[$walPath] = substr((string) $corruptWalFiles[$walPath], 0, 40) . '!' . substr((string) $corruptWalFiles[$walPath], 41);
$missingWalFiles = $applyResult['files'];
$missingWalFiles[$walPath] = null;

$failedApply = $applyResult;
$failedApply['status'] = 'wal-hot-journal-savepoint-checkpoint-current-source-failed-next180';

$cases = [
    'status' => [static fn (): mixed => $verify()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next186'],
    'reason' => [static fn (): mixed => $verify()['reason'], 'retained_wal_header_source_verified_after_hot_journal_apply'],
    'base status' => [static fn (): mixed => $verify()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next183'],
    'database path' => [static fn (): mixed => $verify()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $verify()['journal_path'], $journalPath],
    'wal path' => [static fn (): mixed => $verify()['wal_path'], $walPath],
    'epoch' => [static fn (): mixed => $verify()['reader_epoch'], 186],
    'token prefix' => [static fn (): mixed => str_starts_with($verify()['reader_source_token'], 'wal-hot-journal-savepoint-checkpoint-next186:wal-source:'), true],
    'token stable' => [static fn (): mixed => $verify()['reader_source_token'], $verify()['reader_source_token']],
    'token changes with epoch' => [static fn (): mixed => $verify([], 186, 512, 187)['reader_source_token'] !== $verify([], 186, 512, 186)['reader_source_token'], true],
    'retained token accepted' => [static fn (): mixed => $verify([$freshToken])['retained_reader_cache_tokens'], [$freshToken]],
    'fresh token no reopen' => [static fn (): mixed => $verify([$freshToken])['requires_reader_reopen'], false],
    'stale token blocked' => [static fn (): mixed => $verify(['wal-hot-journal-savepoint-checkpoint-next186:wal-source:stale'])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next186'],
    'stale token reason' => [static fn (): mixed => $verify(['wal-hot-journal-savepoint-checkpoint-next186:wal-source:stale'])['blocked_reasons'], ['wal_source_reader_cache_token_predates_verified_header']],
    'stale token reopen' => [static fn (): mixed => $verify(['wal-hot-journal-savepoint-checkpoint-next186:wal-source:stale'])['requires_reader_reopen'], true],
    'wal digest' => [static fn (): mixed => $verify()['wal_digest'], hash('sha256', (string) $applyResult['files'][$walPath])],
    'wal byte order' => [static fn (): mixed => $verify()['wal_byte_order'], 'big-endian'],
    'checksums validated' => [static fn (): mixed => $verify()['wal_checksums_validated'], true],
    'wal frame count' => [static fn (): mixed => $verify()['wal_frame_count'], 2],
    'commit frame count' => [static fn (): mixed => $verify()['committed_frame_count'], 1],
    'last commit page count' => [static fn (): mixed => $verify()['last_commit_page_count'], 4],
    'first commit frame' => [static fn (): mixed => $verify()['committed_frames'][0], ['frame_index' => 2, 'page_number' => 2, 'commit_page_count' => 4]],
    'only retained commit frame list' => [static fn (): mixed => count($verify()['committed_frames']), 1],
    'header magic' => [static fn (): mixed => $verify()['wal_header']['magic'], SQLiteWalHeader::MAGIC_BIG_ENDIAN],
    'header format' => [static fn (): mixed => $verify()['wal_header']['format_version'], 3007000],
    'header page size' => [static fn (): mixed => $verify()['wal_header']['page_size'], 512],
    'header checkpoint' => [static fn (): mixed => $verify()['wal_header']['checkpoint_sequence'], 186],
    'header salt1' => [static fn (): mixed => $verify()['wal_header']['salt1'], 0x18618601],
    'header salt2' => [static fn (): mixed => $verify()['wal_header']['salt2'], 0x18618602],
    'expected checkpoint' => [static fn (): mixed => $verify()['expected_checkpoint_sequence'], 186],
    'expected page size' => [static fn (): mixed => $verify()['expected_page_size'], 512],
    'base token prefix' => [static fn (): mixed => str_starts_with($verify()['base_reader_source_token'], 'wal-hot-journal-savepoint-checkpoint-next183:current:'), true],
    'base paths' => [static fn (): mixed => $verify()['base_verified_paths'], [$journalPath]],
    'blocked empty' => [static fn (): mixed => $verify()['blocked_reasons'], []],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next186', $verify()['dependencies'], true), true],
    'retained wal marker' => [static fn (): mixed => in_array('sqlite-retained-wal-header-current-source-admission', $verify()['dependencies'], true), true],
    'inherits next183 marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next183', $verify()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($verify()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($verify()['non_overlap'], 'does not repeat next183'), true],
    'wrong checkpoint blocked' => [static fn (): mixed => $verify([], 187)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next186'],
    'wrong checkpoint reason' => [static fn (): mixed => $verify([], 187)['blocked_reasons'], ['wal_checkpoint_sequence_drift_after_hot_journal_apply']],
    'wrong page size blocked' => [static fn (): mixed => $verify([], 186, 1024)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next186'],
    'wrong page size reasons' => [static fn (): mixed => $verify([], 186, 1024)['blocked_reasons'], ['wal_page_size_drift_after_hot_journal_apply']],
    'missing wal blocked' => [static fn (): mixed => $verify([], 186, 512, 186, $missingWalFiles)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next186'],
    'missing wal reason' => [static fn (): mixed => $verify([], 186, 512, 186, $missingWalFiles)['blocked_reasons'], ['next183_current_source_admission_required', 'retained_wal_payload_missing_after_hot_journal_apply']],
    'corrupt wal blocked' => [static fn (): mixed => $verify([], 186, 512, 186, $corruptWalFiles)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next186'],
    'corrupt wal reason' => [static fn (): mixed => $verify([], 186, 512, 186, $corruptWalFiles)['blocked_reasons'], ['next183_current_source_admission_required', 'retained_wal_checksum_or_header_invalid_after_hot_journal_apply']],
    'failed apply blocked' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next186VerifyWalSource($failedApply, $applyResult['files'], 186, 512)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next186'],
    'failed apply reason' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next186VerifyWalSource($failedApply, $applyResult['files'], 186, 512)['blocked_reasons'], ['next183_current_source_admission_required']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next186 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'negative checkpoint rejected' => static function () use ($applyResult): void {
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next186VerifyWalSource($applyResult, $applyResult['files'], -1, 512);
    },
    'bad page size rejected' => static function () use ($applyResult): void {
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next186VerifyWalSource($applyResult, $applyResult['files'], 186, 513);
    },
    'empty path rejected' => static function () use ($applyResult): void {
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next186VerifyWalSource($applyResult, ['' => 'bad'], 186, 512);
    },
    'bad bytes rejected' => static function () use ($applyResult, $walPath): void {
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next186VerifyWalSource($applyResult, [$walPath => 42], 186, 512);
    },
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next186 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
