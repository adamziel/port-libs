<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext252Plan
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
        string $currentMasterMemberManifestToken,
    ): array {
        if ($currentMasterMemberManifestToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next252 requires a current master-member manifest token');
        }

        $cacheTokens = self::cacheManifestTokens($readerCache);
        $readTokens = self::readManifestTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext248Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripManifestToken($readerCache),
            array_map(static fn (array $read): array => self::stripOneManifestToken($read), $nextReads),
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
            $currentPageOwnerMapToken,
        );

        $manifestInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheToken = $cacheTokens[$pageNumber] ?? '';
            $baseAdmitted = (bool) ($row['page_owner_map_token_admitted'] ?? false);
            $tokenMatches = hash_equals($cacheToken, $currentMasterMemberManifestToken);
            if ($baseAdmitted && !$tokenMatches) {
                $manifestInvalidated[] = $pageNumber;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_master_member_manifest_after_current_source_next252',
                    'page_number' => $pageNumber,
                    'reason' => 'reader_cache_master_member_manifest_predates_master_journal_current_source',
                ];
            }

            $rows[] = $row + [
                'master_member_manifest_token_admitted' => $baseAdmitted && $tokenMatches,
                'master_member_manifest_token_reason' => $baseAdmitted
                    ? ($tokenMatches
                        ? 'reader_cache_master_member_manifest_matches_current_source'
                        : 'reader_cache_master_member_manifest_predates_master_journal_current_source')
                    : (string) ($row['page_owner_map_token_reason'] ?? $row['rootpage_map_token_reason'] ?? $row['reason']),
                'cache_master_member_manifest_token' => $cacheToken,
                'current_master_member_manifest_token' => $currentMasterMemberManifestToken,
                'master_member_manifest_token_matches' => $tokenMatches,
            ];
        }

        $manifestInvalidated = self::sortedUnique($manifestInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $manifestInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $manifestInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $manifestInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = hash_equals($readTokens[$readerId] ?? '', $currentMasterMemberManifestToken);
            $pageInvalidated = in_array((int) $read['page_number'], $manifestInvalidated, true);
            $read['master_member_manifest_token_current'] = $ticketCurrent;
            $read['master_member_manifest_token'] = $currentMasterMemberManifestToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-master-member-manifest-fence-current-source-next252';
                $read['master_member_manifest_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_master_member_manifest_change'
                    : 'reader_ticket_master_member_manifest_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'reopen_reader_for_master_member_manifest_after_current_source_next252',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['master_member_manifest_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next252';
        $base['reason'] = 'master_journal_reader_cache_rechecks_recovered_member_manifest_before_current_source_reuse';
        $base['current_master_member_manifest_token'] = $currentMasterMemberManifestToken;
        $base['reader_rows'] = $rows;
        $base['master_member_manifest_invalidated_cache_page_numbers'] = $manifestInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentMasterMemberManifestToken . '|' . implode(',', $manifestInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next252';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-master-member-manifest-fence';
        $base['non_overlap'] = 'next252 fences reader-cache reuse on the recovered master-journal member manifest after next248 page-owner-map admission; it does not repeat page-owner, rootpage, statement snapshot, shared generation, schema reparse, read transaction, cleanup-token, page-image receipt, WAL checkpoint/savepoint, rollback-journal commit/apply, VFS writer/lock/sync, B-tree, JSON, SQL executor, or encoding behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheManifestTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['master_member_manifest_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next252 cache entries require master-member manifest tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readManifestTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['master_member_manifest_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next252 reads require reader ids and master-member manifest tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripManifestToken(array $cache): array
    {
        foreach ($cache as &$entry) {
            unset($entry['master_member_manifest_token']);
        }
        unset($entry);

        return $cache;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneManifestToken(array $read): array
    {
        unset($read['master_member_manifest_token']);

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
