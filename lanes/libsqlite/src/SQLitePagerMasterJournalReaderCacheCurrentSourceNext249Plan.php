<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext249Plan
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
        string $currentSourceVersionVectorToken,
        string $currentReaderCacheSourceHandoffToken,
    ): array {
        if ($currentReaderCacheSourceHandoffToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next249 requires a current reader-cache source handoff token');
        }

        $cacheTokens = self::cacheHandoffTokens($readerCache);
        $readTokens = self::readHandoffTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext246Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripHandoffToken($readerCache),
            array_map(static fn (array $read): array => self::stripOneHandoffToken($read), $nextReads),
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
            $currentSourceVersionVectorToken,
        );

        $handoffInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheToken = $cacheTokens[$pageNumber] ?? '';
            $baseAdmitted = (bool) ($row['current_source_version_vector_token_admitted'] ?? false);
            $tokenMatches = hash_equals($cacheToken, $currentReaderCacheSourceHandoffToken);
            if ($baseAdmitted && !$tokenMatches) {
                $handoffInvalidated[] = $pageNumber;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_source_handoff_after_master_journal_next249',
                    'page_number' => $pageNumber,
                    'reason' => 'reader_cache_source_handoff_predates_master_journal_recovery',
                ];
            }

            $rows[] = $row + [
                'reader_cache_source_handoff_token_admitted' => $baseAdmitted && $tokenMatches,
                'reader_cache_source_handoff_token_reason' => $baseAdmitted
                    ? ($tokenMatches
                        ? 'reader_cache_source_handoff_matches_master_journal_recovery'
                        : 'reader_cache_source_handoff_predates_master_journal_recovery')
                    : (string) ($row['current_source_version_vector_token_reason'] ?? $row['reason']),
                'cache_reader_cache_source_handoff_token' => $cacheToken,
                'reader_cache_source_handoff_token' => $currentReaderCacheSourceHandoffToken,
                'reader_cache_source_handoff_token_matches' => $tokenMatches,
            ];
        }

        $handoffInvalidated = self::sortedUnique($handoffInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $handoffInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $handoffInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $handoffInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = hash_equals($readTokens[$readerId] ?? '', $currentReaderCacheSourceHandoffToken);
            $pageInvalidated = in_array((int) $read['page_number'], $handoffInvalidated, true);
            $read['reader_cache_source_handoff_token_current'] = $ticketCurrent;
            $read['reader_cache_source_handoff_token'] = $currentReaderCacheSourceHandoffToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-source-handoff-fence-next249';
                $read['reader_cache_source_handoff_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_source_handoff_change'
                    : 'reader_ticket_source_handoff_predates_recovery';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'reopen_reader_for_source_handoff_after_master_journal_next249',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['reader_cache_source_handoff_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next249';
        $base['reason'] = 'master_journal_reader_cache_rechecks_source_handoff_before_reuse';
        $base['reader_cache_source_handoff_token'] = $currentReaderCacheSourceHandoffToken;
        $base['reader_rows'] = $rows;
        $base['reader_cache_source_handoff_invalidated_cache_page_numbers'] = $handoffInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentReaderCacheSourceHandoffToken . '|' . implode(',', $handoffInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next249';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-source-handoff-fence';
        $base['non_overlap'] = 'next249 fences reader-cache reuse on the source-handoff token after next246 version-vector admission; it does not repeat next246 version-vector/provenance, statement-root/schema/read-transaction tokens, master-journal byte/token/member fences, rollback-journal apply/commit, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, or encoding behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheHandoffTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['reader_cache_source_handoff_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next249 cache entries require reader-cache source handoff tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readHandoffTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['reader_cache_source_handoff_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next249 reads require reader ids and reader-cache source handoff tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripHandoffToken(array $cache): array
    {
        foreach ($cache as &$entry) {
            unset($entry['reader_cache_source_handoff_token']);
        }
        unset($entry);

        return $cache;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneHandoffToken(array $read): array
    {
        unset($read['reader_cache_source_handoff_token']);

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
