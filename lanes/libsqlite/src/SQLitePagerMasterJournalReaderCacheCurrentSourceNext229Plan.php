<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext229Plan
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
    ): array {
        if ($currentPagerCacheSourceToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next229 requires a current pager cache source token');
        }

        $cacheTokens = self::cachePagerCacheSourceTokens($readerCache);
        $readTokens = self::readPagerCacheSourceTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext224Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripPagerCacheSourceToken($readerCache),
            array_map(static fn (array $read): array => self::stripOnePagerCacheSourceToken($read), $nextReads),
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
        );

        $cacheSourceInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheToken = $cacheTokens[$pageNumber] ?? '';
            $reason = hash_equals($cacheToken, $currentPagerCacheSourceToken)
                ? null
                : 'reader_cache_pager_cache_source_token_predates_master_journal_current_source';

            if ((bool) ($row['reader_lease_token_admitted'] ?? false) && $reason !== null) {
                $cacheSourceInvalidated[] = $pageNumber;
            }

            $rows[] = $row + [
                'pager_cache_source_token_admitted' => (bool) ($row['reader_lease_token_admitted'] ?? false) && $reason === null,
                'pager_cache_source_token_reason' => (bool) ($row['reader_lease_token_admitted'] ?? false)
                    ? ($reason ?? 'reader_cache_pager_cache_source_token_matches_current_source')
                    : ($row['reader_lease_token_reason'] ?? $row['master_journal_cleanup_token_reason'] ?? $row['database_file_token_reason'] ?? $row['reason']),
                'cache_pager_cache_source_token' => $cacheToken,
                'current_pager_cache_source_token' => $currentPagerCacheSourceToken,
                'pager_cache_source_token_matches' => hash_equals($cacheToken, $currentPagerCacheSourceToken),
            ];
        }

        $cacheSourceInvalidated = self::sortedUnique($cacheSourceInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $cacheSourceInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $cacheSourceInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $cacheSourceInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = hash_equals($readTokens[$readerId] ?? '', $currentPagerCacheSourceToken);
            $pageInvalidated = in_array($read['page_number'], $cacheSourceInvalidated, true);
            $read['pager_cache_source_token_current'] = $ticketCurrent;
            $read['pager_cache_source_token'] = $currentPagerCacheSourceToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-pager-cache-source-fence-current-source-next229';
                $read['pager_cache_source_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_pager_cache_source_token_change'
                    : 'reader_ticket_pager_cache_source_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_pager_cache_source_after_current_source_next229',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['pager_cache_source_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next229';
        $base['reason'] = 'master_journal_reader_cache_rechecks_pager_cache_source_before_current_source_reuse';
        $base['current_pager_cache_source_token'] = $currentPagerCacheSourceToken;
        $base['reader_rows'] = $rows;
        $base['pager_cache_source_invalidated_cache_page_numbers'] = $cacheSourceInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentPagerCacheSourceToken . '|' . implode(',', $cacheSourceInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next229';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-source-generation-fence';
        $base['non_overlap'] = 'next229 fences reader-cache reuse on the pager cache source token after next224 reader-lease, next218 cleanup-token, and next212 database file-token admission have already passed; it does not repeat reader-lease, cleanup-token, database file-token, raw master bytes, member-journal, rollback-journal, WAL, VFS writer, sync-plan, or super-journal behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cachePagerCacheSourceTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['pager_cache_source_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next229 cache entries require pager cache source tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readPagerCacheSourceTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['pager_cache_source_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next229 reads require reader ids and pager cache source tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripPagerCacheSourceToken(array $cache): array
    {
        $stripped = [];
        foreach ($cache as $pageNumber => $entry) {
            unset($entry['pager_cache_source_token']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOnePagerCacheSourceToken(array $read): array
    {
        unset($read['pager_cache_source_token']);

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
