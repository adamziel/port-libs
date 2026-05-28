<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext211Plan
{
    /**
     * @param array<int,string> $recoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,member_journal_tokens?:array<string,string>,member_journal_header_digests?:array<string,string>,member_journal_recovered_page_digests?:array<string,string>,master_member_order_digest?:string,master_journal_file_token?:string,master_journal_bytes_digest?:string,dirty?:bool,pinned?:bool,shared?:bool,label?:string}> $readerCache
     * @param list<array{reader_id?:string,page_number:int,source_id?:string,epoch?:int,format_signature?:string,publication_generation?:int,master_source_digest?:string,recovery_sequence?:int,recovered_page_set_digest?:string,member_journal_token_digest?:string,member_journal_header_digest?:string,member_journal_recovered_page_digest?:string,master_member_order_digest?:string,master_journal_file_token?:string,master_journal_bytes_digest?:string}> $nextReads
     * @param array<string,string> $currentMemberJournalTokens
     * @param array<string,string> $currentMemberJournalHeaderDigests
     * @param array<string,string> $currentMemberJournalRecoveredPageDigests
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
        array $currentMemberJournalRecoveredPageDigests,
        string $currentMasterJournalFileToken,
    ): array {
        $members = self::members($currentMasterJournalBytes);
        $currentRecovered = self::normalizeMemberMap($currentMemberJournalRecoveredPageDigests, $members, 'recovered page digest');
        $currentRecoveredDigest = self::memberMapDigest($currentRecovered);
        $cacheRecovered = self::normalizeCacheRecoveredPages($readerCache, $members);
        $readRecoveredDigests = self::readRecoveredPageDigests($nextReads);

        $base = SQLitePagerMasterJournalReaderCacheCurrentSourceNext209Plan::plan(
            $databasePath,
            $masterJournalPath,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $recoveredPages,
            self::stripRecoveredPageDigests($readerCache),
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

        $recoveredInvalidated = [];
        $rows = [];
        foreach ($base['reader_rows'] as $row) {
            $pageNumber = $row['page_number'];
            $entryRecovered = $cacheRecovered[$pageNumber];
            $mismatchedMembers = [];
            foreach ($currentRecovered as $member => $digest) {
                if (($entryRecovered[$member] ?? null) !== $digest) {
                    $mismatchedMembers[] = $member;
                }
            }
            $reason = $mismatchedMembers === []
                ? null
                : 'reader_cache_attached_member_recovered_page_set_changed';

            if ((bool) ($row['master_journal_bytes_digest_admitted'] ?? false) && $reason !== null) {
                $recoveredInvalidated[] = $pageNumber;
            }

            $rows[] = $row + [
                'member_recovered_page_admitted' => (bool) ($row['master_journal_bytes_digest_admitted'] ?? false) && $reason === null,
                'member_recovered_page_reason' => (bool) ($row['master_journal_bytes_digest_admitted'] ?? false)
                    ? ($reason ?? 'reader_cache_attached_member_recovered_page_sets_match_current_source')
                    : ($row['master_journal_bytes_digest_reason'] ?? $row['master_journal_file_token_reason'] ?? $row['master_member_order_reason'] ?? $row['member_header_reason'] ?? $row['member_token_reason'] ?? $row['recovery_reason'] ?? $row['reason']),
                'cache_member_journal_recovered_page_digests' => $entryRecovered,
                'current_member_journal_recovered_page_digests' => $currentRecovered,
                'cache_member_journal_recovered_page_digest' => self::memberMapDigest($entryRecovered),
                'current_member_journal_recovered_page_digest' => $currentRecoveredDigest,
                'mismatched_member_journal_recovered_pages' => $mismatchedMembers,
            ];
        }

        $recoveredInvalidated = self::sortedUnique($recoveredInvalidated);
        $base['invalidated_cache_page_numbers'] = self::sortedUnique(array_merge($base['invalidated_cache_page_numbers'], $recoveredInvalidated));
        $base['retained_cache_page_numbers'] = array_values(array_diff($base['retained_cache_page_numbers'], $recoveredInvalidated));
        $base['refreshed_cache_page_numbers'] = array_values(array_diff($base['refreshed_cache_page_numbers'], $recoveredInvalidated));
        $base['requires_reader_reopen'] = $base['invalidated_cache_page_numbers'] !== [];

        $reopenReaders = array_fill_keys($base['reopen_reader_ids'], true);
        foreach ($base['next_reads'] as &$read) {
            $readerId = (string) $read['reader_id'];
            $ticketCurrent = ($readRecoveredDigests[$readerId] ?? '') === $currentRecoveredDigest;
            $pageInvalidated = in_array($read['page_number'], $recoveredInvalidated, true);
            $read['member_journal_recovered_page_current'] = $ticketCurrent;
            $read['member_journal_recovered_page_digest'] = $currentRecoveredDigest;
            if (!$ticketCurrent || $pageInvalidated) {
                $read['cache_hit'] = false;
                $read['source'] = 'master-journal-reader-cache-member-recovered-pages-fence-current-source-next211';
                $read['member_recovered_page_reason'] = $pageInvalidated
                    ? 'reader_cache_reopened_after_attached_member_recovered_page_set_change'
                    : 'reader_ticket_attached_member_recovered_page_set_predates_current_source';
                $reopenReaders[$readerId] = true;
                $base['operations'][] = [
                    'op' => 'invalidate_reader_cache_attached_member_recovered_pages_after_current_source_next211',
                    'page_number' => $read['page_number'],
                    'reader_id' => $readerId,
                    'reason' => $read['member_recovered_page_reason'],
                ];
            }
        }
        unset($read);

        $base['status'] = 'pager-master-journal-reader-cache-current-source-next211';
        $base['reason'] = 'master_journal_reader_cache_rechecks_attached_member_recovered_page_sets_before_current_source_reuse';
        $base['current_member_journal_recovered_page_digests'] = $currentRecovered;
        $base['current_member_journal_recovered_page_digest'] = $currentRecoveredDigest;
        $base['reader_rows'] = $rows;
        $base['member_recovered_page_invalidated_cache_page_numbers'] = $recoveredInvalidated;
        $base['read_cache_hits'] = array_column($base['next_reads'], 'cache_hit', 'reader_id');
        $base['reopen_reader_ids'] = self::sortReaderIds(array_keys($reopenReaders));
        $base['source_digest'] = hash('sha256', $base['source_digest'] . '|' . $currentRecoveredDigest . '|' . implode(',', $recoveredInvalidated));
        $base['dependencies'][] = 'sqlite-pager-master-journal-reader-cache-current-source-next211';
        $base['dependencies'][] = 'sqlite-pager-reader-cache-attached-member-recovered-page-set-fence';
        $base['non_overlap'] = 'next211 fences reader-cache reuse on attached member recovered-page-set digests after raw master-journal bytes, file token, member order, member headers, and member tokens still match; it does not repeat next209 raw bytes, next206 file-token, next203 member-order, next196 member-header, next192 member-token, or accepted rollback/WAL/VFS apply paths.';

        return $base;
    }

    /** @return list<string> */
    private static function members(string $bytes): array
    {
        $members = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $bytes) ?: []), static fn (string $member): bool => $member !== ''));
        if ($members === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next211 requires master-journal members');
        }

        return $members;
    }

    /**
     * @param array<string,string> $values
     * @param list<string> $members
     * @return array<string,string>
     */
    private static function normalizeMemberMap(array $values, array $members, string $label): array
    {
        $normalized = [];
        foreach ($members as $member) {
            $value = $values[$member] ?? null;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next211 requires {$label} for member {$member}");
            }
            $normalized[$member] = $value;
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $readerCache
     * @param list<string> $members
     * @return array<int,array<string,string>>
     */
    private static function normalizeCacheRecoveredPages(array $readerCache, array $members): array
    {
        $normalized = [];
        foreach ($readerCache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next211 cache page numbers must be one-based integers');
            }
            $digests = $entry['member_journal_recovered_page_digests'] ?? null;
            if (!is_array($digests)) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next211 cache entries require member recovered page digests');
            }
            $normalized[$pageNumber] = self::normalizeMemberMap($digests, $members, 'cache recovered page digest');
        }

        return $normalized;
    }

    /** @param list<array<string,mixed>> $reads @return array<string,string> */
    private static function readRecoveredPageDigests(array $reads): array
    {
        $digests = [];
        foreach ($reads as $read) {
            $readerId = (string) ($read['reader_id'] ?? '');
            $digest = $read['member_journal_recovered_page_digest'] ?? '';
            if ($readerId === '' || !is_string($digest) || $digest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next211 reads require reader ids and member recovered page digests');
            }
            $digests[$readerId] = $digest;
        }

        return $digests;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function stripRecoveredPageDigests(array $cache): array
    {
        $stripped = [];
        foreach ($cache as $pageNumber => $entry) {
            unset($entry['member_journal_recovered_page_digests']);
            $stripped[$pageNumber] = $entry;
        }

        return $stripped;
    }

    /** @param array<string,string> $values */
    private static function memberMapDigest(array $values): string
    {
        ksort($values, SORT_STRING);
        $parts = [];
        foreach ($values as $member => $value) {
            $parts[] = $member . '=' . $value;
        }

        return hash('sha256', implode('|', $parts));
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
