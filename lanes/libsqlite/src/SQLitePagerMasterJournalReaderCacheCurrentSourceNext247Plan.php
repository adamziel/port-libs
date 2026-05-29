<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext247Plan
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
        string $currentPagerReaderCacheGenerationToken,
    ): array {
        if ($currentPagerReaderCacheGenerationToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next247 requires a pager reader-cache generation token');
        }

        $cacheTokens = self::cachePagerReaderCacheGenerationTokens($readerCache);
        $readTokens = self::readPagerReaderCacheGenerationTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext243Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripPagerReaderCacheGenerationToken($readerCache),
            array_map(static fn (array $read): array => self::stripOnePagerReaderCacheGenerationToken($read), $nextReads),
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

        $generationInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheToken = $cacheTokens[$pageNumber] ?? '';
            $baseAdmitted = (bool) ($row['current_source_provenance_token_admitted'] ?? false);
            $tokenMatches = hash_equals($cacheToken, $currentPagerReaderCacheGenerationToken);
            if ($baseAdmitted && !$tokenMatches) {
                $generationInvalidated[] = $pageNumber;
                $base['operations'][] = [
                    'op' => 'invalidate_pager_reader_cache_generation_after_master_journal_next247',
                    'page_number' => $pageNumber,
                    'reason' => 'pager_reader_cache_generation_predates_master_journal_current_source',
                ];
            }

            $rows[] = $row + [
                'pager_reader_cache_generation_token_admitted' => $baseAdmitted && $tokenMatches,
                'pager_reader_cache_generation_token_reason' => $baseAdmitted
                    ? ($tokenMatches
                        ? 'pager_reader_cache_generation_matches_current_source'
                        : 'pager_reader_cache_generation_predates_master_journal_current_source')
                    : (string) ($row['current_source_provenance_token_reason'] ?? $row['statement_schema_root_token_reason'] ?? $row['reason']),
                'cache_pager_reader_cache_generation_token' => $cacheToken,
                'current_pager_reader_cache_generation_token' => $currentPagerReaderCacheGenerationToken,
                'pager_reader_cache_generation_token_matches' => $tokenMatches,
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
            $ticketCurrent = hash_equals($readTokens[$readerId] ?? '', $currentPagerReaderCacheGenerationToken);
            $pageInvalidated = in_array((int) $read['page_number'], $generationInvalidated, true);
            $read['pager_reader_cache_generation_token_current'] = $ticketCurrent;
            $read['pager_reader_cache_generation_token'] = $currentPagerReaderCacheGenerationToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-pager-reader-cache-generation-fence-next247';
                $read['pager_reader_cache_generation_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_pager_generation_change'
                    : 'reader_ticket_pager_generation_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'reopen_reader_for_pager_reader_cache_generation_after_master_journal_next247',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['pager_reader_cache_generation_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next247';
        $base['reason'] = 'master_journal_reader_cache_rechecks_pager_generation_before_reuse';
        $base['current_pager_reader_cache_generation_token'] = $currentPagerReaderCacheGenerationToken;
        $base['reader_rows'] = $rows;
        $base['pager_reader_cache_generation_invalidated_cache_page_numbers'] = $generationInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentPagerReaderCacheGenerationToken . '|' . implode(',', $generationInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next247';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-generation-fence';
        $base['non_overlap'] = 'next247 fences reader-cache reuse on the pager cache generation after next243 current-source provenance has already passed; it does not repeat next243 provenance, next240 statement-root, next236 schema-reparse, next233 read-transaction, master-journal byte/token/member fences, rollback-journal apply/commit, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, or encoding behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cachePagerReaderCacheGenerationTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['pager_reader_cache_generation_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next247 cache entries require pager reader-cache generation tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readPagerReaderCacheGenerationTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['pager_reader_cache_generation_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next247 reads require reader ids and pager reader-cache generation tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripPagerReaderCacheGenerationToken(array $cache): array
    {
        foreach ($cache as &$entry) {
            unset($entry['pager_reader_cache_generation_token']);
        }
        unset($entry);

        return $cache;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOnePagerReaderCacheGenerationToken(array $read): array
    {
        unset($read['pager_reader_cache_generation_token']);

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
