<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext251Plan
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
    ): array {
        if ($currentReaderSnapshotToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next251 requires a current reader snapshot token');
        }

        $cacheTokens = self::cacheReaderSnapshotTokens($readerCache);
        $readTokens = self::readReaderSnapshotTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext247Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripReaderSnapshotToken($readerCache),
            array_map(static fn (array $read): array => self::stripOneReaderSnapshotToken($read), $nextReads),
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
        );

        $snapshotInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheToken = $cacheTokens[$pageNumber] ?? '';
            $baseAdmitted = (bool) ($row['pager_reader_cache_generation_token_admitted'] ?? false);
            $tokenMatches = hash_equals($cacheToken, $currentReaderSnapshotToken);
            if ($baseAdmitted && !$tokenMatches) {
                $snapshotInvalidated[] = $pageNumber;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_snapshot_after_master_journal_current_source_next251',
                    'page_number' => $pageNumber,
                    'reason' => 'reader_snapshot_predates_master_journal_current_source',
                ];
            }

            $rows[] = $row + [
                'reader_snapshot_token_admitted' => $baseAdmitted && $tokenMatches,
                'reader_snapshot_token_reason' => $baseAdmitted
                    ? ($tokenMatches
                        ? 'reader_snapshot_matches_master_journal_current_source'
                        : 'reader_snapshot_predates_master_journal_current_source')
                    : (string) ($row['pager_reader_cache_generation_token_reason'] ?? $row['current_source_provenance_token_reason'] ?? $row['reason']),
                'cache_reader_snapshot_token' => $cacheToken,
                'current_reader_snapshot_token' => $currentReaderSnapshotToken,
                'reader_snapshot_token_matches' => $tokenMatches,
            ];
        }

        $snapshotInvalidated = self::sortedUnique($snapshotInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $snapshotInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $snapshotInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $snapshotInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = hash_equals($readTokens[$readerId] ?? '', $currentReaderSnapshotToken);
            $pageInvalidated = in_array((int) $read['page_number'], $snapshotInvalidated, true);
            $read['reader_snapshot_token_current'] = $ticketCurrent;
            $read['reader_snapshot_token'] = $currentReaderSnapshotToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-snapshot-fence-current-source-next251';
                $read['reader_snapshot_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_reader_snapshot_change'
                    : 'reader_ticket_snapshot_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'reopen_reader_for_snapshot_after_master_journal_current_source_next251',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['reader_snapshot_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next251';
        $base['reason'] = 'master_journal_reader_cache_rechecks_reader_snapshot_before_reuse';
        $base['current_reader_snapshot_token'] = $currentReaderSnapshotToken;
        $base['reader_rows'] = $rows;
        $base['reader_snapshot_invalidated_cache_page_numbers'] = $snapshotInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentReaderSnapshotToken . '|' . implode(',', $snapshotInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next251';
        $base['dependencies'][] = 'sqlite-pager-reader-snapshot-current-source-fence';
        $base['non_overlap'] = 'next251 fences reader-cache reuse on the active reader snapshot after next247 pager-generation admission; it does not repeat next247 generation, next243 provenance, statement-root/schema/read-transaction tokens, master-journal byte/token/member fences, rollback-journal apply/commit, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, or encoding behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheReaderSnapshotTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['reader_snapshot_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next251 cache entries require reader snapshot tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readReaderSnapshotTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['reader_snapshot_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next251 reads require reader ids and reader snapshot tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripReaderSnapshotToken(array $cache): array
    {
        foreach ($cache as &$entry) {
            unset($entry['reader_snapshot_token']);
        }
        unset($entry);

        return $cache;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneReaderSnapshotToken(array $read): array
    {
        unset($read['reader_snapshot_token']);

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
