<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext248Plan
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
        string $currentStatementSnapshotToken,
        string $currentRootpageMapToken,
        string $currentPageOwnerMapToken,
    ): array {
        if ($currentPageOwnerMapToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next248 requires a current page-owner map token');
        }

        $cacheTokens = self::cachePageOwnerMapTokens($readerCache);
        $readTokens = self::readPageOwnerMapTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext245Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripPageOwnerMapToken($readerCache),
            array_map(static fn (array $read): array => self::stripOnePageOwnerMapToken($read), $nextReads),
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
            $currentSharedCacheGenerationToken,
            $currentStatementSnapshotToken,
            $currentRootpageMapToken,
        );

        $ownerInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheToken = $cacheTokens[$pageNumber] ?? '';
            $baseAdmitted = (bool) ($row['rootpage_map_token_admitted'] ?? false);
            $tokenMatches = hash_equals($cacheToken, $currentPageOwnerMapToken);
            if ($baseAdmitted && !$tokenMatches) {
                $ownerInvalidated[] = $pageNumber;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_page_owner_map_after_current_source_next248',
                    'page_number' => $pageNumber,
                    'reason' => 'reader_cache_page_owner_map_predates_master_journal_current_source',
                ];
            }

            $rows[] = $row + [
                'page_owner_map_token_admitted' => $baseAdmitted && $tokenMatches,
                'page_owner_map_token_reason' => $baseAdmitted
                    ? ($tokenMatches
                        ? 'reader_cache_page_owner_map_matches_current_source'
                        : 'reader_cache_page_owner_map_predates_master_journal_current_source')
                    : (string) ($row['rootpage_map_token_reason'] ?? $row['statement_snapshot_token_reason'] ?? $row['reason']),
                'cache_page_owner_map_token' => $cacheToken,
                'current_page_owner_map_token' => $currentPageOwnerMapToken,
                'page_owner_map_token_matches' => $tokenMatches,
            ];
        }

        $ownerInvalidated = self::sortedUnique($ownerInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $ownerInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $ownerInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $ownerInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = hash_equals($readTokens[$readerId] ?? '', $currentPageOwnerMapToken);
            $pageInvalidated = in_array((int) $read['page_number'], $ownerInvalidated, true);
            $read['page_owner_map_token_current'] = $ticketCurrent;
            $read['page_owner_map_token'] = $currentPageOwnerMapToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-page-owner-map-fence-current-source-next248';
                $read['page_owner_map_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_page_owner_map_change'
                    : 'reader_ticket_page_owner_map_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'reopen_reader_for_page_owner_map_after_current_source_next248',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['page_owner_map_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next248';
        $base['reason'] = 'master_journal_reader_cache_rechecks_btree_page_owner_map_before_current_source_reuse';
        $base['current_page_owner_map_token'] = $currentPageOwnerMapToken;
        $base['reader_rows'] = $rows;
        $base['page_owner_map_invalidated_cache_page_numbers'] = $ownerInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentPageOwnerMapToken . '|' . implode(',', $ownerInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next248';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-page-owner-map-fence';
        $base['non_overlap'] = 'next248 fences reader-cache reuse on the recovered B-tree page-owner map after next245 rootpage-map, next242 statement snapshots, next239 shared schema-cache generation, next236 schema reparse, next233 read transactions, next229 pager-cache source, next224 reader leases, and next218 cleanup-token admission have already passed; it does not repeat rootpage-map token checks, page-image digest receipts, page-count/header fences, rollback-journal apply/commit, WAL checkpoint/savepoint, VFS writer/lock/sync, B-tree page relocation/freeblock materialization, JSON table, SELECT, or encoding behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cachePageOwnerMapTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['page_owner_map_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next248 cache entries require page-owner map tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readPageOwnerMapTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['page_owner_map_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next248 reads require reader ids and page-owner map tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripPageOwnerMapToken(array $cache): array
    {
        foreach ($cache as &$entry) {
            unset($entry['page_owner_map_token']);
        }
        unset($entry);

        return $cache;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOnePageOwnerMapToken(array $read): array
    {
        unset($read['page_owner_map_token']);

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
