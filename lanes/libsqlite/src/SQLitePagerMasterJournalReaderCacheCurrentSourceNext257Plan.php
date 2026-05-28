<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext257Plan
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
        string $currentRecoveredPageChecksumReceiptToken,
    ): array {
        if ($currentRecoveredPageChecksumReceiptToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next257 requires a recovered page checksum receipt token');
        }

        $cacheTokens = self::cacheChecksumReceiptTokens($readerCache);
        $readTokens = self::readChecksumReceiptTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext254Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripChecksumReceiptToken($readerCache),
            array_map(static fn (array $read): array => self::stripOneChecksumReceiptToken($read), $nextReads),
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

        $checksumInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheToken = $cacheTokens[$pageNumber] ?? '';
            $baseAdmitted = (bool) ($row['master_journal_recovery_receipt_token_admitted'] ?? false);
            $tokenMatches = hash_equals($cacheToken, $currentRecoveredPageChecksumReceiptToken);
            if ($baseAdmitted && !$tokenMatches) {
                $checksumInvalidated[] = $pageNumber;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_recovered_page_checksum_receipt_current_source_next257',
                    'page_number' => $pageNumber,
                    'reason' => 'reader_cache_recovered_page_checksum_receipt_predates_current_source',
                ];
            }

            $rows[] = $row + [
                'recovered_page_checksum_receipt_token_admitted' => $baseAdmitted && $tokenMatches,
                'recovered_page_checksum_receipt_token_reason' => $baseAdmitted
                    ? ($tokenMatches
                        ? 'reader_cache_recovered_page_checksum_receipt_matches_current_source'
                        : 'reader_cache_recovered_page_checksum_receipt_predates_current_source')
                    : (string) ($row['master_journal_recovery_receipt_token_reason'] ?? $row['reason']),
                'cache_recovered_page_checksum_receipt_token' => $cacheToken,
                'current_recovered_page_checksum_receipt_token' => $currentRecoveredPageChecksumReceiptToken,
                'recovered_page_checksum_receipt_token_matches' => $tokenMatches,
            ];
        }

        $checksumInvalidated = self::sortedUnique($checksumInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $checksumInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $checksumInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $checksumInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = hash_equals($readTokens[$readerId] ?? '', $currentRecoveredPageChecksumReceiptToken);
            $pageInvalidated = in_array((int) $read['page_number'], $checksumInvalidated, true);
            $read['recovered_page_checksum_receipt_token_current'] = $ticketCurrent;
            $read['recovered_page_checksum_receipt_token'] = $currentRecoveredPageChecksumReceiptToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-recovered-page-checksum-receipt-fence-current-source-next257';
                $read['recovered_page_checksum_receipt_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_recovered_page_checksum_receipt_change'
                    : 'reader_ticket_recovered_page_checksum_receipt_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'reopen_reader_for_recovered_page_checksum_receipt_current_source_next257',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['recovered_page_checksum_receipt_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next257';
        $base['reason'] = 'master_journal_reader_cache_rechecks_recovered_page_checksum_receipt_before_reuse';
        $base['current_recovered_page_checksum_receipt_token'] = $currentRecoveredPageChecksumReceiptToken;
        $base['reader_rows'] = $rows;
        $base['recovered_page_checksum_receipt_invalidated_cache_page_numbers'] = $checksumInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentRecoveredPageChecksumReceiptToken . '|' . implode(',', $checksumInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next257';
        $base['dependencies'][] = 'sqlite-pager-recovered-page-checksum-receipt-reader-cache-fence';
        $base['non_overlap'] = 'next257 fences reader-cache reuse on recovered page checksum receipts after next254 master-journal recovery-receipt admission; it does not repeat next254 recovery receipts, next251 snapshots, next247 generation, current-source provenance, master-journal byte/token/member fences, rollback-journal apply/commit, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, or encoding behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheChecksumReceiptTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['recovered_page_checksum_receipt_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next257 cache entries require recovered page checksum receipt tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readChecksumReceiptTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['recovered_page_checksum_receipt_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next257 reads require reader ids and recovered page checksum receipt tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripChecksumReceiptToken(array $cache): array
    {
        foreach ($cache as &$entry) {
            unset($entry['recovered_page_checksum_receipt_token']);
        }
        unset($entry);

        return $cache;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneChecksumReceiptToken(array $read): array
    {
        unset($read['recovered_page_checksum_receipt_token']);

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
