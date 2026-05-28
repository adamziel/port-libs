<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext255Plan
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
        string $currentReaderPageMapDigestToken,
    ): array {
        if ($currentReaderPageMapDigestToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next255 requires a current reader page-map digest token');
        }

        $cacheTokens = self::cacheReaderPageMapDigestTokens($readerCache);
        $readTokens = self::readReaderPageMapDigestTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext251Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripReaderPageMapDigestToken($readerCache),
            array_map(static fn (array $read): array => self::stripOneReaderPageMapDigestToken($read), $nextReads),
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
        );

        $pageMapInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheToken = $cacheTokens[$pageNumber] ?? '';
            $baseAdmitted = (bool) ($row['reader_snapshot_token_admitted'] ?? false);
            $tokenMatches = hash_equals($cacheToken, $currentReaderPageMapDigestToken);
            if ($baseAdmitted && !$tokenMatches) {
                $pageMapInvalidated[] = $pageNumber;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_page_map_digest_after_master_journal_current_source_next255',
                    'page_number' => $pageNumber,
                    'reason' => 'reader_page_map_digest_predates_master_journal_current_source',
                ];
            }

            $rows[] = $row + [
                'reader_page_map_digest_token_admitted' => $baseAdmitted && $tokenMatches,
                'reader_page_map_digest_token_reason' => $baseAdmitted
                    ? ($tokenMatches
                        ? 'reader_page_map_digest_matches_master_journal_current_source'
                        : 'reader_page_map_digest_predates_master_journal_current_source')
                    : (string) ($row['reader_snapshot_token_reason'] ?? $row['pager_reader_cache_generation_token_reason'] ?? $row['reason']),
                'cache_reader_page_map_digest_token' => $cacheToken,
                'current_reader_page_map_digest_token' => $currentReaderPageMapDigestToken,
                'reader_page_map_digest_token_matches' => $tokenMatches,
            ];
        }

        $pageMapInvalidated = self::sortedUnique($pageMapInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $pageMapInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $pageMapInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $pageMapInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = hash_equals($readTokens[$readerId] ?? '', $currentReaderPageMapDigestToken);
            $pageInvalidated = in_array((int) $read['page_number'], $pageMapInvalidated, true);
            $read['reader_page_map_digest_token_current'] = $ticketCurrent;
            $read['reader_page_map_digest_token'] = $currentReaderPageMapDigestToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-page-map-digest-fence-current-source-next255';
                $read['reader_page_map_digest_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_reader_page_map_digest_change'
                    : 'reader_ticket_page_map_digest_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'reopen_reader_for_page_map_digest_after_master_journal_current_source_next255',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['reader_page_map_digest_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next255';
        $base['reason'] = 'master_journal_reader_cache_rechecks_reader_page_map_digest_before_reuse';
        $base['current_reader_page_map_digest_token'] = $currentReaderPageMapDigestToken;
        $base['reader_rows'] = $rows;
        $base['reader_page_map_digest_invalidated_cache_page_numbers'] = $pageMapInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentReaderPageMapDigestToken . '|' . implode(',', $pageMapInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next255';
        $base['dependencies'][] = 'sqlite-pager-reader-page-map-digest-current-source-fence';
        $base['non_overlap'] = 'next255 fences reader-cache reuse on the reader page-map digest after next251 reader-snapshot admission; it does not repeat next251 snapshot, next247 generation, next243 provenance, statement-root/schema/read-transaction tokens, master-journal byte/token/member fences, rollback-journal apply/commit, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, or encoding behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheReaderPageMapDigestTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['reader_page_map_digest_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next255 cache entries require reader page-map digest tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readReaderPageMapDigestTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['reader_page_map_digest_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next255 reads require reader ids and reader page-map digest tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripReaderPageMapDigestToken(array $cache): array
    {
        foreach ($cache as &$entry) {
            unset($entry['reader_page_map_digest_token']);
        }
        unset($entry);

        return $cache;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneReaderPageMapDigestToken(array $read): array
    {
        unset($read['reader_page_map_digest_token']);

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
