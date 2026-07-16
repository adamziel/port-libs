<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$sectorSize = 512;
$nonce = 0x33445566;

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
$schemaPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, $schemaPayload, $pageSize),
], $pageSize, 100, $makeFirstPage(2));

$cleanOptionPayload = SQLiteRecord::encode([1, 'siteurl', 'https://example.test/clean', 'yes']);
$cleanOptionPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, $cleanOptionPayload, $pageSize),
], $pageSize);

$dirtyOptionPayload = SQLiteRecord::encode([1, 'siteurl', 'https://example.test/dirty', 'yes']);
$dirtyOptionPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, $dirtyOptionPayload, $pageSize),
], $pageSize);
$dirtyDatabaseBytes = $schemaPage . $dirtyOptionPage;

$journalHeader = SQLiteRollbackJournalHeader::MAGIC . pack('N*', 1, $nonce, 2, $sectorSize, $pageSize);
$journalBytes = str_pad($journalHeader, $sectorSize, "\0")
    . pack('N', 2)
    . $cleanOptionPage
    . pack('N', SQLiteRollbackJournal::pageChecksum($cleanOptionPage, $nonce))
    . str_repeat("\0", 128);

$journal = SQLiteRollbackJournal::parse($journalBytes, true);
$recoveryResult = $journal->hotJournalRecoveryResult($dirtyDatabaseBytes, $journalBytes);
$lockedRecoveryResult = $journal->hotJournalRecoveryResult($dirtyDatabaseBytes, $journalBytes, databaseReservedLock: true);
$database = SQLiteDatabase::fromBytes($recoveryResult['database_bytes']);
$recoveryPlan = $journal->recoveryPlan($dirtyDatabaseBytes);
$hotJournal = SQLiteRollbackJournal::hotJournalCandidate($journalBytes);

echo json_encode([
    'rollbackJournal' => $journal->toArray(),
    'hotJournal' => $hotJournal,
    'hotJournalRecovery' => [
        'recovered' => $recoveryResult['recovered'],
        'reason' => $recoveryResult['reason'],
        'journal_action' => $recoveryResult['journal_action'],
        'final_database_bytes' => $recoveryResult['final_database_bytes'],
        'containsCleanSiteUrl' => str_contains($recoveryResult['database_bytes'], 'https://example.test/clean'),
        'containsDirtySiteUrl' => str_contains($recoveryResult['database_bytes'], 'https://example.test/dirty'),
    ],
    'lockedRecovery' => [
        'recovered' => $lockedRecoveryResult['recovered'],
        'reason' => $lockedRecoveryResult['reason'],
        'journal_action' => $lockedRecoveryResult['journal_action'],
        'preservesDirtySiteUrl' => str_contains($lockedRecoveryResult['database_bytes'], 'https://example.test/dirty'),
    ],
    'recoveryPlan' => $recoveryPlan,
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
    'rolledBackImageBytes' => strlen($journal->rollbackDatabaseImage($dirtyDatabaseBytes)),
    'sectorPaddingBytes' => 128,
    'applicationUse' => 'Preview wp_options page recovery from a sector-padded SQLite rollback journal without the SQLite extension so import/repair tooling can inspect hot-journal recovery admission, pre-transaction option values, lock-preserved dirty pages, applied page offsets, final truncation size, and post-recovery journal deletion before accepting a copied database.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
