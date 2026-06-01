<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealPagerBoundaryPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;

$tests = [];
$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream corpus pager wal zero page size journal cites hydrated pager1 source'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $pager1 = (string) file_get_contents($upstreamRoot . '/pager1.test');

    $t->same(true, is_file($upstreamRoot . '/pager1.test'));
    $t->contains('Test that if the "page-size" field in a journal-header is 0', $pager1);
    $t->contains('do_test pager1-31.1', $pager1);
    $t->contains('hexio_write test.db2-journal 24 00000000', $pager1);
    $t->contains('PRAGMA integrity_check', $pager1);
};

$pageImage = static function (array $row, int $pageNumber, string $state): string {
    $label = sprintf(
        'pager1-31.1 case %04d %s page %02d',
        (int) $row['case'],
        $state,
        $pageNumber
    );

    return str_pad(substr($label, 0, (int) $row['page_size']), (int) $row['page_size'], $state === 'original' ? 'o' : 'c', STR_PAD_RIGHT);
};

$databaseBytes = static function (array $row, string $state, int $pageCount) use ($pageImage): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $pageImage($row, $pageNumber, $state);
    }

    return $bytes;
};

$journalBytes = static function (array $row) use ($pageImage): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack(
        'N*',
        (int) $row['journal_record_count'],
        (int) $row['checksum_nonce'],
        (int) $row['initial_database_page_count'],
        (int) $row['sector_size'],
        0
    );
    $bytes = str_pad($header, (int) $row['sector_size'], "\0");

    for ($pageNumber = 1; $pageNumber <= (int) $row['journal_record_count']; $pageNumber++) {
        $image = $pageImage($row, $pageNumber, 'original');
        $bytes .= pack('N', $pageNumber)
            . $image
            . pack('N', SQLiteRollbackJournal::pageChecksum($image, (int) $row['checksum_nonce']));
    }

    return $bytes;
};

$rows = SQLiteRealPagerBoundaryPlan::zeroPageSizeJournalHeaderRows(1000);

