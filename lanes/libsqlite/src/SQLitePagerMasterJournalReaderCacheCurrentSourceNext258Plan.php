<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext258Plan
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
    ): array {
        if ($currentPagerSpillDrainToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next258 requires a pager spill-drain token');
        }

        $cacheTokens = self::cachePagerSpillDrainTokens($readerCache);
        $readTokens = self::readPagerSpillDrainTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext254Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripPagerSpillDrainToken($readerCache),
            array_map(static fn (array $read): array => self::stripOnePagerSpillDrainToken($read), $nextReads),
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
        );

        $spillInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheToken = $cacheTokens[$pageNumber] ?? '';
            $baseAdmitted = (bool) ($row['master_journal_recovery_receipt_token_admitted'] ?? false);
            $tokenMatches = hash_equals($cacheToken, $currentPagerSpillDrainToken);
            if ($baseAdmitted && !$tokenMatches) {
                $spillInvalidated[] = $pageNumber;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_pager_spill_drain_current_source_next258',
                    'page_number' => $pageNumber,
                    'reason' => 'reader_cache_pager_spill_drain_predates_current_master_journal_source',
                ];
            }

            $rows[] = $row + [
                'pager_spill_drain_token_admitted' => $baseAdmitted && $tokenMatches,
                'pager_spill_drain_token_reason' => $baseAdmitted
                    ? ($tokenMatches
                        ? 'reader_cache_pager_spill_drain_matches_current_source'
                        : 'reader_cache_pager_spill_drain_predates_current_master_journal_source')
                    : (string) ($row['master_journal_recovery_receipt_token_reason'] ?? $row['reader_snapshot_token_reason'] ?? $row['reason']),
                'cache_pager_spill_drain_token' => $cacheToken,
                'current_pager_spill_drain_token' => $currentPagerSpillDrainToken,
                'pager_spill_drain_token_matches' => $tokenMatches,
            ];
        }

        $spillInvalidated = self::sortedUnique($spillInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $spillInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $spillInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $spillInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = hash_equals($readTokens[$readerId] ?? '', $currentPagerSpillDrainToken);
            $pageInvalidated = in_array((int) $read['page_number'], $spillInvalidated, true);
            $read['pager_spill_drain_token_current'] = $ticketCurrent;
            $read['pager_spill_drain_token'] = $currentPagerSpillDrainToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-spill-drain-fence-current-source-next258';
                $read['pager_spill_drain_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_pager_spill_drain_change'
                    : 'reader_ticket_pager_spill_drain_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'reopen_reader_for_pager_spill_drain_current_source_next258',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['pager_spill_drain_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next258';
        $base['reason'] = 'master_journal_reader_cache_rechecks_pager_spill_drain_before_reuse';
        $base['current_pager_spill_drain_token'] = $currentPagerSpillDrainToken;
        $base['reader_rows'] = $rows;
        $base['pager_spill_drain_invalidated_cache_page_numbers'] = $spillInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentPagerSpillDrainToken . '|' . implode(',', $spillInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next258';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-spill-drain-fence';
        $base['non_overlap'] = 'next258 fences reader-cache reuse on the pager spill-drain token after next254 master-journal recovery receipt admission; it does not repeat next254 receipt, next251 snapshots, next247 generation, next243 provenance, rollback-journal apply/commit, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, PRAGMA, trigger, or encoding behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cachePagerSpillDrainTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['pager_spill_drain_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next258 cache entries require pager spill-drain tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readPagerSpillDrainTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['pager_spill_drain_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next258 reads require reader ids and pager spill-drain tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripPagerSpillDrainToken(array $cache): array
    {
        foreach ($cache as &$entry) {
            unset($entry['pager_spill_drain_token']);
        }
        unset($entry);

        return $cache;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOnePagerSpillDrainToken(array $read): array
    {
        unset($read['pager_spill_drain_token']);

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
