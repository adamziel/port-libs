<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext195Plan
{
    /**
     * @param array<int,array{image:string,reader_id?:string,source_id?:string,epoch?:int,master_delete_token?:string,directory_sync_generation?:int,dirty?:bool,pinned?:bool,master_member_ticket?:string}> $readerCache
     * @param list<array{reader_id?:string,page_number:int,master_member_ticket?:string}> $nextReads
     * @param array<int,string> $currentPages
     * @param array<string,string> $currentJournalDigests
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        string $currentMasterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        array $readerCache,
        array $nextReads,
        array $currentPages,
        string $currentSourceId,
        int $currentEpoch,
        int $directorySyncGeneration,
        array $currentJournalDigests,
    ): array {
        if ($nextReads === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next195 requires next reads');
        }

        $readPages = [];
        foreach ($nextReads as $read) {
            $page = $read['page_number'] ?? 0;
            if (!is_int($page) || $page < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next195 reads require one-based page numbers');
            }
            $readPages[] = $page;
        }

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext191Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            self::stripMemberTicket($readerCache),
            $readPages,
            $currentPages,
            $currentSourceId,
            $currentEpoch,
            $directorySyncGeneration,
        );

        $currentTickets = self::memberTickets(
            $base['current_members'],
            $currentJournalDigests,
            (string) $base['current_master_delete_token'],
            $directorySyncGeneration,
        );
        $cacheTickets = self::cacheTickets($readerCache);
        $readTickets = self::readTickets($nextReads);
        $ticketInvalidated = [];
        $readerRows = [];

        foreach ($base['reader_rows'] as $row) {
            $pageNumber = $row['page_number'];
            $cacheTicket = $cacheTickets[$pageNumber] ?? '';
            $currentTicket = self::pageTicket($pageNumber, $currentTickets);
            $ticketReason = $cacheTicket === $currentTicket
                ? 'reader_cache_master_member_ticket_matches_current_source'
                : 'reader_cache_master_member_ticket_predates_current_source';

            if ((bool) ($row['admitted'] ?? false) && $cacheTicket !== $currentTicket) {
                $ticketInvalidated[] = $pageNumber;
            }

            $readerRows[] = $row + [
                'member_ticket_admitted' => (bool) ($row['admitted'] ?? false) && $cacheTicket === $currentTicket,
                'member_ticket_reason' => (bool) ($row['admitted'] ?? false)
                    ? $ticketReason
                    : ($row['reason'] ?? 'reader_cache_rejected_before_master_member_ticket_check'),
                'cache_master_member_ticket' => $cacheTicket,
                'current_master_member_ticket' => $currentTicket,
                'master_member_ticket_matches' => $cacheTicket === $currentTicket,
            ];
        }

        $base['invalidated_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_page_numbers'], $ticketInvalidated));
        $base['retained_page_numbers'] = array_values(array_diff($base['retained_page_numbers'], $ticketInvalidated));
        $base['refreshed_page_numbers'] = array_values(array_diff($base['refreshed_page_numbers'], $ticketInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_page_numbers'] !== [];

        $reopenReaders = [];
        foreach ($base['next_reads'] as &$read) {
            $pageNumber = $read['page_number'];
            $readerId = self::readerIdForPage($nextReads, $pageNumber);
            $expectedTicket = $readTickets[$readerId] ?? '';
            $currentTicket = self::pageTicket($pageNumber, $currentTickets);
            $pageInvalidated = in_array($pageNumber, $ticketInvalidated, true);
            $ticketCurrent = $expectedTicket === $currentTicket;
            $read['reader_id'] = $readerId;
            $read['master_member_ticket_current'] = $ticketCurrent;
            $read['master_member_ticket'] = $currentTicket;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-member-ticket-fence-current-source-next195';
                $read['source_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_master_member_ticket_change'
                    : 'reader_ticket_master_member_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_master_member_ticket_after_delete_next195',
                    'page_number' => $pageNumber,
                    'reader_id' => $readerId,
                    'reason' => $read['source_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next195';
        $base['reason'] = 'master_journal_reader_cache_member_ticket_fences_current_source_reuse';
        $base['reader_rows'] = $readerRows;
        $base['current_master_member_tickets'] = $currentTickets;
        $base['member_ticket_invalidated_page_numbers'] = $ticketInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortedStrings(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . implode(',', $ticketInvalidated) . '|' . hash('sha256', json_encode($currentTickets, JSON_THROW_ON_ERROR)));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next195';
        $base['dependencies'][] = 'sqlite-pager-master-member-ticket-fence';
        $base['non_overlap'] = 'next195 adds per-master-member journal digest tickets after the accepted next191 delete-sync reader-cache fence; it does not repeat next191 directory-sync/delete-token fencing, next190 per-page source digests, or rollback-journal/VFS writer application.';

        return $base;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripMemberTicket(array $cache): array
    {
        $stripped = [];
        foreach ($cache as $pageNumber => $entry) {
            unset($entry['master_member_ticket']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheTickets(array $cache): array
    {
        $tickets = [];
        foreach ($cache as $pageNumber => $entry) {
            $ticket = $entry['master_member_ticket'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($ticket) || $ticket === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next195 cache entries require master member tickets');
            }
            $tickets[$pageNumber] = $ticket;
        }

        return $tickets;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readTickets(array $reads): array
    {
        $tickets = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? ('read-' . ($read['page_number'] ?? '')));
            $ticket = $read['master_member_ticket'] ?? '';
            if ($readerId === '' || !is_string($ticket) || $ticket === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next195 reads require reader ids and master member tickets');
            }
            $tickets[$readerId] = $ticket;
        }

        return $tickets;
    }

    /**
     * @param list<string> $members
     * @param array<string,string> $digests
     * @return array<string,string>
     */
    private static function memberTickets(array $members, array $digests, string $deleteToken, int $directorySyncGeneration): array
    {
        $tickets = [];
        foreach ($members as $member) {
            $digest = $digests[$member] ?? null;
            if (!is_string($digest) || $digest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next195 requires a digest for every master-journal member');
            }
            $tickets[$member] = 'master-member-ticket:' . substr(hash('sha256', $member . '|' . $digest . '|' . $deleteToken . '|' . $directorySyncGeneration), 0, 40);
        }
        ksort($tickets, SORT_STRING);

        return $tickets;
    }

    /** @param array<string,string> $tickets */
    private static function pageTicket(int $pageNumber, array $tickets): string
    {
        $values = array_values($tickets);
        $index = ($pageNumber - 1) % count($values);

        return $values[$index];
    }

    /** @param list<array<string,mixed>> $reads */
    private static function readerIdForPage(array $reads, int $pageNumber): string
    {
        foreach ($reads as $read) {
            if (($read['page_number'] ?? null) === $pageNumber) {
                return (string) ($read['reader_id'] ?? ('read-' . $pageNumber));
            }
        }

        return 'read-' . $pageNumber;
    }

    /** @param list<int> $values @return list<int> */
    private static function sortedUnique(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values, SORT_NUMERIC);

        return $values;
    }

    /** @param list<string> $values @return list<string> */
    private static function sortedStrings(array $values): array
    {
        sort($values, SORT_NATURAL);

        return $values;
    }
}
