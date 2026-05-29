<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext212Plan
{
    /**
     * @param array<int,string> $recoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,member_journal_tokens?:array<string,string>,member_journal_header_digests?:array<string,string>,master_member_order_digest?:string,master_journal_file_token?:string,master_journal_bytes_digest?:string,database_file_token?:string,dirty?:bool,pinned?:bool,shared?:bool,label?:string}> $readerCache
     * @param list<array{reader_id?:string,page_number:int,source_id?:string,epoch?:int,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,member_journal_token_digest?:string,member_journal_header_digest?:string,master_member_order_digest?:string,master_journal_file_token?:string,master_journal_bytes_digest?:string,database_file_token?:string}> $nextReads
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
    ): array {
        if ($currentDatabaseFileToken === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next212 requires a current database file token');
        }

        $cacheTokens = self::cacheDatabaseFileTokens($readerCache);
        $readTokens = self::readDatabaseFileTokens($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext209Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripDatabaseFileToken($readerCache),
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

        $databaseTokenInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = $row['page_number'];
            $cacheToken = $cacheTokens[$pageNumber] ?? '';
            $reason = $cacheToken === $currentDatabaseFileToken
                ? null
                : 'reader_cache_database_file_token_changed_after_master_journal_recovery';

            if ((bool) ($row['master_journal_bytes_digest_admitted'] ?? false) && $reason !== null) {
                $databaseTokenInvalidated[] = $pageNumber;
            }

            $rows[] = $row + [
                'database_file_token_admitted' => (bool) ($row['master_journal_bytes_digest_admitted'] ?? false) && $reason === null,
                'database_file_token_reason' => (bool) ($row['master_journal_bytes_digest_admitted'] ?? false)
                    ? ($reason ?? 'reader_cache_database_file_token_matches_current_source')
                    : ($row['master_journal_bytes_digest_reason'] ?? $row['master_journal_file_token_reason'] ?? $row['master_member_order_reason'] ?? $row['member_header_reason'] ?? $row['member_token_reason'] ?? $row['recovery_reason'] ?? $row['reason']),
                'cache_database_file_token' => $cacheToken,
                'current_database_file_token' => $currentDatabaseFileToken,
                'database_file_token_matches' => $cacheToken === $currentDatabaseFileToken,
            ];
        }

        $databaseTokenInvalidated = self::sortedUnique($databaseTokenInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $databaseTokenInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $databaseTokenInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $databaseTokenInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = ($readTokens[$readerId] ?? '') === $currentDatabaseFileToken;
            $pageInvalidated = in_array($read['page_number'], $databaseTokenInvalidated, true);
            $read['database_file_token_current'] = $ticketCurrent;
            $read['database_file_token'] = $currentDatabaseFileToken;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-database-file-token-fence-current-source-next212';
                $read['database_file_token_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_database_file_token_change'
                    : 'reader_ticket_database_file_token_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_database_file_token_after_current_source_next212',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['database_file_token_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next212';
        $base['reason'] = 'master_journal_reader_cache_rechecks_database_file_token_before_current_source_reuse';
        $base['current_database_file_token'] = $currentDatabaseFileToken;
        $base['reader_rows'] = $rows;
        $base['database_file_token_invalidated_cache_page_numbers'] = $databaseTokenInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentDatabaseFileToken . '|' . implode(',', $databaseTokenInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next212';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-database-file-token-fence';
        $base['non_overlap'] = 'next212 fences reader-cache reuse on the recovered database file token after the accepted next209 raw master-journal bytes, next206 master file-token, next203 member-order, next196 member-header, next192 member-token, and next191 delete-sync fences have admitted the page; it does not repeat WAL, rollback-journal, VFS writer, or master-journal bytes behavior.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheDatabaseFileTokens(array $cache): array
    {
        $tokens = [];
        foreach ($cache as $pageNumber => $entry) {
            $token = $entry['database_file_token'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next212 cache entries require database file tokens');
            }
            $tokens[$pageNumber] = $token;
        }

        return $tokens;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readDatabaseFileTokens(array $reads): array
    {
        $tokens = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $token = $read['database_file_token'] ?? '';
            if ($readerId === '' || !is_string($token) || $token === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next212 reads require reader ids and database file tokens');
            }
            $tokens[$readerId] = $token;
        }

        return $tokens;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripDatabaseFileToken(array $cache): array
    {
        $stripped = [];
        foreach ($cache as $pageNumber => $entry) {
            unset($entry['database_file_token']);
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