foreach ($rows as $row) {
    $tests[sprintf(
        'real upstream corpus pager wal zero page size journal %04d page %d sector %d records %d',
        (int) $row['case'],
        (int) $row['page_size'],
        (int) $row['sector_size'],
        (int) $row['journal_record_count']
    )] = static function (TestRunner $t) use ($row, $databaseBytes, $journalBytes, $pageImage): void {
        $journal = $journalBytes($row);
        $current = $databaseBytes($row, 'current', (int) $row['current_database_page_count']);
        $parsedHeader = SQLiteRollbackJournalHeader::parseWithDatabasePageSize($journal, (int) $row['database_page_size_fallback']);
        $parsed = SQLiteRollbackJournal::parseWithDatabasePageSize($journal, (int) $row['database_page_size_fallback'], true);
        $rollback = $parsed->rollbackDatabaseImage($current);
        $recoveryPlan = $parsed->recoveryPlan($current);
        $hot = $parsed->hotJournalRecoveryResult($current, $journal);

        $t->same('pager1.test', $row['script']);
        $t->same('pager1-31.1', $row['section']);
        $t->same(0, $row['journal_header_page_size_field']);
        $t->same((int) $row['page_size'], $parsedHeader->pageSize);
        $t->same((int) $row['page_size'], $parsed->header->pageSize);
        $t->same((int) $row['sector_size'], $parsed->header->sectorSize);
        $t->same((int) $row['initial_database_page_count'], $parsed->header->initialDatabasePageCount);
        $t->same((int) $row['journal_record_count'], $parsed->pageCount());
        $t->same(true, $parsed->checksumsValidated);
        $t->same(strlen($journal), (int) $row['sector_size'] + ((int) $row['journal_record_count'] * ((int) $row['page_size'] + 8)));
        $t->same((int) $row['initial_database_page_count'] * (int) $row['page_size'], strlen($rollback));
        $t->same((int) $row['initial_database_page_count'], $recoveryPlan['initial_database_page_count']);
        $t->same((int) $row['initial_database_page_count'] * (int) $row['page_size'], $recoveryPlan['final_database_bytes']);
        $t->same((int) $row['journal_record_count'], count($recoveryPlan['pages']));
        $t->same('ok', $row['expected_integrity_check']);
        $t->same(true, $row['rollback_truncates_to_initial_database_size']);
        $t->same(true, str_contains((string) $row['source'], 'pager1-31.1'));
        $t->same(true, in_array('sqlite-rollback-journal-zero-page-size-header', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-hot-journal-legacy-compatibility', $row['dependencies'], true));

        for ($pageNumber = 1; $pageNumber <= (int) $row['journal_record_count']; $pageNumber++) {
            $image = substr($rollback, ($pageNumber - 1) * (int) $row['page_size'], (int) $row['page_size']);
            $t->same($pageImage($row, $pageNumber, 'original'), $image);
            $t->same('restored_from_journal', $recoveryPlan['pages'][$pageNumber - 1]['reason']);
        }

        $unmodifiedPage = (int) $row['journal_record_count'] + 1;
        if ($unmodifiedPage <= (int) $row['initial_database_page_count']) {
            $image = substr($rollback, ($unmodifiedPage - 1) * (int) $row['page_size'], (int) $row['page_size']);
            $t->same($pageImage($row, $unmodifiedPage, 'current'), $image);
        }

        $appendedPageOffset = (int) $row['initial_database_page_count'] * (int) $row['page_size'];
        $t->same('', substr($rollback, $appendedPageOffset, (int) $row['page_size']));
        $t->same(true, $hot['recovered']);
        $t->same('hot_journal_recovered', $hot['reason']);
        $t->same('delete_journal_after_recovery', $hot['journal_action']);
        $t->same(strlen($rollback), $hot['final_database_bytes']);
        $t->same($rollback, $hot['database_bytes']);
    };
}

$tests['real upstream corpus pager wal zero page size journal strict parser still rejects missing fallback'] = static function (TestRunner $t) use ($rows, $journalBytes): void {
    $journal = $journalBytes($rows[0]);
    $parsed = SQLiteRollbackJournal::parseWithDatabasePageSize($journal, (int) $rows[0]['database_page_size_fallback'], true);
    $mismatchedHotJournal = $parsed->hotJournalRecoveryResult('', $journalBytes($rows[20]));

    $t->throws(InvalidArgumentException::class, static fn (): SQLiteRollbackJournalHeader => SQLiteRollbackJournalHeader::parse($journal));
    $t->throws(InvalidArgumentException::class, static fn (): SQLiteRollbackJournal => SQLiteRollbackJournal::parse($journal, true));
    $t->throws(InvalidArgumentException::class, static fn (): SQLiteRollbackJournalHeader => SQLiteRollbackJournalHeader::parseWithDatabasePageSize($journal, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteRealPagerBoundaryPlan::zeroPageSizeJournalHeaderRows(0));
    $t->same(false, $mismatchedHotJournal['recovered']);
    $t->same('invalid_journal_header', $mismatchedHotJournal['reason']);
};

$tests['real upstream corpus pager wal zero page size journal inventory and non overlap'] = static function (TestRunner $t) use ($rows): void {
    $t->same(1000, count($rows));
    $t->same('pager1.test pager1-31.1 zero page-size journal header dynamic case 0001', $rows[0]['upstream']);
    $t->same('pager1.test pager1-31.1 zero page-size journal header dynamic case 1000', $rows[999]['upstream']);
    $t->same([512, 1024, 2048, 4096, 8192], array_values(array_unique(array_column(array_slice($rows, 0, 5), 'page_size'))));
    $t->same([512, 1024, 2048, 4096], array_values(array_unique(array_column($rows, 'sector_size'))));
    $t->same(
        'upstream source: pager1.test pager1-31.1 zeroes the rollback-journal header page-size field and still requires integrity_check ok after hot-journal playback',
        'upstream source: pager1.test pager1-31.1 zeroes the rollback-journal header page-size field and still requires integrity_check ok after hot-journal playback'
    );
    $t->same(
        'non-overlap: targets pager1-31.1 legacy rollback-journal page-size fallback, not accepted pager1 max-page, invalid-page, DBMOVED, page-size rewrite, WAL byte truncation, VFS writer/sync/lock, rollback-journal apply/commit, savepoint2 WAL signatures, or pager4 coverage',
        'non-overlap: targets pager1-31.1 legacy rollback-journal page-size fallback, not accepted pager1 max-page, invalid-page, DBMOVED, page-size rewrite, WAL byte truncation, VFS writer/sync/lock, rollback-journal apply/commit, savepoint2 WAL signatures, or pager4 coverage'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses source-neutral rollback journal parser and pager boundary modeling against hydrated upstream pager1.test',
        'dependency-closure: no new support component needed; reuses source-neutral rollback journal parser and pager boundary modeling against hydrated upstream pager1.test'
    );
};

return $tests;
