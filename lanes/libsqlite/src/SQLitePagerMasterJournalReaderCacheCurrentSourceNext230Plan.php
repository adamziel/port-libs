<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext230Plan
{
    /**
     * @param array<int,string> $recoveredPages
     * @param array<int,array<string,mixed>> $readerCache
     * @param list<array<string,mixed>> $nextReads
     * @param array<string,string> $currentMemberJournalTokens
     * @param array<string,string> $currentMemberJournalHeaderDigests
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        string $currentMasterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        array $recoveredPages,
        array $readerCache,
        array $nextReads,
        string $currentSourceId,
        int $currentEpoch,
        int $currentPublicationGeneration,
        string $currentMasterSourceDigest,
        int $currentRecoverySequence,
        array $currentMemberJournalTokens,
        array $currentMemberJournalHeaderDigests,
        string $currentMasterJournalFileToken,
        string $currentDatabaseFileToken,
        string $currentDatabaseHeaderDigest,
        int $currentDatabasePageCount,
        int $currentDatabaseChangeCounter,
        int $currentVersionValidFor,
        int $currentSqliteVersionNumber,
    ): array {
        if ($currentSqliteVersionNumber < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next230 requires a positive SQLite version-number stamp');
        }

        $cacheVersions = self::cacheSqliteVersionNumbers($readerCache);
        $readVersions = self::readSqliteVersionNumbers($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext226Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripSqliteVersionNumber($readerCache),
            array_map(static fn (array $read): array => self::stripOneSqliteVersionNumber($read), $nextReads),
            $currentSourceId,
            $currentEpoch,
            $currentPublicationGeneration,
            $currentMasterSourceDigest,
            $currentRecoverySequence,
            $currentMemberJournalTokens,
            $currentMemberJournalHeaderDigests,
            $currentMasterJournalFileToken,
            $currentDatabaseFileToken,
            $currentDatabaseHeaderDigest,
            $currentDatabasePageCount,
            $currentDatabaseChangeCounter,
            $currentVersionValidFor,
        );

        $versionInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheVersion = $cacheVersions[$pageNumber] ?? 0;
            $reason = $cacheVersion === $currentSqliteVersionNumber
                ? null
                : 'reader_cache_sqlite_version_number_changed_after_master_journal_recovery';

            if ((bool) ($row['header_counter_pair_admitted'] ?? false) && $reason !== null) {
                $versionInvalidated[] = $pageNumber;
            }

            $rows[] = $row + [
                'sqlite_version_number_admitted' => (bool) ($row['header_counter_pair_admitted'] ?? false) && $reason === null,
                'sqlite_version_number_reason' => (bool) ($row['header_counter_pair_admitted'] ?? false)
                    ? ($reason ?? 'reader_cache_sqlite_version_number_matches_current_source')
                    : ($row['header_counter_pair_reason'] ?? $row['database_page_count_reason'] ?? $row['database_header_digest_reason'] ?? $row['reason']),
                'cache_sqlite_version_number' => $cacheVersion,
                'current_sqlite_version_number' => $currentSqliteVersionNumber,
                'sqlite_version_number_matches' => $cacheVersion === $currentSqliteVersionNumber,
            ];
        }

        $versionInvalidated = self::sortedUnique($versionInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $versionInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $versionInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $versionInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = ($readVersions[$readerId] ?? 0) === $currentSqliteVersionNumber;
            $pageInvalidated = in_array($read['page_number'], $versionInvalidated, true);
            $read['sqlite_version_number_current'] = $ticketCurrent;
            $read['sqlite_version_number'] = $currentSqliteVersionNumber;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-sqlite-version-number-fence-current-source-next230';
                $read['sqlite_version_number_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_sqlite_version_number_change'
                    : 'reader_ticket_sqlite_version_number_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_sqlite_version_number_after_current_source_next230',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['sqlite_version_number_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next230';
        $base['reason'] = 'master_journal_reader_cache_rechecks_sqlite_version_number_before_current_source_reuse';
        $base['current_sqlite_version_number'] = $currentSqliteVersionNumber;
        $base['reader_rows'] = $rows;
        $base['sqlite_version_number_invalidated_cache_page_numbers'] = $versionInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentSqliteVersionNumber . '|' . implode(',', $versionInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next230';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-sqlite-version-number-fence';
        $base['non_overlap'] = 'next230 fences reader-cache reuse on the SQLite page-1 version-number stamp after next226 header counter admission; it does not repeat format signatures, change-counter/version-valid-for coherence, page-count, database header digest, master-journal bytes, member token/header/order, cleanup-token, rollback-journal apply, WAL, or VFS writer behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,int> */
    private static function cacheSqliteVersionNumbers(array $cache): array
    {
        $versions = [];
        foreach ($cache as $pageNumber => $entry) {
            $version = $entry['sqlite_version_number'] ?? null;
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_int($version) || $version < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next230 cache entries require positive SQLite version-number stamps');
            }
            $versions[$pageNumber] = $version;
        }

        return $versions;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,int> */
    private static function readSqliteVersionNumbers(array $reads): array
    {
        $versions = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $version = $read['sqlite_version_number'] ?? null;
            if ($readerId === '' || !is_int($version) || $version < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next230 reads require reader ids and positive SQLite version-number stamps');
            }
            $versions[$readerId] = $version;
        }

        return $versions;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripSqliteVersionNumber(array $cache): array
    {
        $stripped = [];
        foreach ($cache as $pageNumber => $entry) {
            unset($entry['sqlite_version_number']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneSqliteVersionNumber(array $read): array
    {
        unset($read['sqlite_version_number']);

        return $read;
    }

    /** @param list<int> $values @return list<int> */
    private static function sortedUnique(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values, SORT_NUMERIC);

        return $values;
    }

    /** @param list<string> $values @return list<string> */
    private static function sortReaderIds(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values, SORT_NATURAL);

        return $values;
    }
}
