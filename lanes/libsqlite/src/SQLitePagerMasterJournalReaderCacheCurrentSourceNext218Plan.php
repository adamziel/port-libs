<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext218Plan
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
    ): array {
        if ($currentMasterJournalCleanupToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next218 requires a current master-journal cleanup token');
        }

        $cacheCleanupTokens = self::cacheCleanupTokens($readerCache);
        $readCleanupTokens = self::readCleanupTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext212Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripCleanupToken($readerCache),
            array_map(static fn (array $read): array => self::stripOneCleanupToken($read), $nextReads),
            $currentSourceId,
            $currentEpoch,
            $currentPublicationGeneration,
            $currentMasterSourceDigest,
            $currentRecoverySequence,
            $currentMemberJournalTokens,
            $currentMemberJournalHeaderDigests,
            $currentMasterJournalFileToken,
            $currentDatabaseFileToken,
        );

        $cleanupInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = $row['page_number'];
            $cacheToken = $cacheCleanupTokens[$pageNumber] ?? '';
            $reason = $cacheToken === $currentMasterJournalCleanupToken
                ? null
                : 'reader_cache_master_journal_cleanup_token_changed_after_recovery';

            if ((bool) ($row['database_file_token_admitted'] ?? false) && $reason !== null) {
                $cleanupInvalidated[] = $pageNumber;
            }

            $rows[] = $row + [
                'master_journal_cleanup_token_admitted' => (bool) ($row['database_file_token_admitted'] ?? false) && $reason === null,
                'master_journal_cleanup_token_reason' => (bool) ($row['database_file_token_admitted'] ?? false)
                    ? ($reason ?? 'reader_cache_master_journal_cleanup_token_matches_current_source')
                    : ($row['database_file_token_reason'] ?? $row['master_journal_bytes_digest_reason'] ?? $row['reason']),
                'cache_master_journal_cleanup_token' => $cacheToken,
                'current_master_journal_cleanup_token' => $currentMasterJournalCleanupToken,
                'master_journal_cleanup_token_matches' => $cacheToken === $currentMasterJournalCleanupToken,
            ];
        }

        $cleanupInvalidated = self::sortedUnique($cleanupInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $cleanupInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $cleanupInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $cleanupInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = ($readCleanupTokens[$readerId] ?? '') === $currentMasterJournalCleanupToken;
            $pageInvalidated = in_array($read['page_number'], $cleanupInvalidated, true);
            $read['master_journal_cleanup_token_current'] = $ticketCurrent;
            $read['master_journal_cleanup_token'] = $currentMasterJournalCleanupToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-cleanup-token-fence-current-source-next218';
                $read['master_journal_cleanup_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_master_journal_cleanup'
                    : 'reader_ticket_master_journal_cleanup_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_master_journal_cleanup_after_current_source_next218',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['master_journal_cleanup_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next218';
        $base['reason'] = 'master_journal_reader_cache_rechecks_cleanup_token_before_current_source_reuse';
        $base['current_master_journal_cleanup_token'] = $currentMasterJournalCleanupToken;
        $base['reader_rows'] = $rows;
        $base['master_journal_cleanup_invalidated_cache_page_numbers'] = $cleanupInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentMasterJournalCleanupToken . '|' . implode(',', $cleanupInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next218';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-master-journal-cleanup-token-fence';
        $base['non_overlap'] = 'next218 fences reader-cache reuse after master-journal cleanup/deletion tokens change, layered after next212 database file-token admission; it does not repeat rollback-journal apply, super-journal commit, database file-token, recovered-page, raw bytes, file-token, member-order, member-header, or member-token fences.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheCleanupTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['master_journal_cleanup_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next218 cache entries require cleanup tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readCleanupTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['master_journal_cleanup_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next218 reads require reader ids and cleanup tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripCleanupToken(array $cache): array
    {
        $stripped = [];
        foreach ($cache as $pageNumber => $entry) {
            unset($entry['master_journal_cleanup_token']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneCleanupToken(array $read): array
    {
        unset($read['master_journal_cleanup_token']);

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
