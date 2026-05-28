<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext219Plan
{
    /**
     * @param array<int,string> $recoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,member_journal_tokens?:array<string,string>,member_journal_header_digests?:array<string,string>,master_member_order_digest?:string,master_journal_file_token?:string,master_journal_bytes_digest?:string,database_file_token?:string,database_header_digest?:string,database_page_count?:int,dirty?:bool,pinned?:bool,shared?:bool,label?:string}> $readerCache
     * @param list<array{reader_id?:string,page_number:int,source_id?:string,epoch?:int,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,member_journal_token_digest?:string,member_journal_header_digest?:string,master_member_order_digest?:string,master_journal_file_token?:string,master_journal_bytes_digest?:string,database_file_token?:string,database_header_digest?:string,database_page_count?:int}> $nextReads
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
    ): array {
        if ($currentDatabasePageCount < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next219 requires a positive current database page count');
        }

        $cachePageCounts = self::cacheDatabasePageCounts($readerCache);
        $readPageCounts = self::readDatabasePageCounts($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext217Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripDatabasePageCount($readerCache),
            array_map(static fn (array $read): array => [
                'reader_id' => $read['reader_id'] ?? null,
                'page_number' => $read['page_number'],
                'source_id' => $read['source_id'] ?? null,
                'epoch' => $read['epoch'] ?? null,
                'format_signature' => $read['format_signature'] ?? null,
                'publication_generation' => $read['publication_generation'] ?? null,
                'master_source_digest' => $read['master_source_digest'] ?? null,
                'recovery_sequence' => $read['recovery_sequence'] ?? null,
                'recovered_page_set_digest' => $read['recovered_page_set_digest'] ?? null,
                'member_journal_token_digest' => $read['member_journal_token_digest'] ?? null,
                'member_journal_header_digest' => $read['member_journal_header_digest'] ?? null,
                'master_member_order_digest' => $read['master_member_order_digest'] ?? null,
                'master_journal_file_token' => $read['master_journal_file_token'] ?? null,
                'master_journal_bytes_digest' => $read['master_journal_bytes_digest'] ?? null,
                'database_file_token' => $read['database_file_token'] ?? null,
                'database_header_digest' => $read['database_header_digest'] ?? null,
            ], $nextReads),
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
        );

        $pageCountInvalidated = [];
        $truncatedPageNumbers = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cachePageCount = $cachePageCounts[$pageNumber] ?? 0;
            $pageCountChanged = $cachePageCount !== $currentDatabasePageCount;
            $pagePastEnd = $pageNumber > $currentDatabasePageCount;
            $reason = match (true) {
                $pagePastEnd => 'reader_cache_page_number_exceeds_current_database_page_count',
                $pageCountChanged => 'reader_cache_database_page_count_changed_after_master_journal_recovery',
                default => null,
            };

            if ((bool) ($row['database_header_digest_admitted'] ?? false) && $reason !== null) {
                $pageCountInvalidated[] = $pageNumber;
                if ($pagePastEnd) {
                    $truncatedPageNumbers[] = $pageNumber;
                }
            }

            $rows[] = $row + [
                'database_page_count_admitted' => (bool) ($row['database_header_digest_admitted'] ?? false) && $reason === null,
                'database_page_count_reason' => (bool) ($row['database_header_digest_admitted'] ?? false)
                    ? ($reason ?? 'reader_cache_database_page_count_matches_current_source')
                    : ($row['database_header_digest_reason'] ?? $row['database_file_token_reason'] ?? $row['master_journal_bytes_digest_reason'] ?? $row['reason']),
                'cache_database_page_count' => $cachePageCount,
                'current_database_page_count' => $currentDatabasePageCount,
                'database_page_count_matches' => $cachePageCount === $currentDatabasePageCount,
                'page_number_within_current_page_count' => !$pagePastEnd,
            ];
        }

        $pageCountInvalidated = self::sortedUnique($pageCountInvalidated);
        $truncatedPageNumbers = self::sortedUnique($truncatedPageNumbers);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $pageCountInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $pageCountInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $pageCountInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketPageCount = $readPageCounts[$readerId] ?? 0;
            $ticketCurrent = $ticketPageCount === $currentDatabasePageCount;
            $pageInvalidated = in_array($read['page_number'], $pageCountInvalidated, true);
            $pagePastEnd = ((int) $read['page_number']) > $currentDatabasePageCount;
            $read['database_page_count_current'] = $ticketCurrent;
            $read['database_page_count'] = $currentDatabasePageCount;
            $read['page_number_within_current_page_count'] = !$pagePastEnd;
            if (!$ticketCurrent || $pageInvalidated || $pagePastEnd) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-database-page-count-fence-current-source-next219';
                $read['database_page_count_reason'] = $pagePastEnd
                    ? 'reader_page_number_exceeds_current_database_page_count'
                    : ($pageInvalidated
                        ? 'reader_cache_reopened_after_database_page_count_change'
                        : 'reader_ticket_database_page_count_predates_current_source');
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_database_page_count_after_current_source_next219',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['database_page_count_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next219';
        $base['reason'] = 'master_journal_reader_cache_rechecks_database_page_count_before_current_source_reuse';
        $base['current_database_page_count'] = $currentDatabasePageCount;
        $base['reader_rows'] = $rows;
        $base['database_page_count_invalidated_cache_page_numbers'] = $pageCountInvalidated;
        $base['database_page_count_truncated_cache_page_numbers'] = $truncatedPageNumbers;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentDatabasePageCount . '|' . implode(',', $pageCountInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next219';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-database-page-count-fence';
        $base['non_overlap'] = 'next219 fences reader-cache reuse on the recovered database page count and out-of-range reads after next217 database header admission; it does not repeat raw master-journal bytes, member-token/header/order, file-token, database-header digest, rollback-journal apply, WAL, VFS writer, or super-journal commit behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,int> */
    private static function cacheDatabasePageCounts(array $cache): array
    {
        $counts = [];
        foreach ($cache as $pageNumber => $entry) {
            $pageCount = $entry['database_page_count'] ?? null;
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_int($pageCount) || $pageCount < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next219 cache entries require positive database page counts');
            }
            $counts[$pageNumber] = $pageCount;
        }

        return $counts;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,int> */
    private static function readDatabasePageCounts(array $reads): array
    {
        $counts = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $pageCount = $read['database_page_count'] ?? null;
            if ($readerId === '' || !is_int($pageCount) || $pageCount < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next219 reads require reader ids and positive database page counts');
            }
            $counts[$readerId] = $pageCount;
        }

        return $counts;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripDatabasePageCount(array $cache): array
    {
        $stripped = [];
        foreach ($cache as $pageNumber => $entry) {
            unset($entry['database_page_count']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
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
