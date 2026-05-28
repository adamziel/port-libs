<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext254Plan
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
    ): array {
        if ($currentMasterJournalRecoveryReceiptToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next254 requires a master-journal recovery receipt token');
        }

        $cacheTokens = self::cacheRecoveryReceiptTokens($readerCache);
        $readTokens = self::readRecoveryReceiptTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext251Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripRecoveryReceiptToken($readerCache),
            array_map(static fn (array $read): array => self::stripOneRecoveryReceiptToken($read), $nextReads),
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

        $receiptInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheToken = $cacheTokens[$pageNumber] ?? '';
            $baseAdmitted = (bool) ($row['reader_snapshot_token_admitted'] ?? false);
            $tokenMatches = hash_equals($cacheToken, $currentMasterJournalRecoveryReceiptToken);
            if ($baseAdmitted && !$tokenMatches) {
                $receiptInvalidated[] = $pageNumber;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_master_journal_recovery_receipt_current_source_next254',
                    'page_number' => $pageNumber,
                    'reason' => 'reader_cache_master_journal_recovery_receipt_predates_current_source',
                ];
            }

            $rows[] = $row + [
                'master_journal_recovery_receipt_token_admitted' => $baseAdmitted && $tokenMatches,
                'master_journal_recovery_receipt_token_reason' => $baseAdmitted
                    ? ($tokenMatches
                        ? 'reader_cache_master_journal_recovery_receipt_matches_current_source'
                        : 'reader_cache_master_journal_recovery_receipt_predates_current_source')
                    : (string) ($row['reader_snapshot_token_reason'] ?? $row['pager_reader_cache_generation_token_reason'] ?? $row['reason']),
                'cache_master_journal_recovery_receipt_token' => $cacheToken,
                'current_master_journal_recovery_receipt_token' => $currentMasterJournalRecoveryReceiptToken,
                'master_journal_recovery_receipt_token_matches' => $tokenMatches,
            ];
        }

        $receiptInvalidated = self::sortedUnique($receiptInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $receiptInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $receiptInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $receiptInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = hash_equals($readTokens[$readerId] ?? '', $currentMasterJournalRecoveryReceiptToken);
            $pageInvalidated = in_array((int) $read['page_number'], $receiptInvalidated, true);
            $read['master_journal_recovery_receipt_token_current'] = $ticketCurrent;
            $read['master_journal_recovery_receipt_token'] = $currentMasterJournalRecoveryReceiptToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-recovery-receipt-fence-current-source-next254';
                $read['master_journal_recovery_receipt_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_master_journal_recovery_receipt_change'
                    : 'reader_ticket_master_journal_recovery_receipt_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'reopen_reader_for_master_journal_recovery_receipt_current_source_next254',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['master_journal_recovery_receipt_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next254';
        $base['reason'] = 'master_journal_reader_cache_rechecks_recovery_receipt_before_reuse';
        $base['current_master_journal_recovery_receipt_token'] = $currentMasterJournalRecoveryReceiptToken;
        $base['reader_rows'] = $rows;
        $base['master_journal_recovery_receipt_invalidated_cache_page_numbers'] = $receiptInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentMasterJournalRecoveryReceiptToken . '|' . implode(',', $receiptInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next254';
        $base['dependencies'][] = 'sqlite-pager-master-journal-recovery-receipt-reader-cache-fence';
        $base['non_overlap'] = 'next254 fences reader-cache reuse on a completed master-journal recovery receipt after next251 reader-snapshot admission; it does not repeat next251 snapshots, next247 generation, next243 provenance, statement-root/schema/read-transaction tokens, master-journal byte/token/member fences, rollback-journal apply/commit, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, or encoding behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheRecoveryReceiptTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['master_journal_recovery_receipt_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next254 cache entries require master-journal recovery receipt tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readRecoveryReceiptTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['master_journal_recovery_receipt_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next254 reads require reader ids and master-journal recovery receipt tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripRecoveryReceiptToken(array $cache): array
    {
        foreach ($cache as &$entry) {
            unset($entry['master_journal_recovery_receipt_token']);
        }
        unset($entry);

        return $cache;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneRecoveryReceiptToken(array $read): array
    {
        unset($read['master_journal_recovery_receipt_token']);

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
