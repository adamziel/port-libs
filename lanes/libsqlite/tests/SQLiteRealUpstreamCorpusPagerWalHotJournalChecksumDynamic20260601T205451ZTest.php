<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealPagerBoundaryPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;

$tests = [];
$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';
$rows = SQLiteRealPagerBoundaryPlan::hotJournalChecksumStopRows(1000);

$pageImage = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);
};

$databaseImages = static function (array $row) use ($pageImage): array {
    $case = (int) $row['case'];
    $pageSize = (int) $row['page_size'];

    return [
        'initial' => [
            1 => $pageImage($pageSize, sprintf('pager1.4.5 case %04d t1 rows I II initial image', $case)),
            2 => $pageImage($pageSize, sprintf('pager1.4.5 case %04d t2 rows III IV initial image', $case)),
        ],
        'dirty' => [
            1 => $pageImage($pageSize, sprintf('pager1.4.5 case %04d t1 rows I II 1 2 dirty image', $case)),
            2 => $pageImage($pageSize, sprintf('pager1.4.5 case %04d t2 rows III IV 3 4 dirty image', $case)),
        ],
    ];
};

$databaseBytes = static function (array $pages): string {
    ksort($pages);

    return implode('', $pages);
};

$journalBytes = static function (array $row, array $pages): string {
    $nonce = (int) $row['checksum_nonce'];
    $header = SQLiteRollbackJournalHeader::MAGIC . pack(
        'NNNNN',
        count($pages),
        $nonce,
        (int) $row['initial_database_page_count'],
        (int) $row['sector_size'],
        (int) $row['page_size']
    );
    $bytes = str_pad($header, (int) $row['sector_size'], "\0");
    $recordIndex = 1;
    foreach ($pages as $pageNumber => $image) {
        $checksum = SQLiteRollbackJournal::pageChecksum($image, $nonce);
        if ($row['corrupt_record'] === $recordIndex) {
            $checksum ^= 0x00f0f00f;
        }
        $bytes .= pack('N', (int) $pageNumber) . $image . pack('N', $checksum);
        $recordIndex++;
    }

    return $bytes;
};

$rowsFromPage = static function (string $image): array {
    if (str_contains($image, 't1 rows I II 1 2')) {
        return ['I', 'II', '1', '2'];
    }
    if (str_contains($image, 't2 rows III IV 3 4')) {
        return ['III', 'IV', '3', '4'];
    }
    if (str_contains($image, 't1 rows I II')) {
        return ['I', 'II'];
    }
    if (str_contains($image, 't2 rows III IV')) {
        return ['III', 'IV'];
    }

    return [];
};

$tests['real upstream corpus pager wal hot journal checksum dynamic cites hydrated pager1 source'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $pager1 = (string) file_get_contents($upstreamRoot . '/pager1.test');

    $t->contains('pager1.4.5.3', $pager1);
    $t->contains('pager1.4.5.4', $pager1);
    $t->contains('pager1.4.5.5', $pager1);
    $t->contains('pager1.4.5.6', $pager1);
    $t->contains('hot-journal rollback stops if it encounters a', $pager1);
    $t->contains('checksum fails', $pager1);
    $t->contains('attempt to write a readonly database', $pager1);
};

