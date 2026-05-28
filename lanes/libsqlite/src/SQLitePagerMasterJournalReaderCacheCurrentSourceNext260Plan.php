<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext260Plan
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
        string $currentSourceReaderTicketToken,
    ): array {
        if ($currentSourceReaderTicketToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next260 requires a current source reader ticket token');
        }

        $cacheTokens = self::cacheCurrentSourceReaderTicketTokens($readerCache);
        $readTokens = self::readCurrentSourceReaderTicketTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext257Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripCurrentSourceReaderTicketToken($readerCache),
            array_map(static fn (array $read): array => self::stripOneCurrentSourceReaderTicketToken($read), $nextReads),
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
            $currentRecoveredPageChecksumReceiptToken,
        );

        $ticketInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheToken = $cacheTokens[$pageNumber] ?? '';
            $baseAdmitted = (bool) ($row['recovered_page_checksum_receipt_token_admitted'] ?? false);
            $tokenMatches = hash_equals($cacheToken, $currentSourceReaderTicketToken);
            if ($baseAdmitted && !$tokenMatches) {
                $ticketInvalidated[] = $pageNumber;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_current_source_reader_ticket_current_source_next260',
                    'page_number' => $pageNumber,
                    'reason' => 'reader_cache_current_source_reader_ticket_predates_current_source',
                ];
            }

            $rows[] = $row + [
                'current_source_reader_ticket_token_admitted' => $baseAdmitted && $tokenMatches,
                'current_source_reader_ticket_token_reason' => $baseAdmitted
                    ? ($tokenMatches
                        ? 'reader_cache_current_source_reader_ticket_matches_current_source'
                        : 'reader_cache_current_source_reader_ticket_predates_current_source')
                    : (string) ($row['recovered_page_checksum_receipt_token_reason'] ?? $row['reason']),
                'cache_current_source_reader_ticket_token' => $cacheToken,
                'current_source_reader_ticket_token' => $currentSourceReaderTicketToken,
                'current_source_reader_ticket_token_matches' => $tokenMatches,
            ];
        }

        $ticketInvalidated = self::sortedUnique($ticketInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $ticketInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $ticketInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $ticketInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = hash_equals($readTokens[$readerId] ?? '', $currentSourceReaderTicketToken);
            $pageInvalidated = in_array((int) $read['page_number'], $ticketInvalidated, true);
            $read['current_source_reader_ticket_token_current'] = $ticketCurrent;
            $read['current_source_reader_ticket_token'] = $currentSourceReaderTicketToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-current-source-reader-ticket-fence-current-source-next260';
                $read['current_source_reader_ticket_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_current_source_reader_ticket_change'
                    : 'reader_ticket_current_source_reader_ticket_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'reopen_reader_for_current_source_reader_ticket_current_source_next260',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['current_source_reader_ticket_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next260';
        $base['reason'] = 'master_journal_reader_cache_rechecks_current_source_reader_ticket_before_reuse';
        $base['current_source_reader_ticket_token'] = $currentSourceReaderTicketToken;
        $base['reader_rows'] = $rows;
        $base['current_source_reader_ticket_invalidated_cache_page_numbers'] = $ticketInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentSourceReaderTicketToken . '|' . implode(',', $ticketInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next260';
        $base['dependencies'][] = 'sqlite-pager-current-source-reader-ticket-fence';
        $base['non_overlap'] = 'next260 fences reader-cache reuse on the current-source reader ticket after next257 recovered page checksum receipt admission; it does not repeat next257 checksum receipts, next254 recovery receipts, next251 snapshots, next247 generation, current-source provenance, master-journal byte/token/member fences, rollback-journal apply/commit, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, or encoding behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheCurrentSourceReaderTicketTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['current_source_reader_ticket_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next260 cache entries require current source reader ticket tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readCurrentSourceReaderTicketTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['current_source_reader_ticket_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next260 reads require reader ids and current source reader ticket tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripCurrentSourceReaderTicketToken(array $cache): array
    {
        foreach ($cache as &$entry) {
            unset($entry['current_source_reader_ticket_token']);
        }
        unset($entry);

        return $cache;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOneCurrentSourceReaderTicketToken(array $read): array
    {
        unset($read['current_source_reader_ticket_token']);

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
