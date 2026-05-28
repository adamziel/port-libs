<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext261Plan
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
        string $currentReaderSnapshotToken,
        string $currentMasterJournalRecoveryReceiptToken,
        string $currentPagerSpillDrainToken,
        string $currentPagerRollbackJournalReaderSourceToken,
    ): array {
        if ($currentPagerSpillDrainToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next261 requires a pager spill-drain token');
        }
        if ($currentPagerRollbackJournalReaderSourceToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next261 requires a rollback-journal reader-source token');
        }

        $cacheTokens = self::cachePagerRollbackJournalReaderSourceTokens($readerCache);
        $readTokens = self::readPagerRollbackJournalReaderSourceTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext258Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripPagerRollbackJournalReaderSourceToken($readerCache),
            array_map(static fn (array $read): array => self::stripOnePagerRollbackJournalReaderSourceToken($read), $nextReads),
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
            $currentPagerReaderCacheGenerationToken,
            $currentReaderSnapshotToken,
            $currentMasterJournalRecoveryReceiptToken,
            $currentPagerSpillDrainToken,
        );

        $rollbackSourceInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheToken = $cacheTokens[$pageNumber] ?? '';
            $baseAdmitted = (bool) ($row['master_journal_recovery_receipt_token_admitted'] ?? false);
            $tokenMatches = hash_equals($cacheToken, $currentPagerRollbackJournalReaderSourceToken);
            if ($baseAdmitted && !$tokenMatches) {
                $rollbackSourceInvalidated[] = $pageNumber;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_rollback_journal_reader_source_current_source_next261',
                    'page_number' => $pageNumber,
                    'reason' => 'reader_cache_rollback_journal_reader_source_predates_current_master_journal_source',
                ];
            }

            $rows[] = $row + [
                'rollback_journal_reader_source_token_admitted' => $baseAdmitted && $tokenMatches,
                'rollback_journal_reader_source_token_reason' => $baseAdmitted
                    ? ($tokenMatches
                        ? 'reader_cache_rollback_journal_reader_source_matches_current_source'
                        : 'reader_cache_rollback_journal_reader_source_predates_current_master_journal_source')
                    : (string) ($row['master_journal_recovery_receipt_token_reason'] ?? $row['reader_snapshot_token_reason'] ?? $row['reason']),
                'cache_rollback_journal_reader_source_token' => $cacheToken,
                'current_rollback_journal_reader_source_token' => $currentPagerRollbackJournalReaderSourceToken,
                'rollback_journal_reader_source_token_matches' => $tokenMatches,
            ];
        }

        $rollbackSourceInvalidated = self::sortedUnique($rollbackSourceInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $rollbackSourceInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $rollbackSourceInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $rollbackSourceInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = hash_equals($readTokens[$readerId] ?? '', $currentPagerRollbackJournalReaderSourceToken);
            $pageInvalidated = in_array((int) $read['page_number'], $rollbackSourceInvalidated, true);
            $read['rollback_journal_reader_source_token_current'] = $ticketCurrent;
            $read['rollback_journal_reader_source_token'] = $currentPagerRollbackJournalReaderSourceToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-rollback-source-fence-current-source-next261';
                $read['rollback_journal_reader_source_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_rollback_journal_reader_source_change'
                    : 'reader_ticket_rollback_journal_reader_source_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'reopen_reader_for_rollback_journal_reader_source_current_source_next261',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['rollback_journal_reader_source_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next261';
        $base['reason'] = 'master_journal_reader_cache_rechecks_rollback_journal_reader_source_before_reuse';
        $base['current_rollback_journal_reader_source_token'] = $currentPagerRollbackJournalReaderSourceToken;
        $base['reader_rows'] = $rows;
        $base['rollback_journal_reader_source_invalidated_cache_page_numbers'] = $rollbackSourceInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentPagerRollbackJournalReaderSourceToken . '|' . implode(',', $rollbackSourceInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next261';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-rollback-source-fence';
        $base['non_overlap'] = 'next261 fences reader-cache reuse on the rollback-journal reader-source token after next258 pager spill-drain and next254 master-journal recovery receipt admission; it does not repeat next258 spill drain, next254 receipt, next251 snapshots, next247 generation, next243 provenance, rollback-journal apply/commit, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, PRAGMA, trigger, or encoding behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cachePagerRollbackJournalReaderSourceTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['rollback_journal_reader_source_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next261 cache entries require rollback-journal reader-source tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readPagerRollbackJournalReaderSourceTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['rollback_journal_reader_source_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next261 reads require reader ids and rollback-journal reader-source tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripPagerRollbackJournalReaderSourceToken(array $cache): array
    {
        foreach ($cache as &$entry) {
            unset($entry['rollback_journal_reader_source_token']);
        }
        unset($entry);

        return $cache;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOnePagerRollbackJournalReaderSourceToken(array $read): array
    {
        unset($read['rollback_journal_reader_source_token']);

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
