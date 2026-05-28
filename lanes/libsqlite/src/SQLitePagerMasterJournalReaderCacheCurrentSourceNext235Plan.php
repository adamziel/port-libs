<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext235Plan
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
        string $currentMasterJournalCleanupToken,
        string $currentReaderLeaseToken,
        string $currentPagerCacheSourceToken,
        string $currentDatabasePathToken,
        int $currentDatabaseChangeCounter,
    ): array {
        if ($currentDatabaseChangeCounter < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next235 requires a positive current database change counter');
        }

        $cacheCounters = self::cacheDatabaseChangeCounters($readerCache);
        $readCounters = self::readDatabaseChangeCounters($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext232Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripDatabaseChangeCounter($readerCache),
            array_map(static fn (array $read): array => self::stripOneDatabaseChangeCounter($read), $nextReads),
            $currentSourceId,
            $currentEpoch,
            $currentPublicationGeneration,
            $currentMasterSourceDigest,
            $currentRecoverySequence,
            $currentMemberJournalTokens,
            $currentMemberJournalHeaderDigests,
            $currentMasterJournalFileToken,
            $currentDatabaseFileToken,
            $currentMasterJournalCleanupToken,
            $currentReaderLeaseToken,
            $currentPagerCacheSourceToken,
            $currentDatabasePathToken,
        );

        $counterInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheCounter = $cacheCounters[$pageNumber] ?? 0;
            $reason = $cacheCounter === $currentDatabaseChangeCounter
                ? null
                : 'reader_cache_database_change_counter_predates_master_journal_current_source';

            if ((bool) ($row['database_path_token_admitted'] ?? false) && $reason !== null) {
                $counterInvalidated[] = $pageNumber;
            }

            $rows[] = $row + [
                'database_change_counter_admitted' => (bool) ($row['database_path_token_admitted'] ?? false) && $reason === null,
                'database_change_counter_reason' => (bool) ($row['database_path_token_admitted'] ?? false)
                    ? ($reason ?? 'reader_cache_database_change_counter_matches_current_source')
                    : (string) ($row['database_path_token_reason'] ?? $row['pager_cache_source_token_reason'] ?? $row['reason']),
                'cache_database_change_counter' => $cacheCounter,
                'current_database_change_counter' => $currentDatabaseChangeCounter,
                'database_change_counter_matches' => $cacheCounter === $currentDatabaseChangeCounter,
            ];
        }

        $counterInvalidated = self::sortedUnique($counterInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $counterInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $counterInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $counterInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = ($readCounters[$readerId] ?? 0) === $currentDatabaseChangeCounter;
            $pageInvalidated = in_array($read['page_number'], $counterInvalidated, true);
            $read['database_change_counter_current'] = $ticketCurrent;
            $read['database_change_counter'] = $currentDatabaseChangeCounter;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-change-counter-fence-current-source-next235';
                $read['database_change_counter_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_database_change_counter_change'
                    : 'reader_ticket_database_change_counter_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_database_change_counter_after_current_source_next235',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['database_change_counter_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next235';
        $base['reason'] = 'master_journal_reader_cache_rechecks_database_change_counter_before_current_source_reuse';
        $base['current_database_change_counter'] = $currentDatabaseChangeCounter;
        $base['reader_rows'] = $rows;
        $base['database_change_counter_invalidated_cache_page_numbers'] = $counterInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentDatabaseChangeCounter . '|' . implode(',', $counterInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next235';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-database-change-counter-fence';
        $base['non_overlap'] = 'next235 fences reader-cache reuse on the SQLite database-header change counter after next232 database path namespace, next229 pager-cache-source, next224 reader-lease, and next218 cleanup-token admission have already passed; it does not repeat database path, page count, database header digest, file token, cleanup token, pager-cache-source, reader lease, master-journal bytes, member-journal, WAL, VFS writer, sync-plan, rollback-journal apply, or super-journal behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,int> */
    private static function cacheDatabaseChangeCounters(array $cache): array
    {
        $counters = [];
        foreach ($cache as $pageNumber => $entry) {
            $counter = $entry['database_change_counter'] ?? null;
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_int($counter) || $counter < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next235 cache entries require positive database change counters');
            }
            $counters[$pageNumber] = $counter;
        }

        return $counters;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,int> */
    private static function readDatabaseChangeCounters(array $reads): array
    {
        $counters = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $counter = $read['database_change_counter'] ?? null;
            if ($readerId === '' || !is_int($counter) || $counter < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next235 reads require reader ids and positive database change counters');
            }
            $counters[$readerId] = $counter;
        }

        return $counters;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripDatabaseChangeCounter(array $cache): array
    {
        $stripped = [];
        foreach ($cache as $pageNumber => $entry) {
            unset($entry['database_change_counter']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneDatabaseChangeCounter(array $read): array
    {
        unset($read['database_change_counter']);

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
