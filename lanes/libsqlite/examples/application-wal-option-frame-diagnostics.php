<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalFileWritePlan;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$salt1 = 0x10203040;
$salt2 = 0x50607080;

$makeFirstPage = static function (int $databaseSizePages) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $databaseSizePages), 28, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$schemaPayload = SQLiteRecord::encode([
    'table',
    'wp_options',
    'wp_options',
    2,
    'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
]);
$schemaCell = SQLiteTableLeafCell::encode(1, $schemaPayload, $pageSize);
$schemaPage = SQLiteTableLeafPage::assemble([$schemaCell], $pageSize, 100, $makeFirstPage(2));

$baseOptionPayload = SQLiteRecord::encode([1, 'siteurl', 'https://example.test/from-base', 'yes']);
$baseOptionCell = SQLiteTableLeafCell::encode(1, $baseOptionPayload, $pageSize);
$baseOptionPage = SQLiteTableLeafPage::assemble([$baseOptionCell], $pageSize);

$siteUrlPayload = SQLiteRecord::encode([1, 'siteurl', 'https://example.test/from-wal', 'yes']);
$blogNamePayload = SQLiteRecord::encode([2, 'blogname', 'WAL imported site', 'yes']);
$siteUrlCell = SQLiteTableLeafCell::encode(1, $siteUrlPayload, $pageSize);
$blogNameCell = SQLiteTableLeafCell::encode(2, $blogNamePayload, $pageSize);
$optionPage = SQLiteTableLeafPage::assemble([$siteUrlCell, $blogNameCell], $pageSize);
$baseDatabaseBytes = $schemaPage . $baseOptionPage;

$walHeaderPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 0, $salt1, $salt2);
$checksumSeed = SQLiteWal::checksumPair($walHeaderPrefix, false);
$walBytes = $walHeaderPrefix . pack('N*', $checksumSeed[0], $checksumSeed[1]);
$appendFrame = static function (string $walBytes, array &$checksumSeed, int $pageNumber, int $commit, string $pageImage) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $checksumSeed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $pageImage, false, $checksumSeed[0], $checksumSeed[1]);

    return $walBytes . $framePrefix . pack('N*', $checksumSeed[0], $checksumSeed[1]) . $pageImage;
};

$walBytes = $appendFrame($walBytes, $checksumSeed, 1, 0, $schemaPage);
$walBytes = $appendFrame($walBytes, $checksumSeed, 2, 2, $optionPage);
$committedWalBytes = $walBytes;
$draftOptionPayload = SQLiteRecord::encode([1, 'siteurl', 'https://example.test/draft-wal', 'yes']);
$draftOptionCell = SQLiteTableLeafCell::encode(1, $draftOptionPayload, $pageSize);
$draftOptionPage = SQLiteTableLeafPage::assemble([$draftOptionCell], $pageSize);
$walBytes = $appendFrame($walBytes, $checksumSeed, 2, 0, $draftOptionPage);
$corruptWalBytes = substr_replace($walBytes, 'X', strlen($committedWalBytes) + 24 + 24, 1);
$corruptRecovery = SQLiteWal::checksumRecoveryBoundary($corruptWalBytes, $baseDatabaseBytes);

