<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext210Plan
{
    /**
     * @param array<int,string> $recoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,member_journal_tokens?:array<string,string>,member_journal_header_digests?:array<string,string>,master_member_order_digest?:string,master_journal_file_token?:string,master_journal_bytes_digest?:string,master_journal_read_source_token?:string,dirty?:bool,pinned?:bool,shared?:bool,label?:string}> $readerCache
     * @param list<array{reader_id?:string,page_number:int,source_id?:string,epoch?:int,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,member_journal_token_digest?:string,member_journal_header_digest?:string,master_member_order_digest?:string,master_journal_file_token?:string,master_journal_bytes_digest?:string,master_journal_read_source_token?:string}> $nextReads
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
        string $currentMasterJournalReadSourceToken,
    ): array {
        if ($currentMasterJournalReadSourceToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next210 requires current master-journal read source token');
        }
        $cacheTokens = self::cacheMasterJournalReadSourceTokens($readerCache);
        $readTokens = self::readMasterJournalReadSourceTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext209Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripMasterJournalReadSourceToken($readerCache),
            array_map(static fn (array $read): array => [
                'reader_id' => $read['reader_id'] ?? null,
                'page_number' => $read['page_number'],
                'source_id' => $read['source_id'] ?? null,
                'epoch' => $read['epoch'] ?? null,
                'format_signature' => $read['format_signature'] ?? null,
                'publication_generation' => $read['publication_generation'] ?? null,
                'master_source_digest' => $read['master_source_digest'] ?? null,
                'recovery_sequence' => $read['recovery_sequence'] ?? null,
                'recovered_page_set_digest' => $read['recovered_page_set_digest'] ?? null,
                'member_journal_token_digest' => $read['member_journal_token_digest'] ?? null,
                'member_journal_header_digest' => $read['member_journal_header_digest'] ?? null,
                'master_member_order_digest' => $read['master_member_order_digest'] ?? null,
                'master_journal_file_token' => $read['master_journal_file_token'] ?? null,
                'master_journal_bytes_digest' => $read['master_journal_bytes_digest'] ?? null,
            ], $nextReads),
            $currentSourceId,
            $currentEpoch,
            $currentPublicationGeneration,
            $currentMasterSourceDigest,
            $currentRecoverySequence,
            $currentMemberJournalTokens,
            $currentMemberJournalHeaderDigests,
            $currentMasterJournalFileToken,
        );

        $readSourceInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = $row['page_number'];
            $cacheToken = $cacheTokens[$pageNumber] ?? '';
            $reason = $cacheToken === $currentMasterJournalReadSourceToken
                ? null
                : 'reader_cache_master_journal_read_source_token_changed';

            if ((bool) ($row['master_journal_bytes_digest_admitted'] ?? false) && $reason !== null) {
                $readSourceInvalidated[] = $pageNumber;
            }

            $rows[] = $row + [
                'master_journal_read_source_token_admitted' => (bool) ($row['master_journal_bytes_digest_admitted'] ?? false) && $reason === null,
                'master_journal_read_source_token_reason' => (bool) ($row['master_journal_bytes_digest_admitted'] ?? false)
                    ? ($reason ?? 'reader_cache_master_journal_read_source_token_matches_current_source')
                    : ($row['master_journal_bytes_digest_reason'] ?? $row['master_journal_file_token_reason'] ?? $row['master_member_order_reason'] ?? $row['member_header_reason'] ?? $row['member_token_reason'] ?? $row['recovery_reason'] ?? $row['reason']),
                'cache_master_journal_read_source_token' => $cacheToken,
                'current_master_journal_read_source_token' => $currentMasterJournalReadSourceToken,
                'master_journal_read_source_token_matches' => $cacheToken === $currentMasterJournalReadSourceToken,
            ];
        }

        $readSourceInvalidated = self::sortedUnique($readSourceInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $readSourceInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $readSourceInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $readSourceInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = ($readTokens[$readerId] ?? '') === $currentMasterJournalReadSourceToken;
            $pageInvalidated = in_array($read['page_number'], $readSourceInvalidated, true);
            $read['master_journal_read_source_token_current'] = $ticketCurrent;
            $read['master_journal_read_source_token'] = $currentMasterJournalReadSourceToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-read-source-fence-current-source-next210';
                $read['master_journal_read_source_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_master_journal_read_source_token_change'
                    : 'reader_ticket_master_journal_read_source_token_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_master_journal_read_source_after_current_source_next210',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['master_journal_read_source_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next210';
        $base['reason'] = 'master_journal_reader_cache_rechecks_vfs_read_source_before_current_source_reuse';
        $base['current_master_journal_read_source_token'] = $currentMasterJournalReadSourceToken;
        $base['reader_rows'] = $rows;
        $base['master_journal_read_source_invalidated_cache_page_numbers'] = $readSourceInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentMasterJournalReadSourceToken . '|' . implode(',', $readSourceInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next210';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-master-journal-vfs-read-source-fence';
        $base['non_overlap'] = 'next210 fences reader-cache reuse on the VFS master-journal read-source token after raw bytes, file token, member order, member headers, and member tokens still match; it does not repeat next209 raw bytes, next206 file-token, next203 member-order, next196 member-header, next192 member-token, next191 delete-sync, or accepted rollback/WAL/VFS apply paths.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheMasterJournalReadSourceTokens(array $cache): array
    {
        $digests = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['master_journal_read_source_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next210 cache entries require master-journal read source tokens');
            }
            $digests[$pageNumber] = $token;
        }

        return $digests;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readMasterJournalReadSourceTokens(array $reads): array
    {
        $digests = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['master_journal_read_source_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next210 reads require reader ids and master-journal read source tokens');
            }
            $digests[$readerId] = $token;
        }

        return $digests;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripMasterJournalReadSourceToken(array $cache): array
    {
        $stripped = [];
        foreach ($cache as $pageNumber => $entry) {
            unset($entry['master_journal_read_source_token']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
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
