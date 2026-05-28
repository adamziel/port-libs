<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext226Plan
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
    ): array {
        if ($currentDatabaseChangeCounter < 1 || $currentVersionValidFor < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next226 requires positive current header counters');
        }
        if ($currentDatabaseChangeCounter !== $currentVersionValidFor) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next226 requires current change-counter/version-valid-for coherence');
        }

        $cacheCounters = self::cacheHeaderCounters($readerCache);
        $readCounters = self::readHeaderCounters($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext219Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripHeaderCounters($readerCache),
            array_map(static fn (array $read): array => self::stripOneHeaderCounter($read), $nextReads),
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
        );

        $counterInvalidated = [];
        $incoherentCachePages = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $counter = $cacheCounters[$pageNumber] ?? ['change' => 0, 'valid' => 0];
            $cacheCoherent = $counter['change'] === $counter['valid'];
            $counterCurrent = $counter['change'] === $currentDatabaseChangeCounter
                && $counter['valid'] === $currentVersionValidFor;
            $reason = match (true) {
                !$cacheCoherent => 'reader_cache_header_counter_pair_incoherent_after_master_journal_recovery',
                !$counterCurrent => 'reader_cache_header_counter_pair_changed_after_master_journal_recovery',
                default => null,
            };

            if ((bool) ($row['database_page_count_admitted'] ?? false) && $reason !== null) {
                $counterInvalidated[] = $pageNumber;
                if (!$cacheCoherent) {
                    $incoherentCachePages[] = $pageNumber;
                }
            }

            $rows[] = $row + [
                'header_counter_pair_admitted' => (bool) ($row['database_page_count_admitted'] ?? false) && $reason === null,
                'header_counter_pair_reason' => (bool) ($row['database_page_count_admitted'] ?? false)
                    ? ($reason ?? 'reader_cache_header_counter_pair_matches_current_source')
                    : ($row['database_page_count_reason'] ?? $row['database_header_digest_reason'] ?? $row['reason']),
                'cache_database_change_counter' => $counter['change'],
                'cache_version_valid_for' => $counter['valid'],
                'current_database_change_counter' => $currentDatabaseChangeCounter,
                'current_version_valid_for' => $currentVersionValidFor,
                'header_counter_pair_coherent' => $cacheCoherent,
                'header_counter_pair_matches' => $counterCurrent,
            ];
        }

        $counterInvalidated = self::sortedUnique($counterInvalidated);
        $incoherentCachePages = self::sortedUnique($incoherentCachePages);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $counterInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $counterInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $counterInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $counter = $readCounters[$readerId] ?? ['change' => 0, 'valid' => 0];
            $ticketCoherent = $counter['change'] === $counter['valid'];
            $ticketCurrent = $ticketCoherent
                && $counter['change'] === $currentDatabaseChangeCounter
                && $counter['valid'] === $currentVersionValidFor;
            $pageInvalidated = in_array($read['page_number'], $counterInvalidated, true);
            $read['header_counter_pair_current'] = $ticketCurrent;
            $read['header_counter_pair_coherent'] = $ticketCoherent;
            $read['database_change_counter'] = $currentDatabaseChangeCounter;
            $read['version_valid_for'] = $currentVersionValidFor;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-header-counter-fence-current-source-next226';
                $read['header_counter_pair_reason'] = match (true) {
                    !$ticketCoherent => 'reader_ticket_header_counter_pair_incoherent',
                    $pageInvalidated => 'reader_cache_reopened_after_header_counter_change',
                    default => 'reader_ticket_header_counter_pair_predates_current_source',
                };
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_header_counter_pair_after_current_source_next226',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['header_counter_pair_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next226';
        $base['reason'] = 'master_journal_reader_cache_rechecks_change_counter_version_valid_for_before_current_source_reuse';
        $base['current_database_change_counter'] = $currentDatabaseChangeCounter;
        $base['current_version_valid_for'] = $currentVersionValidFor;
        $base['reader_rows'] = $rows;
        $base['header_counter_invalidated_cache_page_numbers'] = $counterInvalidated;
        $base['header_counter_incoherent_cache_page_numbers'] = $incoherentCachePages;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentDatabaseChangeCounter . '|' . $currentVersionValidFor . '|' . implode(',', $counterInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next226';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-header-counter-fence';
        $base['non_overlap'] = 'next226 fences reader-cache reuse on SQLite page-1 change-counter/version-valid-for coherence after next219 database page-count admission; it does not repeat master-journal bytes, member token/header/order, cleanup token, database file-token, header digest, page-count, rollback-journal apply, WAL, or VFS writer behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array{change:int,valid:int}> */
    private static function cacheHeaderCounters(array $cache): array
    {
        $counters = [];
        foreach ($cache as $pageNumber => $entry) {
            $change = $entry['database_change_counter'] ?? null;
            $valid = $entry['version_valid_for'] ?? null;
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_int($change) || !is_int($valid) || $change < 1 || $valid < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next226 cache entries require positive header counters');
            }
            $counters[$pageNumber] = ['change' => $change, 'valid' => $valid];
        }

        return $counters;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,array{change:int,valid:int}> */
    private static function readHeaderCounters(array $reads): array
    {
        $counters = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $change = $read['database_change_counter'] ?? null;
            $valid = $read['version_valid_for'] ?? null;
            if ($readerId === '' || !is_int($change) || !is_int($valid) || $change < 1 || $valid < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next226 reads require reader ids and positive header counters');
            }
            $counters[$readerId] = ['change' => $change, 'valid' => $valid];
        }

        return $counters;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripHeaderCounters(array $cache): array
    {
        $stripped = [];
        foreach ($cache as $pageNumber => $entry) {
            unset($entry['database_change_counter'], $entry['version_valid_for']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneHeaderCounter(array $read): array
    {
        unset($read['database_change_counter'], $read['version_valid_for']);

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