$wal = SQLiteWal::parse($walBytes, null, true);
$committedWal = SQLiteWal::parse($committedWalBytes, null, true);
$database = SQLiteDatabase::fromBytes($wal->checkpointDatabaseImage($baseDatabaseBytes));
$readerPageMap = $wal->readerPageMap($baseDatabaseBytes);
$readerOptionPage = $wal->readerPageImage($baseDatabaseBytes, 2);
$readerBeforeCommit = $wal->readerSnapshotPageImage($baseDatabaseBytes, 2, 1);
$readerAtCommit = $wal->readerSnapshotPageImage($baseDatabaseBytes, 2, 2);
$readerAfterDraft = $wal->readerSnapshotPageImage($baseDatabaseBytes, 2, 3);
$readerSnapshotMap = $wal->readerSnapshotPageMap($baseDatabaseBytes, 2);
$readMarkPlan = $wal->readMarkPlan([0, 1, 2, null, 7]);
$checkpointPlan = $wal->checkpointPlan($baseDatabaseBytes);
$resetPlan = $wal->resetPlan($baseDatabaseBytes);
$checkpointModes = [
    'passiveWithReader' => $wal->checkpointModePlan($baseDatabaseBytes, 'passive', 1),
    'fullWithReader' => $wal->checkpointModePlan($baseDatabaseBytes, 'full', 1),
    'restart' => $wal->checkpointModePlan($baseDatabaseBytes, 'restart'),
    'restartWithReaderAtCommit' => $wal->checkpointModePlan($baseDatabaseBytes, 'restart', 2),
    'truncate' => $wal->checkpointModePlan($baseDatabaseBytes, 'truncate'),
    'truncateWithReaderAtCommit' => $wal->checkpointModePlan($baseDatabaseBytes, 'truncate', 2),
    'restartCommittedWithReaderAtCommit' => $committedWal->checkpointModePlan($baseDatabaseBytes, 'restart', 2),
    'truncateCommittedWithReaderAtCommit' => $committedWal->checkpointModePlan($baseDatabaseBytes, 'truncate', 2),
];
$checkpointResults = [
    'passiveWithReader' => $wal->checkpointModeResult($baseDatabaseBytes, 'passive', 1),
    'fullWithReader' => $wal->checkpointModeResult($baseDatabaseBytes, 'full', 1),
    'restartCommitted' => $committedWal->checkpointModeResult($baseDatabaseBytes, 'restart'),
    'truncateCommitted' => $committedWal->checkpointModeResult($baseDatabaseBytes, 'truncate'),
];
$durableCheckpointResults = [
    'preserveWithReader' => $wal->durableCheckpointResult($baseDatabaseBytes, 'passive', 1),
    'restartCommitted' => $committedWal->durableCheckpointResult($baseDatabaseBytes, 'restart'),
    'truncateCommitted' => $committedWal->durableCheckpointResult($baseDatabaseBytes, 'truncate'),
];
$durableFileWritePlans = [
    'preserveWithReader' => SQLiteWalFileWritePlan::checkpoint($wal, $baseDatabaseBytes, '/srv/www/wp-content/database/.ht.sqlite', 'passive', 1),
    'restartCommitted' => SQLiteWalFileWritePlan::checkpoint($committedWal, $baseDatabaseBytes, '/srv/www/wp-content/database/.ht.sqlite', 'restart'),
    'truncateCommitted' => SQLiteWalFileWritePlan::checkpoint($committedWal, $baseDatabaseBytes, '/srv/www/wp-content/database/.ht.sqlite', 'truncate'),
];
$readerVisibility = [
    'activeReaderDuringPassiveCheckpoint' => $wal->checkpointReaderVisibility($baseDatabaseBytes, [2], 'passive', 2),
    'activeReaderDuringFullCheckpoint' => $wal->checkpointReaderVisibility($baseDatabaseBytes, [2], 'full', 2),
    'newReaderAfterTruncateCheckpoint' => $committedWal->checkpointReaderVisibility($baseDatabaseBytes, [2], 'truncate'),
];
$checkpointResultSummary = [];
foreach ($checkpointResults as $name => $result) {
    $checkpointResultSummary[$name] = [
        'busy' => $result['busy'],
        'reason' => $result['reason'],
        'checkpointed_frame_count' => $result['checkpointed_frame_count'],
        'remaining_committed_frame_count' => $result['remaining_committed_frame_count'],
        'wal_action' => $result['wal_action'],
        'database_page_count' => $result['database_page_count'],
        'final_database_bytes' => $result['final_database_bytes'],
        'containsWalSiteUrl' => str_contains($result['database_bytes'], 'from-wal'),
        'containsBaseSiteUrl' => str_contains($result['database_bytes'], 'from-base'),
        'containsUncommittedTail' => str_contains($result['database_bytes'], 'draft-wal'),
    ];
}
$durableCheckpointSummary = [];
foreach ($durableCheckpointResults as $name => $result) {
    $durableCheckpointSummary[$name] = [
        'reason' => $result['reason'],
        'wal_action' => $result['wal_action'],
        'busy' => $result['busy'],
        'checkpointed_frame_count' => $result['checkpointed_frame_count'],
        'wal_bytes_length' => $result['wal_bytes_length'],
        'wal_header' => $result['wal_header'],
        'containsWalSiteUrl' => str_contains($result['database_bytes'], 'from-wal'),
        'containsUncommittedTail' => str_contains($result['database_bytes'], 'draft-wal'),
        'dependencies' => $result['dependencies'],
    ];
}
$readerVisibilitySummary = [];
foreach ($readerVisibility as $name => $result) {
    $readerVisibilitySummary[$name] = [
        'mode' => $result['mode'],
        'wal_action' => $result['wal_action'],
        'checkpoint_reason' => $result['checkpoint_reason'],
        'checkpoint_busy' => $result['checkpoint_busy'],
        'stable' => $result['stable'],
        'before_source' => $result['before'][0]['source'],
        'before_frame_index' => $result['before'][0]['frame_index'],
        'before_contains_committed_siteurl' => str_contains($result['before'][0]['image'], 'from-wal'),
        'before_contains_draft_siteurl' => str_contains($result['before'][0]['image'], 'draft-wal'),
        'after_source' => $result['after'][0]['source'],
        'after_frame_index' => $result['after'][0]['frame_index'],
        'after_contains_committed_siteurl' => str_contains($result['after'][0]['image'], 'from-wal'),
        'after_contains_draft_siteurl' => str_contains($result['after'][0]['image'], 'draft-wal'),
        'dependencies' => $result['dependencies'],
    ];
}

