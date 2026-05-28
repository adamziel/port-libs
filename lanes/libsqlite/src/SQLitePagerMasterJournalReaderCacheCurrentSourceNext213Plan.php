<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext213Plan
{
    /**
     * @param array<int,string> $recoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,member_journal_tokens?:array<string,string>,member_journal_header_digests?:array<string,string>,master_member_order_digest?:string,master_journal_file_token?:string,master_journal_bytes_digest?:string,database_file_token?:string,database_header_digest?:string,dirty?:bool,pinned?:bool,shared?:bool,label?:string}> $readerCache
     * @param list<array{reader_id?:string,page_number:int,source_id?:string,epoch?:int,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,member_journal_token_digest?:string,member_journal_header_digest?:string,master_member_order_digest?:string,master_journal_file_token?:string,master_journal_bytes_digest?:string,database_file_token?:string,database_header_digest?:string}> $nextReads
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
        $currentHeaderDigest = self::currentDatabaseHeaderDigest($databaseBytes, $pageSize, $recoveredPages);
        $cacheHeaderDigests = self::cacheDatabaseHeaderDigests($readerCache);
        $readHeaderDigests = self::readDatabaseHeaderDigests($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext212Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripDatabaseHeaderDigest($readerCache),
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
                'database_file_token' => $read['database_file_token'] ?? null,
            ], $nextReads),
            $currentSourceId,
            $currentEpoch,
            $currentPublicationGeneration,
            $currentMasterSourceDigest,
            $currentRecoverySequence,
            $currentMemberJournalTokens,
            $currentMemberJournalHeaderDigests,
            $currentMasterJournalFileToken,
            $currentDatabaseFileToken,
        );

        $headerInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = $row['page_number'];
            $cacheDigest = $cacheHeaderDigests[$pageNumber] ?? '';
            $reason = $cacheDigest === $currentHeaderDigest
                ? null
                : 'reader_cache_database_header_digest_changed_after_master_journal_recovery';

            if ((bool) ($row['database_file_token_admitted'] ?? false) && $reason !== null) {
                $headerInvalidated[] = $pageNumber;
            }

            $rows[] = $row + [
                'database_header_digest_admitted' => (bool) ($row['database_file_token_admitted'] ?? false) && $reason === null,
                'database_header_digest_reason' => (bool) ($row['database_file_token_admitted'] ?? false)
                    ? ($reason ?? 'reader_cache_database_header_digest_matches_current_source')
                    : ($row['database_file_token_reason'] ?? $row['master_journal_bytes_digest_reason'] ?? $row['master_journal_file_token_reason'] ?? $row['master_member_order_reason'] ?? $row['member_header_reason'] ?? $row['member_token_reason'] ?? $row['recovery_reason'] ?? $row['reason']),
                'cache_database_header_digest' => $cacheDigest,
                'current_database_header_digest' => $currentHeaderDigest,
                'database_header_digest_matches' => $cacheDigest === $currentHeaderDigest,
            ];
        }

        $headerInvalidated = self::sortedUnique($headerInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $headerInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $headerInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $headerInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = ($readHeaderDigests[$readerId] ?? '') === $currentHeaderDigest;
            $pageInvalidated = in_array($read['page_number'], $headerInvalidated, true);
            $read['database_header_digest_current'] = $ticketCurrent;
            $read['database_header_digest'] = $currentHeaderDigest;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-database-header-digest-fence-current-source-next213';
                $read['database_header_digest_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_database_header_digest_change'
                    : 'reader_ticket_database_header_digest_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_database_header_digest_after_current_source_next213',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['database_header_digest_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next213';
        $base['reason'] = 'master_journal_reader_cache_rechecks_database_header_digest_before_current_source_reuse';
        $base['current_database_header_digest'] = $currentHeaderDigest;
        $base['reader_rows'] = $rows;
        $base['database_header_digest_invalidated_cache_page_numbers'] = $headerInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentHeaderDigest . '|' . implode(',', $headerInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next213';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-database-header-digest-fence';
        $base['non_overlap'] = 'next213 fences reader-cache reuse on the recovered database page-1 header digest after accepted next212 database file-token, next209 master bytes, next206 master file-token, next203 member-order, next196 member-header, and next192 member-token fences admit a page; it does not repeat WAL, rollback-journal, VFS writer, database file-token, or older header-ticket helper behavior.';

        return $base;
    }

    /**
     * @param array<int,string> $recoveredPages
     */
    private static function currentDatabaseHeaderDigest(string $databaseBytes, int $pageSize, array $recoveredPages): string
    {
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next213 page size must be a power of two at least 512');
        }
        $pageOne = $recoveredPages[1] ?? substr($databaseBytes, 0, $pageSize);
        if (!is_string($pageOne) || strlen($pageOne) !== $pageSize) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next213 requires a page-size page-one image');
        }

        return hash('sha256', substr($pageOne, 0, 100));
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheDatabaseHeaderDigests(array $cache): array
    {
        $digests = [];
        foreach ($cache as $pageNumber => $entry) {
            $digest = $entry['database_header_digest'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($digest) || $digest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next213 cache entries require database header digests');
            }
            $digests[$pageNumber] = $digest;
        }

        return $digests;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readDatabaseHeaderDigests(array $reads): array
    {
        $digests = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $digest = $read['database_header_digest'] ?? '';
            if ($readerId === '' || !is_string($digest) || $digest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next213 reads require reader ids and database header digests');
            }
            $digests[$readerId] = $digest;
        }

        return $digests;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripDatabaseHeaderDigest(array $cache): array
    {
        $stripped = [];
        foreach ($cache as $pageNumber => $entry) {
            unset($entry['database_header_digest']);
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
