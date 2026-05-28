<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext242Plan
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
    ): array {
        if ($currentStatementSnapshotToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next242 requires a current statement snapshot token');
        }

        $cacheTokens = self::cacheStatementSnapshotTokens($readerCache);
        $readTokens = self::readStatementSnapshotTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext239Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripStatementSnapshotToken($readerCache),
            array_map(static fn (array $read): array => self::stripOneStatementSnapshotToken($read), $nextReads),
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
        );

        $snapshotInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheToken = $cacheTokens[$pageNumber] ?? '';
            $baseAdmitted = (bool) ($row['shared_cache_generation_token_admitted'] ?? false);
            $tokenMatches = hash_equals($cacheToken, $currentStatementSnapshotToken);
            if ($baseAdmitted && !$tokenMatches) {
                $snapshotInvalidated[] = $pageNumber;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_statement_snapshot_after_current_source_next242',
                    'page_number' => $pageNumber,
                    'reason' => 'reader_cache_statement_snapshot_predates_master_journal_current_source',
                ];
            }

            $rows[] = $row + [
                'statement_snapshot_token_admitted' => $baseAdmitted && $tokenMatches,
                'statement_snapshot_token_reason' => $baseAdmitted
                    ? ($tokenMatches
                        ? 'reader_cache_statement_snapshot_matches_current_source'
                        : 'reader_cache_statement_snapshot_predates_master_journal_current_source')
                    : (string) ($row['shared_cache_generation_token_reason'] ?? $row['schema_reparse_token_reason'] ?? $row['reason']),
                'cache_statement_snapshot_token' => $cacheToken,
                'current_statement_snapshot_token' => $currentStatementSnapshotToken,
                'statement_snapshot_token_matches' => $tokenMatches,
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
            $ticketCurrent = hash_equals($readTokens[$readerId] ?? '', $currentStatementSnapshotToken);
            $pageInvalidated = in_array((int) $read['page_number'], $snapshotInvalidated, true);
            $read['statement_snapshot_token_current'] = $ticketCurrent;
            $read['statement_snapshot_token'] = $currentStatementSnapshotToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-statement-snapshot-fence-current-source-next242';
                $read['statement_snapshot_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_statement_snapshot_change'
                    : 'reader_ticket_statement_snapshot_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'reopen_reader_for_statement_snapshot_after_current_source_next242',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['statement_snapshot_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next242';
        $base['reason'] = 'master_journal_reader_cache_rechecks_statement_snapshot_before_current_source_reuse';
        $base['current_statement_snapshot_token'] = $currentStatementSnapshotToken;
        $base['reader_rows'] = $rows;
        $base['statement_snapshot_invalidated_cache_page_numbers'] = $snapshotInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentStatementSnapshotToken . '|' . implode(',', $snapshotInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next242';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-statement-snapshot-fence';
        $base['non_overlap'] = 'next242 fences reader-cache reuse on a prepared-statement snapshot token after next239 shared schema-cache generation, next236 schema-reparse, next233 read-transaction, next229 pager-cache source, next224 reader-lease, and next218 cleanup-token admission have already passed; it does not repeat shared-generation, schema-reparse, page-count, payload-digest, file-token, member-journal, rollback-journal, WAL, VFS writer, B-tree, JSON, or SELECT behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheStatementSnapshotTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['statement_snapshot_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next242 cache entries require statement snapshot tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readStatementSnapshotTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['statement_snapshot_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next242 reads require reader ids and statement snapshot tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripStatementSnapshotToken(array $cache): array
    {
        foreach ($cache as &$entry) {
            unset($entry['statement_snapshot_token']);
        }
        unset($entry);

        return $cache;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneStatementSnapshotToken(array $read): array
    {
        unset($read['statement_snapshot_token']);

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