echo json_encode([
    'wal' => $wal->toArray(),
    'committedTransactions' => $wal->committedTransactions(),
    'uncommittedFrameCount' => $wal->uncommittedFrameCount(),
    'checkpointPlan' => $checkpointPlan,
    'resetPlan' => $resetPlan,
    'checkpointModes' => $checkpointModes,
    'checkpointResults' => $checkpointResultSummary,
    'durableCheckpointWrites' => $durableCheckpointSummary,
    'durableFileWritePlans' => $durableFileWritePlans,
    'corruptWalRecoveryBoundary' => [
        'status' => $corruptRecovery['status'],
        'reason' => $corruptRecovery['reason'],
        'valid_frame_count' => $corruptRecovery['valid_frame_count'],
        'total_frame_slots' => $corruptRecovery['total_frame_slots'],
        'first_invalid_frame' => $corruptRecovery['first_invalid_frame'],
        'recovery_end_offset' => $corruptRecovery['recovery_end_offset'],
        'last_commit_frame' => $corruptRecovery['last_commit_frame'],
        'last_commit_page_count' => $corruptRecovery['last_commit_page_count'],
        'can_checkpoint' => $corruptRecovery['can_checkpoint'],
        'checkpoint_database_page_count' => $corruptRecovery['checkpoint_database_page_count'],
        'containsCommittedSiteUrl' => str_contains((string) $corruptRecovery['checkpoint_database_bytes'], 'from-wal'),
        'containsCorruptDraftSiteUrl' => str_contains((string) $corruptRecovery['checkpoint_database_bytes'], 'draft-wal'),
        'dependencies' => $corruptRecovery['dependencies'],
    ],
    'checkpointReaderVisibility' => $readerVisibilitySummary,
    'readerPageMap' => $readerPageMap,
    'walIndexReadMarks' => $readMarkPlan,
    'readerOptionPage' => [
        'page_number' => $readerOptionPage['page_number'],
        'source' => $readerOptionPage['source'],
        'frame_index' => $readerOptionPage['frame_index'],
        'database_offset' => $readerOptionPage['database_offset'],
        'snapshot_end_frame' => $readerOptionPage['snapshot_end_frame'],
        'snapshot_commit_frame' => $readerOptionPage['snapshot_commit_frame'],
        'database_page_count' => $readerOptionPage['database_page_count'],
        'containsUncommittedTail' => str_contains($readerOptionPage['image'], 'draft-wal'),
    ],
    'readerSnapshots' => [
        'beforeCommit' => [
            'source' => $readerBeforeCommit['source'],
            'frame_index' => $readerBeforeCommit['frame_index'],
            'snapshot_end_frame' => $readerBeforeCommit['snapshot_end_frame'],
            'snapshot_commit_frame' => $readerBeforeCommit['snapshot_commit_frame'],
            'containsBaseSiteUrl' => str_contains($readerBeforeCommit['image'], 'from-base'),
            'containsCommittedSiteUrl' => str_contains($readerBeforeCommit['image'], 'from-wal'),
            'containsDraftSiteUrl' => str_contains($readerBeforeCommit['image'], 'draft-wal'),
        ],
        'atCommit' => [
            'source' => $readerAtCommit['source'],
            'frame_index' => $readerAtCommit['frame_index'],
            'snapshot_end_frame' => $readerAtCommit['snapshot_end_frame'],
            'snapshot_commit_frame' => $readerAtCommit['snapshot_commit_frame'],
            'containsBaseSiteUrl' => str_contains($readerAtCommit['image'], 'from-base'),
            'containsCommittedSiteUrl' => str_contains($readerAtCommit['image'], 'from-wal'),
            'containsDraftSiteUrl' => str_contains($readerAtCommit['image'], 'draft-wal'),
        ],
        'afterDraft' => [
            'source' => $readerAfterDraft['source'],
            'frame_index' => $readerAfterDraft['frame_index'],
            'snapshot_end_frame' => $readerAfterDraft['snapshot_end_frame'],
            'snapshot_commit_frame' => $readerAfterDraft['snapshot_commit_frame'],
            'containsBaseSiteUrl' => str_contains($readerAfterDraft['image'], 'from-base'),
            'containsCommittedSiteUrl' => str_contains($readerAfterDraft['image'], 'from-wal'),
            'containsDraftSiteUrl' => str_contains($readerAfterDraft['image'], 'draft-wal'),
        ],
        'mapAtCommit' => $readerSnapshotMap,
    ],
    'schema' => array_map(
        static fn (SQLiteSchemaRecord $record): array => [
            'type' => $record->type,
            'name' => $record->name,
            'table_name' => $record->tableName,
            'root_page' => $record->rootPage,
            'sql' => $record->sql,
            'rowid' => $record->rowId,
        ],
        $database->schemaRecords(),
    ),
    'options' => array_map(
        static fn (SQLiteKeyValueRow $option): array => $option->toArray(),
        $database->keyValueRows(),
    ),
    'checkpointImageBytes' => strlen($wal->checkpointDatabaseImage($baseDatabaseBytes)),
    'applicationUse' => 'Read committed wp_options page images from a SQLite WAL fixture without the SQLite extension so repair/import tooling can inspect reader-visible Application option writes at pinned snapshot end frames, WAL-index read-mark checkpoint pins, checkpoint provenance, checkpoint mode eligibility, bounded checkpoint dry-run images, durable preserve/restart/truncate sidecar bytes, corrupt WAL checksum recovery boundaries, ordered VFS file-write/sync/truncate plans, and current reader visibility before/after checkpoint application while preserving uncommitted WAL tail frames.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
