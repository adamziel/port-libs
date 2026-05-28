<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext232Plan
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
    ): array {
        if ($currentDatabasePathToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next232 requires a current database path token');
        }

        $cachePathTokens = self::cacheDatabasePathTokens($readerCache);
        $readPathTokens = self::readDatabasePathTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext229Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripDatabasePathToken($readerCache),
            array_map(static fn (array $read): array => self::stripOneDatabasePathToken($read), $nextReads),
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
        );

        $pathInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheToken = $cachePathTokens[$pageNumber] ?? '';
            $reason = hash_equals($cacheToken, $currentDatabasePathToken)
                ? null
                : 'reader_cache_database_path_token_crosses_master_journal_database_slot';

            if ((bool) ($row['pager_cache_source_token_admitted'] ?? false) && $reason !== null) {
                $pathInvalidated[] = $pageNumber;
            }

            $rows[] = $row + [
                'database_path_token_admitted' => (bool) ($row['pager_cache_source_token_admitted'] ?? false) && $reason === null,
                'database_path_token_reason' => (bool) ($row['pager_cache_source_token_admitted'] ?? false)
                    ? ($reason ?? 'reader_cache_database_path_token_matches_current_source')
                    : (string) ($row['pager_cache_source_token_reason'] ?? $row['reader_lease_token_reason'] ?? $row['reason']),
                'cache_database_path_token' => $cacheToken,
                'current_database_path_token' => $currentDatabasePathToken,
                'database_path_token_matches' => hash_equals($cacheToken, $currentDatabasePathToken),
            ];
        }

        $pathInvalidated = self::sortedUnique($pathInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $pathInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $pathInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $pathInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = hash_equals($readPathTokens[$readerId] ?? '', $currentDatabasePathToken);
            $pageInvalidated = in_array($read['page_number'], $pathInvalidated, true);
            $read['database_path_token_current'] = $ticketCurrent;
            $read['database_path_token'] = $currentDatabasePathToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-database-path-fence-current-source-next232';
                $read['database_path_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_database_path_token_change'
                    : 'reader_ticket_database_path_token_crosses_database_slot';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_database_path_after_current_source_next232',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['database_path_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next232';
        $base['reason'] = 'master_journal_reader_cache_rechecks_database_path_namespace_before_current_source_reuse';
        $base['current_database_path_token'] = $currentDatabasePathToken;
        $base['reader_rows'] = $rows;
        $base['database_path_invalidated_cache_page_numbers'] = $pathInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentDatabasePathToken . '|' . implode(',', $pathInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next232';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-database-path-namespace-fence';
        $base['non_overlap'] = 'next232 fences reader-cache reuse on the current database path/attachment namespace after next229 pager-cache-source, next224 reader-lease, next218 cleanup-token, and next212 database file-token admission have already passed; it does not repeat pager cache source, reader lease, cleanup token, database file token, master-journal bytes, member-journal, WAL, VFS writer, sync-plan, or super-journal behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheDatabasePathTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['database_path_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next232 cache entries require database path tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readDatabasePathTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['database_path_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next232 reads require reader ids and database path tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripDatabasePathToken(array $cache): array
    {
        $stripped = [];
        foreach ($cache as $pageNumber => $entry) {
            unset($entry['database_path_token']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneDatabasePathToken(array $read): array
    {
        unset($read['database_path_token']);

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
