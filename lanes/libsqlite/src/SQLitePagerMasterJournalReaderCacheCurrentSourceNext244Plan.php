<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext244Plan
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
        string $currentPageImageDigestReceiptToken,
    ): array {
        if ($currentPageImageDigestReceiptToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next244 requires a current page-image digest receipt token');
        }

        $cacheTokens = self::cachePageImageDigestReceiptTokens($readerCache);
        $readTokens = self::readPageImageDigestReceiptTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext240Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripPageImageDigestReceiptToken($readerCache),
            array_map(static fn (array $read): array => self::stripOnePageImageDigestReceiptToken($read), $nextReads),
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
        );

        $digestInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $cacheToken = $cacheTokens[$pageNumber] ?? '';
            $baseAdmitted = (bool) ($row['statement_schema_root_token_admitted'] ?? false);
            $tokenMatches = hash_equals($cacheToken, $currentPageImageDigestReceiptToken);
            if ($baseAdmitted && !$tokenMatches) {
                $digestInvalidated[] = $pageNumber;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_page_image_digest_receipt_after_current_source_next244',
                    'page_number' => $pageNumber,
                    'reason' => 'reader_cache_page_image_digest_receipt_predates_master_journal_current_source',
                ];
            }

            $rows[] = $row + [
                'page_image_digest_receipt_admitted' => $baseAdmitted && $tokenMatches,
                'page_image_digest_receipt_reason' => $baseAdmitted
                    ? ($tokenMatches
                        ? 'reader_cache_page_image_digest_receipt_matches_current_source'
                        : 'reader_cache_page_image_digest_receipt_predates_master_journal_current_source')
                    : (string) ($row['statement_schema_root_token_reason'] ?? $row['schema_reparse_token_reason'] ?? $row['reason']),
                'cache_page_image_digest_receipt_token' => $cacheToken,
                'current_page_image_digest_receipt_token' => $currentPageImageDigestReceiptToken,
                'page_image_digest_receipt_matches' => $tokenMatches,
            ];
        }

        $digestInvalidated = self::sortedUnique($digestInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $digestInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $digestInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $digestInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = hash_equals($readTokens[$readerId] ?? '', $currentPageImageDigestReceiptToken);
            $pageInvalidated = in_array((int) $read['page_number'], $digestInvalidated, true);
            $read['page_image_digest_receipt_current'] = $ticketCurrent;
            $read['page_image_digest_receipt_token'] = $currentPageImageDigestReceiptToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-page-image-digest-fence-current-source-next244';
                $read['page_image_digest_receipt_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_page_image_digest_receipt_change'
                    : 'reader_ticket_page_image_digest_receipt_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'reopen_reader_for_page_image_digest_receipt_after_current_source_next244',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['page_image_digest_receipt_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next244';
        $base['reason'] = 'master_journal_reader_cache_rechecks_page_image_digest_receipts_before_current_source_reuse';
        $base['current_page_image_digest_receipt_token'] = $currentPageImageDigestReceiptToken;
        $base['reader_rows'] = $rows;
        $base['page_image_digest_receipt_invalidated_cache_page_numbers'] = $digestInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentPageImageDigestReceiptToken . '|' . implode(',', $digestInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next244';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-page-image-digest-receipt-fence';
        $base['non_overlap'] = 'next244 fences reader-cache reuse on page-image digest receipts after next240 statement schema-root, next236 schema-reparse, next233 read-transaction, next229 pager-cache source, next224 reader-lease, and next218 cleanup-token admission have already passed; it does not repeat page-count, page-1 header counters, statement schema-root tokens, member-journal, rollback-journal apply, WAL checkpoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, or encoding behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cachePageImageDigestReceiptTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['page_image_digest_receipt_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next244 cache entries require page-image digest receipt tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readPageImageDigestReceiptTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['page_image_digest_receipt_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next244 reads require reader ids and page-image digest receipt tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripPageImageDigestReceiptToken(array $cache): array
    {
        foreach ($cache as &$entry) {
            unset($entry['page_image_digest_receipt_token']);
        }
        unset($entry);

        return $cache;
    }

    /** @param array<string,mixed> $read @return array<string,mixed> */
    private static function stripOnePageImageDigestReceiptToken(array $read): array
    {
        unset($read['page_image_digest_receipt_token']);

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