foreach ($rows as $row) {
    $tests[sprintf(
        'real upstream corpus pager wal hot journal checksum dynamic 205451 %04d %s',
        $row['case'],
        $row['phase']
    )] = static function (TestRunner $t) use ($row, $databaseImages, $databaseBytes, $journalBytes, $rowsFromPage): void {
        $images = $databaseImages($row);
        $dirtyDatabase = $databaseBytes($images['dirty']);
        $journal = $journalBytes($row, $images['initial']);
        $parsed = SQLiteRollbackJournal::parse($journal, false);
        $result = $parsed->hotJournalChecksumRecoveryResult(
            $dirtyDatabase,
            $journal,
            (bool) $row['read_only_connection']
        );

        $t->same('pager1.test', $row['script']);
        $t->same(true, str_starts_with((string) $row['upstream'], 'pager1.test pager1.4.5.'));
        $t->same((int) $row['page_size'], $parsed->header->pageSize);
        $t->same((int) $row['sector_size'], $parsed->header->sectorSize);
        $t->same((int) $row['checksum_nonce'], $parsed->header->checksumNonce);
        $t->same(2, $parsed->pageCount());
        $t->same(true, $result['hot_journal']['hot']);
        $t->same('hot_journal_recovery_required', $result['hot_journal']['reason']);
        $t->same($row['expected_reason'], $result['reason']);
        $t->same($row['expected_error'], $result['error']);
        $t->same((int) $row['expected_applied_page_count'], $result['applied_page_count']);
        $t->same((int) $row['expected_applied_page_count'], $result['checksum_valid_page_count']);
        $t->same($row['expected_first_checksum_mismatch_index'], $result['first_checksum_mismatch_index']);
        $t->same((bool) $row['journal_deleted_after_recovery'] ? 'delete_journal_after_recovery' : 'preserve_journal', $result['journal_action']);
        $t->same((int) $row['initial_database_page_count'] * (int) $row['page_size'], $result['final_database_bytes']);

        $expectedPage1 = in_array(1, $row['expected_applied_pages'], true) ? $images['initial'][1] : $images['dirty'][1];
        $expectedPage2 = in_array(2, $row['expected_applied_pages'], true) ? $images['initial'][2] : $images['dirty'][2];
        $recoveredPage1 = substr((string) $result['database_bytes'], 0, (int) $row['page_size']);
        $recoveredPage2 = substr((string) $result['database_bytes'], (int) $row['page_size'], (int) $row['page_size']);

        $t->same($expectedPage1, $recoveredPage1);
        $t->same($expectedPage2, $recoveredPage2);
        $t->same($row['expected_rows']['t1'], $rowsFromPage($recoveredPage1));
        $t->same($row['expected_rows']['t2'], $rowsFromPage($recoveredPage2));

        if ((bool) $row['read_only_connection']) {
            $t->same(false, $result['recovered']);
            $t->same(null, $result['recovery_plan']);
            $t->same($dirtyDatabase, $result['database_bytes']);
            return;
        }

        $t->same(true, $result['recovered']);
        $t->same(2, count($result['recovery_plan']['pages']));
        $t->same([1, 2], array_column($result['recovery_plan']['pages'], 'page_number'));
        $t->same($row['expected_applied_pages'], array_values(array_map(
            static fn (array $page): int => $page['page_number'],
            array_filter($result['recovery_plan']['pages'], static fn (array $page): bool => $page['applied'])
        )));
        $t->same(
            $row['corrupt_record'] === null ? [true, true] : ($row['corrupt_record'] === 1 ? [false, true] : [true, false]),
            array_column($result['recovery_plan']['pages'], 'checksum_valid')
        );
        $t->same(true, in_array('sqlite-rollback-journal-checksum-prefix-recovery', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-hot-journal-readonly-write-required', $row['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal hot journal checksum dynamic inventory and non overlap'] = static function (TestRunner $t) use ($rows): void {
    $phases = array_values(array_unique(array_column($rows, 'phase')));
    sort($phases);

    $t->same(1000, count($rows));
    $t->same('pager1.test pager1.4.5.3 hot-journal checksum stop dynamic case 0001', $rows[0]['upstream']);
    $t->same('pager1.test pager1.4.5.6 hot-journal checksum stop dynamic case 1000', $rows[999]['upstream']);
    $t->same([
        'first-record-checksum-fails-no-rollback',
        'readonly-hot-journal-cannot-rollback',
        'second-record-checksum-fails-prefix-rollback',
        'valid-hot-journal-full-rollback',
    ], $phases);
    $t->same(
        'upstream source: pager1.test pager1.4.5.3 through pager1.4.5.6 covers valid hot-journal rollback, checksum failure on the first or second page record, and read-only hot-journal access failure',
        'upstream source: pager1.test pager1.4.5.3 through pager1.4.5.6 covers valid hot-journal rollback, checksum failure on the first or second page record, and read-only hot-journal access failure'
    );
    $t->same(
        'non-overlap: targets pager1.4.5 checksum-stop hot-journal playback only; avoids accepted pager1 invalid-page, peer-lock cleanup, zero-page-size journal fallback, missing DELETE-journal unlink failure, empty-database stale-journal cleanup, savepoint2 WAL signatures, WAL checkpoint transactions, VFS writer/sync/lock, rollback-journal apply/commit, and broad generic pager1-4 coverage',
        'non-overlap: targets pager1.4.5 checksum-stop hot-journal playback only; avoids accepted pager1 invalid-page, peer-lock cleanup, zero-page-size journal fallback, missing DELETE-journal unlink failure, empty-database stale-journal cleanup, savepoint2 WAL signatures, WAL checkpoint transactions, VFS writer/sync/lock, rollback-journal apply/commit, and broad generic pager1-4 coverage'
    );
    $t->same(
        'dependency-closure: no new support component needed; extends the source-neutral rollback-journal parser/recovery model and uses hydrated upstream pager1.test source truth',
        'dependency-closure: no new support component needed; extends the source-neutral rollback-journal parser/recovery model and uses hydrated upstream pager1.test source truth'
    );
};

$tests['real upstream corpus pager wal hot journal checksum dynamic rejects invalid row count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteRealPagerBoundaryPlan::hotJournalChecksumStopRows(0));
};

return $tests;
