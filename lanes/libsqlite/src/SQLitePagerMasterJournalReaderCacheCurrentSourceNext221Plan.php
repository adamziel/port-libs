<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext221Plan
{
    /**
     * @param array<int,string> $recoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,member_journal_tokens?:array<string,string>,member_journal_header_digests?:array<string,string>,master_member_order_digest?:string,master_journal_file_token?:string,master_journal_bytes_digest?:string,database_file_token?:string,database_header_digest?:string,pager_header_ticket?:array<string,int>,dirty?:bool,pinned?:bool,shared?:bool,label?:string}> $readerCache
     * @param list<array{reader_id?:string,page_number:int,source_id?:string,epoch?:int,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,member_journal_token_digest?:string,member_journal_header_digest?:string,master_member_order_digest?:string,master_journal_file_token?:string,master_journal_bytes_digest?:string,database_file_token?:string,database_header_digest?:string,pager_header_ticket?:array<string,int>}> $nextReads
     * @param array<string,string> $currentMemberJournalTokens
     * @param array<string,string> $currentMemberJournalHeaderDigests
     * @param array<string,int> $currentPagerHeaderTicket
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
        string $currentDatabaseHeaderDigest,
        array $currentPagerHeaderTicket,
    ): array {
        $currentTicket = self::normalizePagerHeaderTicket($currentPagerHeaderTicket, 'current');
        $cacheTickets = self::cachePagerHeaderTickets($readerCache);
        $readTickets = self::readPagerHeaderTickets($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext217Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripPagerHeaderTicket($readerCache),
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
                'database_header_digest' => $read['database_header_digest'] ?? null,
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
            $currentDatabaseHeaderDigest,
        );

        $ticketInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = $row['page_number'];
            $cacheTicket = $cacheTickets[$pageNumber] ?? [];
            $matches = self::ticketsMatch($cacheTicket, $currentTicket);
            $reason = $matches
                ? null
                : 'reader_cache_pager_header_ticket_changed_after_master_journal_recovery';

            if ((bool) ($row['database_header_digest_admitted'] ?? false) && $reason !== null) {
                $ticketInvalidated[] = $pageNumber;
            }

            $rows[] = $row + [
                'pager_header_ticket_admitted' => (bool) ($row['database_header_digest_admitted'] ?? false) && $reason === null,
                'pager_header_ticket_reason' => (bool) ($row['database_header_digest_admitted'] ?? false)
                    ? ($reason ?? 'reader_cache_pager_header_ticket_matches_current_source')
                    : ($row['database_header_digest_reason'] ?? $row['database_file_token_reason'] ?? $row['reason']),
                'cache_pager_header_ticket' => $cacheTicket,
                'current_pager_header_ticket' => $currentTicket,
                'pager_header_ticket_matches' => $matches,
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
            $ticketCurrent = self::ticketsMatch($readTickets[$readerId] ?? [], $currentTicket);
            $pageInvalidated = in_array($read['page_number'], $ticketInvalidated, true);
            $read['pager_header_ticket_current'] = $ticketCurrent;
            $read['pager_header_ticket'] = $currentTicket;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-pager-header-ticket-current-source-next221';
                $read['pager_header_ticket_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_pager_header_ticket_change'
                    : 'reader_ticket_pager_header_ticket_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_pager_header_ticket_after_current_source_next221',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['pager_header_ticket_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next221';
        $base['reason'] = 'master_journal_reader_cache_rechecks_structured_pager_header_ticket_before_current_source_reuse';
        $base['current_pager_header_ticket'] = $currentTicket;
        $base['reader_rows'] = $rows;
        $base['pager_header_ticket_invalidated_cache_page_numbers'] = $ticketInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . self::ticketDigest($currentTicket) . '|' . implode(',', $ticketInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next221';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-structured-header-ticket-fence';
        $base['non_overlap'] = 'next221 fences reader-cache reuse on the structured recovered page-1 change-counter/schema-cookie/version-valid-for/page-count ticket after next217 database header digest admission; it does not repeat opaque header digest, database file-token, master-journal bytes, member-token/header/order, WAL, VFS writer, or super-journal behavior.';

        return $base;
    }

    /** @param array<string,mixed> $ticket @return array{change_counter:int,schema_cookie:int,version_valid_for:int,page_count:int} */
    private static function normalizePagerHeaderTicket(array $ticket, string $label): array
    {
        $required = ['change_counter', 'schema_cookie', 'version_valid_for', 'page_count'];
        $normalized = [];
        foreach ($required as $key) {
            if (!array_key_exists($key, $ticket) || !is_int($ticket[$key]) || $ticket[$key] < 0) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next221 ' . $label . ' pager header ticket requires non-negative integer ' . $key);
            }
            $normalized[$key] = $ticket[$key];
        }

        return $normalized;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,int>> */
    private static function cachePagerHeaderTickets(array $cache): array
    {
        $tickets = [];
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1 || !isset($entry['pager_header_ticket']) || !is_array($entry['pager_header_ticket'])) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next221 cache entries require pager header tickets');
            }
            $tickets[$pageNumber] = self::normalizePagerHeaderTicket($entry['pager_header_ticket'], 'cache');
        }

        return $tickets;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,array<string,int>> */
    private static function readPagerHeaderTickets(array $reads): array
    {
        $tickets = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            if ($readerId === '' || !isset($read['pager_header_ticket']) || !is_array($read['pager_header_ticket'])) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next221 reads require reader ids and pager header tickets');
            }
            $tickets[$readerId] = self::normalizePagerHeaderTicket($read['pager_header_ticket'], 'read');
        }

        return $tickets;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripPagerHeaderTicket(array $cache): array
    {
        $stripped = [];
        foreach ($cache as $pageNumber => $entry) {
            unset($entry['pager_header_ticket']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
    }

    /** @param array<string,int> $left @param array<string,int> $right */
    private static function ticketsMatch(array $left, array $right): bool
    {
        return self::ticketDigest($left) === self::ticketDigest($right);
    }

    /** @param array<string,int> $ticket */
    private static function ticketDigest(array $ticket): string
    {
        ksort($ticket, SORT_STRING);

        return hash('sha256', json_encode($ticket, JSON_THROW_ON_ERROR));
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
