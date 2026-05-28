<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext250Plan
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
        string $currentMasterJournalReaderSnapshotToken,
    ): array {
        if ($currentMasterJournalReaderSnapshotToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next250 requires a master-journal reader snapshot token');
        }

        $cacheTokens = self::cacheMasterJournalReaderSnapshotTokens($readerCache);
        $readTokens = self::readMasterJournalReaderSnapshotTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext243Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripMasterJournalReaderSnapshotToken($readerCache),
            array_map(static fn (array $read): array => self::stripOneMasterJournalReaderSnapshotToken($read), $nextReads),
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

        $snapshotInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheToken = $cacheTokens[$pageNumber] ?? '';
            $baseAdmitted = (bool) ($row['current_source_provenance_token_admitted'] ?? false);
            $tokenMatches = hash_equals($cacheToken, $currentMasterJournalReaderSnapshotToken);
            if ($baseAdmitted && !$tokenMatches) {
                $snapshotInvalidated[] = $pageNumber;
                $base['operations'][] = [
                    'op' => 'invalidate_master_journal_reader_snapshot_after_master_journal_next250',
                    'page_number' => $pageNumber,
                    'reason' => 'master_journal_reader_snapshot_predates_master_journal_current_source',
                ];
            }

            $rows[] = $row + [
                'master_journal_reader_snapshot_token_admitted' => $baseAdmitted && $tokenMatches,
                'master_journal_reader_snapshot_token_reason' => $baseAdmitted
                    ? ($tokenMatches
                        ? 'master_journal_reader_snapshot_matches_current_source'
                        : 'master_journal_reader_snapshot_predates_master_journal_current_source')
                    : (string) ($row['current_source_provenance_token_reason'] ?? $row['statement_schema_root_token_reason'] ?? $row['reason']),
                'cache_master_journal_reader_snapshot_token' => $cacheToken,
                'current_master_journal_reader_snapshot_token' => $currentMasterJournalReaderSnapshotToken,
                'master_journal_reader_snapshot_token_matches' => $tokenMatches,
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
            $ticketCurrent = hash_equals($readTokens[$readerId] ?? '', $currentMasterJournalReaderSnapshotToken);
            $pageInvalidated = in_array((int) $read['page_number'], $snapshotInvalidated, true);
            $read['master_journal_reader_snapshot_token_current'] = $ticketCurrent;
            $read['master_journal_reader_snapshot_token'] = $currentMasterJournalReaderSnapshotToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-pager-reader-cache-snapshot-fence-next250';
                $read['master_journal_reader_snapshot_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_master_journal_reader_snapshot_change'
                    : 'reader_ticket_master_journal_reader_snapshot_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'reopen_reader_for_master_journal_reader_snapshot_after_master_journal_next250',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['master_journal_reader_snapshot_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next250';
        $base['reason'] = 'master_journal_reader_cache_rechecks_master_journal_reader_snapshot_before_reuse';
        $base['current_master_journal_reader_snapshot_token'] = $currentMasterJournalReaderSnapshotToken;
        $base['reader_rows'] = $rows;
        $base['master_journal_reader_snapshot_invalidated_cache_page_numbers'] = $snapshotInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentMasterJournalReaderSnapshotToken . '|' . implode(',', $snapshotInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next250';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-snapshot-fence';
        $base['non_overlap'] = 'next250 fences reader-cache reuse on the master-journal reader snapshot after next243 current-source provenance has already passed; it does not repeat next243 provenance, next240 statement-root, next236 schema-reparse, next233 read-transaction, master-journal byte/token/member fences, rollback-journal apply/commit, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, or encoding behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheMasterJournalReaderSnapshotTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['master_journal_reader_snapshot_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next250 cache entries require master-journal reader snapshot tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readMasterJournalReaderSnapshotTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['master_journal_reader_snapshot_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next250 reads require reader ids and master-journal reader snapshot tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripMasterJournalReaderSnapshotToken(array $cache): array
    {
        foreach ($cache as &$entry) {
            unset($entry['master_journal_reader_snapshot_token']);
        }
        unset($entry);

        return $cache;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneMasterJournalReaderSnapshotToken(array $read): array
    {
        unset($read['master_journal_reader_snapshot_token']);

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
