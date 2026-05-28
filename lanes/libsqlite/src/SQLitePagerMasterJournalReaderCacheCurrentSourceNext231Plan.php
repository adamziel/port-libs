<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext231Plan
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
        int $currentFreelistTrunkPage,
        int $currentFreelistPageCount,
    ): array {
        if ($currentFreelistTrunkPage < 0 || $currentFreelistPageCount < 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next231 requires non-negative current freelist header fields');
        }
        if ($currentFreelistTrunkPage === 0 && $currentFreelistPageCount !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next231 requires a trunk page when freelist count is non-zero');
        }
        if ($currentFreelistTrunkPage > $currentDatabasePageCount) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next231 current freelist trunk exceeds database page count');
        }

        $cacheFreelists = self::cacheFreelistHeaders($readerCache);
        $readFreelists = self::readFreelistHeaders($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext226Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripFreelistHeaders($readerCache),
            array_map(static fn (array $read): array => self::stripOneFreelistHeader($read), $nextReads),
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

        $freelistInvalidated = [];
        $incoherentFreelistPages = [];
        $trunkPastEndPages = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $freelist = $cacheFreelists[$pageNumber] ?? ['trunk' => 0, 'count' => 0];
            $cacheCoherent = self::freelistHeaderCoherent($freelist['trunk'], $freelist['count'], $currentDatabasePageCount);
            $freelistCurrent = $cacheCoherent
                && $freelist['trunk'] === $currentFreelistTrunkPage
                && $freelist['count'] === $currentFreelistPageCount;
            $reason = match (true) {
                !$cacheCoherent && $freelist['trunk'] > $currentDatabasePageCount => 'reader_cache_freelist_trunk_exceeds_current_database_page_count_after_master_journal_recovery',
                !$cacheCoherent => 'reader_cache_freelist_header_incoherent_after_master_journal_recovery',
                !$freelistCurrent => 'reader_cache_freelist_header_changed_after_master_journal_recovery',
                default => null,
            };

            if ((bool) ($row['header_counter_pair_admitted'] ?? false) && $reason !== null) {
                $freelistInvalidated[] = $pageNumber;
                if (!$cacheCoherent) {
                    $incoherentFreelistPages[] = $pageNumber;
                }
                if ($freelist['trunk'] > $currentDatabasePageCount) {
                    $trunkPastEndPages[] = $pageNumber;
                }
            }

            $rows[] = $row + [
                'freelist_header_admitted' => (bool) ($row['header_counter_pair_admitted'] ?? false) && $reason === null,
                'freelist_header_reason' => (bool) ($row['header_counter_pair_admitted'] ?? false)
                    ? ($reason ?? 'reader_cache_freelist_header_matches_current_source')
                    : ($row['header_counter_pair_reason'] ?? $row['database_page_count_reason'] ?? $row['reason']),
                'cache_freelist_trunk_page' => $freelist['trunk'],
                'cache_freelist_page_count' => $freelist['count'],
                'current_freelist_trunk_page' => $currentFreelistTrunkPage,
                'current_freelist_page_count' => $currentFreelistPageCount,
                'freelist_header_coherent' => $cacheCoherent,
                'freelist_header_matches' => $freelistCurrent,
                'freelist_trunk_within_current_page_count' => $freelist['trunk'] <= $currentDatabasePageCount,
            ];
        }

        $freelistInvalidated = self::sortedUnique($freelistInvalidated);
        $incoherentFreelistPages = self::sortedUnique($incoherentFreelistPages);
        $trunkPastEndPages = self::sortedUnique($trunkPastEndPages);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $freelistInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $freelistInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $freelistInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $freelist = $readFreelists[$readerId] ?? ['trunk' => 0, 'count' => 0];
            $ticketCoherent = self::freelistHeaderCoherent($freelist['trunk'], $freelist['count'], $currentDatabasePageCount);
            $ticketCurrent = $ticketCoherent
                && $freelist['trunk'] === $currentFreelistTrunkPage
                && $freelist['count'] === $currentFreelistPageCount;
            $pageInvalidated = in_array($read['page_number'], $freelistInvalidated, true);
            $read['freelist_header_current'] = $ticketCurrent;
            $read['freelist_header_coherent'] = $ticketCoherent;
            $read['freelist_trunk_page'] = $currentFreelistTrunkPage;
            $read['freelist_page_count'] = $currentFreelistPageCount;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-freelist-header-fence-current-source-next231';
                $read['freelist_header_reason'] = match (true) {
                    !$ticketCoherent && $freelist['trunk'] > $currentDatabasePageCount => 'reader_ticket_freelist_trunk_exceeds_current_database_page_count',
                    !$ticketCoherent => 'reader_ticket_freelist_header_incoherent',
                    $pageInvalidated => 'reader_cache_reopened_after_freelist_header_change',
                    default => 'reader_ticket_freelist_header_predates_current_source',
                };
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_freelist_header_after_current_source_next231',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['freelist_header_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next231';
        $base['reason'] = 'master_journal_reader_cache_rechecks_freelist_header_before_current_source_reuse';
        $base['current_freelist_trunk_page'] = $currentFreelistTrunkPage;
        $base['current_freelist_page_count'] = $currentFreelistPageCount;
        $base['reader_rows'] = $rows;
        $base['freelist_header_invalidated_cache_page_numbers'] = $freelistInvalidated;
        $base['freelist_header_incoherent_cache_page_numbers'] = $incoherentFreelistPages;
        $base['freelist_trunk_past_end_cache_page_numbers'] = $trunkPastEndPages;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentFreelistTrunkPage . '|' . $currentFreelistPageCount . '|' . implode(',', $freelistInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next231';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-freelist-header-fence';
        $base['non_overlap'] = 'next231 fences reader-cache reuse on SQLite page-1 freelist trunk/count state after next226 header-counter admission; it does not repeat master-journal bytes, member token/header/order, cleanup token, database file-token, header digest, page-count, change-counter, rollback-journal apply, WAL, VFS writer, or super-journal behavior.';

        return $base;
    }

    private static function freelistHeaderCoherent(int $trunkPage, int $pageCount, int $databasePageCount): bool
    {
        if ($trunkPage < 0 || $pageCount < 0 || $trunkPage > $databasePageCount) {
            return false;
        }

        return !($trunkPage === 0 && $pageCount !== 0);
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array{trunk:int,count:int}> */
    private static function cacheFreelistHeaders(array $cache): array
    {
        $headers = [];
        foreach ($cache as $pageNumber => $entry) {
            $trunk = $entry['freelist_trunk_page'] ?? null;
            $count = $entry['freelist_page_count'] ?? null;
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_int($trunk) || !is_int($count) || $trunk < 0 || $count < 0) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next231 cache entries require non-negative freelist header fields');
            }
            $headers[$pageNumber] = ['trunk' => $trunk, 'count' => $count];
        }

        return $headers;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,array{trunk:int,count:int}> */
    private static function readFreelistHeaders(array $reads): array
    {
        $headers = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $trunk = $read['freelist_trunk_page'] ?? null;
            $count = $read['freelist_page_count'] ?? null;
            if ($readerId === '' || !is_int($trunk) || !is_int($count) || $trunk < 0 || $count < 0) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next231 reads require reader ids and non-negative freelist header fields');
            }
            $headers[$readerId] = ['trunk' => $trunk, 'count' => $count];
        }

        return $headers;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripFreelistHeaders(array $cache): array
    {
        $stripped = [];
        foreach ($cache as $pageNumber => $entry) {
            unset($entry['freelist_trunk_page'], $entry['freelist_page_count']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneFreelistHeader(array $read): array
    {
        unset($read['freelist_trunk_page'], $read['freelist_page_count']);

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
