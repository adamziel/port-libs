<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext224Plan
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
    ): array {
        if ($currentReaderLeaseToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next224 requires a current reader lease token');
        }

        $cacheLeaseTokens = self::cacheReaderLeaseTokens($readerCache);
        $readLeaseTokens = self::readReaderLeaseTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext218Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripReaderLeaseToken($readerCache),
            array_map(static fn (array $read): array => self::stripOneReaderLeaseToken($read), $nextReads),
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
        );

        $leaseInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = $row['page_number'];
            $cacheToken = $cacheLeaseTokens[$pageNumber] ?? '';
            $reason = $cacheToken === $currentReaderLeaseToken
                ? null
                : 'reader_cache_reader_lease_token_predates_master_journal_current_source';

            if ((bool) ($row['master_journal_cleanup_token_admitted'] ?? false) && $reason !== null) {
                $leaseInvalidated[] = $pageNumber;
            }

            $rows[] = $row + [
                'reader_lease_token_admitted' => (bool) ($row['master_journal_cleanup_token_admitted'] ?? false) && $reason === null,
                'reader_lease_token_reason' => (bool) ($row['master_journal_cleanup_token_admitted'] ?? false)
                    ? ($reason ?? 'reader_cache_reader_lease_token_matches_current_source')
                    : ($row['master_journal_cleanup_token_reason'] ?? $row['database_file_token_reason'] ?? $row['master_journal_bytes_digest_reason'] ?? $row['reason']),
                'cache_reader_lease_token' => $cacheToken,
                'current_reader_lease_token' => $currentReaderLeaseToken,
                'reader_lease_token_matches' => $cacheToken === $currentReaderLeaseToken,
            ];
        }

        $leaseInvalidated = self::sortedUnique($leaseInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $leaseInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $leaseInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $leaseInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = ($readLeaseTokens[$readerId] ?? '') === $currentReaderLeaseToken;
            $pageInvalidated = in_array($read['page_number'], $leaseInvalidated, true);
            $read['reader_lease_token_current'] = $ticketCurrent;
            $read['reader_lease_token'] = $currentReaderLeaseToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-reader-lease-fence-current-source-next224';
                $read['reader_lease_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_reader_lease_token_change'
                    : 'reader_ticket_reader_lease_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_reader_lease_after_current_source_next224',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['reader_lease_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next224';
        $base['reason'] = 'master_journal_reader_cache_rechecks_reader_lease_before_current_source_reuse';
        $base['current_reader_lease_token'] = $currentReaderLeaseToken;
        $base['reader_rows'] = $rows;
        $base['reader_lease_invalidated_cache_page_numbers'] = $leaseInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentReaderLeaseToken . '|' . implode(',', $leaseInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next224';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-reader-lease-fence';
        $base['non_overlap'] = 'next224 fences reader-cache reuse on the pager reader lease after next218 master-journal cleanup and next212 database file-token admission have already passed; it does not repeat cleanup-token, database file-token, raw master bytes, member-journal, rollback-journal, WAL, VFS writer, or sync-plan behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheReaderLeaseTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['reader_lease_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next224 cache entries require reader lease tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readReaderLeaseTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['reader_lease_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next224 reads require reader ids and reader lease tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripReaderLeaseToken(array $cache): array
    {
        $stripped = [];
        foreach ($cache as $pageNumber => $entry) {
            unset($entry['reader_lease_token']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneReaderLeaseToken(array $read): array
    {
        unset($read['reader_lease_token']);

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
