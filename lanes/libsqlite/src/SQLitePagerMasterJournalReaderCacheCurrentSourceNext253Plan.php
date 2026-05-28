<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext253Plan
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
        string $currentReadTransactionToken,
        string $currentSchemaReparseToken,
        string $currentStatementSchemaRootToken,
        string $currentSourceProvenanceToken,
        string $currentDatabaseHeaderChangeCounterToken,
    ): array {
        if ($currentDatabaseHeaderChangeCounterToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next253 requires a database header change-counter token');
        }

        $cacheTokens = self::cacheDatabaseHeaderChangeCounterTokens($readerCache);
        $readTokens = self::readDatabaseHeaderChangeCounterTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext243Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripDatabaseHeaderChangeCounterToken($readerCache),
            array_map(static fn (array $read): array => self::stripOneDatabaseHeaderChangeCounterToken($read), $nextReads),
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
            $currentReadTransactionToken,
            $currentSchemaReparseToken,
            $currentStatementSchemaRootToken,
            $currentSourceProvenanceToken,
        );

        $changeCounterInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheToken = $cacheTokens[$pageNumber] ?? '';
            $baseAdmitted = (bool) ($row['current_source_provenance_token_admitted'] ?? false);
            $tokenMatches = hash_equals($cacheToken, $currentDatabaseHeaderChangeCounterToken);
            if ($baseAdmitted && !$tokenMatches) {
                $changeCounterInvalidated[] = $pageNumber;
                $base['operations'][] = [
                    'op' => 'invalidate_database_header_change_counter_after_master_journal_next253',
                    'page_number' => $pageNumber,
                    'reason' => 'database_header_change_counter_predates_master_journal_current_source',
                ];
            }

            $rows[] = $row + [
                'database_header_change_counter_token_admitted' => $baseAdmitted && $tokenMatches,
                'database_header_change_counter_token_reason' => $baseAdmitted
                    ? ($tokenMatches
                        ? 'database_header_change_counter_matches_current_source'
                        : 'database_header_change_counter_predates_master_journal_current_source')
                    : (string) ($row['current_source_provenance_token_reason'] ?? $row['statement_schema_root_token_reason'] ?? $row['reason']),
                'cache_database_header_change_counter_token' => $cacheToken,
                'current_database_header_change_counter_token' => $currentDatabaseHeaderChangeCounterToken,
                'database_header_change_counter_token_matches' => $tokenMatches,
            ];
        }

        $changeCounterInvalidated = self::sortedUnique($changeCounterInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $changeCounterInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $changeCounterInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $changeCounterInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = hash_equals($readTokens[$readerId] ?? '', $currentDatabaseHeaderChangeCounterToken);
            $pageInvalidated = in_array((int) $read['page_number'], $changeCounterInvalidated, true);
            $read['database_header_change_counter_token_current'] = $ticketCurrent;
            $read['database_header_change_counter_token'] = $currentDatabaseHeaderChangeCounterToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-database-header-change-counter-fence-next253';
                $read['database_header_change_counter_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_database_header_change_counter_change'
                    : 'reader_ticket_database_header_change_counter_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'reopen_reader_for_database_header_change_counter_after_master_journal_next253',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['database_header_change_counter_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next253';
        $base['reason'] = 'master_journal_reader_cache_rechecks_database_header_change_counter_before_reuse';
        $base['current_database_header_change_counter_token'] = $currentDatabaseHeaderChangeCounterToken;
        $base['reader_rows'] = $rows;
        $base['database_header_change_counter_invalidated_cache_page_numbers'] = $changeCounterInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentDatabaseHeaderChangeCounterToken . '|' . implode(',', $changeCounterInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next253';
        $base['dependencies'][] = 'sqlite-database-header-change-counter-fence';
        $base['non_overlap'] = 'next253 fences reader-cache reuse on the database header change-counter after next243 current-source provenance has already passed; it does not repeat next243 provenance, next240 statement-root, next236 schema-reparse, next233 read-transaction, master-journal byte/token/member fences, rollback-journal apply/commit, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, or encoding behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheDatabaseHeaderChangeCounterTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['database_header_change_counter_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next253 cache entries require database header change-counter tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readDatabaseHeaderChangeCounterTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['database_header_change_counter_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next253 reads require reader ids and database header change-counter tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripDatabaseHeaderChangeCounterToken(array $cache): array
    {
        foreach ($cache as &$entry) {
            unset($entry['database_header_change_counter_token']);
        }
        unset($entry);

        return $cache;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneDatabaseHeaderChangeCounterToken(array $read): array
    {
        unset($read['database_header_change_counter_token']);

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
