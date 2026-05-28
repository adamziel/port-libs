<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext239Plan
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
        string $currentSharedCacheGenerationToken,
    ): array {
        if ($currentSharedCacheGenerationToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next239 requires a current shared-cache generation token');
        }

        $cacheTokens = self::cacheSharedCacheGenerationTokens($readerCache);
        $readTokens = self::readSharedCacheGenerationTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext236Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripSharedCacheGenerationToken($readerCache),
            array_map(static fn (array $read): array => self::stripOneSharedCacheGenerationToken($read), $nextReads),
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
        );

        $generationInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheToken = $cacheTokens[$pageNumber] ?? '';
            $baseAdmitted = (bool) ($row['schema_reparse_token_admitted'] ?? false);
            $tokenMatches = hash_equals($cacheToken, $currentSharedCacheGenerationToken);
            if ($baseAdmitted && !$tokenMatches) {
                $generationInvalidated[] = $pageNumber;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_shared_generation_after_current_source_next239',
                    'page_number' => $pageNumber,
                    'reason' => 'reader_cache_shared_cache_generation_predates_master_journal_current_source',
                ];
            }

            $rows[] = $row + [
                'shared_cache_generation_token_admitted' => $baseAdmitted && $tokenMatches,
                'shared_cache_generation_token_reason' => $baseAdmitted
                    ? ($tokenMatches
                        ? 'reader_cache_shared_cache_generation_matches_current_source'
                        : 'reader_cache_shared_cache_generation_predates_master_journal_current_source')
                    : (string) ($row['schema_reparse_token_reason'] ?? $row['read_transaction_token_reason'] ?? $row['reason']),
                'cache_shared_cache_generation_token' => $cacheToken,
                'current_shared_cache_generation_token' => $currentSharedCacheGenerationToken,
                'shared_cache_generation_token_matches' => $tokenMatches,
            ];
        }

        $generationInvalidated = self::sortedUnique($generationInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $generationInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $generationInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $generationInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = hash_equals($readTokens[$readerId] ?? '', $currentSharedCacheGenerationToken);
            $pageInvalidated = in_array((int) $read['page_number'], $generationInvalidated, true);
            $read['shared_cache_generation_token_current'] = $ticketCurrent;
            $read['shared_cache_generation_token'] = $currentSharedCacheGenerationToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-shared-generation-fence-current-source-next239';
                $read['shared_cache_generation_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_shared_cache_generation_change'
                    : 'reader_ticket_shared_cache_generation_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'reopen_reader_for_shared_generation_after_current_source_next239',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['shared_cache_generation_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next239';
        $base['reason'] = 'master_journal_reader_cache_rechecks_shared_cache_generation_before_current_source_reuse';
        $base['current_shared_cache_generation_token'] = $currentSharedCacheGenerationToken;
        $base['reader_rows'] = $rows;
        $base['shared_cache_generation_invalidated_cache_page_numbers'] = $generationInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentSharedCacheGenerationToken . '|' . implode(',', $generationInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next239';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-shared-generation-fence';
        $base['non_overlap'] = 'next239 fences reader-cache reuse on a shared schema-cache generation after next236 schema-reparse admission, next233 read-transaction, next229 pager-cache source, next224 reader-lease, and next218 cleanup-token admission have already passed; it does not repeat schema-reparse tokens, page-count, payload-digest, file-token, member-journal, rollback-journal, WAL, VFS writer, B-tree, JSON, or SELECT behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheSharedCacheGenerationTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['shared_cache_generation_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next239 cache entries require shared-cache generation tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readSharedCacheGenerationTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['shared_cache_generation_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next239 reads require reader ids and shared-cache generation tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripSharedCacheGenerationToken(array $cache): array
    {
        foreach ($cache as &$entry) {
            unset($entry['shared_cache_generation_token']);
        }
        unset($entry);

        return $cache;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneSharedCacheGenerationToken(array $read): array
    {
        unset($read['shared_cache_generation_token']);

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
