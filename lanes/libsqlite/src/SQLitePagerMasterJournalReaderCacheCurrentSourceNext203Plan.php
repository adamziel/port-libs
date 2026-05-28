<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext203Plan
{
    /**
     * @param array<int,string> $recoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,member_journal_tokens?:array<string,string>,member_journal_header_digests?:array<string,string>,master_member_order_digest?:string,dirty?:bool,pinned?:bool,shared?:bool,label?:string}> $readerCache
     * @param list<array{reader_id?:string,page_number:int,source_id?:string,epoch?:int,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,member_journal_token_digest?:string,member_journal_header_digest?:string,master_member_order_digest?:string}> $nextReads
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
    ): array {
        $members = self::members($currentMasterJournalBytes);
        $currentOrderDigest = self::memberOrderDigest($members);
        $cacheOrderDigests = self::cacheOrderDigests($readerCache);
        $readOrderDigests = self::readOrderDigests($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext196Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripOrderDigest($readerCache),
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
            ], $nextReads),
            $currentSourceId,
            $currentEpoch,
            $currentPublicationGeneration,
            $currentMasterSourceDigest,
            $currentRecoverySequence,
            $currentMemberJournalTokens,
            $currentMemberJournalHeaderDigests,
        );

        $orderInvalidated = [];
        $orderRows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = $row['page_number'];
            $cacheDigest = $cacheOrderDigests[$pageNumber] ?? '';
            $reason = $cacheDigest === $currentOrderDigest
                ? null
                : 'reader_cache_master_member_order_changed';

            if ((bool) ($row['member_header_admitted'] ?? false) && $reason !== null) {
                $orderInvalidated[] = $pageNumber;
            }

            $orderRows[] = $row + [
                'master_member_order_admitted' => (bool) ($row['member_header_admitted'] ?? false) && $reason === null,
                'master_member_order_reason' => (bool) ($row['member_header_admitted'] ?? false)
                    ? ($reason ?? 'reader_cache_master_member_order_matches_current_source')
                    : ($row['member_header_reason'] ?? $row['member_token_reason'] ?? $row['recovery_reason'] ?? $row['reason']),
                'cache_master_member_order_digest' => $cacheDigest,
                'current_master_member_order_digest' => $currentOrderDigest,
                'master_member_order_digest_matches' => $cacheDigest === $currentOrderDigest,
            ];
        }

        $orderInvalidated = self::sortedUnique($orderInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $orderInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $orderInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $orderInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = ($readOrderDigests[$readerId] ?? '') === $currentOrderDigest;
            $pageInvalidated = in_array($read['page_number'], $orderInvalidated, true);
            $read['master_member_order_current'] = $ticketCurrent;
            $read['master_member_order_digest'] = $currentOrderDigest;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-member-order-fence-current-source-next203';
                $read['master_member_order_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_master_member_order_change'
                    : 'reader_ticket_master_member_order_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_master_member_order_after_current_source_next203',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['master_member_order_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next203';
        $base['reason'] = 'master_journal_reader_cache_rechecks_member_order_before_current_source_reuse';
        $base['current_master_member_order_digest'] = $currentOrderDigest;
        $base['reader_rows'] = $orderRows;
        $base['master_member_order_invalidated_cache_page_numbers'] = $orderInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentOrderDigest . '|' . implode(',', $orderInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next203';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-master-member-order-fence';
        $base['non_overlap'] = 'next203 fences reader-cache reuse on the ordered master-journal member list when token/header maps still match; it does not repeat next196 member header digests, next192 member tokens, next187 prefix membership admission, or accepted super-journal commit/apply paths.';

        return $base;
    }

    /** @return list<string> */
    private static function members(string $bytes): array
    {
        $members = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $bytes) ?: []), static fn (string $member): bool => $member !== ''));
        if ($members === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next203 requires master-journal members');
        }

        return $members;
    }

    /** @param list<string> $members */
    private static function memberOrderDigest(array $members): string
    {
        return hash('sha256', implode("\n", $members));
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,string> */
    private static function cacheOrderDigests(array $cache): array
    {
        $digests = [];
        foreach ($cache as $pageNumber => $entry) {
            $digest = $entry['master_member_order_digest'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($digest) || $digest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next203 cache entries require master member order digests');
            }
            $digests[$pageNumber] = $digest;
        }

        return $digests;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readOrderDigests(array $reads): array
    {
        $digests = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $digest = $read['master_member_order_digest'] ?? '';
            if ($readerId === '' || !is_string($digest) || $digest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next203 reads require reader ids and master member order digests');
            }
            $digests[$readerId] = $digest;
        }

        return $digests;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripOrderDigest(array $cache): array
    {
        $stripped = [];
        foreach ($cache as $pageNumber => $entry) {
            unset($entry['master_member_order_digest']);
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
